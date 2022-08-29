<?php

namespace Comerline\Syncg\Helper;

use Comerline\Syncg\Service\SyncgApiRequest\Login;
use Comerline\Syncg\Service\SyncgApiRequest\Logout;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Phrase;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\CategoryFactory;
use Comerline\Syncg\Service\SyncgApiRequest\GetVehicleTires;

class MappingHelper
{
    private LoggerInterface $logger;
    protected CollectionFactory $productCollectionFactory;
    private CategoryCollectionFactory $categoryCollectionFactory;
    protected StoreManagerInterface $storeManager;
    protected CategoryFactory $categoryFactory;
    private string $prefixLog;
    private ProductRepositoryInterface $productRepository;
    private Configurable $configurable;
    private Config $config;
    private DateTime $dateTime;
    private array $processedProducts;
    private array $groupCategories;
    private array $magentoCategories;
    private Registry $registry;
    private GetVehicleTires $getVehiclesTires;
    private Login $login;
    private Logout $logout;

    /**
     * @var int[]|string[]
     */
    private array $allStoreIds;

    public function __construct(
        LoggerInterface            $logger,
        CollectionFactory          $productCollectionFactory,
        CategoryCollectionFactory  $categoryCollectionFactory,
        StoreManagerInterface      $storeManager,
        CategoryFactory            $categoryFactory,
        ProductRepositoryInterface $productRepository,
        Configurable               $configurable,
        Config                     $configHelper,
        DateTime                   $dateTime,
        GetVehicleTires            $getVehicleTires,
        Login                       $login,
        Logout                      $logout,
        Registry                   $registry
    )
    {
        $this->logger = $logger;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->allStoreIds = array_keys($storeManager->getStores(true));
        $this->categoryFactory = $categoryFactory;
        $this->productRepository = $productRepository;
        $this->configurable = $configurable;
        $this->config = $configHelper;
        $this->dateTime = $dateTime;
        $this->registry = $registry;
        $this->login = $login;
        $this->logout = $logout;
        $this->magentoCategories = [];
        $this->getVehiclesTires = $getVehicleTires;
        $this->prefixLog = uniqid() . ' | Comerline Car - Rims Mapping System |';
    }

    private function checkMakeMapping(): bool
    {
        $makeSync = true;
        if ($this->config->mappingInProgress()) {
            $makeSync = false;
            $this->logger->info('Mapping | Mapping in progress');
        }
        return $makeSync;
    }

    public function mapCarRims()
    {
        $this->storeManager->setCurrentStore(0);
        if ($this->checkMakeMapping()) {
            $this->config->setMappingInProgress(true);
            $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping Start.'));
            $timeStart = microtime(true);
            $this->login->send();
            $this->getVehiclesTires->send();
            $this->logout->send();
            $this->groupCategories = $this->getVehiclesTires->getVehiclesTiresGroup();
            $this->createCategories();
            $this->mapProductCategories(); // We traverse through the collection and in an array we map the categories to the products
            $this->assignCategoriesToProducts($this->processedProducts); // We traverse through the array and save the products with their new categories
            $this->deleteEmptyCategories();
            $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping End.'));
            $this->config->setLastDateMappingCategories($this->dateTime->gmtDate());
            $this->logger->info(new Phrase($this->prefixLog . ' Finished Rim <-> Car Mapping ' . $this->getTrackTime($timeStart)));
            $this->config->setMappingInProgress(false);
        }
    }

    private function mapProductCategories()
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $cont = 0;
        foreach ($collection as $product) {
            $cont++;
            $this->logger->info(new Phrase($this->prefixLog . ' ' . count($collection) . '/' . $cont . ' | Magento Product: ' . $product->getId() . ' | Loaded.'));
            $productId = $product->getId(); // We get the product ID
            $productCategories = $this->checkAttributes($product); // We get the new categories of this product
            if ($productCategories) {
                $this->processedProducts[$productId] = $productCategories; // We assign them to its array position
                $parentIds = $this->configurable->getParentIdsByChild($product->getId()); // We check if the product has a parent
                if ($parentIds) { // If it does....
                    foreach ($parentIds as $parentId) {
                        if (array_key_exists($parentId, $this->processedProducts)) {
                            $this->processedProducts[$parentId] = array_unique(array_merge($this->processedProducts[$parentId], $productCategories)); // We add the child categories to the parent product
                        } else {
                            $this->processedProducts[$parentId] = $productCategories;
                        }
                    }
                }
            }
        }
    }

    private function assignCategoriesToProducts($processedProducts)
    {
        $cont = 0;
        foreach ($processedProducts as $productId => $productCategories) {
            $countProcessedProducts = count($processedProducts);
            $cont++;
            if ($productCategories) {
                $product = $this->productRepository->getById($productId, true, 0); // We need to load the
                // product in edit mode, otherwise the categories will not be saved
                $currentProductCategories = $product->getCategoryIds();
                $setProductCategories = array_unique(array_merge($currentProductCategories, $productCategories)); // To keep the categories
                // existing in the product, we merge the arrays with array_unique
                $differentCategories = !array_diff($productCategories, $currentProductCategories);
                if (!$differentCategories) {
                    $product->setStoreIds($this->allStoreIds); //Sometimes, product info would not save on admin
                    $product->setCategoryIds($setProductCategories);
                    try {
                        $this->productRepository->save($product);
                    } catch (Exception $e) {
                        $this->logger->warning(new Phrase($this->prefixLog . ' ' . $countProcessedProducts . '/' . $cont . ' Magento Product: ' . $product->getId() . ' | Categories not saved: ' . $e->getMessage()));
                        continue;
                    }
                    $this->logger->info(new Phrase($this->prefixLog . ' ' . $countProcessedProducts . '/' . $cont . ' Magento Product: ' . $product->getId() . ' | Product Categories Saved.'));
                }
            }
        }
    }

    /**
     * @param $child ProductInterface
     * @return array|false
     */
    private function getAttributeTexts($child)
    {
        $searchableAttributes = ['width', 'diameter', 'offset'];
        $attributeKeys = ['ancho', 'diametro', 'et'];
        $attributes = [];
        foreach ($searchableAttributes as $sa) {
            $attributes[] = (float) filter_var($child->getAttributeText($sa), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        return array_combine($attributeKeys, $attributes);
    }

    private function checkAttributes($product): array
    {
        $attributes = $this->getAttributeTexts($product);
        $categoryIds = [];
        $mountingAttribute = $product->getAttributeText('mounting'); //Anclaje?
        if (!$mountingAttribute) {
            return $categoryIds;
        }
        foreach ($this->groupCategories as $groupCategory) {
            $minMaxValid = true;
            foreach ($attributes as $key => $value) { //Product measurements must be between ranges.
                $min = $groupCategory[$key . '_min'];
                $max = $groupCategory[$key . '_max'];
                if ($value < $min || $value > $max) {
                    $minMaxValid = false;
                    break;
                }
            }
            if ($minMaxValid && $this->isMatchingMounting($groupCategory['anclaje_group'], $mountingAttribute)) {
                //Keep existing product categories.
                $categoryIds = array_unique(array_merge($product->getCategoryIds(), $this->getCategoryIds($groupCategory)));
            }
        }
        return $categoryIds;
    }

    private function isMatchingMounting($anclajes, $attribute): bool
    {
        foreach ($anclajes as $anclaje) {
            $sanitizedArgs = array_map(function ($v) {
                $parts = explode('x', strtolower($v));
                if (count($parts) < 2) {
                    return $parts[array_key_first($parts)];
                }
                $sanitizedParts = array_map(function ($p) {
                    preg_match('/^[0-9]+/m', $p, $matchSegments);
                    if (!$matchSegments) {
                        return $p;
                    } else {
                        return $matchSegments[0];
                    }
                }, $parts);
                return implode('x', $sanitizedParts);
            }, [$anclaje, $attribute]);
            if (count(array_unique($sanitizedArgs)) === 1) {
                return true; //We only need one match of the group.
            }
        }
        return false;
    }

    private function getSpecificMagentoCategory($attribute, $value)
    {
        $categoryCollection = $this->categoryCollectionFactory->create();
        if (is_array($attribute)) {
            for ($i = 0; $i < count($attribute); $i++) {
                $categoryCollection->addAttributeToFilter($attribute[$i], $value[$i]);
            }
        } else {
            $categoryCollection->addAttributeToFilter($attribute, $value);
        }
        $categoryCollection->setCurPage(0);
        $categoryCollection->setPageSize(1);
        if ($categoryCollection->getSize()) {
            $categoryId = $categoryCollection->getFirstItem()->getId();
        } else {
            $categoryId = null;
        }
        return $categoryId;
    }

    /**
     * Get category ids from structure.
     * Loads category from already loaded list. If not, it re-tries database.
     * @param $rowCategory
     * @return array
     */
    private function getCategoryIds($rowCategory)
    {
        $categoriesIds = [];
        $tree = [
            $rowCategory['marca'],
            $rowCategory['name'], //Model
            $rowCategory['type']
        ];
        foreach ($tree as $catName) {
            $key = base64_encode($catName);
            if (array_key_exists($key, $this->magentoCategories)) {
                $categoriesIds[] = $this->magentoCategories[$key];
            }
        }
        return $categoriesIds;
    }

    /**
     * Creates category tree for each group.
     * @return void
     */
    private function createCategories()
    {
        foreach ($this->groupCategories as $rowCategory) {
            $tree = [
                'Por Vehículo',
                $rowCategory['marca'],
                $rowCategory['name'], //Model
                $rowCategory['type']
            ];
            $parentId = null;
            foreach ($tree as $catName) {
                $key = base64_encode($catName);
                if (!array_key_exists($key, $this->magentoCategories)) {
                    $forVehicleId = $this->getSpecificMagentoCategory('name', $catName);
                    if ($parentId) {
                        $forVehicleId = $this->createUpdateCategory($catName, "Llantas - $catName", "Llantas para $catName", $parentId, $forVehicleId);
                    }
                    $this->magentoCategories[$key] = $forVehicleId;
                } else {
                    $forVehicleId = $this->magentoCategories[$key];
                }
                $parentId = $forVehicleId;
            }
        }
    }

    private function createUpdateCategory($name, $metaTitle, $metaDescription, $parentId, $categoryId = null)
    {
        $parentCategory = $this->categoryFactory->create()->load($parentId);
        if ($categoryId) {
            $category = $this->categoryFactory->create()->load($categoryId);
            $action = ' Updated Category ';
        } else {
            $category = $this->categoryFactory->create();
            $category->setPath($parentCategory->getPath());
            $action = ' Created Category ';
        }
        $category->setName($name);
        $category->setIsActive(true);
        $category->setParentId($parentId);
        $category->setMetaTitle($metaTitle);
        $category->setMetaDescription($metaDescription);
        // Brief explanation as why we need this: There are few categories that have a + in their name.
        // What does Magento do? Removes the + on the URL Key, causing a collision with other categories. This solves that problem
        if (strpos($name, '+')) {
            $category->setUrlKey(str_replace('+', ' plus', $name));
        }
        try {
            $category->save();
            $categoryId = $category->getId();
            $this->logger->info(new Phrase($this->prefixLog . $action . $name . '.'));
        } catch (Exception $e) {
            $this->logger->info(new Phrase($this->prefixLog . ' Error saving category ' . $name . '.'));
        }

        return $categoryId;
    }

    public function deleteEmptyCategories()
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Deleting Empty Categories - Start.'));
        $parentId = $this->getSpecificMagentoCategory('name', 'Por Vehículo');
        $parentCategory = $this->categoryFactory->create()->load($parentId);
        $brandCategories = $parentCategory->getChildrenCategories();
        $modelCategories = $this->getChildCategoriesFromCategories($brandCategories);
        $yearCategories = $this->getChildCategoriesFromCategories($modelCategories);
        $this->registry->register('isSecureArea', true); // With this we can delete the categories without problem

        $this->logger->info(new Phrase($this->prefixLog . ' Deleting Empty "Year" categories.'));
        $this->deleteCategoriesFromIds($yearCategories);
        $this->logger->info(new Phrase($this->prefixLog . ' Deleting Empty "Model" categories.'));
        $this->deleteCategoriesFromIds($modelCategories);
        $this->logger->info(new Phrase($this->prefixLog . ' Deleting Empty "Brand" categories.'));
        $this->deleteCategoriesFromIds($brandCategories);
        $this->logger->info(new Phrase($this->prefixLog . ' Deleting Empty Categories - Finish.'));
    }

    private function deleteCategoriesFromIds($categories)
    {
        foreach ($categories as $cat) {
            /** @var Category $cat */
            if ($cat->getProductCount() <= 0) {
                $name = $cat->getName();
                $cat->delete();
                $this->logger->info(new Phrase($this->prefixLog . ' Deleted Category ' . $name . '.'));
            }
        }
    }

    private function getChildCategoriesFromCategories($inputCategories): array
    {
        $categories = [];
        foreach ($inputCategories as $category) {
            $childCategoriesFromCategory = $category->getChildrenCategories();
            foreach ($childCategoriesFromCategory as $cc) {
                $categories[] = $cc;
            }
        }
        return $categories;
    }

    public function getTrackTime($timeStart): string
    {
        $timeEnd = microtime(true);
        $executionTime = round(($timeEnd - $timeStart), 2);
        return $executionTime . 's';
    }
}

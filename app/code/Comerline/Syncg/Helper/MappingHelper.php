<?php

namespace Comerline\Syncg\Helper;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Filesystem\DirectoryList;
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

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DirectoryList
     */
    private $dir;

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var string
     */
    private $prefixLog;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Configurable
     */
    private $configurable;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var array
     */
    private $processedProducts;

    /**
     * @var array
     */
    private $arrayKeys;

    /**
     * @var array
     */
    private $groupCategories;

    /**
     * @var array
     */
    private $magentoCategories;

    /**
     * @var Registry
     */
    private $registry;
    private GetVehicleTires $getVehiclesTires;

    public function __construct(
        LoggerInterface            $logger,
        DirectoryList              $dir,
        CollectionFactory          $productCollectionFactory,
        CategoryCollectionFactory  $categoryCollectionFactory,
        StoreManagerInterface      $storeManager,
        CategoryFactory            $categoryFactory,
        ProductRepositoryInterface $productRepository,
        Configurable               $configurable,
        Config                     $configHelper,
        DateTime                   $dateTime,
        GetVehicleTires            $getVehicleTires,
        Registry                   $registry
    )
    {
        $this->logger = $logger;
        $this->dir = $dir;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->productRepository = $productRepository;
        $this->configurable = $configurable;
        $this->config = $configHelper;
        $this->dateTime = $dateTime;
        $this->registry = $registry;
        $this->magentoCategories = [];
        $this->getVehiclesTires = $getVehicleTires;
        $this->prefixLog = uniqid() . ' | Comerline Car - Rims Mapping System |';
        $this->arrayKeys = ['marca', 'modelo', 'ano', 'meta_title', 'meta_description'];
    }

    public function mapCarRims()
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping Start.'));
        $timeStart = microtime(true);
        $this->getVehiclesTires->send();
        $this->groupCategories = $this->getVehiclesTires->getVehiclesTiresGroup();
//        $this->createCategoriesByAlphabeticOrder();
//        $this->mapProductCategories(); // We traverse through the collection and in an array we map the categories to the products
//        $this->assignCategoriesToProducts($this->processedProducts); // We traverse through the array and save the products with their new categories
//        $this->deleteEmptyCategories();
        $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping End.'));
        $this->config->setLastDateMappingCategories($this->dateTime->gmtDate());
        $this->logger->info(new Phrase($this->prefixLog . ' Finished Rim <-> Car Mapping ' . $this->getTrackTime($timeStart)));
    }

    private function createCategoriesByAlphabeticOrder()
    {
        foreach ($this->groupCategories as $groupCategory) {
            $this->createCategories($groupCategory);
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
        $this->storeManager->setCurrentStore(0);
        $cont = 0;
        foreach ($processedProducts as $productId => $productCategories) {
            $countProcessedProducts = count($processedProducts);
            $cont++;
            if ($productCategories) {
                $product = $this->productRepository->getById($productId, true); // We need to load the
                // product in edit mode, otherwise the categories will not be saved
                $currentProductCategories = $product->getCategoryIds();
                $setProductCategories = array_unique(array_merge($currentProductCategories, $productCategories)); // To keep the categories
                // existing in the product, we merge the arrays with array_unique
                $differentCategories = !array_diff($productCategories, $currentProductCategories);
                if (!$differentCategories) {
                    $product->setStoreIds([0, 1]);
                    $product->setCategoryIds($setProductCategories);
                    $this->productRepository->save($product);
                    $this->logger->info(new Phrase($this->prefixLog . ' ' . $countProcessedProducts . '/' . $cont . ' Magento Product: ' . $product->getId() . ' | Product Categories Saved.'));
                }
            }
        }
    }

    private function getAttributeTexts($child)
    {
        $searchableAttributes = ['width', 'diameter', 'offset'];
        $attributeKeys = ['ancho', 'diametro', 'et'];
        $attributes = [];
        foreach ($searchableAttributes as $sa) {
            $attribute = filter_var($child->getAttributeText($sa), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $attributes[] = str_replace('.', ',', $attribute); // We need to replace the dots with commas for
            // the comparison, as well as we need to remove unnecessary zeros
        }
        return array_combine($attributeKeys, $attributes);
    }

    private function checkAttributes($product): array
    {
        $attributes = $this->getAttributeTexts($product);
        $categoryIds = [];
        foreach ($this->groupCategories as $groupCategory) {
            if (count(array_diff_assoc($attributes, $groupCategory)) === 0) {
                $categoryIds = array_unique($this->getCategoryIds($groupCategory));
                $currentProductCategories = $product->getCategoryIds();
                $categoryIds = array_unique(array_merge($currentProductCategories, $categoryIds)); // To keep the categories
            }
        }
        return $categoryIds;
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

    private function getCategoryIds($rowCategory)
    {
        $categoriesIds = [];
        $name = $rowCategory['name'];
        $type = $rowCategory['type'];
        $marca = $rowCategory['marca'];
        $elements = [$name, $type, $marca];
        foreach ($elements as $element) {
            if (!isset($this->magentoCategories[base64_encode($element)])) {
                $this->magentoCategories[base64_encode($element)] = $this->getSpecificMagentoCategory('name', $element);
            }
            $categoriesIds[] = $this->magentoCategories[base64_encode($element)];
        }
        return $categoriesIds;
    }

    private function createCategories($rowCategory)
    {
        $name = $rowCategory['name'];
        $type = $rowCategory['type'];
        $marca = $rowCategory['marca'];
        $metaTitle = 'Llantas - ' . $type;
        $metaDescription = $metaTitle;

        if (!array_key_exists(base64_encode('Por Vehículo'), $this->magentoCategories)) { // Get Por Vehículo
            $forVehicleId = $this->getSpecificMagentoCategory('name', 'Por Vehículo');
            $this->magentoCategories[base64_encode('Por Vehículo')] = $forVehicleId;
        } else {
            $forVehicleId = $this->magentoCategories[base64_encode('Por Vehículo')];
        }
        if (!array_key_exists(base64_encode($marca), $this->magentoCategories)) { // Get or create brand
            $brandId = $this->getSpecificMagentoCategory('name', $marca);
            if (!$brandId) {
                $brandId = $this->createUpdateCategory($marca, '', '', $forVehicleId);
            }
            $this->magentoCategories[base64_encode($marca)] = $brandId;
        } else {
            $brandId = $this->magentoCategories[base64_encode($marca)];
        }
        if (!array_key_exists(base64_encode($name), $this->magentoCategories)) { // Get or create vehicle
            $vehicleId = $this->getSpecificMagentoCategory('name', $name);
            $vehicleId = $this->createUpdateCategory($name, $metaTitle, $metaDescription, $brandId, $vehicleId);
            $this->magentoCategories[base64_encode($name)] = $vehicleId;
        } else {
            $vehicleId = $this->magentoCategories[base64_encode($name)];
        }
        if (!array_key_exists(base64_encode($type), $this->magentoCategories)) { // Get or create type
            $typeId = $this->getSpecificMagentoCategory('name', $type);
            $typeId = $this->createUpdateCategory($type, $metaTitle, $metaDescription, $vehicleId, $typeId);
            $this->magentoCategories[base64_encode($type)] = $typeId;
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

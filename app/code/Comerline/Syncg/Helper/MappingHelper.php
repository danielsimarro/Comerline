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
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\CategoryFactory;

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
     * @var array
     */
    private $csvData;

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
        DateTime                   $dateTime
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
        $this->prefixLog = uniqid() . ' | Comerline Car - Rims Mapping System |';
    }

    public function mapCarRims()
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping Start.'));
        $timeStart = microtime(true);
        $csvFile = $this->dir->getPath('media') . '/mapeo_llantas_modelos.csv';
        $lastCategoriesMapping = $this->config->getParamsWithoutSystem('syncg/general/last_date_mapping_categories')->getValue(); // We get the last mapping date
        $lastChangeCsv = date('Y-m-d H:i:s', @filemtime($csvFile)); //We get the last CSV change date

        if ($lastChangeCsv > $lastCategoriesMapping) {
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('status', Status::STATUS_ENABLED); // We will only map the enabled products to reduce workload
            $this->csvData = $this->readCsv($csvFile); // We load the CSV file
            $this->mapProductCategories($collection); // We traverse through the collection and in an array we map the categories to the products
            $this->assignCategoriesToProducts($this->processedProducts); // We traverse through the array and save the products with their new categories
            $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping End.'));
            $this->config->setLastDateMappingCategories($this->dateTime->gmtDate());
        } else {
            $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping Not Necessary.'));
        }
        $this->logger->info(new Phrase($this->prefixLog . 'Finished Rim <-> Car Mapping ' . $this->getTrackTime($timeStart)));
    }

    private function mapProductCategories($collection)
    {
        foreach ($collection as $product) {
            $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Loaded.'));
            $productId = $product->getId(); // We get the product ID
            $this->processedProducts[$productId] = []; // We create an array position for it
            $productCategories = $this->checkAttributes($product); // We get the new categories of this product
            $this->processedProducts[$productId] = $productCategories; // We assign them to its array position

            $parentIds = $this->configurable->getParentIdsByChild($product->getId()); // We check if the product has a parent

            if ($parentIds) { // If it does....
                foreach ($parentIds as $parentId) {
                    $this->processedProducts[$parentId] = array_unique(array_merge($this->processedProducts[$parentId], $productCategories)); // We add the child categories to the parent product
                }
            }
        }
    }

    private function assignCategoriesToProducts($processedProducts)
    {
        foreach ($processedProducts as $productId => $productCategories) {
            if (!$productCategories) {
                $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $productId . ' | No New Categories To Add To Product.'));
            } else {
                $product = $this->productRepository->getById($productId, true, 0, true); // We need to load the
                // product in edit mode, otherwise the categories will not be saved
                $currentProductCategories = $product->getCategoryIds();
                $setProductCategories = array_unique(array_merge($currentProductCategories, $productCategories)); // To keep the categories
                // existing in the product, we merge the arrays with array_unique
                $differentCategories = !array_diff($productCategories, $currentProductCategories);
                if (!$differentCategories) {
                    $product->setCategoryIds($setProductCategories);
                    $product->save();
                    $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Product Categories Saved.'));
                } else {
                    $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Product Already Has This Categories.'));
                }
            }
        }
    }

    private function getAttributeTexts($child)
    {
        $searchableAttributes = ['width', 'diameter', 'offset', 'hub'];
        $attributeKeys = ['ancho', 'diametro', 'et', 'buje'];
        $attributes = [];
        foreach ($searchableAttributes as $sa) {
            $attribute = filter_var($child->getAttributeText($sa), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $attributes[] = str_replace('.', ',', sprintf('%g', $attribute)); // We need to replace the dots with commas for
            // the comparison, as well as we need to remove unnecessary zeros
        }
        return array_combine($attributeKeys, $attributes);
    }

    private function checkAttributes($product): array
    {
        $attributes = $this->getAttributeTexts($product);
        $categoryIds = [];
        foreach ($this->csvData as $csv) {
            if (count(array_diff_assoc($attributes, $csv)) === 0) {
                if ($this->checkCsvRow($csv)) {
                    $csvCategories = $this->mountCsvCategoriesArray($csv);
                    $position = 0;
                    foreach ($csvCategories as $category) {
                        $categoryIds[] = $this->getCategoryIds($csvCategories, $position);
                        $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Mapped Category | ' . $category . '.'));
                        $position++;
                        if ($position === 3) {
                            break;
                        }
                    }
                    $currentProductCategories = $product->getCategoryIds();
                    $categoryIds = array_unique(array_merge($currentProductCategories, $categoryIds)); // To keep the categories
                    // existing in the product, we merge the arrays with array_unique
                    $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Product Categories Mapped.'));
                } else {
                    $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Incomplete CSV Category.'));
                }
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
        $categoryCollection->setPageSize(1);
        if ($categoryCollection->getSize()) {
            $categoryId = $categoryCollection->getFirstItem()->getId();
        } else {
            $categoryId = null;
        }
        return $categoryId;
    }

    private function getCategoryIds($array, $position)
    {
        $arrayKeys = ['marca', 'modelo', 'ano', 'meta_title', 'meta_description', 'meta_title_parent', 'meta_description_parent'];
        if ($position === 0) { // In the first position, the parent category will always be 'Por Vehículo'
            $parentId = $this->getSpecificMagentoCategory('name', 'Por Vehículo');
            $metaDescriptionKey = $arrayKeys[6]; // The meta description will be 'meta_description_parent'
            $metaTitleKey = $arrayKeys[5]; // The meta title will be 'meta_title_parent'
        } else { // In the following positions, the parent category will be the one in the prior position
            $parentId = $this->getSpecificMagentoCategory('name', $array[$arrayKeys[$position - 1]]);
            $metaDescriptionKey = $arrayKeys[4]; // The meta description will be 'meta_description'
            $metaTitleKey = $arrayKeys[3]; // The meta title will be 'meta_title'
        }
        $metaDescription = $array[$metaDescriptionKey];
        $metaTitle = $array[$metaTitleKey];

        $categoryId = $this->getSpecificMagentoCategory(['parent_id', 'name'], [$parentId, $array[$arrayKeys[$position]]]);
        $name = $array[$arrayKeys[$position]];
        if (!$categoryId) { // If the category does not exist, we create it
            $categoryId = $this->createUpdateCategory($name, $metaTitle, $metaDescription, $parentId);
        } else {
            $categoryId = $this->createUpdateCategory($name, $metaTitle, $metaDescription, $parentId, $categoryId);
        }

        return $categoryId;
    }

    private function createUpdateCategory($name, $metaTitle, $metaDescription, $parentId, $categoryId = null)
    {
        $parentCategory = $this->categoryFactory->create()->load($parentId);
        if ($categoryId) {
            $category = $this->categoryFactory->create()->load($categoryId);
        } else {
            $category = $this->categoryFactory->create();
            $category->setPath($parentCategory->getPath());
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
        } catch (Exception $e) {
            $this->logger->info(new Phrase($this->prefixLog . ' Error saving category ' . $name . '.'));
        }

        return $categoryId;
    }

    private function readCsv($csv): array
    {
        try {
            $file = @file($csv);
            if (!$file) {
                throw new Exception('File does not exists.');
            }
        } catch (Exception $e) {
            $this->logger->error(new Phrase($this->prefixLog . ' CSV File does not exist in the media folder.'));
            die();
        }
        $rows = array_map(function ($row) {
            return str_getcsv($row, ';');
        }, $file);
        $header = array_shift($rows);
        $data = [];
        foreach ($rows as $row) {
            $data[] = array_combine($header, $row); // We save every row of the CSV into an array
        }
        return $data;
    }

    private function checkCsvRow($csv): bool
    {
        $valid = false;
        $validFields = 0;
        $requiredKeys = ['marca', 'modelo', 'ano_desde', 'ano_hasta', 'ancho', 'diametro', 'et', 'buje'];
        foreach ($requiredKeys as $rk) {
            if (isset($csv[$rk]) && ($csv[$rk] !== "")) {
                $validFields++;
            }
        }
        if ($validFields === 8) { // If the validFields count is 8, that means we can work with that row
            $valid = true;
        }
        return $valid;
    }

    private function mountCsvCategoriesArray($csv): array
    {
        $csvCategories = [];

        $csvCategories['marca'] = $csv['marca'];
        $csvCategories['modelo'] = $csv['modelo'];
        if ($csv['ano_hasta'] !== "") { // If 'ano_hasta' is not empty, the category will be a year period instead of only a year
            $csvCategories['ano'] = $csv['ano_desde'] . " - " . $csv['ano_hasta'];
        } else {
            $csvCategories['ano'] = $csv['ano_desde'];
        }
        $csvCategories['meta_title'] = $csv['meta_title'];
        $csvCategories['meta_description'] = $csv['meta_description'];
        $csvCategories['meta_title_parent'] = $csv['meta_title_parent'];
        $csvCategories['meta_description_parent'] = $csv['meta_description_parent'];

        return $csvCategories;
    }

    public function getTrackTime($timeStart): string
    {
        $timeEnd = microtime(true);
        $executionTime = round(($timeEnd - $timeStart), 2);
        return $executionTime . 's';
    }
}

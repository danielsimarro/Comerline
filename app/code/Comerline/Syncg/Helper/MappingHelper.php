<?php

namespace Comerline\Syncg\Helper;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
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

    public function __construct(
        LoggerInterface            $logger,
        DirectoryList              $dir,
        CollectionFactory          $productCollectionFactory,
        CategoryCollectionFactory  $categoryCollectionFactory,
        StoreManagerInterface      $storeManager,
        CategoryFactory            $categoryFactory,
        ProductRepositoryInterface $productRepository
    )
    {
        $this->logger = $logger;
        $this->dir = $dir;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->productRepository = $productRepository;
        $this->prefixLog = uniqid() . ' | Comerline Car - Rims Mapping System |';
    }

    public function mapCarRims()
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Rim <-> Car Mapping Start.'));
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', Status::STATUS_ENABLED); // We will only map the enabled products to reduce workload

        try {
            $csvData = $this->readCsv($this->dir->getPath('media') . '/mapeo_llantas_modelos_test.csv'); // We load the CSV file
            $processedProducts = [];
            $parentProductCategories = [];

            foreach ($collection as $product) {
                if (!in_array($product->getId(), $processedProducts)) {
                    $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Loaded.'));
                    if ($product->getTypeId() === 'configurable') { // If the product is configurable, we load its child products as well
                        $children = $product->getTypeInstance()->getUsedProducts($product);
                        $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Loaded Child Products.'));
                        foreach ($children as $child) {
                            $childAttributes = $this->checkAttributes($child, $csvData);
                            if ($childAttributes) {
                                $parentProductCategories = array_unique(array_merge($parentProductCategories, $childAttributes));
                            }
                            if (!in_array($child->getId(), $processedProducts)) {
                                $processedProducts[] = $child->getId();
                            }
                        }
                    } else {
                        $this->checkAttributes($product, $csvData);
                    }
                    $product = $this->productRepository->getById($product->getId(), true, 0, true); // We need to load the
                    // product in edit mode, otherwise the categories will not be saved
                    $currentProductCategories = $product->getCategoryIds();
                    $parentProductCategories = array_unique(array_merge($currentProductCategories, $parentProductCategories)); // To keep the categories
                    // existing in the product, we merge the arrays with array_unique
                    $product->setCategoryIds($parentProductCategories);
                    $product->save();
                    $processedProducts[] = $product->getId(); // Once a product is processed, we save it in the array to avoid loading it again in the future
                } else {
                    $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Already processed.'));
                }
            }
        } catch (FileSystemException $e) {
            $this->logger->error(new Phrase($this->prefixLog . ' CSV File does not exist in the media folder.'));
        } catch (NoSuchEntityException $e) {
            $this->logger->error(new Phrase($this->prefixLog . ' Error loading product.'));
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

    private function checkAttributes($product, $csvData): array
    {
        $attributes = $this->getAttributeTexts($product);
        $categoryIds = [];
        foreach ($csvData as $csv) {
            if (count(array_diff_assoc($attributes, $csv)) === 0) {
                $csvCategories = [];
                if ($this->checkCsvRow($csv)) {
                    $csvCategories[] = $csv['marca'];
                    $csvCategories[] = $csv['modelo'];
                    if ($csv['ano_hasta'] !== "") { // If 'ano_hasta' is not empty, the category will be a year period instead of only a year
                        $csvCategories[] = $csv['ano_desde'] . " - " . $csv['ano_hasta'];
                    } else {
                        $csvCategories[] = $csv['ano_desde'];
                    }
                    $position = 0;
                    foreach ($csvCategories as $category) {
                        $categoryIds[] = $this->createCategory($category, $csvCategories, $position);
                        $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Mapped Category | ' . $category . '.'));
                        $position++;
                    }
                    $currentProductCategories = $product->getCategoryIds();
                    $categoryIds = array_unique(array_merge($currentProductCategories, $categoryIds)); // To keep the categories
                    // existing in the product, we merge the arrays with array_unique
                    $product->setCategoryIds($categoryIds);
                    $product->save();
                    $this->logger->info(new Phrase($this->prefixLog . ' Magento Product: ' . $product->getId() . ' | Product Categories Saved.'));
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

    private function createCategory($name, $array, $position)
    {
        if (array_search($name, $array) === 0) { // In the first position, the parent category will always be 'Por Vehículo'
            $parentId = $this->getSpecificMagentoCategory('name', 'Por Vehículo');
        } else { // In the following positions, the parent category will be the one in the prior position
            $parentId = $this->getSpecificMagentoCategory('name', $array[$position - 1]);
        }

        $categoryId = $this->getSpecificMagentoCategory(['parent_id', 'name'], [$parentId, $name]);
        if (!$categoryId) { // If the category does not exist, we create it
            $parentCategory = $this->categoryFactory->create()->load($parentId);
            $category = $this->categoryFactory->create();
            $category->setName($name);
            $category->setIsActive(true);
            $category->setParentId($parentId);
            $category->setPath($parentCategory->getPath());
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
        }

        return $categoryId;
    }

    private function readCsv($csv): array
    {

        $rows = array_map(function ($row) {
            return str_getcsv($row, ';');
        }, file($csv));
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
}

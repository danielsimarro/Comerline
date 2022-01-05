<?php

namespace Comerline\Syncg\Helper;

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
     * @var array
     */
    private $categories;

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

    public function __construct(
        LoggerInterface           $logger,
        DirectoryList             $dir,
        CollectionFactory         $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        StoreManagerInterface     $storeManager,
        CategoryFactory           $categoryFactory
    )
    {
        $this->logger = $logger;
        $this->dir = $dir;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->prefixLog = uniqid() . ' | Comerline Car - Rims Mapping System |';
    }

    public function mapCarRims()
    {
        $this->logger->info(new Phrase($this->prefixLog . 'Rim <-> Car Mapping Start'));
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);

        $file = fopen($this->dir->getPath('media') . '/mapeo_llantas_modelos.csv', 'r', '"');
        $processedProducts = [];

        foreach ($collection as $product) {
            $this->logger->info(new Phrase($this->prefixLog . 'Producto nuevo ID ' . $product->getData('entity_id')));
            while ($row = fgetcsv($file, 3000, ";")) {
                if ($product->getTypeId() === 'configurable') {
                    $children = $product->getTypeInstance()->getUsedProducts($product);
                    foreach ($children as $child) {
                        $this->checkAttributes($child, $row);
                    }
                } else {
                    $this->checkAttributes($product, $row);
                }
            }
        }
    }

    private function getAttributeTexts($child)
    {
        $searchableAttributes = ['width', 'diameter', 'offset', 'hub'];
        $attributes = [];
        foreach ($searchableAttributes as $sa) {
            $attribute = filter_var($child->getAttributeText($sa), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $attributes[] = sprintf('%g', $attribute);
        }
        return $attributes;
    }

    private function checkAttributes($product, $row)
    {
        $attributes = str_replace('.', ',', $this->getAttributeTexts($product));
        $csvAttributes = array_slice($row, 4);
        $categoryIds = [];
        if ($attributes === $csvAttributes) {
            $csvCategoriesRaw = array_slice($row, 0, 4);
            $csvCategories[] = $csvCategoriesRaw[0];
            $csvCategories[] = $csvCategoriesRaw[1];
            if ($csvCategoriesRaw[3] !== "") {
                $csvCategories[] = $csvCategoriesRaw[2] . " - " . $csvCategoriesRaw[3];
            } else {
                $csvCategories[] = $csvCategoriesRaw[2];
            }
            $position = 0;
            foreach ($csvCategories as $category) {
                $categoryIds[] = $this->createCategory($category, $csvCategories, $position);
                $position++;
            }
            $product->setCategoryIds($categoryIds);
            $this->logger->info(new Phrase($this->prefixLog . '¡¡¡¡¡¡IGUALES!!!!!!!!!'));
        } else {
            $this->logger->info(new Phrase($this->prefixLog . 'No son iguales'));
        }
    }

    private function getSpecificMagentoCategory($attribute, $value)
    {
        $categoryCollection = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter($attribute, $value)
            ->setPageSize(1);
        if ($categoryCollection->getSize()) {
            $categoryId = $categoryCollection->getFirstItem()->getId();
        } else {
            $categoryId = null;
        }
        return $categoryId;
    }

    private function createCategory($name, $array, $position)
    {
        if (array_search($name, $array) === 0) {
            $parentId = $this->getSpecificMagentoCategory('name','Por Vehículo');
        } else {
            $parentId = $this->getSpecificMagentoCategory('name', $array[$position - 1]);
        }

        $categoryId = $this->getSpecificMagentoCategory('parent_id', $parentId);
        if (!$categoryId) {
            $parentCategory = $this->categoryFactory->create()->load($parentId);
            $category = $this->categoryFactory->create();
            $category->setName($name);
            $category->setIsActive(true);
            $category->setParentId($parentId);
            $category->setPath($parentCategory->getPath());
            $category->save();
            $this->categories[$category->getName()] = $category->getId();
            $categoryId = $category->getId();
        }

        return $categoryId;
    }
}

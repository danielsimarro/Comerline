<?php

namespace Comerline\ConfigCar\Block;

use Comerline\ConfigCar\Helper\ConfigCarHelper;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Helper\Category;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Framework\Registry;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\View\Element\Template;

class ConfigCar extends Template
{
    protected $_isScopePrivate;
    protected $_categoryFactory;
    protected $_category;
    protected $_categoryHelper;
    protected $_categoryRepository;
    protected $_registry;
    protected $_productCollectionFactory;
    protected $_productAttributeRepository;
    protected $dir;
    protected $configCarHelper;
    private $comprobationOrder;

    public function __construct(
        Context                             $context,
        CategoryFactory                     $categoryFactory,
        Category                            $categoryHelper,
        CategoryRepository                  $categoryRepository,
        Registry                            $registry,
        CollectionFactory                   $productCollectionFactory,
        ProductAttributeRepositoryInterface $productAttributeRepository,
        DirectoryList                       $dir,
        ConfigCarHelper                     $configCarHelper,
        array                               $data = []
    )
    {
        $this->_categoryFactory = $categoryFactory;
        $this->_categoryHelper = $categoryHelper;
        $this->_categoryRepository = $categoryRepository;
        $this->_registry = $registry;
        $this->_productAttributeRepository = $productAttributeRepository;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->dir = $dir;
        $this->configCarHelper = $configCarHelper;
        $this->comprobationOrder = ['diameter', 'width', 'offset', 'hub'];
        parent::__construct($context, $data);
        $this->_isScopePrivate = true;
    }

    public function getframeAction(): string
    {
        return $this->getUrl('configcar/ajaxhandler', ['_secure' => true]);
    }

    public function getCategoriesAttributes(): string
    {
        return $this->getUrl('configcar/ajaxhandler/attributes', ['_secure' => true]);
    }

    public function getRims(): string
    {
        return $this->getUrl('configcar/ajaxhandler/rims', ['_secure' => true]);
    }

    public function getMainCategory()
    {
        $this->_category = $this->_categoryFactory->create()
            ->getCollection()
            ->addAttributeToFilter('name', 'Por Vehículo')
            ->setPageSize(1);
        return $this->_category;
    }

    public function getCategoryById($categoryId): \Magento\Catalog\Model\Category
    {
        return $this->_categoryFactory->create()->load($categoryId);
    }

    public function getProductCollection($categoryId)
    {
        return $this->getCategoryById($categoryId)->getProductCollection()->addAttributeToSelect('*');
    }

    public function getCurrentProduct()
    {
        return $this->_registry->registry('current_product');
    }


    public function getProductCollectionByCategories($ids): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        $collection = $this->_productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('type_id', 'simple');
        $collection->addCategoriesFilter(['in' => $ids]);
        return $collection;
    }

    public function compareProductAttributes($product, $variations): array
    {
        $diameter = $this->getAttributeText($this->comprobationOrder[0], $product->getData($this->comprobationOrder[0]));
        $width = $this->getAttributeText($this->comprobationOrder[1], $product->getData($this->comprobationOrder[1]));
        $offset = $this->getAttributeText($this->comprobationOrder[2], $product->getData($this->comprobationOrder[2]));
        $hub = $this->getAttributeText($this->comprobationOrder[3], $product->getData($this->comprobationOrder[3]));

        $validAttributes = [];
        foreach (json_decode($variations) as $variation) {
            $variationExplode = explode(',', $variation);
            if (($diameter === $variationExplode[0]) && ($width === $variationExplode[1]) && ($offset === $variationExplode[2]) && ($hub === $variationExplode[3])) {
                $validAttributes[] = $variation;
            }
        }

        return array_unique($validAttributes);
    }

    public function getAttributeId($attributeText, $optionText)
    {
        $attribute = $this->_productAttributeRepository->get($attributeText);
        return $attribute->getSource()->getOptionId($optionText);
    }

    public function getAttributeText($attributeText, $optionId)
    {
        $attribute = $this->_productAttributeRepository->get($attributeText);
        return $attribute->getSource()->getOptionText($optionId);
    }

    public function getCacheLifetime()
    {
        return null;
    }

}

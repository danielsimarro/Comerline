<?php

namespace Comerline\ConfigCar\Block;

class ConfigCar extends \Magento\Framework\View\Element\Template
{
    protected $_isScopePrivate;
    protected $_categoryFactory;
    protected $_category;
    protected $_categoryHelper;
    protected $_categoryRepository;
    protected $_registry;
    protected $_productCollectionFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context               $context,
        \Magento\Catalog\Model\CategoryFactory                         $categoryFactory,
        \Magento\Catalog\Helper\Category                               $categoryHelper,
        \Magento\Catalog\Model\CategoryRepository                      $categoryRepository,
        \Magento\Framework\Registry                                    $registry,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        array                                                          $data = []
    )
    {
        $this->_categoryFactory = $categoryFactory;
        $this->_categoryHelper = $categoryHelper;
        $this->_categoryRepository = $categoryRepository;
        $this->_registry = $registry;
        $this->_productCollectionFactory = $productCollectionFactory;
        parent::__construct($context, $data);
        $this->_isScopePrivate = true;
    }

    public function getframeAction()
    {
        $url = $this->getUrl('configcar/ajaxhandler', ['_secure' => true]);
        return $url;
    }

    public function getCategoriesAttributes()
    {
        $url = $this->getUrl('configcar/ajaxhandler/attributes', ['_secure' => true]);
        return $url;
    }

    public function getMainCategory()
    {
        $this->_category = $this->_categoryFactory->create()
            ->getCollection()
            ->addAttributeToFilter('name', 'Por Vehículo')
            ->setPageSize(1);
        return $this->_category;
    }

    public function getCategoryById($categoryId)
    {
        $category = $this->_categoryFactory->create()->load($categoryId);
        return $category;
    }

    public function getProductCollection($categoryId)
    {
        return $this->getCategoryById($categoryId)->getProductCollection()->addAttributeToSelect('*');
    }

    public function getCurrentProduct()
    {
        return $this->_registry->registry('current_product');
    }


    public function getProductCollectionByCategories($ids)
    {
        $collection = $this->_productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('type_id', 'simple');
        $collection->addCategoriesFilter(['in' => $ids]);
        return $collection;
    }

    public function compareProductAttributes($product1, $product2, $comparableAttributes): int
    {
        $validAttribute = 0;
        foreach ($comparableAttributes as $attribute) {
            if ($product1->getData($attribute) === $product2->getData($attribute)) $validAttribute++;
        }

        return $validAttribute;
    }
}

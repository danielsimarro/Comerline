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

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Framework\Registry $registry,
        array $data = []
    )
    {
        $this->_categoryFactory = $categoryFactory;
        $this->_categoryHelper = $categoryHelper;
        $this->_categoryRepository = $categoryRepository;
        $this->_registry = $registry;
        parent::__construct($context, $data);
        $this->_isScopePrivate = true;
    }

    public function getframeAction()
    {
        $url = $this->getUrl('configcar/ajax-handler', ['_secure' => true]);
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
        return $this->_categoryRepository->get($categoryId);
    }

    public function getProductCollection($categoryId)
    {
        return $this->getCategoryById($categoryId)->getProductCollection()->addAttributeToSelect('*');
    }

    public function getCurrentProduct()
    {
        return $this->_registry->registry('current_product');
    }
}

<?php

namespace Comerline\ConfigCar\Controller\AjaxHandler;

use Comerline\ConfigCar\Block\ConfigCar;
use Comerline\ConfigCar\Helper\ConfigCarHelper;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class Rims extends Action
{
    protected $resultJsonFactory;
    protected $resultPageFactory;
    protected $regionColFactory;
    protected $categoryFactory;
    protected $configCarHelper;
    protected $cookieManager;
    protected $dir;
    protected $productFactory;
    protected $configCarBlock;


    public function __construct(
        Context                $context,
        JsonFactory            $resultJsonFactory,
        RegionFactory          $regionColFactory,
        PageFactory            $resultPageFactory,
        DirectoryList          $dir,
        CategoryFactory        $categoryFactory,
        ConfigCarHelper        $configCarHelper,
        CookieManagerInterface $cookieManager,
        ProductFactory         $productFactory,
        ConfigCar              $configCarBlock
    )
    {
        $this->dir = $dir;
        $this->regionColFactory = $regionColFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->categoryFactory = $categoryFactory;
        $this->configCarHelper = $configCarHelper;
        $this->cookieManager = $cookieManager;
        $this->productFactory = $productFactory;
        $this->configCarBlock = $configCarBlock;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $resultPage = $this->resultPageFactory->create();
        $productId = $this->getRequest()->getParam('productId');
        $variations = $this->getRequest()->getParam('variations');

        $product = $this->productFactory->create()->load($productId);
        $validVariations = [];
        if ($product->getTypeId() === "simple") {
            $validVariations = $this->configCarBlock->compareProductAttributes($product, $variations);
        } else {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($children as $child) {
                $validVariations = array_merge($validVariations, $this->configCarBlock->compareProductAttributes($child, $variations));
            }
            $validVariations = array_unique($validVariations);
        }

        $data = ['validVariations' => $validVariations];

        $block = $resultPage->getLayout()
            ->createBlock('Comerline\ConfigCar\Block\ConfigCar')
            ->setTemplate('Comerline_ConfigCar::product/view/compatible-rims.phtml')
            ->setData('data', $data)
            ->toHtml();

        return $result->setData(['output' => $block]);
    }
}

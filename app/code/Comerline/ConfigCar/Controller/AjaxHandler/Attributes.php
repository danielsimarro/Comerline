<?php

namespace Comerline\ConfigCar\Controller\AjaxHandler;

use Comerline\ConfigCar\Helper\ConfigCarHelper;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class Attributes extends Action
{
    protected $resultJsonFactory;
    protected $resultPageFactory;
    protected $regionColFactory;
    protected $categoryFactory;
    protected $configCarHelper;
    protected $cookieManager;
    protected $dir;


    public function __construct(
        Context                $context,
        JsonFactory            $resultJsonFactory,
        RegionFactory          $regionColFactory,
        PageFactory            $resultPageFactory,
        DirectoryList          $dir,
        CategoryFactory        $categoryFactory,
        ConfigCarHelper        $configCarHelper,
        CookieManagerInterface $cookieManager
    )
    {
        $this->dir = $dir;
        $this->regionColFactory = $regionColFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->categoryFactory = $categoryFactory;
        $this->configCarHelper = $configCarHelper;
        $this->cookieManager = $cookieManager;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $resultPage = $this->resultPageFactory->create();
        $categoryId = $this->getRequest()->getParam('frame');
        $cookieCategory = $this->cookieManager->getCookie('llantas_user_car');
        if ($categoryId) {
            if ($cookieCategory === $categoryId) {
                $variations = $this->cookieManager->getCookie('llantas_variations');
            } else {
                $yearCategory = $this->categoryFactory->create()->load($categoryId);
                $yearCategoryName = $yearCategory->getName();
                $path = explode('/', $yearCategory->getPath());
                $brandCategoryId = $path[3];
                $brandCategory = $this->categoryFactory->create()->load($brandCategoryId);
                $brandCategoryName = $brandCategory->getName();
                $modelCategoryId = $path[4];
                $modelCategory = $this->categoryFactory->create()->load($modelCategoryId);
                $modelCategoryName = $modelCategory->getName();

                $csvData = $this->configCarHelper->getFilteredCsvData($brandCategoryName, $modelCategoryName, $yearCategoryName);
                $variations = [];
                foreach ($csvData as $csv) {
                    $diameter = $this->configCarHelper->mountOptionText($csv['diametro']) . "''";
                    $width = $this->configCarHelper->mountOptionText($csv['ancho']) . "cm";
                    $offset = $this->configCarHelper->mountOptionText($csv['et']) . "mm";
                    $hub = $this->configCarHelper->mountOptionText($csv['buje']) . "mm";
                    $variations[] = $diameter . "," . $width . "," . $offset . "," . $hub . ",";
                }
                $variations = json_encode($variations);
            }
            $text = $this->cookieManager->getCookie('llantas_user_text');

            $data = ['categoryId' => $categoryId, 'validVariations' => $variations, 'validText' => $text];
        }

        $block = $resultPage->getLayout()
            ->createBlock('Comerline\ConfigCar\Block\ConfigCar')
            ->setTemplate('Comerline_ConfigCar::product/view/comparable-attributes.phtml')
            ->setData('data', $data)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true)
            ->toHtml();

        return $result->setData(['output' => $block]);
    }
}

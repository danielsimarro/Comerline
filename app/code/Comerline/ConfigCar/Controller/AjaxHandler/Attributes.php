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

class Attributes extends Action
{
    protected $resultJsonFactory;
    protected $resultPageFactory;
    protected $regionColFactory;
    protected $categoryFactory;
    protected $configCarHelper;
    protected $dir;


    public function __construct(
        Context         $context,
        JsonFactory     $resultJsonFactory,
        RegionFactory   $regionColFactory,
        PageFactory     $resultPageFactory,
        DirectoryList   $dir,
        CategoryFactory $categoryFactory,
        ConfigCarHelper $configCarHelper
    )
    {
        $this->dir = $dir;
        $this->regionColFactory = $regionColFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->categoryFactory = $categoryFactory;
        $this->configCarHelper = $configCarHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $resultPage = $this->resultPageFactory->create();
        $categoryId = $this->getRequest()->getParam('frame');

        if ($categoryId) {
            $yearCategory = $this->categoryFactory->create()->load($categoryId);
            $yearCategoryName = $yearCategory->getName();
            $path = explode('/', $yearCategory->getPath());
            $brandCategoryId = $path[3];
            $brandCategory = $this->categoryFactory->create()->load($brandCategoryId);
            $brandCategoryName = $brandCategory->getName();
            $modelCategoryId = $path[4];
            $modelCategory = $this->categoryFactory->create()->load($modelCategoryId);
            $modelCategoryName = $modelCategory->getName();

            $carText = $brandCategoryName . " " . $modelCategoryName . " " . $yearCategoryName;
            $csvData = $this->configCarHelper->getFilteredCsvData(explode(" ", $carText));
            $variations = [];
            foreach ($csvData as $csv) {
                $diameter = $this->configCarHelper->mountOptionText($csv['diametro']) . "''";
                $width = $this->configCarHelper->mountOptionText($csv['ancho']) . "cm";
                $offset = $this->configCarHelper->mountOptionText($csv['et']) . "mm";
                $hub = $this->configCarHelper->mountOptionText($csv['buje']) . "mm";
                $variations[] = $diameter . "," . $width . "," . $offset . "," . $hub . ",";
            }

            $data = ['categoryId' => $categoryId, 'validVariations' => $variations];
        }

        $block = $resultPage->getLayout()
            ->createBlock('Comerline\ConfigCar\Block\ConfigCar')
            ->setTemplate('Comerline_ConfigCar::product/view/comparable-attributes.phtml')
            ->setData('data', $data)
            ->toHtml();

        return $result->setData(['output' => $block]);
    }
}

<?php

namespace Comerline\ConfigCar\Controller\AjaxHandler;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Phrase;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Directory\Model\RegionFactory;

class Compatibles extends Action
{
    protected $resultJsonFactory;

    protected $resultPageFactory;

    protected $regionColFactory;

    protected $categoryFactory;

    protected $dir;

    private $csvData;

    public function __construct(
        Context         $context,
        JsonFactory     $resultJsonFactory,
        RegionFactory   $regionColFactory,
        PageFactory     $resultPageFactory,
        DirectoryList   $dir,
        CategoryFactory $categoryFactory
    )
    {
        $this->dir = $dir;
        $this->regionColFactory = $regionColFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->categoryFactory = $categoryFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $resultPage = $this->resultPageFactory->create();
        $categoryId = $this->getRequest()->getParam('frame');

        $yearCategory = $this->categoryFactory->create()->load($categoryId);
        $yearCategoryName = $yearCategory->getName();
        $path = explode('/', $yearCategory->getPath());
        $brandCategoryId = $path[3];
        $brandCategory = $this->categoryFactory->create()->load($brandCategoryId);
        $brandCategoryName = $brandCategory->getName();
        $modelCategoryId = $path[4];
        $modelCategory = $this->categoryFactory->create()->load($modelCategoryId);
        $modelCategoryName = $modelCategory->getName();

        $csvFile = $this->dir->getPath('media') . '/mapeo_llantas_modelos.csv';
        $this->csvData = $this->readCsv($csvFile);
        $variations = [];
        foreach ($this->csvData as $csv) {
            $variation = [];
            if ($csv['marca'] === $brandCategoryName && $csv['modelo'] === $modelCategoryName) {
                if (($yearCategoryName === $csv['ano_desde'] . ' - ' . $csv['ano_hasta']) || ($yearCategoryName === $csv['ano_desde'])) {
                    $variation['diametro'] = $this->mountOptionText($csv['diametro']);
                    $variation['ancho'] = $this->mountOptionText($csv['ancho']);
                    $variation['et'] = $this->mountOptionText($csv['et']);
                    $variation['buje'] = $this->mountOptionText($csv['buje']);
                    $variations[] = $variation;
                }
            }
        }

        $data = ['categoryId' => $categoryId, 'variations' => $variations];

        $block = $resultPage->getLayout()
            ->createBlock('Comerline\ConfigCar\Block\ConfigCar')
            ->setTemplate('Comerline_ConfigCar::product/view/comparable-attributes-valid.phtml')
            ->setData('data', $data)
            ->toHtml();

        return $result->setData(['output' => $block]);
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

    private function mountOptionText($option) {
        if (strpos($option, ',')) {
            $explodedOption = explode(',', $option);
            $optionText = $explodedOption[0] . '.' . $explodedOption[1] . '0';
        } else {
            $optionText = $option . '.00';
        }
        return $optionText;
    }
}

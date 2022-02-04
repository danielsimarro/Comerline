<?php

namespace Comerline\ConfigCar\Controller\AjaxHandler;

use Comerline\ConfigCar\Block\ConfigCar;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;

class Index extends Action
{
    protected $resultJsonFactory;

    protected $resultPageFactory;

    protected $regionColFactory;

    public function __construct(
        Context       $context,
        JsonFactory   $resultJsonFactory,
        RegionFactory $regionColFactory,
        PageFactory   $resultPageFactory
    )
    {
        $this->regionColFactory = $regionColFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $resultPage = $this->resultPageFactory->create();
        $categoryId = $this->getRequest()->getParam('frame');

        $data = ['categoryId' => $categoryId];

        $block = $resultPage->getLayout()
            ->createBlock('Comerline\ConfigCar\Block\ConfigCar')
            ->setTemplate('Comerline_ConfigCar::product/view/comparable-options.phtml')
            ->setData('data', $data)
            ->toHtml();

        return $result->setData(['output' => $block]);
    }
}

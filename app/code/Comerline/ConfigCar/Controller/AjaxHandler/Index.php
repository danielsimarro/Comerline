<?php

namespace Comerline\ConfigCar\Controller\AjaxHandler;

use Comerline\ConfigCar\Block\ConfigCar;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Directory\Model\RegionFactory;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;

    protected $regionColFactory;

    private $configCar;

    public function __construct(
        Context       $context,
        JsonFactory   $resultJsonFactory,
        RegionFactory $regionColFactory,
        ConfigCar     $configCar
    )
    {
        $this->configCar = $configCar;
        $this->regionColFactory = $regionColFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $html = '<option selected="selected" value="">Seleccione una opción</option>';

        $categoryId = $this->getRequest()->getParam('frame');
        if ($categoryId != '') {
            $category = $this->configCar->getCategoryById($categoryId);
            $childrenCategories = $category->getChildrenCategories();
            foreach ($childrenCategories as $children) {
                $html .= '<option  value="' . $children->getId() . '">' . $children->getName() . '</option>';
            }
        }

        return $result->setData(['output' => $html]);
    }
}

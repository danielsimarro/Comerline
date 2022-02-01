<?php

namespace Comerline\ConfigCar\Controller\AjaxHandler;

use Comerline\ConfigCar\Block\ConfigCar;

class Attributes extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;

    protected $regionColFactory;

    private $configCar;

    public function __construct(
        \Magento\Framework\App\Action\Context            $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Directory\Model\RegionFactory           $regionColFactory,
        ConfigCar                                        $configCar
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

        $comparableAttributes = ['diameter', 'width', 'offset', 'hub'];

        $html = '<br/>
        <h4>Opciones:</h4>
        <br/>
        <table id="compatible-table">
            <tr>
                <th>Diametro</th>
                <th>Ancho</th>
                <th>ET</th>
                <th>Buje</th>
            </tr>
            <tr>';

        $categoryId = $this->getRequest()->getParam('frame');
        if ($categoryId != '') {
            $productCollection = $this->configCar->getProductCollectionByCategories(array($categoryId));
            $categoryProduct = $productCollection->getFirstItem();
            foreach ($comparableAttributes as $attribute) {
                $html .= '<th id="attribute-option">' . $categoryProduct->getAttributeText($attribute) . '</th>';
            }
            $html .= '</tr></table>';
        }

        return $result->setData(['success' => true, 'value' => $html]);
    }
}

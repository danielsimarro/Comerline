<?php
//Create the new input checkout with your settings
namespace Comerline\CheckoutFields\Plugin\Checkout\Block\Checkout;

class LayoutProcessor
{
    public function afterProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        array  $jsLayout
    ) {
 
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
        ['shippingAddress']['children']['shipping-address-fieldset']['children']['custom_dni'] = [
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes',
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
                'options' => [],
                'tooltip' => [
                    'description' => 'Example DNI: 7894521F / CIF: A58818501',
                ],
                'id' => 'custom-dni'
            ],
            'dataScope' => 'shippingAddress.custom_attributes.custom_dni',
            'label' => 'DNI/CIF',
            'provider' => 'checkoutProvider',
            'visible' => true,
            'validation' => [
                "max_text_length" => 9,
                "pattern" => "^[0-9]{8}[A-Za-z]{1}$|^[A-Za-z]{1}[0-9]{7}[A-Za-z]{1}$|^[A-Za-z]{1}[0-9]{8}$",
            ],
            'sortOrder' => 70,
            'id' => 'custom-dni'
        ];
 
 
        return $jsLayout;
    }
}
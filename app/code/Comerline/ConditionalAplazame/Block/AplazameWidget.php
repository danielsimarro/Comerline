<?php

namespace Comerline\ConditionalAplazame\Block;

use Aplazame\Payment\Block\Js;
use Aplazame\Payment\Gateway\Config\Config;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;

class AplazameWidget extends Js
{

    private Registry $registry;

    public function __construct(
        Context $context,
        Config $config,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
        $this->registry = $registry;
    }

    public function getTemplate()
    {
        return 'Comerline_ConditionalAplazame::aplazamewidget.phtml';
    }

    public function isEnabled()
    {
        $product = $this->registry->registry('current_product') ?? null;
        if (!$product) {
            return true; //Not product page.
        }
        $price = $product->getFinalPrice(true);
        return $price > 2000;
    }
}

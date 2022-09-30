<?php

namespace Comerline\ConditionalAplazame\Plugin;

use Aplazame\Payment\Model\Aplazame;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\Data\CartInterface;

class MethodAvailable
{
    public function aroundGetAvailableMethods(MethodList $subject, callable $proceed, CartInterface $quote = null)
    {
        $grandTotal = 0;
        if (is_object($quote)) {
            $grandTotal = $quote->getGrandTotal();
        }
        $result = $proceed($quote); //Original method.
        if (floatval($grandTotal) < 1000) {
            //Remove aplazame.
            foreach ($result as $key => $_result) {
                if ($_result->getCode() == Aplazame::PAYMENT_METHOD_CODE) {
                    unset($result[$key]);
                }
            }
        }
        return $result;
    }
}

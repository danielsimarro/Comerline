<?php
//With the observer we can store the input data in the BD

namespace Comerline\CheckoutFields\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class SaveOrderObserver implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();

        $order->setData('custom_dni', $quote->getCustomDni());

        return $this;
    }
}
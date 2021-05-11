<?php

namespace Llantasonline\Orders\Observer;

use Llantasonline\Orders\Model\Order;

class ProcessOrder implements \Magento\Framework\Event\ObserverInterface
{

    private $order;

    public function __construct(
      Order $order
    ) {
        $this->order = $order;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $orderDetails = $observer->getOrder();

        $this->order->getOrderDetails($orderDetails->getData());
    }
}

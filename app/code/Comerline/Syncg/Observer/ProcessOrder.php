<?php

namespace Comerline\Syncg\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Comerline\Syncg\Model\Order;

class ProcessOrder implements ObserverInterface
{

    private $order;

    public function __construct(
      Order $order
    ) {
        $this->order = $order;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();
        $this->order->getOrderDetails($order);
    }
}

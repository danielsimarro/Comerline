<?php

namespace Comerline\Syncg\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Comerline\Syncg\Model\Order;
use Comerline\Syncg\Helper\Config;

class ProcessOrder implements ObserverInterface
{

    private $order;

    private $config;

    public function __construct(
      Order $order,
      Config $configHelper
    ) {
        $this->order = $order;
        $this->config = $configHelper;
    }

    public function execute(Observer $observer)
    {
        if ($this->config->getGeneralConfig('enable_order_sync') === "1") {
            $order = $observer->getOrder();
            $this->order->getOrderDetails($order);
        }
    }
}

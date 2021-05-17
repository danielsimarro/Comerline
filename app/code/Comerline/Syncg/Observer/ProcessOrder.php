<?php

namespace Comerline\Syncg\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Comerline\Syncg\Model\Order;
use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\SyncgStatus;

class ProcessOrder implements ObserverInterface
{

    private $order;

    private $config;

    private $syncgStatusRepository;

    public function __construct(
      Order $order,
      Config $configHelper,
      SyncgStatusRepository $syncgStatusRepository
    ) {
        $this->order = $order;
        $this->config = $configHelper;
        $this->syncgStatusRepository = $syncgStatusRepository;
    }

    public function execute(Observer $observer)
    {
        if ($this->config->getGeneralConfig('enable_order_sync') === "1") {
            $order = $observer->getOrder();
            $this->syncgStatusRepository->updateEntityStatus($order->getData('increment_id'), SyncgStatus::TYPE_ORDER, SyncgStatus::STATUS_PENDING);
            $this->order->getOrderDetails($order);
//  This will go in the CRON job, and was made only to test that the database changes when the TXT of the order is created
//            if($this->order->getOrderDetails($order) === 1){
//                $this->syncgStatusRepository->updateStatus($order, SyncgStatus::STATUS_COMPLETED);
//            }
        }
    }
}

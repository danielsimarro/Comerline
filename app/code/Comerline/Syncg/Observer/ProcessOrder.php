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

    /**
     * @var Order
     */
    private $order;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var SyncgStatusRepository
     */
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
        }
    }
}

<?php

namespace Comerline\Syncg\Helper;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\Order;

class Syncg
{

    /**
     * @var SyncgStatusRepository
     */
    private $syncgStatusRepository;

    /**
     * @var CollectionFactory
     */
    private $syncgStatusCollectionFactory;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        SyncgStatusRepository $syncgStatusRepository,
        CollectionFactory $syncgStatusCollectionFactory,
        Order $order,
        Config $config
    ) {
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->order = $order;
        $this->config = $config;
    }

    public function syncgAll(){
        if ($this->config->getGeneralConfig('enable_order_sync') === "1") {
            $this->fetchPending();
        }
    }

    public function fetchPending(){
        $orderIds = [];
        $collection = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('status', SyncgStatus::STATUS_PENDING);
        foreach ($collection as $item){
            $orderIds[] = $item->getData('mg_id');
        }
        $this->order->getOrderDetails($orderIds);
    }
}

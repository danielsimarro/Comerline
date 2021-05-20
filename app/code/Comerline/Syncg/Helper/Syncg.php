<?php

namespace Comerline\Syncg\Helper;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\Order;
use Comerline\Syncg\Service\SyncgApiRequest\Login;

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

    private $login;

    public function __construct(
        SyncgStatusRepository $syncgStatusRepository,
        CollectionFactory $syncgStatusCollectionFactory,
        Order $order,
        Config $config,
        Login $login
    ) {
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->order = $order;
        $this->config = $config;
        $this->login = $login;
    }

    public function syncgAll(){
        $this->connectToAPI();
        if ($this->config->getGeneralConfig('enable_order_sync') === "1") {
            $this->fetchPendingOrders();
        }
    }

    public function fetchPendingOrders(){
        $orderIds = [];
        $collection = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('status', SyncgStatus::STATUS_PENDING)
            ->addFieldToFilter('type', SyncgStatus::TYPE_ORDER);
        foreach ($collection as $item){
            $orderIds[] = $item->getData('mg_id');
        }
        $this->order->getOrderDetails($orderIds);
    }

    public function connectToAPI(){
        $this->login->send();
    }
}

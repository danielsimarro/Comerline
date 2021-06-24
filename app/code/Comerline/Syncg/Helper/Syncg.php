<?php

namespace Comerline\Syncg\Helper;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\Order;
use Comerline\Syncg\Service\SyncgApiRequest\CheckDeletedArticles;
use Comerline\Syncg\Service\SyncgApiRequest\Login;
use Comerline\Syncg\Service\SyncgApiRequest\GetArticles;
use Comerline\Syncg\Service\SyncgApiRequest\Logout;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

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

    /**
     * @var Login
     */
    private $login;

    /**
     * @var GetArticles
     */
    private $getArticles;

    private $checkDeletedArticles;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Logout
     */
    private $logout;

    public function __construct(
        SyncgStatusRepository $syncgStatusRepository,
        CollectionFactory $syncgStatusCollectionFactory,
        Order $order,
        Config $config,
        Login $login,
        Logout $logout,
        GetArticles $getArticles,
        CheckDeletedArticles $checkDeletedArticles,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->order = $order;
        $this->config = $config;
        $this->login = $login;
        $this->logout = $logout;
        $this->getArticles = $getArticles;
        $this->checkDeletedArticles = $checkDeletedArticles;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    public function syncgAll(){
        $this->connectToAPI();
        if ($this->config->getGeneralConfig('enable_order_sync') === "1") {
            $this->fetchPendingOrders();
        }
        if ($this->checkMakeSync()){
            $this->config->setSyncInProgress(true);
            $this->fetchArticles();
            $this->config->setLastDateSyncProducts($this->dateTime->gmtDate());
            $this->config->setSyncInProgress(false);
        }
        $this->disconnectFromAPI();
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

    public function fetchArticles(){
        $this->getArticles->send();
        $this->checkDeletedArticles->send();
    }

    public function connectToAPI(){
        $this->login->send();
    }

    public function disconnectFromAPI(){
        $this->logout->send();
    }

    private function checkMakeSync(): bool
    {
        $makeSync = true;
        $currentDate = $this->dateTime->gmtTimestamp();
        $lastSyncPlusFiveMinutes = $this->config->getLastSyncPlusFiveMinutes();
        if ($this->config->syncInProgress()) {
            $makeSync = false;
            $this->logger->info('Syncg | Sync in progress');
        }
//        if ($currentDate < $lastSyncPlusFiveMinutes) {
//            $makeSync = false;
//            $this->logger->info("Syncg | Five minutes haven't passed");
//        }
        return $makeSync;
    }
}

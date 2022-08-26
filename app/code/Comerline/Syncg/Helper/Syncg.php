<?php

namespace Comerline\Syncg\Helper;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\Order;
use Comerline\Syncg\Service\SyncgApiRequest\CheckDeletedArticles;
use Comerline\Syncg\Service\SyncgApiRequest\Login;
use Comerline\Syncg\Service\SyncgApiRequest\GetArticles;
use Comerline\Syncg\Service\SyncgApiRequest\GetStock;
use Comerline\Syncg\Service\SyncgApiRequest\Logout;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Syncg
{
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Logout
     */
    private $logout;
    private GetStock $getStock;
    private StoreManagerInterface $storeManager;

    public function __construct(
        CollectionFactory $syncgStatusCollectionFactory,
        Order $order,
        Config $config,
        Login $login,
        Logout $logout,
        GetArticles $getArticles,
        GetStock $getStock,
        CheckDeletedArticles $checkDeletedArticles,
        StoreManagerInterface      $storeManager,
        LoggerInterface $logger
    ) {
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->order = $order;
        $this->config = $config;
        $this->login = $login;
        $this->logout = $logout;
        $this->getArticles = $getArticles;
        $this->getStock = $getStock;
        $this->checkDeletedArticles = $checkDeletedArticles;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    public function syncgAll(){
        $this->storeManager->setCurrentStore(0);
        if ($this->checkMakeSync()){
            $this->config->setSyncInProgress(true);
            $this->connectToAPI();
            if ($this->config->getGeneralConfig('enable_order_sync') === "1") {
                $this->fetchPendingOrders();
            }
            $this->fetchArticles();
            $this->config->setSyncInProgress(false);
            $this->disconnectFromAPI();
        }
    }

    public function syncgStock()
    {
        $this->storeManager->setCurrentStore(0);
        if ($this->checkMakeStock()) {
            $this->config->setStockInProgress(true);
            $this->connectToAPI();
            $this->fetchStock();
            $this->disconnectFromAPI();
            $this->config->setStockInProgress(false);
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

    public function fetchArticles(){
        $this->getArticles->send();
        $this->checkDeletedArticles->send();
    }

    public function fetchStock(){
        $this->getStock->send();
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
        if ($this->config->syncInProgress()) {
            $makeSync = false;
            $this->logger->info('Syncg | Sync in progress');
        }
        return $makeSync;
    }

    private function checkMakeStock(): bool
    {
        $makeSync = true;
        if ($this->config->stockInProgress()) {
            $makeSync = false;
            $this->logger->info('Stock | Stock in progress');
        }
        return $makeSync;
    }
}

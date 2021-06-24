<?php

namespace Comerline\Syncg\Observer;

use Comerline\Syncg\Service\SyncgApiRequest\CreateOrder;
use Comerline\Syncg\Service\SyncgApiRequest\GetClients;
use Comerline\Syncg\Service\SyncgApiRequest\Logout;
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

    /**
     * @var GetClients
     */
    private $getClients;

    /**
     * @var CreateOrder
     */
    private $createOrder;

    /**
     * @var Logout
     */
    private $logout;

    public function __construct(
      Order $order,
      Config $configHelper,
      SyncgStatusRepository $syncgStatusRepository,
      GetClients $getClients,
      CreateOrder $createOrder,
      Logout $logout
    ) {
        $this->order = $order;
        $this->config = $configHelper;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->getClients = $getClients;
        $this->createOrder = $createOrder;
        $this->logout = $logout;
    }

    public function execute(Observer $observer)
    {
        if ($this->config->getGeneralConfig('enable_order_sync') === "1") {
            $order = $observer->getOrder();
            $clientId = $this->getClients->checkClients($order);
            $gId = $this->createOrder->createOrder($order, $clientId);
            $this->syncgStatusRepository->updateEntityStatus($order->getData('increment_id'), $gId, SyncgStatus::TYPE_ORDER, SyncgStatus::STATUS_PENDING);
            $this->logout->send();
        }

    }
}

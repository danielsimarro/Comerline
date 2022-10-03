<?php

namespace Comerline\Syncg\Model;

use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Service\SyncgApiRequest\CreateOrder;
use Comerline\Syncg\Service\SyncgApiRequest\GetClients;
use Comerline\Syncg\Service\SyncgApiRequest\Logout;
use Magento\Framework\Filesystem;
use \Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Order
{

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SyncgStatusRepository
     */
    private $syncgStatusRepository;

    /**
     * @var CollectionFactory
     */
    private $syncgStatusCollectionFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SyncgStatusRepository    $syncgStatusRepository,
        Filesystem               $filesystem,
        CreateOrder              $createOrder,
        Logout                   $logout,
        GetClients               $getClients,
        CollectionFactory        $syncgStatusCollectionFactory
    )
    {
        $this->orderRepository = $orderRepository;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->filesystem = $filesystem;
        $this->getClients = $getClients;
        $this->createOrder = $createOrder;
        $this->logout = $logout;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
    }

    public function sendOrders($orderIds)
    {
        $varPath = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        $relativePath = $varPath . 'log/order.log';
        foreach ($orderIds as $id) {
            $order = $this->orderRepository->get(intval($id));
            $orderData = "####### ID Pedido: " . $order->getData('increment_id') . PHP_EOL;
            $orderData .= "Nombre Cliente: " . $order->getData('customer_firstname') . PHP_EOL;
            $orderData .= "Apellidos Cliente: " . $order->getData('customer_firstname') . PHP_EOL;
            $orderData .= "E-mail Cliente: " . $order->getData('customer_email') . PHP_EOL;
            $orderData .= "Artículos [ " . PHP_EOL;
            $items = $order->getData('items');
            $count = 1;
            foreach ($items as $item) {
                $orderData .= "Artículo " . $count . ": " . PHP_EOL;
                $orderData .= "Nombre Artículo: " . $item->getData('name') . PHP_EOL;
                $orderData .= "SKU: " . $item->getData('sku') . PHP_EOL;
                $orderData .= "Precio: " . $item->getData('price') . PHP_EOL;
                $count++;
            }
            $orderData .= "] " . PHP_EOL;
            $orderData .= "Precio Total (con envío): " . $order->getData('base_grand_total') . PHP_EOL;
            file_put_contents($relativePath, $orderData);
            $clientId = $this->getClients->checkClients($order);
            $gId = $this->createOrder->createOrder($order, $clientId);
            $this->syncgStatusRepository->updateEntityStatus($id, $gId, SyncgStatus::TYPE_ORDER, SyncgStatus::STATUS_COMPLETED);
        }
        $this->logout->send();
    }
}

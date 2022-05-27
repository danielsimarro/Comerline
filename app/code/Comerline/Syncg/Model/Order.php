<?php

namespace Comerline\Syncg\Model;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
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


    protected $filesystem;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SyncgStatusRepository $syncgStatusRepository,
        Filesystem $filesystem
    ){
        $this->orderRepository = $orderRepository;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->filesystem = $filesystem;
    }

    public function getOrderDetails($orderIds)
    {
        $varPath = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        $relativePath = $varPath . 'log/order.log';
        foreach ($orderIds as $id){
            $order = $this->orderRepository->get(intval($id));
            $orderData = "ID Pedido: " . $order->getData('increment_id') . "\n";
            $orderData .= "Nombre Cliente: " . $order->getData('customer_firstname') . "\n";
            $orderData .= "Apellidos Cliente: " . $order->getData('customer_firstname') . "\n";
            $orderData .= "E-mail Cliente: " . $order->getData('customer_email') . "\n";
            $orderData .= "Artículos[ " . "\n";
            $items = $order->getData('items');
            $count = 1;
            foreach($items as $item) {
                $orderData .= "Artículo " . $count . ": \n";
                $orderData .=  "Nombre Artículo: " . $item->getData('name') . "\n";
                $orderData .=  "SKU: " . $item->getData('sku') . "\n";
                $orderData .=  "Precio: " . $item->getData('price') . "\n";
                $count++;
            }
            $orderData .=  "] \n";
            $orderData .=  "Precio Total (con envío): " . $order->getData('base_grand_total') . "\n";
            file_put_contents($relativePath, $orderData);
            $this->syncgStatusRepository->updateEntityStatus($id, 0, SyncgStatus::TYPE_ORDER, SyncgStatus::STATUS_COMPLETED);
        }
    }
}

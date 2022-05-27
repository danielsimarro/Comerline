<?php

namespace Comerline\Syncg\Model;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use \Magento\Sales\Api\OrderRepositoryInterface;

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

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SyncgStatusRepository $syncgStatusRepository
    ){
        $this->orderRepository = $orderRepository;
        $this->syncgStatusRepository = $syncgStatusRepository;
    }

    public function getOrderDetails($orderIds)
    {
        foreach ($orderIds as $id){
            $order = $this->orderRepository->get(intval($id));
            $file = fopen('/home/alberto/order.txt', 'w');
            fwrite($file, "ID Pedido: " . $order->getData('increment_id') . "\n");
            fwrite($file, "Nombre Cliente: " . $order->getData('customer_firstname') . "\n");
            fwrite($file, "Apellidos Cliente: " . $order->getData('customer_firstname') . "\n");
            fwrite($file, "E-mail Cliente: " . $order->getData('customer_email') . "\n");
            fwrite($file, "Artículos[ " . "\n");
            $items = $order->getData('items');
            $count = 1;
            foreach($items as $item) {
                fwrite($file, "Artículo " . $count . ": \n");
                fwrite($file, "Nombre Artículo: " . $item->getData('name') . "\n");
                fwrite($file, "SKU: " . $item->getData('sku') . "\n");
                fwrite($file, "Precio: " . $item->getData('price') . "\n");
                $count++;
            }
            fwrite($file, "] \n");
            fwrite($file, "Precio Total (con envío): " . $order->getData('base_grand_total') . "\n");
            $this->syncgStatusRepository->updateEntityStatus($id, 0, SyncgStatus::TYPE_ORDER, SyncgStatus::STATUS_COMPLETED);
        }
    }
}

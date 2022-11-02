<?php
namespace Comerline\CheckoutFields\Observer;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\OrderFactory as OrderResourceModelFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;

/**
 * Class AfterPlaceOrder
 */
class AfterPlaceOrder implements ObserverInterface
{
    /**
     * @var RegionCollectionFactory
     */
    private $regionCollectionFactory;
    /**
     * @var OrderResourceModelFactory
     */
    private $orderResourceModelFactory;

    /**
     * @var OrderFactory
     */
    protected $orderModel;
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * PlaceOrder constructor.
     * @param ManagerInterface $messageManager
     * @param OrderResourceModelFactory $orderResourceModelFactory
     * @param RegionCollectionFactory $regionCollectionFactory
     * @param OrderFactory $orderModel
     */
    public function __construct(
        ManagerInterface $messageManager,
        OrderResourceModelFactory $orderResourceModelFactory,
        RegionCollectionFactory $regionCollectionFactory,
        OrderFactory $orderModel
    ) {
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->orderResourceModelFactory = $orderResourceModelFactory;
        $this->orderModel = $orderModel;
        $this->messageManager = $messageManager;
    }

    /**
     * @param EventObserver $observer
     * @throws Exception
     */
    public function execute(EventObserver $observer)
    {
        try {
            /**
             * @var Order $order
             */

            $order = $observer->getEvent()->getOrder();
            if ($order == null) {
                $orders = $observer->getEvent()->getOrders();
                foreach ($orders as $order) {
                    $this->setBillingAddress($order);
                    $this->setShippingAddress($order);
                }
            } else {
                $this->setBillingAddress($order);
                $this->setShippingAddress($order);
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }

    /**
     * We get the value of the custom ID and save it in the Vat Number in Billing Address
     * 
     * @return nothing
     */
    public function setBillingAddress($order)
    {
        $customDNI = $order->getCustomDni();
        $order->getBillingAddress()->setVatId($customDNI);
        $this->orderResourceModelFactory->create()->save($order);
    }

    /**
     * We get the value of the custom ID and save it in the Vat Number Shipping Address
     * 
     * @return nothing
     */
    public function setShippingAddress($order)
    {
        $customDNI = $order->getCustomDni();
        $order->getShippingAddress()->setVatId($customDNI);
        $this->orderResourceModelFactory->create()->save($order);
    }

}
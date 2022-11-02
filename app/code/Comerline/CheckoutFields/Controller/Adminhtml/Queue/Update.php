<?php
namespace Comerline\CheckoutFields\Controller\Adminhtml\Queue;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Backend\App\Action;
use Magento\Sales\Api\OrderRepositoryInterface;

class Update extends Action
{
    protected $quoteFactory;
    protected $orderinterface;
    protected $request;
 
    public function __construct(
        Action\Context $context,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Sales\Api\Data\OrderInterface $orderinterface,
        \Magento\Framework\App\Request\Http $request)
    {
        parent::__construct($context);
        $this->orderinterface = $orderinterface;
        $this->request = $request;
        $this->quoteFactory = $quoteFactory;
    }
   
    public function execute()
    {
        
        $customfield1value = $this->getRequest()->getPostValue('customfield1value');
        $orderid = $this->getRequest()->getPostValue('orderid');
        $order = $this->orderinterface->load($orderid);
        $order->setData('custom_dni', $customfield1value);
        $order->save();
    }
}

<?php

namespace Redsys\Redsys\Observer;

use Redsys\Redsys\Helper\RedsysLibrary;

class Email implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    try{
        $order = $observer->getEvent()->getOrder();
        $this->_current_order = $order;

        $idLog=RedsysLibrary::generateIdLog();
        RedsysLibrary::escribirLog($idLog, "Entrando en Email.php...", true);

        $payment = $order->getPayment()->getMethodInstance()->getCode();
        RedsysLibrary::escribirLog($idLog, "Capturado evento de E-Mail. Valor de payment: " .$payment, true);

        if($payment == 'redsys'){
            RedsysLibrary::escribirLog($idLog, "Intentando parar envío...", true);
            $this->stopNewOrderEmail($order);
        }
    }
    catch (\ErrorException $ee){

    }
    catch (\Exception $ex)
    {

    }
    catch (\Error $error){

    }

}

public function stopNewOrderEmail(\Magento\Sales\Model\Order $order){
    $order->setCanSendNewEmailFlag(false);
    $order->setSendEmail(false);
    $order->setIsCustomerNotified(false);
    try{
        $order->save();
        
        $idLog=RedsysLibrary::generateIdLog();
        RedsysLibrary::escribirLog($idLog, "Parado y guardado.", true);
    }
    catch (\ErrorException $ee){

    }
    catch (\Exception $ex)
    {

    }
    catch (\Error $error){

    }
}
}
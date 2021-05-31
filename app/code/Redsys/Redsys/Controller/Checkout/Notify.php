<?php
/**
* NOTA SOBRE LA LICENCIA DE USO DEL SOFTWARE
* 
* El uso de este software está sujeto a las Condiciones de uso de software que
* se incluyen en el paquete en el documento "Aviso Legal.pdf". También puede
* obtener una copia en la siguiente url:
* http://www.redsys.es/wps/portal/redsys/publica/areadeserviciosweb/descargaDeDocumentacionYEjecutables
* 
* Redsys es titular de todos los derechos de propiedad intelectual e industrial
* del software.
* 
* Quedan expresamente prohibidas la reproducción, la distribución y la
* comunicación pública, incluida su modalidad de puesta a disposición con fines
* distintos a los descritos en las Condiciones de uso.
* 
* Redsys se reserva la posibilidad de ejercer las acciones legales que le
* correspondan para hacer valer sus derechos frente a cualquier infracción de
* los derechos de propiedad intelectual y/o industrial.
* 
* Redsys Servicios de Procesamiento, S.L., CIF B85955367
*/
namespace Redsys\Redsys\Controller\Checkout;

use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DB\Transaction;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

use Redsys\Redsys\Controller\RedsysController;
use Redsys\Redsys\Helper\RedsysApi;
use Redsys\Redsys\Helper\RedsysLibrary;

class Notify extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\CsrfAwareActionInterface
{
    protected $_resultPageFactory;
    protected $_redsysController;
    protected $_session;
    protected $_invoiceService;
    protected $_invoiceSender;
    protected $_cart;
    protected $_formKey;
    protected $_productRepository;

    public function __construct(Context $context, Session $session, PageFactory $resultPageFactory, StoreManagerInterface $storeManager, RedsysController $redsysController, InvoiceService $invoiceService, InvoiceSender $invoiceSender, Cart $cart, ProductRepository $productRepository, FormKey $formKey)
    {
    	$this->_session = $session;
    	$this->_invoiceSender = $invoiceSender;
    	$this->_invoiceService = $invoiceService;
    	$this->_redsysController = $redsysController;
    	$this->_resultPageFactory = $resultPageFactory;
    	$this->_cart = $cart;
    	$this->_formKey = $formKey;
    	$this->_productRepository = $productRepository;
    	parent::__construct($context);
    }

	/**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    
    public function execute()
    {
    	$resultPage = $this->_resultPageFactory->create();
    	$resultPage->getConfig()->getTitle()->append(__("Notificacion")." - Redsys");
    	$resultPage->getLayout()->initMessages();
    	
    	$version=null;
    	$datos=null;
    	$firma_remota=null;
    	$hayDatos=false;
    	$esPost=false;
    	$idLog=RedsysLibrary::generateIdLog();
    	$logActivo=$this->_redsysController->get_logactivo();
		RedsysLibrary::escribirLog($idLog,"Parámetros POST recibidos: " . print_r($_POST, true) ,$logActivo);
		RedsysLibrary::escribirLog($idLog,"Parámetros GET recibidos: " . print_r($_GET, true) ,$logActivo);
        if (!empty($_POST)) //URL RESP. ONLINE
        { 
			$version     = $_POST["Ds_SignatureVersion"];
			$datos    = $_POST["Ds_MerchantParameters"];
			$firma_remota    = $_POST["Ds_Signature"];
    		$hayDatos=true;
			$esPost=true;
        }
        else if (!empty($_GET)) //URL RESP. ONLINE
        { 
			$version     	= $_GET["Ds_SignatureVersion"];
			$datos    		= $_GET["Ds_MerchantParameters"];
			$firma_remota   = $_GET["Ds_Signature"];
    		$hayDatos=true;
			$esPost=false;
        }

        if($hayDatos){
        	$resValidacion=$this->validaPedido($version, $datos, $firma_remota, $esPost, $idLog, $logActivo);
        	$order=$resValidacion[1];
        	$pedido=$order->getId();
        	
        	switch ($resValidacion[0]) {
        		case 1:
        			if($esPost){        				
        				RedsysLibrary::escribirLog($idLog,"Pedido ".$pedido.": Validaciones NO superadas. Código de error: " .$resValidacion[3]. ".",$logActivo);

						$order->cancel()->setState(\Magento\Sales\Model\Order::STATE_CANCELED, 'canceled', 'El pedido ha sido cancelado.', false)->save();
						RedsysLibrary::escribirLog($idLog,"Orden cancelada.",$logActivo);

        				$order->addStatusHistoryComment($resValidacion[2], false)
        				->setIsCustomerNotified(false)
        				->save();
        				
        				die(__("Validaciones NO superadas"));
        			}			
        			else{
        				if($order && $this->_cart->getItemsCount()==0){
							$orderItems = $order->getAllItems();
        					if($orderItems){
						    	foreach($orderItems as $item){
						    		$info = $item->getProductOptionByCode('info_buyRequest');
						    		$params=array(
					    				'form_key'	=> $this->_formKey->getFormKey(),
						    		);
						    		$product = $this->_productRepository->getById($item->getProductId());
						    		
						    		$this->_cart->addProduct($product,$params + $info);
						    	}
					    		$this->_cart->save();
        					}
        				}
        				
        				$resultPage->getLayout()->getBlock('redsys_checkout_notify')->setExito(2);
        				$resultPage->getLayout()->getBlock('redsys_checkout_notify')->setURL($this->_redsysController->get_baseURL());
        			}
        		break;
        		case 2:
        			if($esPost){
        				if($order){
        					if ($order->canCancel()) {
        						try {
        							$order->cancel();
        							$order->save();
        				
        							$order->addStatusHistoryComment(__("Pedido cancelado por Redsys."), false)
        							->setIsCustomerNotified(false)
        							->save();
        						} catch (Exception $e) {
        							RedsysLibrary::escribirLog($idLog,"Pedido ".$pedido.": Excepción: $e",$logActivo);
        							$order->addStatusHistoryComment('Redsys: Exception message: '.$e->getMessage(), false);
        							$order->save();
        						}
        					}
        				}
        				 
        				RedsysLibrary::escribirLog($idLog,"Pedido ".$pedido.": Validaciones NO superadas.",$logActivo);
        				die(__("Validaciones NO superadas"));
        			}	
        			else{        				
        				$resultPage->getLayout()->getBlock('redsys_checkout_notify')->setExito(3);
        			}
        		break;
        		default: 
        			if($esPost){
        				RedsysLibrary::escribirLog($idLog,"Pedido ".$pedido.": Validaciones superadas.",$logActivo);
        				$this->confirmaPedido($order);

        				die(__("Validaciones superadas"));
        			}
        			else{
        				$resultPage->getLayout()->getBlock('redsys_checkout_notify')->setExito(0);
        			}
        		break;
        	}
        }
        else{
   			RedsysLibrary::escribirLog($idLog,"Notificacion vacía.",$logActivo);
        	$resultPage->getLayout()->getBlock('redsys_checkout_notify')->setExito(1);
        }
    	return $resultPage;
    }
    
    private function validaPedido($version, $datos, $firma_remota, $idLog, $logActivo){
    	$mantenerPedidoAnteError =$this->_redsysController->get_errorpago();
    	$clave256 = $this->_redsysController->get_clave256();
    	
    	/** Extraer datos locales **/
    	$codigoOrig =    $this->_redsysController->get_num();
    	$terminalOrig =  $this->_redsysController->get_terminal();
    	//$tipoTransOrig = $this->_redsysController->get_trans();
    	$monedaOrig =    $this->_redsysController->get_moneda();
    	if($monedaOrig=="0")
    		$monedaOrig="978";
    	else
    		$monedaOrig="840";
    			 
   		$miObj=new RedsysApi();
   		$decodec = $miObj->decodeMerchantParameters($datos);
		$firma_local = $miObj->createMerchantSignatureNotif($clave256,$datos);
  			 
  			/** Extraer datos de la notificación **/
		$total     = $miObj->getParameter('Ds_Amount');
   		$pedido    = ltrim(substr($miObj->getParameter('Ds_Order'),0,11),'0');
   		$codigo    = $miObj->getParameter('Ds_MerchantCode');
   		$terminal  = $miObj->getParameter('Ds_Terminal');
   		$moneda    = $miObj->getParameter('Ds_Currency');
   		$respuesta = $miObj->getParameter('Ds_Response');
   		$fecha	   = $miObj->getParameter('Ds_Date');
   		$hora	   = $miObj->getParameter('Ds_Hour');
   		$id_trans  = $miObj->getParameter('Ds_AuthorisationCode');
   		//$tipoTrans = $miObj->getParameter('Ds_actionType');
   		 
   		$totalOrig=0;
   		$order=null;
   		if(RedsysLibrary::checkPedidoNum($pedido)){
   			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
   			$order = $objectManager->create('\Magento\Sales\Model\Order')->load($pedido);
   			$totalOrig=floatval($order->getTotalDue())*100;
   		}
    		 
   		if ($firma_local === $firma_remota
   			&& RedsysLibrary::checkImporte($total)
   			&& RedsysLibrary::checkPedidoNum($pedido)
   			&& $totalOrig == $totalOrig
   			&& RedsysLibrary::checkFuc($codigo)
   			&& RedsysLibrary::checkMoneda($moneda)
   			&& RedsysLibrary::checkRespuesta($respuesta)
   			&& intval($respuesta)<100
   			&& $codigo == $codigoOrig
   			&& intval(strval($terminalOrig)) == intval(strval($terminal))
   		) {   	
   			return array(0, $order);
   		}
   		else{
   			$comentario="REDSYS - Error desconocido.";
   			if (!($firma_local === $firma_remota)) {
   				RedsysLibrary::escribirLog($idLog,"La firma no coincide. ($firma_local : $firma_remota)",$logActivo);
   				$comentario="REDSYS - Error de firma.";
   			}
   			if (!($monedaOrig == $moneda)) {
   				RedsysLibrary::escribirLog($idLog,"La moneda no coincide. ($monedaOrig : $moneda)",$logActivo);
   				$comentario="REDSYS - La moneda no coincide. ($monedaOrig : $moneda).";
   			}
   			if (!($totalOrig == $total)) {
   				RedsysLibrary::escribirLog($idLog,"El importe total no coincide. ($totalOrig : $total)",$logActivo);
   				$comentario="REDSYS - El importe total no coincide. ($totalOrig : $total).";
   			}
   			if (!((int)$codigoOrig == (int)$codigo)) {
   				RedsysLibrary::escribirLog($idLog,"El codigo de comercio no coincide. ($codigoOrig : $codigo)",$logActivo);
   				$comentario="REDSYS - El codigo de comercio no coincide. ($codigoOrig : $codigo).";
   			}
   			if (!RedsysLibrary::checkAutCode($id_trans)){
   				RedsysLibrary::escribirLog($idLog,"Ds_AuthorisationCode invalido. ($id_trans)",$logActivo);
   				$comentario="REDSYS - Ds_AuthorisationCode invalido. ($id_trans).";
   			}
   			if(!RedsysLibrary::checkRespuesta($respuesta)){
   				RedsysLibrary::escribirLog($idLog,"Respuesta invalida. ($id_trans)",$logActivo);
   				$comentario="Respuesta invalida. ($id_trans)";
   			}
   			else{
	   			if (intval($respuesta)>=100){
	   				RedsysLibrary::escribirLog($idLog,"Respuesta . ($respuesta)",$logActivo);
   					$comentario=RedsysLibrary::textDsResponse($respuesta);
	   			}
   			}
   			
   			if ($mantenerPedidoAnteError){
   				return array(1, $order, $comentario, $respuesta);
   			}
   			else{
				return array(2, $order, $comentario);
   			}
        }
    }

	private function confirmaPedido($order){
		try {
			if(!$order->canInvoice()) {
				$order->addStatusHistoryComment(__("Redsys, imposible generar Factura."), false);
				$order->save();
			}
			else{
				$transaction = new Transaction();
				
				$invoice=$this->_invoiceService->prepareInvoice($order);
				$invoice->register();
				$invoice->save();
				$transactionSave = $transaction->addObject($invoice)->addObject($invoice->getOrder());
				$transactionSave->save();
				if(!@$this->_invoiceSender->send($invoice))
					$order->addStatusHistoryComment(__("Redsys, imposible enviar Factura."), false);
				
				$order->addStatusHistoryComment(__("Redsys ha generado la Factura del pedido"), false)->save();		
			}
				
			$estado=$this->_redsysController->get_estado();
			$order->setTotalPaid($order->getTotalDue());
    		$order->setState('new')->setStatus($estado)->save();
    		$order->addStatusHistoryComment(__("Pago con Redsys registrado con éxito."), false)
	    		->setIsCustomerNotified(true)
	    		->save();
		} catch (Exception $e) {
			$this->escribirLog($idLog,"Pedido ".$pedido."Excepción: $e ",$logActivo);
			$order->addStatusHistoryComment('Redsys: Exception message: '.$e->getMessage(), false);
			$order->save();
		}
	}
	
}
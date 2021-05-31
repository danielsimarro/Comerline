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
namespace Redsys\Redsys\Controller;

use Redsys\Redsys\Model\RedsysModel;
use Magento\Store\Model\StoreManagerInterface;
use Redsys\Redsys\Helper\RedsysApi;
use Magento\Framework\Data\Form\FormKey;

class RedsysController extends \Magento\Framework\App\Action\Action
{
	protected $_baseURL;
    protected $_entorno;
    protected $_nombre;
    protected $_num;
    protected $_terminal;
    protected $_clave256;
    protected $_tipopago;
    protected $_moneda;
    protected $_logactivo;
    protected $_errorpago;
    protected $_idiomas;
    protected $_estado;
    protected $_activar3ds;
    protected $_formKey;
   

    public function __construct(RedsysModel $model, StoreManagerInterface $storeManager, FormKey $formKey) {
    	
		$this->_baseURL = $storeManager->getStore()->getBaseUrl();
    	$this->_entorno = $model->getConfigData('entorno');
    	$this->_nombre = $model->getConfigData('nombre');
    	$this->_num = $model->getConfigData('num');
    	$this->_terminal = $model->getConfigData('terminal');
    	$this->_clave256 = $model->getConfigData('clave256');
    	$this->_tipopago = $model->getConfigData('tipopago');
    	$this->_moneda = $model->getConfigData('moneda');
    	$this->_logactivo = $model->getConfigData('logactivo');
    	$this->_errorpago = $model->getConfigData('errorpago');
    	$this->_idiomas = $model->getConfigData('idiomas');
    	$this->_estado = $model->getConfigData('estado');
    	$this->_activar3ds = $model->getConfigData('activar3ds');
    	$this->_formKey = $formKey;
    }

	/**
	 * _entorno
	 * @return unkown
	 */
	public function get_entorno(){
		return $this->_entorno;
	}

	/**
	 * _nombre
	 * @return unkown
	 */
	public function get_nombre(){
		return $this->_nombre;
	}

	/**
	 * _num
	 * @return unkown
	 */
	public function get_num(){
		return $this->_num;
	}

	/**
	 * _terminal
	 * @return unkown
	 */
	public function get_terminal(){
		return $this->_terminal;
	}

	/**
	 * _clave256
	 * @return unkown
	 */
	public function get_clave256(){
		return $this->_clave256;
	}

	/**
	 * _tipopago
	 * @return unkown
	 */
	public function get_tipopago(){
		return $this->_tipopago;
	}

	/**
	 * _moneda
	 * @return unkown
	 */
	public function get_moneda(){
		return $this->_moneda;
	}

	/**
	 * _logactivo
	 * @return unkown
	 */
	public function get_logactivo(){
		return $this->_logactivo;
	}

	/**
	 * _errorpago
	 * @return unkown
	 */
	public function get_errorpago(){
		return $this->_errorpago;
	}

	/**
	 * _idiomas
	 * @return unkown
	 */
	public function get_idiomas(){
		return $this->_idiomas;
	}
	
	/**
	 * _baseURL
	 * @return unkown
	 */
	public function get_baseURL(){
		return $this->_baseURL;
	}

	/**
	 * _estado
	 * @return unkown
	 */
	public function get_estado(){
		return $this->_estado;
	}

	public function get_activar3ds(){
		return $this->_activar3ds;
	}

	public function get_storeLanguage(){
		/** @var \Magento\Framework\ObjectManagerInterface $om */
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		/** @var \Magento\Framework\Locale\Resolver $resolver */
		$resolver = $om->get('Magento\Framework\Locale\Resolver');
		return $resolver->getLocale();
	}
	
	public function generaCamposFormulario($orderId, $productos, $amount, $cliente){
		$res=array();
		$urlTienda=$this->_baseURL."redsys/checkout/notify?form_key=" . $this->_formKey->getFormKey();
		$idioma_tpv="0";
		$moneda="978";
		$tipopago=" ";
		$entorno="";
		
		if($this->_idiomas!="0"){
			$idioma_web = substr($this->get_storeLanguage(),0,2);
			switch ($idioma_web) {
				case 'es':
					$idioma_tpv='001';
					break;
				case 'en':
					$idioma_tpv='002';
					break;
				case 'ca':
					$idioma_tpv='003';
					break;
				case 'fr':
					$idioma_tpv='004';
					break;
				case 'de':
					$idioma_tpv='005';
					break;
				case 'nl':
					$idioma_tpv='006';
					break;
				case 'it':
					$idioma_tpv='007';
					break;
				case 'sv':
					$idioma_tpv='008';
					break;
				case 'pt':
					$idioma_tpv='009';
					break;
				case 'pl':
					$idioma_tpv='011';
					break;
				case 'gl':
					$idioma_tpv='012';
					break;
				case 'eu':
					$idioma_tpv='013';
					break;
				default:
					$idioma_tpv='002';
			}
		}
		
		if($this->_moneda=="0"){
			$moneda="978";
		} else { 
			$moneda="840";
		}

		if($this->_tipopago=="0"){
			$tipopago=" ";
		}
		else if($this->_tipopago=="1"){
			$tipopago="C";
		}
		else {
			$tipopago="T";
		}
		
		if($this->_entorno=="1"){
			$entorno="https://sis-d.redsys.es/sis/realizarPago/utf-8";
		}
		else if($this->_entorno=="2"){
			$entorno="https://sis-i.redsys.es:25443/sis/realizarPago/utf-8";
		}
		else if($this->_entorno=="3"){
			$entorno="https://sis-t.redsys.es:25443/sis/realizarPago/utf-8";
		}
		else{
			$entorno="https://sis.redsys.es/sis/realizarPago/utf-8";
		}
		
		$miObj = new RedsysApi();
		$miObj->setParameter("DS_MERCHANT_AMOUNT", $amount);
		$miObj->setParameter("DS_MERCHANT_ORDER",strval($orderId));
		$miObj->setParameter("DS_MERCHANT_MERCHANTCODE",$this->_num);
		$miObj->setParameter("DS_MERCHANT_TERMINAL",$this->_terminal);
		$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", 0);//Transaction type always must be 0.
		$miObj->setParameter("DS_MERCHANT_CURRENCY",$moneda);
		$miObj->setParameter("DS_MERCHANT_MERCHANTURL",$urlTienda);
		$miObj->setParameter("DS_MERCHANT_URLOK",$urlTienda);
		$miObj->setParameter("DS_MERCHANT_URLKO",$urlTienda);
		$miObj->setParameter("Ds_Merchant_ConsumerLanguage",$idioma_tpv);
		$miObj->setParameter("Ds_Merchant_ProductDescription",$productos);
		$miObj->setParameter("Ds_Merchant_Titular",$cliente);
		$miObj->setParameter("Ds_Merchant_MerchantData",sha1($urlTienda));
		$miObj->setParameter("Ds_Merchant_MerchantName",$this->_nombre);
		$miObj->setParameter("Ds_Merchant_PayMethods",$tipopago);
		$miObj->setParameter("Ds_Merchant_Module","m2_redsys_3.2.0");
		
		///// 3DSecure
		if ($this->get_activar3ds())
			include 'redsys_3ds.php';
		
		$version = $miObj->getVersionClave();
		$paramsBase64 = $miObj->createMerchantParameters();
		$signatureMac = $miObj->createMerchantSignature($this->_clave256);

		$res["Entorno"] = $entorno;
		$res["Ds_SignatureVersion"] = $version;
		$res["Ds_MerchantParameters"] = $paramsBase64;
 		$res["Ds_Signature"] = $signatureMac;
		
		return $res;
	}
    
    public function execute()
    {
    	die(Redsys\Redsys\Helper\Hmac::hash_hmac("sha256", "asdf", "123456"));
    }

}
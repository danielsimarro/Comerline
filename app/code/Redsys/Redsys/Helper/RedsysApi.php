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
namespace Redsys\Redsys\Helper;
use Redsys\Redsys\Helper\RedsysLibrary;

class RedsysApi extends \Magento\Framework\App\Helper\AbstractHelper {

	/******  Array de DatosEntrada ******/
    private $vars_pay = null;
    private $versionClave="HMAC_SHA256_V1";
    
    public function __construct(){
        $vars_pay=array();
    }
	
    public function getVersionClave() {
        return $this->versionClave;
    }
    
    /******  Set parameter ******/
    public function setParameter($key,$value){
        $this->vars_pay[$key]=$value;
    }

    /******  Get parameter ******/
    public function getParameter($key){
        return $this->vars_pay[$key];
    }
	
    public function createMerchantParameters(){
        // Se transforma el array de datos en un objeto Json
        $json = $this->arrayToJson();
        // Se codifican los datos Base64
        return $this->encodeBase64($json);
    }
    
    public function createMerchantSignature($key){
        $idLog=RedsysLibrary::generateIdLog();
        RedsysLibrary::escribirLog($idLog,"Entramos en la función de creación de firma",true);
        RedsysLibrary::escribirLog($idLog,"Clave del comercio: (" . $key . ")",true);
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($key);
        // Se genera el parámetro Ds_MerchantParameters
        $ent = $this->createMerchantParameters();
        RedsysLibrary::escribirLog($idLog,"MerchantParameters: (" . $ent . ")",true);
        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($this->getOrder(), $key);
        // MAC256 del parámetro Ds_MerchantParameters
        $res = $this->mac256($ent, $key);
        // Se codifican los datos Base64
        RedsysLibrary::escribirLog($idLog,"Firma generada: (" . $this->encodeBase64($res) . ")",true);
        return $this->encodeBase64($res);
    }
    
    public function decodeMerchantParameters($datos){
        // Se decodifican los datos Base64
        $decodec = $this->base64_url_decode($datos);
        return $decodec;	
    }
    
    public function createMerchantSignatureNotif($key, $datos){
        $idLog=RedsysLibrary::generateIdLog();
        RedsysLibrary::escribirLog($idLog,"Entramos en la función de creación de firma a partir de los datos recibidos",true);
        RedsysLibrary::escribirLog($idLog,"Clave del comercio: (" . $key . ")",true);
        RedsysLibrary::escribirLog($idLog,"Datos recibidos: (" . $datos . ")",true);
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($key);
        // Se decodifican los datos Base64
        $decodec = $this->base64_url_decode($datos);
        RedsysLibrary::escribirLog($idLog,"Datos decodificados: (" . $decodec . ")",true);
        // Los datos decodificados se pasan al array de datos
        $this->stringToArray($decodec);
        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($this->getOrderNotif(), $key);
        // MAC256 del parámetro Ds_Parameters que envía Redsys
        $res = $this->mac256($datos, $key);
        // Se codifican los datos Base64
        RedsysLibrary::escribirLog($idLog,"Firma generada (1): (" . $this->base64_url_encode($res) . ")",true);
        RedsysLibrary::escribirLog($idLog,"Firma generada (2): (" . $this->encodeBase64($res) . ")",true);
        return $this->base64_url_encode($res);	
    }
    
	
	
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    ////////////					FUNCIONES AUXILIARES:	      ////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    /******  3DES Function  ******/
    private function encrypt_3DES($message, $key){
        // Se establece un IV por defecto
        $bytes = array(0,0,0,0,0,0,0,0); //byte [] IV = {0, 0, 0, 0, 0, 0, 0, 0}
        $iv = implode(array_map("chr", $bytes)); //PHP 4 >= 4.0.2

    	// Se cifra
		if(phpversion() < 7){
			
			$ciphertext = mcrypt_encrypt(MCRYPT_3DES, $key, $message, MCRYPT_MODE_CBC, $iv); //PHP 4 >= 4.0.2
			return $ciphertext;
			
		}else{
			
			$long = ceil(strlen($message) / 16) * 16;
			$ciphertext = substr(openssl_encrypt($message . str_repeat("\0", $long - strlen($message)), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, $iv), 0, $long);
			
			return $ciphertext;
		}
    }

    /******  Base64 Functions  ******/
    private function base64_url_encode($input){
        return strtr(base64_encode($input), '+/', '-_');
    }
    private function encodeBase64($data){
        $data = base64_encode($data);
        return $data;
    }
    private function base64_url_decode($input){
        return base64_decode(strtr($input, '-_', '+/'));
    }
    private function decodeBase64($data){
        $data = base64_decode($data);
        return $data;
    }

    /******  MAC Function ******/
    private function mac256($ent,$key){
        $res = hash_hmac('sha256', $ent, $key, true);
        return $res;
    }


    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    ////////////	   FUNCIONES PARA LA GENERACIÓN DEL FORMULARIO DE PAGO:      ////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////

    /******  Obtener Número de pedido ******/
    private function getOrder(){
        $numPedido = "";
        if(empty($this->vars_pay['DS_MERCHANT_ORDER'])){
            $numPedido = $this->vars_pay['Ds_Merchant_Order'];
        } else {
            $numPedido = $this->vars_pay['DS_MERCHANT_ORDER'];
        }
        return $numPedido;
    }
    /******  Convertir Array en Objeto JSON ******/
    private function arrayToJson(){
        $json = json_encode($this->vars_pay); 
        return $json;
    }



    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////// FUNCIONES PARA LA RECEPCIÓN DE DATOS DE PAGO (Notif, URLOK y URLKO) ////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////

    /******  Obtener Número de pedido ******/
    private function getOrderNotif(){
        $numPedido = "";
        if(empty($this->vars_pay['Ds_Order'])){
            $numPedido = $this->vars_pay['DS_ORDER'];
        } else {
            $numPedido = $this->vars_pay['Ds_Order'];
        }
        return $numPedido;
    }
    /******  Convertir String en Array ******/
    private function stringToArray($datosDecod){
        $this->vars_pay = json_decode($datosDecod, true);
    }
}
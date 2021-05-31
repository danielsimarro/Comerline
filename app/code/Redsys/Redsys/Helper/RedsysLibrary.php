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
 
class RedsysLibrary extends \Magento\Framework\App\Helper\AbstractHelper {
	
	private static $errores=null;
    
    ///////////////////// FUNCIONES DE VALIDACION
    //Importe
    public static function checkImporte($total) {
        return preg_match("/^\d+$/", $total);
    }
    //Pedido
    public static function checkPedidoNum($pedido) {
        return preg_match("/^\d{1,12}$/", $pedido);
    }
    public static function checkPedidoAlfaNum($pedido) {
            return preg_match("/^\w{1,12}$/", $pedido);
    }
    //Fuc
    public static function checkFuc($codigo) {
        $retVal = preg_match("/^\d{2,9}$/", $codigo);
        if($retVal) {
            $codigo = str_pad($codigo,9,"0",STR_PAD_LEFT);
            $fuc = intval($codigo);
            $check = substr($codigo, -1);
            $fucTemp = substr($codigo, 0, -1);
            $acumulador = 0;
            $tempo = 0;

            for ($i = strlen($fucTemp)-1; $i >= 0; $i-=2) {
                $temp = intval(substr($fucTemp, $i, 1)) * 2;
                $acumulador += intval($temp/10) + ($temp%10);
                if($i > 0) {
                    $acumulador += intval(substr($fucTemp,$i-1,1));
                }
            }
            $ultimaCifra = $acumulador % 10;
            $resultado = 0;
            if($ultimaCifra != 0) {
                $resultado = 10 - $ultimaCifra;
            }
            $retVal = $resultado == $check;
        }
        return $retVal;
    }
    //Moneda
    public static function checkMoneda($moneda) {
        return preg_match("/^\d{1,3}$/", $moneda);
    }
    //Respuesta
    public static function checkRespuesta($respuesta) {
        return preg_match("/^\d{1,4}$/", $respuesta);
    }
    //Firma
    public static function checkFirma($firma) {
        return preg_match("/^[a-zA-Z0-9\/+]{32}$/", $firma);
    }
    //AutCode
    public static function checkAutCode($id_trans) {
        return preg_match("/^\w{1,6}$/", $id_trans);
    }
    //Nombre del Comecio
    public static function checkNombreComecio($nombre) {
        return preg_match("/^\w*$/", $nombre);
    }
    //Terminal
    public static function checkTerminal($terminal) {
        return preg_match("/^\d{1,3}$/", $terminal);
    }

    ///////////////////// FUNCIONES DE LOG
    public static function generateIdLog() {
        $vars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $stringLength = strlen($vars);
        $result = '';
        for ($i = 0; $i < 20; $i++) {
            $result .= $vars[rand(0, $stringLength - 1)];
        }
        return $result;
    }
    public static function escribirLog($idLog, $texto, $activo) {
    	if($activo){
			$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/redsys.log');
			$logger = new \Zend\Log\Logger();
			$logger->addWriter($writer);
			
			$logger->info("Redsys_".$idLog." - ".$texto);
    	}
    }
    
    private static function inicializaErrores(){
    	self::$errores=array(
    			'101' => 'Tarjeta caducada',
    			'102' => 'Tarjeta en excepcion transitoria o bajo sospecha de fraude',
    			'106' => 'Intentos de PIN excedidos',
    			'125' => 'Tarjeta no efectiva',
    			'129' => 'Codigo de seguridad (CVV2/CVC2) incorrecto',
    			'180' => 'Tarjeta ajena al servicio',
    			'184' => 'Error en la autenticacion del titular',
    			'190' => 'Denegacion sin especificar motivo',
    			'191' => 'Fecha de caducidad erronea',
    			'202' => 'Tarjeta en excepcion transitoria o bajo sospecha de fraude con retirada de tarjeta',
    			'904' => 'Comercio no registrado en FUC',
    			'909' => 'Error de sistema',
    			'912' => 'Emisor no disponible',
    			'913' => 'Pedido repetido',
    			'944' => 'Sesion incorrecta',
    			'950' => 'Operacion de devolucion no permitida',
    			'9064' => 'Numero de posiciones de la tarjeta incorrecto',
    			'9078' => 'Tipo de operacion no permitida para esta tarjeta',
    			'9093' => 'Tarjeta no existente',
    			'9094' => 'Rechazo servidores internacionales',
    			'9104' => 'Comercion con "titular seguro" y titular sin clave de compra segura',
    			'9218' => 'El comercio no permite operaciones seguras por entrada /operaciones',
    			'9253' => 'Tarjeta no cumple check-digit',
    			'9256' => 'El comercio no puede realizar preautorizaciones',
    			'9257' => 'Esta tarjeta no permite operativa de preautorizaciones',
    			'9261' => 'Operacion detenida por superar el control de restricciones en la entrada al SIS',
    			'9912' => 'Emisor no disponible',
    			'9913' => 'Error en la confirmacion que el comercio envía al TPV Virtual',
    			'9914' => 'Confirmación "KO" del comercio',
    			'9915' => 'A petición del usuario se ha cancelado el pago',
    			'9928' => 'Anulación de preautorización en diferido realizada por el SIS',
    			'9929' => 'Anulación de autorización en diferido realizada por el comercio',
    			'9997' => 'Se está procesando otra transacción en SIS con la misma tarjeta',
    			'9998' => 'Operación en proceso de solicitud de datos de tarjeta',
    			'9999' => 'Operación que ha sido redirigida al emisor a autenticar'
    	);
    }
    
    public static function textDsResponse($codResp){
    	if(self::$errores==null){
    		self::inicializaErrores();
    	}
    	
    	$textoRespuesta="REDSYS - Error en el pago";
    	
    	if(isset(self::$errores[$codResp]))
    		$textoRespuesta=$textoRespuesta.": ".self::$errores[$codResp];
    	
    	return $textoRespuesta." (".$codResp.")";
    }
}























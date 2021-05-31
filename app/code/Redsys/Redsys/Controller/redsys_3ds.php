<?php

function tep_sanitize_string_rds($string) {
	$string = trim(preg_replace('/\s+/', '%', $string));
    $patterns = array ('/ +/','/[<>]/');
    $replace = array (' ', '_');
    return preg_replace($patterns, $replace, trim($string));
}

$objectManager				= \Magento\Framework\App\ObjectManager::getInstance();

$checkoutSession			= $objectManager->get('\Magento\Checkout\Model\Session');
$orderId					= $checkoutSession->getLastOrderId();
$order						= $objectManager->create('Magento\Sales\Model\Order')->load($orderId);

$customerSession			= $objectManager->get('\Magento\Customer\Model\Session');
$isLoggedIn					= $customerSession->isLoggedIn();

$resource					= $objectManager->get('Magento\Framework\App\ResourceConnection');
$db							= $resource->getConnection();

///// 3DSecure | TABLA 4 - Json Object acctInfo	
//// chAccAgeInd & chAccDate
if (!$isLoggedIn) {
	$chAccAgeInd			= "01";
}
else {
	$customer				= $customerSession->getCustomer()->getData();
	$accountCreated			= intval( (strtotime("now") - strtotime($customer['created_at']))/60 );
	$nDays					= intval($accountCreated/1440);

	$dt						= new DateTime($customer['created_at']);
	$chAccDate				= $dt->format('Ymd');

	if ($accountCreated < 20) {
		$chAccAgeInd 		= "02";
	}
	elseif ($nDays < 30) {
		$chAccAgeInd 		= "03";
	}
	elseif ($nDays >= 30 && $nDays <= 60) {
		$chAccAgeInd 		= "04";
	}
	else {
		$chAccAgeInd 		= "05";
	}
}

//// chAccChange & chAccChangeInd
if ($isLoggedIn) {
	$customer				= $customerSession->getCustomer()->getData();
	$dt						= new DateTime($customer['updated_at']);
	$chAccChange			= $dt->format('Ymd');
	$accountModified		= intval( (strtotime("now") - strtotime($customer['updated_at']))/60 );
	$nDays					= intval($accountModified/1440);
	if($accountModified < 20) {
		$chAccChangeInd		= "01";
	}
	elseif ($nDays < 30) {
		$chAccChangeInd		= "02";
	}
	elseif ($nDays >= 30 && $nDays <= 60) {
		$chAccChangeInd		= "03";
	}
	else {
		$chAccChangeInd		= "04";
	}
}

//// chAccPwChange			| No se puede sacar este dato
// $chAccPwChange			= "";

//// chAccPwChangeInd		| No se puede sacar este dato
// $chAccPwChangeInd		= "";

// nbPurchaseAccount
if ($isLoggedIn) {
	$customerId				= $customerSession->getCustomer()->getId();
	$fechaBase				= strtotime("-6 month");
	$dt						= new DateTime("@$fechaBase");
	$query					= "SELECT COUNT(*) FROM " . $resource->getTableName('sales_order') . " WHERE customer_id = '" . $customerId . "' AND created_at > '" . $dt->format('Y-m-d H:i:s') . "' AND status = 'complete';";
	$nbPurchaseAccount		= $db->fetchOne($query);
}

//// provisionAttemptsDay	| No se puede sacar este dato
// $provisionAttemptsDay	= "";

//// txnActivityDay
if ($isLoggedIn) {
	$customerId				= $customerSession->getCustomer()->getId();
	$fechaBase				= strtotime("-1 day");
	$dt						= new DateTime("@$fechaBase");
	$query					= "SELECT COUNT(*) FROM " . $resource->getTableName('sales_order') . " WHERE customer_id = '" . $customerId . "' AND created_at > '" . $dt->format('Y-m-d H:i:s') . "' AND (status = 'complete' OR status = 'pending') ;";
	$txnActivityDay			= $db->fetchOne($query);
}

//// txnActivityYear
if ($isLoggedIn) {
	$customerId				= $customerSession->getCustomer()->getId();
	$fechaBase				= strtotime("-1 year");
	$dt						= new DateTime("@$fechaBase");
	$query					= "SELECT COUNT(*) FROM " . $resource->getTableName('sales_order') . " WHERE customer_id = '" . $customerId . "' AND created_at > '" . $dt->format('Y-m-d H:i:s') . "' AND (status = 'complete' OR status = 'pending') ;";
	$txnActivityYear		= $db->fetchOne($query);
}

//// paymentAccAge			| No se puede sacar este dato
// $paymentAccAge			= "";

//// paymentAccInd			| No se puede sacar este dato
// $paymentAccInd			= "";

//// shipAddressUsage & shipAddressUsageInd
$shippingInfo				= $order->getShippingAddress()?$order->getShippingAddress()->getData():null;
$billingInfo				= $order->getBillingAddress()->getData();
if ($shippingInfo != null) {
	$shippingStreet			= tep_sanitize_string_rds($shippingInfo['street']);
	$shippingCity			= tep_sanitize_string_rds($shippingInfo['city']);
	$shippingPostcode		= tep_sanitize_string_rds($shippingInfo['postcode']);
	$shippingCountry		= tep_sanitize_string_rds($shippingInfo['country_id']);
	$query					= "SELECT created_at FROM " . $resource->getTableName('quote_address') . " WHERE address_type = 'shipping' AND street like '" . $shippingStreet . "' AND city = '" . $shippingCity . "' AND postcode = '" . $shippingPostcode . "' AND country_id = '" . $shippingCountry . "' ORDER BY created_at;";
	$result 				= $db->query($query)->fetchAll();
	if (count($result) != 0) {
		$queryResult		= $result[0]['created_at'];
		$dt					= new DateTime($queryResult);
		$shipAddressUsage	= $dt->format('Ymd');
		
		$duringTransaction	= intval( (strtotime("now") - strtotime($queryResult))/60 );
		$nDays 				= intval($duringTransaction/1440);
		if ($nDays < 30) {
			$shipAddressUsageInd = "02";
		}
		elseif ($nDays >= 30 && $nDays <= 60) {
			$shipAddressUsageInd = "03";
		}
		else {
			$shipAddressUsageInd = "04";
		}
	}
	else {
		$fechaBase				= strtotime("now");
		$dt						= new DateTime("@$fechaBase");
		$shipAddressUsage		= $dt->format('Ymd');
		$shipAddressUsageInd	= "01";
	}
}

//// shipNameIndicator		| No se puede sacar este dato
// $shipNameIndicator		= "";

//// suspiciousAccActivity	| No se puede sacar este dato
// $suspiciousAccActivity	= "";

///// 3DSecure | FIN TABLA 4


///// 3DSecure | TABLA 1 - Ds_Merchant_EMV3DS (json Object)
//// addrMatch
if ($shippingInfo) {
	if (
		($shippingInfo['street'] == $billingInfo['street'])
		&&
		($shippingInfo['city'] == $billingInfo['city'])
		&&
		($shippingInfo['postcode'] == $billingInfo['postcode'])
		&&
		($shippingInfo['country_id'] == $billingInfo['country_id'])
	) {
		$addrMatch			= "Y";
	}
	else {
		$addrMatch			= "N";
	}
}
else {
	$addrMatch				= "N";
}

//// billAddrCity
$billAddrCity				= $billingInfo['city'];

//// billAddrCountry		| No se puede sacar este dato
//$billAddrCountry 			= "";

//// billAddrLine1
$billAddrLine1 				= $billingInfo['street'];

//// billAddrLine2			| No se puede sacar este dato
//$billAddrLine2			= "";

//// billAddrLine3			| No se puede sacar este dato
// $billAddrLine3			= "";

//// billAddrPostCode
$billAddrPostCode			= $billingInfo['postcode'];

//// billAddrState			| No se puede sacar este dato
//$billAddrState			= "";

//// Email
$Email						= $billingInfo['email'];

//// homePhone
$homePhone					= array("subscriber" => $billingInfo['telephone'], "cc" => "34");

//// mobilePhone
// $mobilePhone				= "";

//// cardholderName 		| No se puede sacar este dato
// $cardholderName			= "";

if ($shippingInfo) {
	//// shipAddrCity
	$shipAddrCity 			= $shippingInfo['city'];
	
	//// shipAddrCountry	| No se puede sacar este dato
	//$shipAddrCountry 		= "";
	
	//// shipAddrLine1
	$shipAddrLine1 			= $shippingInfo['street'];
	
	//// shipAddrLine2		| No se puede sacar este dato
	//$shipAddrLine2		= "";
	
	//// shipAddrLine3		| No se puede sacar este dato
	// $shipAddrLine3		= "";
	
	//// shipAddrPostCode
	$shipAddrPostCode		= $shippingInfo['postcode'];
	
	//// shipAddrState		| No se puede sacar este dato
	//$shipAddrState		= $order->delivery['state'];
}

//// homePhone
// $homePhone				= "";

//// threeDSRequestorAuthenticationInfo | No lo ponemos

//// acctInfo					| Información de la TABLA 4
$acctInfo						= array(
	'chAccAgeInd'				=> $chAccAgeInd
);
if ($shippingInfo) {
	$acctInfo['shipAddressUsage'] 		= strval($shipAddressUsage);
	$acctInfo['shipAddressUsageInd']	= strval($shipAddressUsageInd);
}
if ($isLoggedIn) {
	$acctInfo['chAccDate']				= strval($chAccDate);
	$acctInfo['chAccChange']			= strval($chAccChange);
	$acctInfo['chAccChangeInd']			= strval($chAccChangeInd);
	$acctInfo['nbPurchaseAccount']		= strval($nbPurchaseAccount);
	$acctInfo['txnActivityDay']			= strval($txnActivityDay);
	$acctInfo['txnActivityYear']		= strval($txnActivityYear);
}

//// purchaseInstalData		| No se puede sacar este dato
// $purchaseInstalData		= "";

//// recurringExpiry		| No se puede sacar este dato
// $recurringExpiry			= "";

//// recurringFrequency		| No se puede sacar este dato
// $recurringFrequency		= "";

//// merchantRiskIndicator	| No se puede sacar este dato
// $merchantRiskIndicator   = array();

//// challengeWindowSize	| No se puede sacar este dato
// $challengeWindowSize 	= "";


///// 3DSecure | FIN TABLA 1

///// 3DSecure | Insertamos el parámetro "Ds_Merchant_EMV3DS" en $miObj
$Ds_Merchant_EMV3DS 		= array(
	'addrMatch'				=> $addrMatch,
	'billAddrCity'			=> $billAddrCity,
	'billAddrLine1'			=> $billAddrLine1,
	'billAddrPostCode'		=> $billAddrPostCode,
	'email'					=> $Email,
	'acctInfo'				=> $acctInfo,
	'homePhone'				=> $homePhone
);
if ($shippingInfo) {
	$Ds_Merchant_EMV3DS['shipAddrCity']		= $shipAddrCity;
	$Ds_Merchant_EMV3DS['shipAddrLine1']	= $shipAddrLine1;
	$Ds_Merchant_EMV3DS['shipAddrPostCode']	= $shipAddrPostCode;
	// $Ds_Merchant_EMV3DS['acctInfo']			= array(
	// 	'shipAddressUsage'					=> $shipAddressUsage,
	// 	'shipAddressUsageInd'				=> $shipAddressUsageInd
	// );
}

$Ds_Merchant_EMV3DS 		= json_encode($Ds_Merchant_EMV3DS);

$miObj->setParameter("Ds_Merchant_EMV3DS", $Ds_Merchant_EMV3DS);


?>

















































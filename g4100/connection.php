<?php

//VARIABLES
$usuario = 'info@comerline.es';
$clave = '4988';
$baseUrl = "https://s1.g4100.es/SW/86/";

//ERRORES
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//INICIO CURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIESESSION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'session');

//LOGIN 0
curl_setopt($ch, CURLOPT_URL, $baseUrl);
$result = curl_exec($ch);
$json = json_decode($result, true);

//LOGIN 1
curl_setopt($ch, CURLOPT_URL, $baseUrl . "?usr=" . $usuario . "&clave=" . md5($clave . $json['llave']));
curl_exec($ch);



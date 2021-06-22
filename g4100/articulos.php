<?php
require_once 'prettyprint.php';
require_once 'connection.php';

//SELECT
$fields = [
    'campos' => json_encode(array("id", "descripcion", "pvp1", "modelo")),
    'filtro' => json_encode(array(
        "inicio" => 22079,
        "filtro" => array(
            array("campo" => "descripcion", "valor" => "gloss", "tipo" => 2)
        )
    ))
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
curl_setopt($ch, CURLOPT_URL, $baseUrl . "articulos/listar/");
$result = curl_exec($ch);
prettyPrint($result);

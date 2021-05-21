<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class GetArticles extends SyncgApiService
{

    protected $method = Request::HTTP_METHOD_GET;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams()
    {
        $fields = [
            'campos' => json_encode(array("id", "descripcion", "pvp1", "modelo")),
            'filtro' => json_encode(array(
                "inicio" => 22079,
                "filtro" => array(
                    array("campo" => "descripcion", "valor" => "gloss", "tipo" => 2)
                )
            ))
        ];
        $this->endpoint .= '/articulos/listar?campos=' . $fields['campos'] . '&filtro=' . $fields['filtro'];
    }

    public function send()
    {
        $this->buildParams();
        $response = $this->execute();
    }
}

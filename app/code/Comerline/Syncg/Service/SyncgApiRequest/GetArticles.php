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

    protected $config;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger
    ) {
        $this->config = $configHelper;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $fields = [
            'campos' => json_encode(array("id", "descripcion", "pvp1", "modelo")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "descripcion", "valor" => "gloss", "tipo" => 2)
                )
            ))
        ];
        $this->endpoint = $this->config->getGeneralConfig('database_id') . '/articulos/listar?campos=' . $fields['campos'] . '&filtro=' . $fields['filtro'];
    }

    public function send()
    {
        $loop = true;
        $start = 1;
        $pages = [];
        while ($loop){
            $this->buildParams($start);
            $response = $this->execute();
            if($response['listado']){
                $pages[] = $response['listado'];
                $start = intval($response['listado'][0]['id']) + 1;
            } else {
                $loop = false;
            }
        }
    }
}

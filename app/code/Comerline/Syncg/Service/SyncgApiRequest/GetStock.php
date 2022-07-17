<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Service\SyncgApiService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Rest\Request;
use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class GetStock extends SyncgApiService
{
    protected $method = Request::HTTP_METHOD_POST;

    /**
     * @var SyncgStatus
     */
    private $syncgStatus;

    /**
     * @var SyncgStatusRepository
     */
    private $syncgStatusRepository;

    private $stockG4100;
    private string $prefixLog;

    public function __construct(
        Config                     $configHelper,
        Json                       $json,
        ClientFactory              $clientFactory,
        ResponseFactory            $responseFactory,
        ProductRepositoryInterface $productRepository,
        SyncgStatus                $syncgStatus,
        SyncgStatusRepository      $syncgStatusRepository,
        LoggerInterface            $logger
    )
    {
        $this->productRepository = $productRepository;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->prefixLog = uniqid() . ' | G4100 Sync Stock |';
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($page)
    {
        $this->endpoint = 'api/g4100/list';
        $this->params = [
            'allow_redirects' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->getTokenFromDatabase()}",
            ],
            'body' => json_encode([
                'endpoint' => 'articulos_proveedores/listar',
                'fields' => json_encode(["id_articulo", "stock_proveedor"]),
                'filters' => json_encode([
                    "inicio" => $page * 100
                ]),
                'order' => json_encode(["campo" => "id", "orden" => "ASC"])
            ]),
        ];
    }

    public function send()
    {
        $page = 0;
        $this->buildParams($page);
        $response = $this->execute();
        while ($response && isset($response['listado'])) {
            $productGId = $response['listado'][0]['id_articulo'];
            $stock = $response['listado'][0]['stock_proveedor'];
            $this->stockG4100[$productGId] = $stock;
            $this->logger->info(new Phrase($this->prefixLog . ' Page ' . $page . ' | Product G4100 ' . $productGId . ' | ' . $stock));
            $page++;
            $this->buildParams($page);
            $response = $this->execute();
        }
    }
}

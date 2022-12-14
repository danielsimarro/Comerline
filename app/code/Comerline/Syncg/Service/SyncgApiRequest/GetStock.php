<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Service\SyncgApiService;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Exception;
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
    private CollectionFactory $syncgStatusCollectionFactory;
    private string $prefixLog;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        Config                     $configHelper,
        Json                       $json,
        ClientFactory              $clientFactory,
        ResponseFactory            $responseFactory,
        ProductRepositoryInterface $productRepository,
        CollectionFactory          $syncgStatusCollectionFactory,
        LoggerInterface            $logger
    )
    {
        $this->productRepository = $productRepository;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
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
                    "inicio" => $page * 100,
                    "filtro" => []
                ]),
                'order' => json_encode(["campo" => "id", "orden" => "ASC"])
            ]),
        ];
    }

    public function send()
    {
        $page = 0;
        $contProducts = 1;
        $this->buildParams($page);
        $response = $this->execute();
        while ($response && isset($response['listado']) && $response['listado']) {
            $productStocks = $response['listado'];
            foreach ($productStocks as $productStock) {
                $productGId = $productStock['id_articulo']['id'];
                $stock = $productStock['stock_proveedor'];
                $this->stockG4100[$productGId] = $stock;
                $this->logger->info(new Phrase($this->prefixLog . ' Page ' . $page . ' ' . $contProducts . ' | Product G4100 ' . $productGId . ' | ' . $stock));
                $this->processStock($productGId, $stock);
                $contProducts++;
            }
            $page++;
            $this->buildParams($page);
            $response = $this->execute();
        }
    }

    private function processStock($productGId, $stock) {
        $collectionSyncg = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('g_id', $productGId)
            ->addFieldToFilter('mg_id', ['neq' => 0])
            ->addFieldToFilter('type', ['in' => [SyncgStatus::TYPE_PRODUCT, SyncgStatus::TYPE_PRODUCT_SIMPLE]]) // We check if the product already exists
            ->addOrder('type', 'asc')
            ->setPageSize(1)
            ->setCurPage(0);
        if ($collectionSyncg->getSize() > 0 && $stock > 0) {
            foreach ($collectionSyncg as $itemSyncg) {
                $product = $this->productRepository->getById($itemSyncg->getData('mg_id'), true); // We load the product in edit mode
                $product->setStockData([
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => $stock
                ]);
                try {
                    $this->productRepository->save($product);
                } catch (Exception $e) {
                    $this->logger->warning(new Phrase($this->prefixLog . ' | Product G4100 ' . $productGId . ' | Product Mg ' . $itemSyncg->getData('mg_id') . ' | Not saved: ' . $e->getMessage()));
                    continue;
                }
                $this->logger->info(new Phrase($this->prefixLog . ' | Product G4100 ' . $productGId . ' | Product Mg ' . $itemSyncg->getData('mg_id') . ' |  ' . $stock . ' saved'));
            }
        }
    }
}

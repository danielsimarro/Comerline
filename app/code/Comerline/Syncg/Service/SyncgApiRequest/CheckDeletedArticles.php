<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory as ProductFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class CheckDeletedArticles extends SyncgApiService
{

    protected $method = Request::HTTP_METHOD_GET;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var SyncgStatus
     */
    private $syncgStatus;

    /**
     * @var CollectionFactory
     */
    private $syncgStatusCollectionFactory;

    /**
     * @var SyncgStatusRepository
     */
    private $syncgStatusRepository;

    /**
     * @var
     */
    private $order;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductResource
     */
    private $productResource;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger,
        SyncgStatus $syncgStatus,
        CollectionFactory $syncgStatusCollectionFactory,
        SyncgStatusRepository $syncgStatusRepository,
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductResource $productResource
    ) {
        $this->config = $configHelper;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productResource = $productResource;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $fields = [
            'campos' => json_encode(array("nombre", "ref_fabricante", "fecha_cambio", "borrado", "ref_proveedor", "descripcion", "desc_detallada" ,"pvp1", "modelo", "si_vender_en_web", "existencias_globales", "grupo")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "si_vender_en_web", "valor" => "1", "tipo" => 0)
                )
            )),
            'orden' => json_encode(array("campo" => "id", "orden" => "ASC"))
        ];
        $this->endpoint = $this->config->getGeneralConfig('database_id') . '/articulos/catalogo?' . http_build_query($fields);
        $this->order = $fields['orden']; // We will need this to get the products correctly
    }

    public function send()
    {
        $loop = true; // Variable to check if we need to break the loop or keep on it
        $start = 0; // Counter to check from which page we start the query
        $pages = []; // Array where we will store the items, ordered in pages
        while ($loop){
            $this->buildParams($start);
            $response = $this->execute();
            if($response['listado']){
                $pages[] = $response['listado'];
                if (strpos($this->order, 'ASC')){
                    $start = intval($response['listado'][count($response['listado']) - 1]['id'] + 1);// If orden is ASC, the first item that the API gives us
                    // is the first, so we get it for the next query, and we add 1 to avoid duplicating that item

                } else {
                    $start = intval($response['listado'][0]['id']) + 1; // If orden is not ASC, the first item that the API gives us is the one with highest ID,
                    // so we get it for the next query, and we add 1 to avoid duplicating that item
                }
            } else {
                $loop = false;  // If $response['listado'] is empty, we end the while loop
            }
        }
        if ($pages) {
            $ids = []; // Array where we will store the active products ids
            $syncgIds = []; // Array where we will store the IDs on our database
            $collectionSyncg = $this->syncgStatusCollectionFactory->create()
                ->addFieldToFilter('type', SyncgStatus::TYPE_PRODUCT);
            foreach ($pages as $page){
                for ($i = 0; $i < count($page); $i++) {
                    $ids[] = $page[$i]['cod'];
                }
            }

            foreach ($collectionSyncg as $itemSyncg) {
                $syncgIds[] = $itemSyncg->getData('g_id');
            }

            foreach ($syncgIds as $syncgId) {
                if (!(in_array($syncgId, $ids))){ // If the ID is not in the array, that means the product is deleted
                    $collectionSyncg = $this->syncgStatusCollectionFactory->create()
                        ->addFieldToFilter('g_id', $syncgId)
                        ->addFieldToFilter('type', SyncgStatus::TYPE_PRODUCT);
                    foreach ($collectionSyncg as $itemSyncg) {
                        $deletedId = $itemSyncg->getData('mg_id');
                        $product = $this->productRepository->getById($deletedId, true);
                        $product->setStatus(0);
                        $this->productRepository->save($product);
                    }
                }
            }
        }
    }
}

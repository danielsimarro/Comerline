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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;
use DateTimeZone;
use DateInterval;
use Safe\DateTime;

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
        Config                     $configHelper,
        Json                       $json,
        ClientFactory              $clientFactory,
        ResponseFactory            $responseFactory,
        LoggerInterface            $logger,
        SyncgStatus                $syncgStatus,
        CollectionFactory          $syncgStatusCollectionFactory,
        SyncgStatusRepository      $syncgStatusRepository,
        ProductFactory             $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductResource            $productResource
    )
    {
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
        $coreConfigData = $this->config->getParamsWithoutSystem('syncg/general/last_date_sync_products')->getValue(); // We get the last sync date

        $timezone = new DateTimeZone('Europe/Madrid');
        $date = new DateTime($coreConfigData, $timezone);
        $hours = $date->getOffset() / 3600; // We have to add the offset, since the date from the API comes in CEST
        $newDate = $date->add(new DateInterval(("PT{$hours}H")));

        $fields = [
            'campos' => json_encode(array("nombre", "ref_fabricante", "fecha_cambio", "ref_proveedor", "descripcion", "desc_detallada", "pvp1", "modelo", "si_vender_en_web", "existencias_globales", "grupo")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "si_vender_en_web", "valor" => "1", "tipo" => 0),
                    array("campo" => "fecha_cambio", "valor" => $newDate->format('Y-m-d H:i'), "tipo" => 3)
                )
            )),
            'orden' => json_encode(array("campo" => "id", "orden" => "ASC"))
        ];
        $this->endpoint = $this->config->getGeneralConfig('database_id') . '/articulos/papelera?' . http_build_query($fields);
        $this->order = $fields['orden']; // We will need this to get the products correctly
    }

    public function send()
    {
        $loop = true; // Variable to check if we need to break the loop or keep on it
        $start = 0; // Counter to check from which page we start the query
        $pages = []; // Array where we will store the items, ordered in pages
        $this->logger->info(new Phrase('G4100 Sync | Fetching deleted products'));
        while ($loop) {
            $this->buildParams($start);
            $response = $this->execute();
            if (array_key_exists('listado', $response)) {
                if ($response['listado']) {
                    $pages[] = $response['listado'];
                    if (strpos($this->order, 'ASC')) {
                        $start = intval($response['listado'][count($response['listado']) - 1]['id'] + 1);// If orden is ASC, the first item that the API gives us
                        // is the first, so we get it for the next query, and we add 1 to avoid duplicating that item

                    } else {
                        $start = intval($response['listado'][0]['id']) + 1; // If orden is not ASC, the first item that the API gives us is the one with highest ID,
                        // so we get it for the next query, and we add 1 to avoid duplicating that item
                    }
                } else {
                    $loop = false; // If $response['listado'] is empty, we end the while loop
                }
            } else {
                $this->logger->error(new Phrase('G4100 Sync | Error fetching deleted products.'));
            }
        }
        $this->logger->info(new Phrase('G4100 Sync | Fetching deleted products successful.'));
        if ($pages) {
            $ids = []; // Array where we will store the active products ids
            foreach ($pages as $page) {
                for ($i = 0; $i < count($page); $i++) {
                    $ids[] = $page[$i]['cod'];
                }
            }
            foreach ($ids as $id) {
                $collectionSyncg = $this->syncgStatusCollectionFactory->create()
                    ->addFieldToFilter('g_id', $id)
                    ->addFieldToFilter('type', SyncgStatus::TYPE_PRODUCT);
                foreach ($collectionSyncg as $itemSyncg) {
                    $deletedId = $itemSyncg->getData('mg_id');
                    try {
                        $product = $this->productRepository->getById($deletedId, true);
                        $product->setStatus(0);
                        $product->save();
                        $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($deletedId, $id, SyncgStatus::TYPE_PRODUCT, SyncgStatus::STATUS_DELETED);
                        $this->logger->info(new Phrase('G4100 Sync | [G4100 Product: ' . $id . '] | [Magento Product: ' . $product->getId() . '] | DELETED.'));
                    } catch (LocalizedException $e) {
                        $this->logger->error(new Phrase('G4100 Sync | [G4100 Product: ' . $id . '] | [Magento Product: ' . $product->getId() . "] | MAGENTO PRODUCT DOESN'T EXISTS."));
                    }
                }
            }
        }
    }
}

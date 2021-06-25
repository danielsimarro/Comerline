<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Service\SyncgApiService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Webapi\Rest\Request;
use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class CreateOrder extends SyncgApiService
{
    protected $method = Request::HTTP_METHOD_GET;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SyncgStatus
     */
    private $syncgStatus;

    /**
     * @var SyncgStatusRepository
     */
    private $syncgStatusRepository;

    /**
     * @var CollectionFactory
     */
    private $syncgStatusCollectionFactory;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        SyncgStatus $syncgStatus,
        SyncgStatusRepository $syncgStatusRepository,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository,
        CollectionFactory $syncgStatusCollectionFactory
    ) {
        $this->customerRepository = $customerRepository;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($lines = null, $clientId = null, $codeG4100 = null)
    {
        if ($codeG4100) {
            $fields = [
                'campos' => json_encode(array("descripcion", "desc_detallada" ,"pvp1", "modelo", "si_vender_en_web")),
                'filtro' => json_encode(array(
                    "inicio" => 0,
                    "filtro" => array(
                        array("campo" => "cod", "valor" => $codeG4100, "tipo" => 0),
                    )
                )),
                'orden' => json_encode(array("campo" => "id", "orden" => "ASC"))
            ];
            $this->endpoint = $this->config->getGeneralConfig('database_id') . '/articulos/catalogo?' . http_build_query($fields);
        } else {
            $fields = [
                'cliente' => intval($clientId), // Here we have to add much more fields
                'notas' => "", // Here we have to add much more fields
                'lineas' => $lines
            ];
            $this->endpoint = $this->config->getGeneralConfig('database_id') . '/pedir/finalizar?pedido=' . json_encode($fields);
        }
    }

    public function createOrder($order, $clientId){
        $lines = $this->createLine($order); // We create the lines necessary with all the articles on the order
        $this->buildParams($lines, $clientId);
        $response = $this->execute();
        $orderId = 0;
        if (!empty($response['listado'])) {
            $orderId = $response['listado'][0]['id']; // After we create the order, we get the ID to put it in our database
        }
        return $orderId;
    }

    public function createLine($order){
        $items = $order->getData('items');
        $lines = array();
        foreach ($items as $item){
            $price = floatval($item->getData('price'));
            if ($price != 0.0){ // If the price is 0, that means that article is not right, so we don't add it to the order
                $qty = intval($item->getData('qty_ordered'));
                if (array_key_exists('selected_configurable_option', $item->getData('product_options')['info_buyRequest'])){
                    $idMg = $item->getData('product_options')['info_buyRequest']['selected_configurable_option'];
                } else {
                    $idMg = $item->getData('product_id');
                }
                $collectionSyncg = $this->syncgStatusCollectionFactory->create() // With the Magento ID, we get the G4100 ID of the product
                    ->addFieldToFilter('mg_id', $idMg)
                    ->addFieldToFilter('type', SyncgStatus::TYPE_PRODUCT);
                if ($collectionSyncg->getSize() > 0) {
                    foreach ($collectionSyncg as $itemSyncg){
                        $codeG4100 = $itemSyncg->getData('g_id');
                        $this->buildParams(null, null, $codeG4100);
                        $response = $this->execute();
                        if ($response) {
                            $idG4100 = intval($response['listado'][0]['id']);
                        }
                    }
                }
                array_push($lines, array("articulo" => $idG4100, "cantidad" => $qty, "precio" => $price));
            }
        }
        return $lines;
    }
}

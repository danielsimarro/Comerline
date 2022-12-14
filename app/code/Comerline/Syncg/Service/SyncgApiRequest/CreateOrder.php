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
    protected $method = Request::HTTP_METHOD_POST;

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
        Config                      $configHelper,
        Json                        $json,
        ClientFactory               $clientFactory,
        ResponseFactory             $responseFactory,
        SyncgStatus                 $syncgStatus,
        SyncgStatusRepository       $syncgStatusRepository,
        LoggerInterface             $logger,
        CustomerRepositoryInterface $customerRepository,
        CollectionFactory           $syncgStatusCollectionFactory
    )
    {
        $this->customerRepository = $customerRepository;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($codeG4100)
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
                'endpoint' => 'articulos/catalogo',
                'fields' => json_encode(["descripcion", "desc_detallada", "pvp1", "modelo", "si_vender_en_web"]),
                'filters' => json_encode([
                    "inicio" => 0,
                    "filtro" => [
                        ["campo" => "cod", "valor" => $codeG4100, "tipo" => 0],
                    ]
                ]),
                'order' => json_encode(["campo" => "id", "orden" => "ASC"])
            ]),
        ];
    }

    public function buildOrderParams($order, $lines, $clientId)
    {
        $this->endpoint = 'api/g4100/create/order';
        $this->params = [
            'allow_redirects' => true,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->getTokenFromDatabase()}",
            ],
            'form_params' => [
                'customer' => intval($clientId),
                'notes' => "ID de pedido Magento: " . $order->getData('increment_id') . $this->checkComment($order),
                'lines' => json_encode($lines)
            ],
        ];
    }

    public function checkComment($order){
        $comment = "";

        if($order->getData('bold_order_comment')){
            $comment = "\nComentario del cliente: " . $order->getData('bold_order_comment');
        }

        return $comment;
    }

    public function createOrder($order, $clientId)
    {
        $lines = $this->createLine($order); // We create the lines necessary with all the articles on the order
        $this->buildOrderParams($order, $lines, $clientId);
        $response = $this->execute();
        $orderId = 0;
        if (!empty($response['id'])) {
            $orderId = $response['id']; // After we create the order, we get the ID to put it in our database
        }
        return $orderId;
    }

    public function createLine($order)
    {
        $items = $order->getData('items');
        $lines = [];
        foreach ($items as $item) {
            // @todo Todo esto est?? para refactorizar. Se resuelve con una llamada
            $price = floatval($item->getData('price'));
            if ($price != 0.0) { // If the price is 0, that means that article is not right, so we don't add it to the order
                $qty = intval($item->getData('qty_ordered'));
                if (array_key_exists('selected_configurable_option', $item->getData('product_options')['info_buyRequest'])) {
                    $idMg = $item->getData('product_options')['info_buyRequest']['selected_configurable_option'];
                } else {
                    $idMg = $item->getData('product_id');
                }
                $collectionSyncg = $this->syncgStatusCollectionFactory->create() // With the Magento ID, we get the G4100 ID of the product
                ->addFieldToFilter('mg_id', $idMg)
                    ->addFieldToFilter('type', ['in' => [SyncgStatus::TYPE_PRODUCT,SyncgStatus::TYPE_PRODUCT_SIMPLE]]);
                if ($collectionSyncg->getSize() > 0) {
                    foreach ($collectionSyncg as $itemSyncg) {
                        $idG4100 = $itemSyncg->getData('g_id');
                    }
                }
                array_push($lines, ["articulo" => $idG4100, "cantidad" => $qty, "precio" => $price, "descuento" => 0]);
            }
        }
        $codUpdate = $this->config->getGeneralConfig('shipping_rate_g4100_id');
        $this->buildParams($codUpdate);
        $response = $this->execute();
        if ($response) {
            $idUpdate = intval($response['listado'][0]['id']);
        }
        $dataUpdate = strval($order->getData('base_shipping_amount'));
        array_push($lines, ["articulo" => $idUpdate, "cantidad" => 1, "precio" => $dataUpdate, "descuento" => 0]); // We add the shipping rates here
        if ($order->getData('coupon_code')) {
            $codDiscount = $this->config->getGeneralConfig('discount_g4100_id');
            $this->buildParams($codDiscount);
            $response = $this->execute();
            if ($response) {
                $idDiscount = intval($response['listado'][0]['id']);
            }
            $dataDiscount = strval($order->getData('discount_amount'));
            array_push($lines, ["articulo" => $idDiscount, "cantidad" => 1, "precio" => $dataDiscount, "descuento" => 0]); // We add the discount here (if exists)
        }
        return $lines;
    }
}

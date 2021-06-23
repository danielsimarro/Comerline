<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

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

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        SyncgStatus $syncgStatus,
        SyncgStatusRepository $syncgStatusRepository,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusRepository = $syncgStatusRepository;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($orderCreate)
    {
        $fields = [
            'campos' => json_encode(array("id_persona", "total", "numero_bultos")), // Here we have to add much more fields
            'datos' => json_encode(array($orderCreate))
        ];
        $this->endpoint = $this->config->getGeneralConfig('database_id') . '/pedidosv/guardar/0/?' . http_build_query($fields);
    }

    public function createOrder($order){
        $orderCreate = array(); // When we know what data we must pass to the API, here we will put it to create the order, just like we did with clients
        $this->buildParams($orderCreate);
        $response = $this->execute();
        if (!empty($response['listado'])) {
            $orderId = $response['listado'][0]['id']; // After we create the order, we get the ID to put it in our database
        }
        return $orderId;
    }
}

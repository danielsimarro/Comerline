<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Service\SyncgApiService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Webapi\Rest\Request;
use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use Comerline\Syncg\Service\SyncgApiRequest\Login;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class GetClients extends SyncgApiService
{
    protected $method = Request::HTTP_METHOD_GET;

    /**
     * @var Login
     */
    private $login;

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
     * @var GetProvince
     */
    private $getProvince;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        Login $login,
        SyncgStatus $syncgStatus,
        SyncgStatusRepository $syncgStatusRepository,
        LoggerInterface $logger,
        GetProvince $getProvince,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->login = $login;
        $this->customerRepository = $customerRepository;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->getProvince = $getProvince;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($clientEmail, $search, $client = null)
    {
        if ($search) {
            $fields = [
                'campos' => json_encode(array("nombre", "direccion1", "email", "poblacion", "telefono", "id_provincia", "cp")),
                'filtro' => json_encode(array(
                    "inicio" => 0,
                    "filtro" => array(
                        array("campo" => "email", "valor" => $clientEmail, "tipo" => 0),
                    )
                )),
                'orden' => json_encode(array("campo" => "id", "orden" => "ASC"))
            ];
            $this->endpoint = $this->config->getGeneralConfig('database_id') . '/personas/listar?' . http_build_query($fields);
            $this->order = $fields['orden']; // We will need this to get the products correctly
        } else {
            $fields = [
                'campos' => json_encode(array("nombre", "direccion1", "email", "poblacion", "telefono", "id_provincia", "cp")),
                'datos' => json_encode($client)
            ];
            $this->endpoint = $this->config->getGeneralConfig('database_id') . '/personas/guardar/0/?' . http_build_query($fields);
        }
    }

    public function checkClients($order){
        $this->connectToAPI(); // First we need to login
        $clientEmail = $order->getData('customer_email'); // Client's email
        $clientName = $order->getData('customer_firstname') . " " . $order->getData('customer_lastname'); // Client's name
        $clientAddress = $order->getData('addresses')[0]->getData('street'); // Client's street
        $clientCity = $order->getData('addresses')[0]->getData('city'); // Client's city
        $clientPhone = $order->getData('addresses')[0]->getData('telephone');
        $clientProvince = $this->getProvince->checkProvince($order->getData('addresses')[0]->getData('region')); // Client's province
        $clientPostcode = $order->getData('addresses')[0]->getData('postcode'); // Client's postcode
        $client = array($clientName, $clientAddress, $clientEmail, $clientCity, $clientPhone, $clientProvince, $clientPostcode); // We put it all togheter on an array
        $this->buildParams($clientEmail, $search = true); // First, we search if the client exists in G4100
        $response = $this->execute();
        if (!empty($response['listado'])) { // If it exists, we get it's ID
            $clientG4100 = $response['listado'][0];
            $clientG4100Id = $clientG4100['id'];
        } else { // If it doesn't exists, we create it and then we get the ID
            $this->buildParams($clientEmail, $search = false, $client);
            $response = $this->execute();
            $clientG4100Id = $response['id'];
        }
        if ($order->getData('customer_is_guest') === 0) {
            $clientMg = $this->customerRepository->get($clientEmail); // We get the client from Magento using the email from the order
            $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($clientMg->getId(), $clientG4100Id, SyncgStatus::TYPE_CLIENT, SyncgStatus::STATUS_COMPLETED);
        } else {
            $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus(SyncgStatus::CLIENT_GUEST, $clientG4100Id, SyncgStatus::TYPE_CLIENT, SyncgStatus::STATUS_COMPLETED);
        }
        return $clientG4100Id;
    }

    public function connectToAPI(){
        $this->login->send();
    }
}

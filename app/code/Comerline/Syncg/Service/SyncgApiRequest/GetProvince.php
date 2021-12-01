<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Service\SyncgApiRequest\Login;
use Comerline\Syncg\Service\SyncgApiService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Webapi\Rest\Request;
use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class GetProvince extends SyncgApiService
{
    protected $method = Request::HTTP_METHOD_POST;

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

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        Login $login,
        SyncgStatus $syncgStatus,
        SyncgStatusRepository $syncgStatusRepository,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->login = $login;
        $this->customerRepository = $customerRepository;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusRepository = $syncgStatusRepository;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($clientProvince)
    {
        $this->endpoint = 'api/g4100/list';
        $this->params = [
            'allow_redirects' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->getTokenFromDatabase('syncg/general/g4100_middleware_token')}",
            ],
            'body' => json_encode([
                'endpoint' => 'provincias/listar',
                'fields' => json_encode(array("nombre", "comunidad", "pais")),
                'filters' => json_encode(array(
                    "inicio" => 0,
                    "filtro" => array(
                        array("campo" => "nombre", "valor" => $clientProvince, "tipo" => 0),
                    )
                )),
                'order' => json_encode(array("campo" => "id", "orden" => "ASC"))
            ]),
        ];
        $decoded = json_decode($this->params['body']);
        $decoded = (array)$decoded;
        $this->order = $decoded['order']; // We will need this to get the products correctly
    }

    public function checkProvince($clientProvince){
        $provinceId = '';
        $this->buildParams($clientProvince);
        $response = $this->execute();
        if (!empty($response['listado'])) {
            $provinceId = $response['listado'][0]['id'];
        }
        return $provinceId;
    }
}

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
        $fields = [
            'campos' => json_encode(array("nombre", "comunidad", "pais")),
            'filtro' => json_encode(array(
                "inicio" => 0,
                "filtro" => array(
                    array("campo" => "nombre", "valor" => $clientProvince, "tipo" => 0),
                )
            )),
            'orden' => json_encode(array("campo" => "id", "orden" => "ASC"))
        ];
        $this->endpoint = $this->config->getGeneralConfig('database_id') . '/provincias/listar?' . http_build_query($fields);
        $this->order = $fields['orden']; // We will need this to get the products correctly
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

    public function connectToAPI(){
        $this->login->send();
    }
}

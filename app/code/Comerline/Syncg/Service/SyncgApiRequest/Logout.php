<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class Logout extends SyncgApiService
{

    protected $method = Request::HTTP_METHOD_DELETE;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams()
    {
        $this->params = [
            'allow_redirects' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->getTokenFromDatabase('syncg/general/g4100_middleware_token')}",
            ],
        ];
        $this->endpoint .= '/api/users/logout';
    }

    public function send()
    {
        $this->buildParams();
        $response = $this->execute();
    }
}

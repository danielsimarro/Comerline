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
        $this->endpoint .= '/api/users/logout';
    }

    public function send()
    {
        $this->buildParams();
        $response = $this->execute();
    }
}

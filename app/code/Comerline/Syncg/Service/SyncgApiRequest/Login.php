<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class Login extends SyncgApiService
{

    protected $method = Request::HTTP_METHOD_GET;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams(string $key)
    {
        $this->endpoint .= '/?usr=' . $this->config->getGeneralConfig('email') . '&clave=' . $key;
    }

    public function send()
    {
        $response = $this->execute();
        $key = md5($this->config->getGeneralConfig('user_key') . $response['llave']);
        $this->buildParams($key);
        $response = $this->execute();
    }
}

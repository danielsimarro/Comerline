<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Service\SyncgApiService;
use Magento\Framework\Webapi\Rest\Request;
use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class GetClients extends SyncgApiService
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

    public function buildParams()
    {

    }

    public function getClients($order){
        if ($order->getData('customer_is_guest') !== 1) { // If the customer is not a guest, that means he has an account
            $clientId = $order->getData('customer_id'); // We get it's ID
        }
    }
}

<?php

declare(strict_types=1);

namespace Comerline\Syncg\Service;

use Comerline\Syncg\Helper\Config;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Phrase;

abstract class SyncgApiService
{
    protected $method = Request::HTTP_METHOD_GET;
    protected $endpoint = '';
    protected $params = [];

    private $responseFactory;

    protected $config;

    protected $json;

    protected $logger;

    public function __construct(
        Config $configHelper,
        Json $json,
        ResponseFactory $responseFactory,
        ClientFactory $clientFactory,
        LoggerInterface $logger
    ) {
        $this->config = $configHelper;
        $this->json = $json;
        $this->clientFactory = $clientFactory;
        $this->responseFactory = $responseFactory;
        $this->logger = $logger;
        $this->uri = $this->config->getGeneralConfig('installation_url');
        $this->id = $this->config->getGeneralConfig('database_id');
    }

    public function execute(){
        $data = null;
        $response = $this->doRequest($this->id, $this->params, $this->method);
        $status = $response->getStatusCode();
        if ($status === 200){
            $responseJson = $response->getBody()->getContents();
            try {
                $data = $this->json->unserialize($responseJson);
            } catch (InvalidArgumentException $e) {
                $this->logger->error(new Phrase('Comerline Syncg | Invalid JSON data'));
            }
        } else {
            $this->logger->error(new Phrase('Comerline Syncg | There seems to be a problem doing the request'));
        }
    }

    private function doRequest(
        string $uriEndpoint,
        array $params,
        string $requestMethod
    ): Response {
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => $this->uri,
        ]]);

        try {
            $response = $client->request(
                $requestMethod,
                $uriEndpoint,
                $params
            );
        } catch (GuzzleException $exception) {
            $response = $this->responseFactory->create([
                'status' => $exception->getCode(),
                'reason' => $exception->getMessage()
            ]);
        }

        return $response;
    }
}

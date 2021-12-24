<?php

declare(strict_types=1);

namespace Comerline\Syncg\Service;

use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

abstract class SyncgApiService
{
    protected $method = Request::HTTP_METHOD_GET;
    protected $endpoint = '';
    protected $params = [];

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        Config          $configHelper,
        Json            $json,
        ResponseFactory $responseFactory,
        ClientFactory   $clientFactory,
        LoggerInterface $logger
    )
    {
        $this->config = $configHelper;
        $this->json = $json;
        $this->clientFactory = $clientFactory;
        $this->responseFactory = $responseFactory;
        $this->logger = $logger;
        $this->uri = $this->config->getGeneralConfig('installation_url');
        $this->endpoint = '';
    }

    public function execute()
    {
        $data = null;
        $response = $this->doRequest($this->endpoint, $this->params, $this->method);
        $status = $response->getStatusCode();
        if ($status === 200) {
            $responseJson = $response->getBody()->__toString();
            try {
                $data = $this->json->unserialize($responseJson);
            } catch (InvalidArgumentException $e) {
                $this->logger->error(new Phrase('Comerline Syncg | Invalid JSON data'));
            }
        } else {
            $this->logger->error(new Phrase('Comerline Syncg | There seems to be a problem doing the request'));
        }
        return $data;
    }

    private function doRequest(
        string $uriEndpoint,
        array  $params,
        string $requestMethod
    ): Response
    {
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => $this->uri,
            'cookies' => new FileCookieJar('cookie_path', true),
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

    public function getTrackTime($timeStart): string
    {
        $timeEnd = microtime(true);
        $executionTime = round(($timeEnd - $timeStart), 2);
        return $executionTime . 's';
    }
}

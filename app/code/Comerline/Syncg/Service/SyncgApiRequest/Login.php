<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class Login extends SyncgApiService
{

    protected $method = Request::HTTP_METHOD_POST;
    const PATH = 'syncg/';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    public function __construct(
        Config $configHelper,
        WriterInterface $writer,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger
    ) {
        $this->configWriter = $writer;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams()
    {
        $this->endpoint .= 'api/users/login';
        $this->params = [
            'form_params' => [
                'name' => $this->config->getGeneralConfig('username'),
                'password' => $this->config->getGeneralConfig('password')
            ],
        ];
    }

    public function send()
    {
        $this->buildParams();
        $response = $this->execute();
        if ($response && array_key_exists('success', $response)) {
            $this->configWriter->save(self::PATH .'general/g4100_middleware_token', $response['success']['token'], 'default');
        }
    }
}

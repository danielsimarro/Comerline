<?php

namespace Comerline\ImprovedMegaMenu\Console;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\App\State;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComerlineRunMegaMenuGenerate extends Command
{

    const TARGET_URL = '/comerline_improvedmegamenu/render/tofile';

    private State $state;
    private ClientFactory $clientFactory;
    private string $baseUrl;
    private LoggerInterface $logger;

    public function __construct(
        State $state,
        StoreManagerInterface $storeManager,
        ClientFactory $clientFactory,
        LoggerInterface $logger
    ) {
        $this->state = $state;
        $this->clientFactory = $clientFactory;
        $this->baseUrl = $storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('comerline:megamenu:generate');
        $this->setDescription('Generate MegaMenu HTML');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $output->writeln("Generate HTML for MegaMenu");
        if ($this->sendRequest()) {
            $output->writeln("Finished");
        } else {
            $output->writeln("Failed, check the error logs");
        }
    }

    private function sendRequest()
    {
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => $this->baseUrl
        ]]);
        try {
            $response = $client->request(
                'GET',
                self::TARGET_URL
            );
            return $response->getStatusCode() === 204;
        } catch (GuzzleException $exception) {
            $this->logger->error('Comerline - Improved Mega Menu: Generation failed. ' . $exception->getMessage());
            return false;
        }
    }
}

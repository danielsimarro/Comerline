<?php

namespace Comerline\Syncg\Console;

use Comerline\Syncg\Helper\Config;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Comerline\Syncg\Helper\Syncg;

class ComerlineResetInProgress extends Command
{
    private State $state;
    private WriterInterface $configWriter;

    public function __construct(
        WriterInterface $writer,
        State $state)
    {
        $this->configWriter = $writer;
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('comerline:reset');
        $this->setDescription('Set in_progress to 0');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_ADMINHTML);
        $this->configWriter->save(Config::PATH .'general/mapping_in_progress', 0, 'default');
        $this->configWriter->save(Config::PATH .'general/stock_in_progress', 0, 'default');
        $this->configWriter->save(Config::PATH .'general/sync_in_progress', 0, 'default');
    }
}

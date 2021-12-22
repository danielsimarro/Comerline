<?php

namespace Comerline\Syncg\Console;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Comerline\Syncg\Helper\Syncg;

class ComerlineSyncg extends Command
{
    protected $syncgHelper;
    private $state;

    public function __construct(
        Syncg $syncgHelper,
        State $state)
    {
        $this->state = $state;
        $this->syncgHelper = $syncgHelper;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('comerline:runsyncg');
        $this->setDescription('Runs Comerline Syncg Synchronizer');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $output->writeln("Ejecutando CRON");
        $this->syncgHelper->syncgAll();
        $output->writeln("Ejecutado sin problemas");
    }
}

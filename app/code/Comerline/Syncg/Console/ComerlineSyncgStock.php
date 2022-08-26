<?php

namespace Comerline\Syncg\Console;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Comerline\Syncg\Helper\Syncg;

class ComerlineSyncgStock extends Command
{
    protected Syncg $syncgHelper;
    private State $state;

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
        $this->setName('comerline:runsyncg:stock');
        $this->setDescription('Runs Comerline Syncg Stock Synchronizer');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_ADMINHTML);
        $output->writeln("Executing Syncg stock");
        $this->syncgHelper->syncgStock();
        $output->writeln("Finished");
    }
}

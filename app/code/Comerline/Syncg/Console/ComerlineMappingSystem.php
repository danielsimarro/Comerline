<?php

namespace Comerline\Syncg\Console;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComerlineMappingSystem extends Command
{
    private $state;

    public function __construct(
        State $state
    )
    {
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('comerline:runmapping');
        $this->setDescription('Runs Comerline Wheel - Rim Mapping System');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $output->writeln("Executing Wheel - Rim Mapping");
        $output->writeln('Command works!');
        $output->writeln("Finished");
    }
}

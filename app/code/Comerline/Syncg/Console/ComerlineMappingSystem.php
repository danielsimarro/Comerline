<?php

namespace Comerline\Syncg\Console;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Comerline\Syncg\Helper\MappingHelper;
use Symfony\Component\Console\Output\OutputInterface;

class ComerlineMappingSystem extends Command
{
    protected $mappingHelper;
    private $state;

    public function __construct(
        MappingHelper $mappingHelper,
        State $state
    )
    {
        $this->mappingHelper = $mappingHelper;
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
        $this->mappingHelper->mapCarRims();
        $output->writeln("Finished");
    }
}

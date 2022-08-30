<?php

namespace Comerline\Syncg\Console;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Helper\MappingHelper;
use Magento\Framework\App\Area;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Comerline\Syncg\Helper\Syncg;

class ComerlineResetCategories extends Command
{
    private State $state;
    private MappingHelper $mappingHelper;
    private TypeListInterface $cacheTypeList;

    public function __construct(
        MappingHelper $mappingHelper,
        TypeListInterface $cacheTypeList,
        State $state
    )
    {
        $this->cacheTypeList = $cacheTypeList;
        $this->mappingHelper = $mappingHelper;
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('comerline:reset:categories');
        $this->setDescription('Delete vehicle categories');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $this->mappingHelper->deleteCategories(true);
        //Clean cache to reflect dynamic category changes in menu.
        $this->cacheTypeList->cleanType('full_page');
        $this->cacheTypeList->cleanType('block_html');
        $output->writeln("Finished");
    }
}

<?php

namespace Comerline\Syncg\Controller\Index;

use Magento\Framework\App\ActionInterface;
use Comerline\Syncg\Model\SyncgStatus;

class Index implements ActionInterface
{

    private $syncgStatus;

    public function __construct(
        SyncgStatus $syncgStatus
    ) {
        $this->syncgStatus = $syncgStatus;
    }

    public function execute()
    {

    }
}

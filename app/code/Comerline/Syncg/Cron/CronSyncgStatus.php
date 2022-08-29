<?php

namespace Comerline\Syncg\Cron;

use Comerline\Syncg\Helper\Syncg;

class CronSyncgStatus
{
    /**
     * @var Syncg
     */
    protected $syncgHelper;

    public function __construct(
        Syncg $helper
    ) {
        $this->syncgHelper = $helper;
    }

    public function execute()
    {
        // Disabled, execute with crontab manually
        // $this->syncgHelper->syncgAll();
    }
}

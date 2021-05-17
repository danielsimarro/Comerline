<?php

namespace Comerline\Syncg\Helper;

use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\SyncgStatus;

class Syncg
{

    private $syncgStatusRepository;

    private $syncgStatusCollectionFactory;

    public function __construct(
        SyncgStatusRepository $syncgStatusRepository,
        CollectionFactory $syncgStatusCollectionFactory,
    ) {
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->syncStatusCollectionFactory = $syncgStatusCollectionFactory;
    }

    public function fetchPending(){
        $orderIds = [];
        $collection = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('status', SyncgStatus::STATUS_PENDING);
        foreach ($collection as $item){
            $orderIds[] = $item->getData('mg_id'); // We will get this IDs on a helper and, from there, we will
        }                                          // get them from the Order Repository and then send them to the Order model to print them on a TXT

    }
}

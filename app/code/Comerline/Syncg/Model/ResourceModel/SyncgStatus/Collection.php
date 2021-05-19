<?php

namespace Comerline\Syncg\Model\ResourceModel\SyncgStatus;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'comerline_syncg_status_collection';
    protected $_eventObject = 'syncg_status_collection';

    protected function _construct()
    {
        $this->_init('Comerline\Syncg\Model\SyncgStatus', 'Comerline\Syncg\Model\ResourceModel\SyncgStatus');
    }
}

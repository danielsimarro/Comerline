<?php

namespace Comerline\Syncg\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

class SyncgStatus extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'comerline_syncg_status';

    const STATUS_PENDING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_FAILED = 2;
    const STATUS_DELETED = 3;

    const CLIENT_GUEST = 0;

    const TYPE_ORDER = 0;
    const TYPE_PRODUCT = 1;
    const TYPE_CLIENT = 2;
    const TYPE_PRODUCT_SIMPLE = 3;
    const TYPE_IMAGE = 6;
//    const TYPE_CATEGORY = 1;  We will have more types in the future, so I leave this as a placeholder

    protected $_cacheTag = 'comerline_syncg_status';

    protected $_eventPrefix = 'comerline_syncg_status';

    protected function _construct()
    {
        $this->_init('Comerline\Syncg\Model\ResourceModel\SyncgStatus');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        return [];
    }
}

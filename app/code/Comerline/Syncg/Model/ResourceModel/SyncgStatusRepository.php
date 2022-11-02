<?php

namespace Comerline\Syncg\Model\ResourceModel;

use Magento\Framework\Exception\AlreadyExistsException;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus as SyncgStatusResource;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\SyncgStatusFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Comerline\Syncg\Helper\SQLHelper;

class SyncgStatusRepository
{

    /**
     * @var SyncgStatus
     */
    protected $syncgStatus;

    /**
     * @var SyncgStatusResource
     */
    protected $syncgStatusResource;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var CollectionFactory
     */
    protected $syncgStatusCollectionFactory;

    /**
     * @var SyncgStatusFactory
     */
    protected $syncgStatusFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;
    private SQLHelper $sqlHelper;

    public function __construct(
        SyncgStatusResource $syncgStatusResource,
        DateTime $date,
        CollectionFactory $syncgStatusCollectionFactory,
        SyncgStatusFactory $syncgStatusFactory,
        LoggerInterface $logger,
        SQLHelper $sqlHelper
    ) {
        $this->syncgStatusResource = $syncgStatusResource;
        $this->date = $date;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->syncgStatusFactory = $syncgStatusFactory;
        $this->logger = $logger;
        $this->sqlHelper = $sqlHelper;
    }

    public function updateEntityStatus($mgId, $gId, $type, $status, $parentGId = 0)
    {
        if ($type == SyncgStatus::TYPE_ORDER) {
            $entityStatus = $this->getEntityStatusByMgId($mgId, $type);
        } else {
            $entityStatus = $this->getEntityStatus($gId, $type);
        }
        if ($entityStatus) { // Exists Entity Status
            $this->syncgStatus = $entityStatus;
            if (!$this->syncgStatus->getMgId()) {
                $this->syncgStatus->setMgId($mgId);
            }
            if ($type == SyncgStatus::TYPE_ORDER && $gId && !$this->syncgStatus->getGId()) {
                $this->syncgStatus->setGId($gId);
            }
            $this->syncgStatus->setStatus($status);
            $this->syncgStatus->setUpdatedAt($this->date->date());
            $this->saveSyncgStatus($this->syncgStatus);
        } else { // No exist, create
            $valuesToInsert['type'] = $type;
            $valuesToInsert['mg_id'] = $mgId;
            $valuesToInsert['g_id'] = $gId;
            $valuesToInsert['status'] = $status;
            $valuesToInsert['parent_g'] = $parentGId;
            $valuesToInsert['parent_mg'] = 0;
            $valuesToInsert['created_at'] = $this->date->date();
            $this->sqlHelper->addSyncgStatus($valuesToInsert);
        }
    }

    public function getEntityStatus($gId, $type) {
        $entityStatus = null;
        $collection = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('g_id', $gId)
            ->setCurPage(0)
            ->setPageSize(1);
        if ($collection->getSize() > 0) {
            foreach ($collection as $item) {
                $entityStatus = $this->syncgStatusFactory->create()->load($item->getData('id'));
            }
        }
        return $entityStatus;
    }

    public function getEntityStatusByMgId($mgId, $type) {
        $entityStatus = null;
        $collection = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('mg_id', $mgId)
            ->setCurPage(0)
            ->setPageSize(1);
        if ($collection->getSize() > 0) {
            foreach ($collection as $item) {
                $entityStatus = $this->syncgStatusFactory->create()->load($item->getData('id'));
            }
        }
        return $entityStatus;
    }

    public function deleteEntity($mgId, $type) {
        $collection = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('mg_id', $mgId);
        if ($collection->getSize() > 0) {
            foreach ($collection as $item) {
                $this->syncgStatus = $this->syncgStatusFactory->create()->load($item->getData('id'));
                $this->syncgStatus->delete();
                $this->saveSyncgStatus($this->syncgStatus);
            }
        }
    }

    private function saveSyncgStatus($model): void
    {
        try {
            $this->syncgStatusResource->save($model);
        } catch (AlreadyExistsException | Exception $e) {
            $this->logger->error(new Phrase('Comerline Syncg | Save Error | ' . $e->getMessage()));
        }
    }
}

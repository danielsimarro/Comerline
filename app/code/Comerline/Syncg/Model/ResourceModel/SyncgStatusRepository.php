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

    public function __construct(
        SyncgStatusResource $syncgStatusResource,
        DateTime $date,
        CollectionFactory $syncgStatusCollectionFactory,
        SyncgStatusFactory $syncgStatusFactory,
        LoggerInterface $logger
    ) {
        $this->syncgStatusResource = $syncgStatusResource;
        $this->date = $date;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->syncgStatusFactory = $syncgStatusFactory;
        $this->logger = $logger;
    }

    public function updateEntityStatus($mgId, $gId, $type, $status)
    {
        $collection = $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('mg_id', $mgId)
            ->addFieldToFilter('g_id', $gId);
        if ($collection->getSize() > 0) {
            foreach ($collection as $item) {
                $this->syncgStatus = $this->syncgStatusFactory->create()->load($item->getData('id'));
                $this->syncgStatus->setMgId($mgId);
                $this->syncgStatus->setStatus($status);
                $this->syncgStatus->setUpdatedAt($this->date->date());
                $this->saveSyncgStatus($this->syncgStatus);
            }
        } else {
            $this->syncgStatus = $this->syncgStatusFactory->create();
            $this->syncgStatus->setType($type);
            $this->syncgStatus->setMgId($mgId);
            $this->syncgStatus->setGId($gId);
            $this->syncgStatus->setStatus($status);
            $this->syncgStatus->setCreatedAt($this->date->date());
            $this->saveSyncgStatus($this->syncgStatus);
        }
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

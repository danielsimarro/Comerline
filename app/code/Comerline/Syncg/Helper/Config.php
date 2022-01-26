<?php
namespace Comerline\Syncg\Helper;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Datetime as DatetimeOrigin;
use Magento\Framework\Stdlib\DateTime\DateTime;
use DateInterval;

class Config extends AbstractHelper
{

    /**
     * @var WriterInterface
     */
    private $configWriter;

    private $dateTime;

    private $coreConfigDataCollection;

    public function __construct(
        Context $context,
        WriterInterface $writer,
        DateTime $dateTime,
        CollectionFactory $coreConfigDataCollection
    ) {
        $this->configWriter = $writer;
        $this->dateTime = $dateTime;
        $this->coreConfigDataCollection = $coreConfigDataCollection;
        parent::__construct($context);
    }

    const PATH = 'syncg/';
    const TOKEN_PATH = 'syncg/general/g4100_middleware_token';

    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field, ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    public function getTokenFromDatabase() {
        $collection = $this->coreConfigDataCollection->create();
        $collection->addFieldToFilter('path', self::TOKEN_PATH)
            ->getFirstItem();
        return $collection->getFirstItem()->getData('value');
    }

    public function setSyncInProgress(bool $syncInProgress)
    {
        $this->configWriter->save(self::PATH .'general/sync_in_progress', $syncInProgress, 'default');
    }

    public function syncInProgress(): bool
    {
        $syncInProgress = false;
        $coreConfigData = $this->getParamsWithoutSystem('syncg/general/sync_in_progress');
        if ($coreConfigData) {
            $syncInProgress = $coreConfigData->getValue() ?? false;
        }
        return $syncInProgress;
    }

    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::PATH .'general/'. $code, $storeId);
    }

    public function setLastDateSyncProducts($date)
    {
        $this->configWriter->save(self::PATH .'general/last_date_sync_products', $date, 'default');
    }

    public function setLastDateMappingCategories($date)
    {
        $this->configWriter->save(self::PATH .'general/last_date_mapping_categories', $date, 'default');
    }

    public function getLastSyncPlusFiveMinutes() : int
    {
        $lastSyncPlusFiveMinutesTms = 0;
        $coreConfigData = $this->getParamsWithoutSystem('syncg/general/last_date_sync_products');
        if ($coreConfigData) {
            $date = $coreConfigData->getValue();
            if($date){
                $dateFormat = DatetimeOrigin::createFromFormat('Y-m-d H:i:s', $date);
                $lastSyncPlusFiveMinutesTms = $dateFormat->add(new DateInterval('PT05M'))->getTimestamp();
            }
        }
        return $lastSyncPlusFiveMinutesTms;
    }

    public function getLastSyncPlusHalfHour() : int
    {
        $lastSyncPlusHalfHourTms = 0;
        $coreConfigData = $this->getParamsWithoutSystem('syncg/general/last_date_sync_products');
        if ($coreConfigData) {
            $date = $coreConfigData->getValue();
            if($date){
                $dateFormat = DatetimeOrigin::createFromFormat('Y-m-d H:i:s', $date);
                $lastSyncPlusHalfHourTms = $dateFormat->add(new DateInterval('PT30M'))->getTimestamp();
            }
        }
        return $lastSyncPlusHalfHourTms;
    }

    public function getParamsWithoutSystem(string $param)
    {
        return $this->coreConfigDataCollection
            ->create()
            ->addFieldToFilter('path', $param)
            ->getFirstItem();
    }

}

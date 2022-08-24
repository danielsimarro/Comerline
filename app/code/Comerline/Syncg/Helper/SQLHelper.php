<?php

namespace Comerline\Syncg\Helper;

use Comerline\Syncg\Model\SyncgStatus;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

class SQLHelper extends AbstractHelper
{
    protected $resource;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    const ATTRIBUTE_STATUS_ID = 97;

    public function __construct(
        Context            $context,
        ResourceConnection $resource,
        LoggerInterface    $logger
    )
    {
        parent::__construct($context);
        $this->resource = $resource;
        $this->logger = $logger;
        $this->prefixLog = uniqid() . ' | G4100 Sync |';
    }

    public function disableProducts($productsG4100)
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Init Disable Products.'));
        $ids = [];
        $disable = [];
        foreach ($productsG4100 as $product) {
            if ($product['si_vender_en_web'] === false) {
                $ids[] = $product['id']; // We have to get COD instead of ID from G4100, otherwise we will never get the correct products
            }
        }
        if ($ids) {
            $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
            $tableName = $connection->getTableName('comerline_syncg_status');
            $sql = "SELECT * FROM " . $tableName . " WHERE type IN (1,3) AND g_id IN (" . implode(',', $ids) . ");";
            $result = $connection->fetchAll($sql);
            foreach ($result as $r) {
                $disable[] = $r['mg_id'];
                $this->logger->info(new Phrase($this->prefixLog . ' [Magento Product: ' . $r['mg_id'] . '] | DISABLED PRODUCT.'));
            }
            if ($disable) { // If $disable is empty, we don't have to set any product status to disabled, so we skip it
                $this->setStatusAsDisabled($disable);
            }
        }
        $this->logger->info(new Phrase($this->prefixLog . ' Finish Disable Products.'));
    }

    private function setStatusAsDisabled($mgIds)
    {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('catalog_product_entity_int');
        $sql = "UPDATE " . $tableName . " SET VALUE = '2' WHERE attribute_id = '" . self::ATTRIBUTE_STATUS_ID . "' AND entity_id IN (" . implode(',', $mgIds) . ");";
        $connection->query($sql);
    }


    /**
     * @param $relatedIds
     * @param $parentGId
     * @return void
     */
    public function setRelatedProducts($relatedIds, $parentGId)
    {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('comerline_syncg_status');
        foreach ($relatedIds as $relationG4100Cod) {
            $sql = "INSERT INTO " . $tableName . " (type, mg_id, g_id, status, parent_g, parent_mg) " .
                "VALUES(" . SyncgStatus::TYPE_PRODUCT_SIMPLE . ", 0, " . $relationG4100Cod . ", " . SyncgStatus::STATUS_PENDING . ", " . $parentGId . ", 0) " .
                "ON DUPLICATE KEY UPDATE parent_g = '" . $parentGId . "' ";
            $connection->query($sql);
        }
    }

    public function updateRelatedProductsStatus($relatedIds, $parentMgId)
    {
        if ($relatedIds) {
            $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
            $tableName = $connection->getTableName('comerline_syncg_status');
            $sql = "UPDATE " . $tableName . " SET parent_mg = '" . $parentMgId . "' " .
                "WHERE mg_id IN (" . implode(',', $relatedIds) . ") AND type = " . SyncgStatus::TYPE_PRODUCT_SIMPLE;
            $connection->query($sql);
        }
    }

    public function getPendingRelatedProducts(): array
    {
        $relatedProducts = [];
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('comerline_syncg_status');
        $sql = "SELECT table1.mg_id AS child_mg_id, table2.mg_id AS parent_mg_id FROM " . $tableName . " AS table1 " .
            "JOIN " . $tableName . " AS table2 ON table1.parent_g = table2.g_id AND table2.`type` = " . SyncgStatus::TYPE_PRODUCT . " " .
            "WHERE table1.parent_g != 0 AND table1.parent_mg = 0 " .
            "AND table1.`type` = " . SyncgStatus::TYPE_PRODUCT_SIMPLE . ";";
        $result = $connection->fetchAll($sql);
        foreach ($result as $r) {
            $relatedProducts[$r['parent_mg_id']][] = $r['child_mg_id'];
        }
        return $relatedProducts;
    }
}

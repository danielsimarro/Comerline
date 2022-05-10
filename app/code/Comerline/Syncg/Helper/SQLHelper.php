<?php

namespace Comerline\Syncg\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;

class SQLHelper extends AbstractHelper
{
    protected $resource;

    public function __construct(
        Context            $context,
        ResourceConnection $resource
    )
    {
        parent::__construct($context);
        $this->resource = $resource;
    }

    public function disableProducts($products) {
        $ids = [];
        $disable = [];
        foreach ($products as $product) {
            $ids[] = $product['cod']; // We have to get COD instead of ID from G4100, otherwise we will never get the correct products
        }
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('comerline_syncg_status');
        $sql = "SELECT * FROM " . $tableName . " WHERE (type = '1' OR type = '3') AND g_id NOT IN (" . implode(',', $ids) . ");";
        $result = $connection->fetchAll($sql);
        foreach ($result as $r) {
            $disable[] = $r['mg_id'];
        }
        if ($disable) { // If $disable is empty, we don't have to set any product status to disabled, so we skip it
            $this->setStatusAsDisabled($disable);
        }
    }

    private function setStatusAsDisabled($mgIds)
    {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('catalog_product_entity_int');
        $sql = "UPDATE " . $tableName . " SET VALUE = '2' WHERE attribute_id = 97 AND entity_id IN (" . implode(',', $mgIds) . ");";
        $connection->query($sql);
    }

    public function setRelatedProducts($relatedIds, $parentGId, $parentMgId) {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('comerline_syncg_status');
        $sql = "INSERT INTO ". $tableName ." (type, mg_id, g_id, status, parent_g, parent_mg) VALUES(1, null, " . $relatedIds[0] . ", 0, " . $parentGId . ", " . $parentMgId .") ON DUPLICATE KEY UPDATE parent_g='" . $parentGId . "', parent_mg='" . $parentMgId ."'";
        $connection->query($sql);
    }
}

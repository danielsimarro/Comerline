<?php

namespace Comerline\Syncg\Helper;

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

    public function disableProducts($productsG4100) {
        $this->logger->info(new Phrase($this->prefixLog . ' Init Disable Products.'));
        $ids = [];
        $disable = [];
        foreach ($productsG4100 as $product) {
            if ($product['si_vender_en_web'] === false) {
                $ids[] = $product['cod']; // We have to get COD instead of ID from G4100, otherwise we will never get the correct products
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
        // @todo el atributo 97 puesto a fuego es peligroso, deberíamos tenerlo en una constante para controlarlo
        $sql = "UPDATE " . $tableName . " SET VALUE = '2' WHERE attribute_id = '" . self::ATTRIBUTE_STATUS_ID . "' AND entity_id IN (" . implode(',', $mgIds) . ");";
        $connection->query($sql);
    }


    /**
     * @todo hay que mejorar esta función. Los productos simples (con padre) se deben insertar siempre con tipo 3 puesto que serán
     * productos simples. Además se deben insertar como PENDING aquellos que no existan y/o no tengan padre.
     * @param $relatedIds
     * @param $parentGId
     * @param $parentMgId
     * @return void
     */
    public function setRelatedProducts($relatedIds, $parentGId, $parentMgId) {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('comerline_syncg_status');
        foreach ($relatedIds as $rid) {
            $sql = "INSERT INTO ". $tableName ." (type, mg_id, g_id, status, parent_g, parent_mg) VALUES(3, null, " . $rid . ", 0, " . $parentGId . ", " . $parentMgId .") ON DUPLICATE KEY UPDATE parent_g='" . $parentGId . "', parent_mg='" . $parentMgId ."', status=0";
            $connection->query($sql);
        }
        $sql = "UPDATE ". $tableName ." SET parent_mg = '" . $parentMgId . "', status = 0 WHERE g_id = '" . $parentGId . "' AND type = '3'";
        $connection->query($sql);
    }

    public function updateRelatedProductsStatus($relatedIds) {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('comerline_syncg_status');
        $sql = "UPDATE ". $tableName ." SET status = '1' WHERE mg_id IN (" . implode(',', $relatedIds) . ") AND (type = 1 OR type = 3)";
        $connection->query($sql);
    }

    public function getRelatedProducts(): array
    {
        $relatedProducts = [];
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $tableName = $connection->getTableName('comerline_syncg_status');
        $sql = "SELECT * FROM " . $tableName . " WHERE parent_mg <> '' AND status = 0 AND type IN (1,3);";
        $result = $connection->fetchAll($sql);
        foreach ($result as $r) {
            $parent = $r['parent_mg'];
            $relatedProducts[$parent][] = $r['mg_id'];
        }
        return $relatedProducts;
    }
}

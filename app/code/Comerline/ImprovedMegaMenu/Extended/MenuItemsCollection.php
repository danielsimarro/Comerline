<?php

namespace Comerline\ImprovedMegaMenu\Extended;

use Comerline\ImprovedMegaMenu\Helper\CategoryMenuStruct;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Psr\Log\LoggerInterface;
use Sm\MegaMenu\Model\ResourceModel\MenuItems\Collection;

class MenuItemsCollection extends Collection
{
    private CategoryMenuStruct $categoryMenuHelper;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        CategoryMenuStruct $categoryMenuStruct,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->categoryMenuHelper = $categoryMenuStruct;
    }

    public function getItemsByLv($groupId, $level_start, $status_child): array
    {
        return $this->categoryMenuHelper->fetchBy([
            'group_id' => $groupId,
            'status' => $status_child,
            'depth' => $level_start
        ]);
    }

    public function getAllItemsFirstByGroupId($tableName, $groupId, $level_start, $status_child): array
    {
        return $this->categoryMenuHelper->fetchBy([
            'group_id' => $groupId,
            'status' => $status_child,
            'depth' => $level_start
        ]);
    }

    public function getAllItemsByItemsIdEnabled($parentId, $groupId, $status_child): array
    {
        return $this->categoryMenuHelper->fetchBy([
            'group_id' => $groupId,
            'parent_id' => $parentId,
            'status' => $status_child
        ]);
    }
}

<?php

namespace Comerline\ImprovedMegaMenu\Plugin;

use Comerline\ImprovedMegaMenu\Helper\CategoryMenuStruct;
use Sm\MegaMenu\Model\ResourceModel\MenuItems\Collection as Subject;

class MenuItemsCollection
{
    private CategoryMenuStruct $categoryMenuHelper;

    public function __construct(
        CategoryMenuStruct $categoryMenuStruct
    ) {
        $this->categoryMenuHelper = $categoryMenuStruct;
    }

    public function aroundGetItemsByLv(Subject $subject, callable $proceed, $groupId, $level_start, $status_child): array
    {
        $databaseItems = $proceed($groupId, $level_start, $status_child);
        $mockItems = $this->categoryMenuHelper->fetchBy([
            'group_id' => $groupId,
            'status' => $status_child,
            'depth' => $level_start
        ]);
        return array_merge($databaseItems, $mockItems) ?? [];
    }

    public function aroundGetAllItemsFirstByGroupId(Subject $subject, callable $proceed, $tableName, $groupId, $level_start, $status_child): array
    {
        $databaseItems = $proceed($tableName, $groupId, $level_start, $status_child);
        $mockItems = $this->categoryMenuHelper->fetchBy([
            'group_id' => $groupId,
            'status' => $status_child,
            'depth' => $level_start
        ]);
        return array_merge($databaseItems, $mockItems) ?? [];
    }

    public function aroundGetAllItemsByItemsIdEnabled(Subject $subject, callable $proceed, $parentId, $groupId, $status_child): array
    {
        $databaseItems = $proceed($parentId, $groupId, $status_child);
        $mockItems = $this->categoryMenuHelper->fetchBy([
            'group_id' => $groupId,
            'parent_id' => $parentId,
            'status' => $status_child
        ]);
        return array_merge($databaseItems, $mockItems) ?? [];
    }
}

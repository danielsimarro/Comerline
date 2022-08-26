<?php

namespace Comerline\ImprovedMegaMenu\Plugin;

use Comerline\ImprovedMegaMenu\Helper\CategoryMenuStruct;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Sm\MegaMenu\Model\MenuItems as Subject;

class MenuItems
{
    private ScopeConfigInterface $scopeConfig;
    private CategoryMenuStruct $categoryMenuHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CategoryMenuStruct $categoryMenuStruct
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->categoryMenuHelper = $categoryMenuStruct;
    }

    public function aroundGetAllItemsInEqLv(Subject $subject, callable $proceed, $item, $mode = 1, $attributes = '')
    {
        $parentMenuItemId = $this->scopeConfig->getValue('improvedmegamenu/main_config/menu_item_id', ScopeInterface::SCOPE_STORE);
        $parentItemId = $item['items_id'];
        $databaseItems = $proceed($item, $mode, $attributes);
        if (!$databaseItems && $parentItemId == $parentMenuItemId) {
            $databaseItems = $this->categoryMenuHelper->fetchBy(['parent_id' => $parentItemId]);
        }
        return $databaseItems;
    }
}

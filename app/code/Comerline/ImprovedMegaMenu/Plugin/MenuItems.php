<?php

namespace Comerline\ImprovedMegaMenu\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Sm\MegaMenu\Model\MenuItems as Subject;

class MenuItems
{
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function aroundGetAllItemsInEqLv(Subject $subject, callable $proceed, $item, $mode = 1, $attributes = '')
    {
        $parentMenuItemId = $this->scopeConfig->getValue('improvedmegamenu/main_config/menu_item_id', ScopeInterface::SCOPE_STORE);
        $parentItemId = $item['items_id'];
        $databaseItems = $proceed($item, $mode, $attributes);
        if (!$databaseItems && $parentItemId == $parentMenuItemId) {
            $databaseItems = ['items_id' => '_cat_none']; //Note: Original function calls only count the results. This works.
        }
        return $databaseItems;
    }
}

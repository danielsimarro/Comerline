<?php

namespace Comerline\ImprovedMegaMenu\Helper;

use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Sm\MegaMenu\Model\Config\Source\Align;
use Sm\MegaMenu\Model\Config\Source\Status;
use Sm\MegaMenu\Model\Config\Source\Type;
use Sm\MegaMenu\Model\ResourceModel\MenuItems\CollectionFactory as MenuItemsCollection;

class CategoryMenuStruct extends AbstractHelper
{
    const ID_PREFIX = '_cat_';

    private MenuItemsCollection $menuItemsCollection;
    private Category $categoryResourceModel;
    private string $baseUrl;

    public function __construct(
        Context $context,
        MenuItemsCollection $menuItemsCollection,
        Category $categoryResourceModel,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->menuItemsCollection = $menuItemsCollection;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->baseUrl = $storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
    }

    private function calculateDepth(int $baseDepth, array $categoryPathIds, $parentVehicleCategoryId)
    {
        $baseKey = array_search($parentVehicleCategoryId, $categoryPathIds);
        $lastKey = array_key_last($categoryPathIds);
        return $baseDepth + ($lastKey - $baseKey);
    }

    private function loadCategories()
    {
        $parentVehicleCategoryId = $this->scopeConfig->getValue('improvedmegamenu/main_config/category_id', ScopeInterface::SCOPE_STORE);
        $parentMenuItemId = $this->scopeConfig->getValue('improvedmegamenu/main_config/menu_item_id', ScopeInterface::SCOPE_STORE);
        if (!$parentVehicleCategoryId || !$parentMenuItemId) {
            return;
        }
        $parentMenuItem = $this->menuItemsCollection->create()->getItemById($parentMenuItemId);
        if (!$parentMenuItem) {
            return;
        }
        $categoryStruct = [];
        $categoryChildren = [];
        $subCategories = $this->categoryResourceModel->getCategories($parentVehicleCategoryId, 0, true, true, true);
        if ($subCategories && $subCategories->count()) {
            $baseMenuItemDepth = (int) $parentMenuItem['depth'];
            $baseMenuItemOrder = $parentMenuItem['order_item']; //Increment each insertion.
            $baseMenuItemPriorities = $parentMenuItem['priorities']; //Increment each insertion.
            $baseMenuItemGroup = $parentMenuItem['group_id']; //Keep as-is.
            $baseMenuItemPosition = $parentMenuItem['position_item']; //Keep as-is.
            $currOrder = $baseMenuItemOrder;
            $currPriorities = $baseMenuItemPriorities;
            foreach ($subCategories as $category) {
                /** @var $category CategoryModel */
                $categoryStruct[] = [
                    'items_id' => self::ID_PREFIX . $category->getId(),
                    'title' => $category->getName(),
                    'level' => $category->getData('level') ?? '',
                    'data_type' => 'category/' . $category->getId(),
                    'data_type_url' => $this->baseUrl . $category->getRequestPath(), //Extra attribute.
                    'show_title' => '1',
                    'description' => null,
                    'align' => Align::LEFT,
                    'icon_url' => '',
                    'content' => null,
                    'custom_class' => '',
                    'position_item' => $baseMenuItemPosition,
                    'priorities' => $currPriorities++,
                    'target' => '0', //Used for urls. Using default functionality.
                    'type' => Type::CATEGORY,
                    'status' => Status::STATUS_ENABLED,
                    'depth' => $this->calculateDepth($baseMenuItemDepth, $category->getPathIds(), $parentVehicleCategoryId),
                    'group_id' => $baseMenuItemGroup,
                    'cols_nb' => '2', //Using 2 from current data.
                    'parent_id' => ($category->getParentId() == $parentVehicleCategoryId) ? $parentMenuItemId : (self::ID_PREFIX . $category->getParentId()),
                    'order_item' => $currOrder++,
                    'show_image_product' => '0',
                    'show_title_product' => '0',
                    'show_rating_product' => '0',
                    'show_price_product' => '0',
                    'show_title_category' => Status::STATUS_DISABLED,
                    'limit_category' => '',
                    'show_sub_category' => Status::STATUS_ENABLED,
                    'limit_sub_category' => '',
                    'view_category_id' => $category->getId()
                ];
                $categoryChildren[$category->getParentId()][] = $category->getId();
            }
        }

        //Find child categories in already loaded data.
        foreach ($categoryStruct as $key => $menuItem) {
            $categoryId = $menuItem['view_category_id'] ?? null;
            if ($categoryId !== null) {
                $categoryStruct[$key]['view_children_category_ids'] = $categoryChildren[$categoryId] ?? [];
            }
        }
        return $categoryStruct;
    }

    private function loadAllMenuItems() {
        return $this->menuItemsCollection->create()
            ->setOrder('title', 'ASC')
            ->setOrder('order_item', 'ASC')
            ->setOrder('priorities', 'ASC')
            ->getData();
    }

    /**
     * Return all mocked menu items.
     * @return array
     */
    public function fetchAll()
    {
        global $_comerline_sm_menu_items;
        $categoryStruct = $_comerline_sm_menu_items ?? [];
        if (!$categoryStruct) {
            $categoryStruct = array_merge($this->loadAllMenuItems(), $this->loadCategories());
            $_comerline_sm_menu_items = $categoryStruct;
        }
        return $categoryStruct ?? [];
    }

    /**
     * Return all mocked menu items filtered by conditions.
     * @param array $where
     * @return array
     */
    public function fetchBy(array $where)
    {
        $categories = $this->fetchAll();
        foreach ($where as $field => $value) {
            $keystoKeep = array_keys(array_combine(array_keys($categories),array_column($categories, $field)), $value);
            $categories = array_filter(
                $categories,
                function ($key) use ($keystoKeep) {
                    return in_array($key, $keystoKeep);
                },
                ARRAY_FILTER_USE_KEY
            );
        }
        return $categories;
    }

    /**
     * Get loaded children of category.
     * @param $id
     * @return array|mixed
     */
    public function fetchChildrenIds($id)
    {
        $categories = $this->fetchAll();
        $categories = array_filter($categories, function ($item) use ($id) {
            return $item['data_type'] == 'category/' . $id;
        });
        return $categories[array_key_first($categories)]['view_children_category_ids'] ?? [];
    }

    /**
     * Get loaded children of category.
     * @param $id
     * @return array|mixed
     */
    public function fetchLoadedMenuItemById($id)
    {
        $categories = $this->fetchAll();
        $categories = array_filter($categories, function ($item) use ($id) {
            return $item['data_type'] == 'category/' . $id;
        });
        return $categories[array_key_first($categories)] ?? null;
    }
}

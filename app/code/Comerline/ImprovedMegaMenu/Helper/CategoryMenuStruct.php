<?php

namespace Comerline\ImprovedMegaMenu\Helper;

use Magento\Catalog\Model\CategoryRepository;
use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Sm\MegaMenu\Model\Config\Source\Align;
use Sm\MegaMenu\Model\Config\Source\Status;
use Sm\MegaMenu\Model\Config\Source\Type;

class CategoryMenuStruct extends AbstractHelper
{
    private CategoryRepository $categoryRepository;
    private $categoryStruct = null;

    public function __construct(
        Context $context,
        CategoryRepository $categoryRepository
    ) {
        parent::__construct($context);
        $this->categoryRepository = $categoryRepository;
    }

    private function loadCategoriesChildLeaf($subCategories, $depthLevel, $parentCategoryId)
    {
        foreach ($subCategories as $category) {
            $mockCategoryId = '_cat_' . $category->getId();
            $this->categoryStruct[] = [
                'items_id' => $mockCategoryId,
                'title' => $category->getName(),
                'data_type' => 'category/' . $category->getId(),
                'show_title' => '1',
                'description' => null,
                'align' => Align::LEFT,
                'icon_url' => '',
                'content' => null,
                'custom_class' => '',
                'position_item' => '2',
                'priorities' => '1',
                'target' => 0, //Used for urls. Using default functionality.
                'type' => Type::CATEGORY,
                'status' => Status::STATUS_ENABLED,
                'depth' => $depthLevel,
                'group_id' => '2', //TODO: See if theres any way to obtain the current level easily.
                'cols_nb' => '2', //Using 2 from current data.
                'parent_id' => $parentCategoryId,
                'order_item' => count($this->categoryStruct),
                'show_image_product' => '0',
                'show_title_product' => '0',
                'show_rating_product' => '0',
                'show_price_product' => '0',
                'show_title_category' => Status::STATUS_DISABLED,
                'limit_category' => '',
                'show_sub_category' => Status::STATUS_ENABLED,
                'limit_sub_category' => '',
            ];
            $children = $category->getChildrenCategories();
            if ($children) {
                $this->loadCategoriesChildLeaf($children, $depthLevel + 1, $mockCategoryId);
            }
        }
    }

    private function loadCategories()
    {
        $this->categoryStruct = [];
        $initialMockDepth = 2; //This is menu item depth + 1.
        $parentVehicleCategoryId = $this->scopeConfig->getValue('improvedmegamenu/main_config/category_id', ScopeInterface::SCOPE_STORE);
        $parentMenuItemId = $this->scopeConfig->getValue('improvedmegamenu/main_config/menu_item_id', ScopeInterface::SCOPE_STORE);
        if (!$parentVehicleCategoryId || !$parentMenuItemId) {
            return;
        }
        try {
            $vehiclesCategory = $this->categoryRepository->get($parentVehicleCategoryId);
        } catch (NoSuchEntityException $e) {
            return;
        }
        $subCategories = $vehiclesCategory->getChildrenCategories();
        if ($subCategories) {
            $this->loadCategoriesChildLeaf($subCategories, $initialMockDepth, $parentMenuItemId);
        }
    }

    /**
     * Return all mocked menu items.
     * @return array
     */
    public function fetchAll()
    {
        if ($this->categoryStruct === null) {
            $this->loadCategories();
        }
        return $this->categoryStruct ?? [];
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
            $keystoKeep = array_keys(array_column($categories, $field), $value);
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
}

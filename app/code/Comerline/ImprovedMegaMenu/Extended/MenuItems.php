<?php

namespace Comerline\ImprovedMegaMenu\Extended;

use Comerline\ImprovedMegaMenu\Helper\CategoryMenuStruct;
use Magento\Framework\App\Action\Context as ActionContext;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Model\Context as FrameContext;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Registry;
use Sm\MegaMenu\Helper\Defaults;
use Sm\MegaMenu\Model\Config\Source\Status;
use Sm\MegaMenu\Model\MenuItems as Subject;

class MenuItems extends Subject
{
    private CategoryMenuStruct $categoryMenuHelper;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        ActionContext $actionContext,
        Context $context,
        Registry $registry,
        Collection $collection,
        DataObject $dataObject,
        Defaults $defaults,
        CategoryMenuStruct $categoryMenuStruct,
        FrameContext $frameContext,
        array $data = []
    ) {
        parent::__construct($entityFactory, $actionContext, $context, $registry, $collection, $dataObject, $defaults, $frameContext, $data);
        $this->categoryMenuHelper = $categoryMenuStruct;
    }

    public function getAllItemsInEqLv($item, $mode = 1, $attributes = '')
    {
        $parentItemId = $item['items_id'];
        $filters = [
            'parent_id' => $parentItemId,
            'group_id' => $item['group_id'],
            'depth' => $item['depth'] + 1
        ];
        if ($mode != 2) {
            $filters['status'] = Status::STATUS_ENABLED;
        }
        return $this->categoryMenuHelper->fetchBy($filters);
    }
}

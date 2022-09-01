<?php

namespace Comerline\ImprovedMegaMenu\Extended;

use Comerline\ImprovedMegaMenu\Helper\CategoryMenuStruct;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Helper\Data;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filter\Email;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\View\Context as ViewContext;
use Magento\Framework\View\Element\Template\Context;
use Sm\MegaMenu\Block\MegaMenu\View as Subject;
use Sm\MegaMenu\Helper\Defaults;

class View extends Subject
{

    const FULL_LOAD = '_read_from_db';
    const FILE = 'cached_mega_menu.html';
    private DirectoryList $_directoryList;
    private CategoryMenuStruct $categoryHelper;

    public function __construct(
        Context $context,
        Defaults $defaults,
        AbstractProduct $abstractProduct,
        ObjectManagerInterface $objectManager,
        DecoderInterface $urlDecoder,
        Email $email,
        Data $catalogData,
        AdapterFactory $imageFactory,
        ViewContext $viewContext,
        CategoryMenuStruct $categoryHelper,
        DirectoryList $directoryList,
        array $data = []
    ){
        parent::__construct($context, $defaults, $abstractProduct, $objectManager, $urlDecoder, $email, $catalogData, $imageFactory, $viewContext, $data);
        $this->categoryHelper = $categoryHelper;
        $this->_directoryList = $directoryList;
    }

    public function toHtml()
    {
        if ($this->getData(self::FULL_LOAD)) {
            return parent::toHtml();
        } else {
            $path = $this->_directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
            $fullPath = $path . '/' . self::FILE;
            if (!file_exists($fullPath)) {
                return '';
            } else {
                return file_get_contents($fullPath) ?? '';
            }
        }
    }

    public function getCategoryLink($item)
    {
        return $item['data_type_url'] ?? parent::getCategoryLink($item);
    }

    public function getCategory($item, $itemId)
    {
        $output = '';
        $dem = 0;
        $id_all_cat = $item['view_children_category_ids'] ?? [];
        $limitCat = (int)$item['limit_category'];
        $prefix = Subject::PREFIX;

        if ($id_all_cat)
        {
            if (count($id_all_cat)>$limitCat)
                $limit = $limitCat;
            else
                $limit = count($id_all_cat);

            foreach ($id_all_cat as $ia)
            {
                $activedClassName = ($this->isActivedChildCat($itemId, $ia))?$prefix.'actived':'';
                $dem++;
                if (($limit == '') || ($dem <= $limit))
                {
                    $output .= $this->commonCategoryBlock($ia, $item, $activedClassName, $itemId);
                }
            }
        } else {
            return '';
        }
        return $output;
    }

    public function getCategoryChild($item, $id_all_cat_child, $limit, $itemId='')
    {
        $dem = 0;
        $output = '';
        $prefix = Subject::PREFIX;
        if ($id_all_cat_child)
        {
            foreach ($id_all_cat_child as $iac)
            {
                $activedClassName = ($this->isActivedChildCat($itemId, $iac))?$prefix.'actived':'';
                $dem++;
                if (($limit == '') || ($dem <= $limit))
                {
                    $output .= $this->commonCategoryBlock($iac, $item, $activedClassName, $itemId);
                }
            }
        }else
        {
            return false;
        }
        return $output;
    }

    private function commonCategoryBlock($ia, $item, $activedClassName, $itemId)
    {
        $prefix = Subject::PREFIX;
        $aClassName = ($this->isDrop($item))?$prefix.'drop':$prefix.'nodrop';
        $addClass['title'] = $prefix.'title';

        $output = '';
        $itemChild = $this->categoryHelper->fetchLoadedMenuItemById($ia);
        if ($itemChild) {
            $link = $itemChild['data_type_url'];
            $title = $itemChild['title'];
            $level = $itemChild['level'];
        } else {
            //Used as fallback.
            $modelCategory = ObjectManager::getInstance()->create('Magento\Catalog\Model\Category');
            $category_child = $modelCategory->load($ia);
            $link = $category_child->getUrl();
            $title = __($category_child->getName());
            $level = $category_child->getLevel();
        }
        $namecat = '<a class="'.$aClassName.'" href="'.$link.'" '.$this->getTargetAttr($item['target']).'>'.__($title).'</a>';
        /** INIT MOD: ADD CLASS TO DIV **/
        $output .= '<div class="'.implode(' ', $addClass).' '.$activedClassName. ' ' .$prefix.'title_lv-'. $level .'">';
        /** END MOD **/
        if ($item['show_sub_category'] == Subject::STATUS_ENABLED)
        {
            $output .= $namecat;
            $id_all_cat_child = $this->categoryHelper->fetchChildrenIds($ia);
            if ($id_all_cat_child) {
                $output .= $this->getCategoryChild($item, $id_all_cat_child, '', $itemId);
            }
        }
        $output .= '</div>';
        return $output;
    }
}

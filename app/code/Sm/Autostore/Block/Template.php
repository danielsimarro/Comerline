<?php
namespace Sm\Autostore\Block;

class Template extends \Magento\Framework\View\Element\Template {
    public $_coreRegistry;
	
	public function _prepareLayout(){
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$helper_config = $objectManager->get('Sm\Autostore\Helper\Data');
		
		/* $header_style = $helper_config->getHeader('header_style')); */
		
		$headerStyle = $helper_config->getThemeLayout('header_style');
		$homeStyle = $helper_config->getThemeLayout('home_style');
		$footerStyle = $helper_config->getThemeLayout('footer_style');
		$layout = $helper_config->getThemeLayout('layout_style');
		$right_to_left = $helper_config->getThemeLayout('direction_rtl');
		
		if($right_to_left){
			$rtl = 'direction-rtl';
		} else {
			$rtl = '';
		}

		$this->pageConfig->addBodyClass($headerStyle . '-style');
		$this->pageConfig->addBodyClass($homeStyle . '-style');
		$this->pageConfig->addBodyClass($footerStyle . '-style');
		$this->pageConfig->addBodyClass('layout-' . $layout);
		$this->pageConfig->addBodyClass($rtl);
		
		return parent::_prepareLayout();
	}
    
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        array $data = []
    ) {
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context, $data);
    }    
	
	public function getProduct()
	{
		return $this->_coreRegistry->registry('product');
	}
	
	public function getProductCount($id)
	{
		/**
		 * @var \Magento\Catalog\Model\Product\Interceptor $product
		 */
		//Get Object Manager Instance
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		//Load product by product id
		$productObj = $objectManager->create('Magento\Catalog\Model\Product')->load($id);
		$productcollection = $objectManager->create('\Magento\Reports\Model\ResourceModel\Product\Collection');
		$productcollection->setProductAttributeSetId($productObj->getAttributeSetId());
		$prodData = $productcollection->addViewsCount()->getData();

		if (count($prodData) > 0) {
			foreach ($prodData as $product) {
				if ($product['entity_id'] == $id) {
					return (int) $product['views'];
				}
			}
		}

		return 0;
	}
}
?>
<?php
/**
 * @category Magentech
 * @package Sm_AttributesSearch
 * @version 2.0.0
 * @copyright Copyright (c) 2018 YouTech Company. All Rights Reserved.
 * @license - Copyrighted Commercial Software
 * @author YouTech Company <contact@ytcvn.com>
 * @link http://www.magentech.com
 */
 
namespace Sm\AttributesSearch\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;

/**
 * Class AttributesSearch
 *
 * @package Sm\AttributesSearch\Block
 */
class AttributesSearch extends Template
{
    const CACHE_TAGS = 'SM_ATTRIBUTES_SEARCH';

    /**
     * @var \Sm\AttributesSearch\Helper\Data
     */
    private $helperData;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $moduleManager;

    /**
     * @var \Magento\Eav\Model\Config
     */
    private $eavConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $jsonSerializer;

    /**
     * AttributesSearch constructor.
     *
     * @param Template\Context                           $context
     * @param \Magento\Eav\Model\Config                  $eavConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Manager          $moduleManager
     * @param SerializerJson                             $jsonSerializer
     * @param \Sm\AttributesSearch\Helper\Data           $helperData
     * @param array                                      $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Manager $moduleManager,
        SerializerJson $jsonSerializer,
        \Sm\AttributesSearch\Helper\Data $helperData,
        array $data = []
    ) {
        $this->moduleManager = $moduleManager;
        $this->eavConfig = $eavConfig;
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context, $data);
    }

    /**
     * Resource initialization
     */
    protected function _construct()
    {
        parent::_construct();
        $this->addData(
            [
            'cache_lifetime' => 86400,
            'cache_tags' => [self::CACHE_TAGS]]
        );
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCacheKeyInfo()
    {
        $params = $this->getRequest()->getParams();
        if (isset($params['_'])) {
            unset($params['_']);
        }
        return [
            'BLOCK_TPL_SM_ATTRIBUTES_SEARCH',
            $this->storeManager->getStore()->getCode(),
            $this->storeManager->getStore()->getId(),
			$this->_storeManager->getStore()->getCurrentCurrencyCode(),
            $this->getNameInLayout(),
            $this->getTemplateFile(),
            'base_url' => $this->getBaseUrl(),
            'template' => $this->getTemplate(),
            $this->jsonSerializer->serialize($params)
        ];
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAGS . '_' . $this->getNameInLayout()];
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getActionUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl().'catalogsearch/result/index/';
    }

    /**
     * @return mixed
     */
    public function getCaption()
    {
        return $this->helperData->getCaption();
    }

    /**
     * @param $code
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOptionLabel($code)
    {
        return $this->helperData->getOptionLabel($code);
    }

    /**
     * @param $code
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getLabelAttribute($code)
    {
        return $this->helperData->getLabelAttribute($code);
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAttributesData()
    {
        $attributes = $this->helperData->getSerializedConfigValue();
        $list = [];
        if (!empty($attributes)) {
            $flag = true;
            $count = 0;
            $total = count($attributes);
            foreach ($attributes as $key => $attr) {
                if ((int)$attr['activation']) {
                    $temps = [];
                    $count++;
                    $attribute =  $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $key);
                    $temps['code'] = $key;
                    $temps['id'] = $attribute->getId();
                    $temps['disabled'] = true;
                    $temps['last'] = $count >= $total ?  true : false;
                    if ($flag) {
                        $temps['options'] = [];
                        $temps['disabled'] = false;
                        $flag = false;
                    }
                    $list[$key] = $temps;
                    unset($temps);
                }
            }
            unset($attributes);
        }
        return $list;
    }

    /**
     * @return string|void
     */
    protected function _toHtml()
    {
        $isEnableModule = $this->moduleManager->isEnabled('Sm_AttributesSearch');
        if (!$this->helperData->isEnabled() || !$isEnableModule) {
            return '';
        }
        if ($this->getRequest()->isAjax()) {
            $template_file = "Sm_AttributesSearch::default_options.phtml";
        } else {
            $template_file = $this->getTemplate();
            $template_file = (!empty($template_file)) ? $template_file : "Sm_AttributesSearch::default.phtml";
        }
        $this->setTemplate($template_file);
        return parent::_toHtml();
    }
}

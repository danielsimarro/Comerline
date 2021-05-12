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

namespace Sm\AttributesSearch\Helper;

/**
 * Class Data
 *
 * @package Sm\AttributesSearch\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const SM_ATTRIBUTES_SEARCH_ACTIVE = 'attributessearch/general/active';

    const SM_ATTRIBUTES_SEARCH_CAPTION = 'attributessearch/general/caption';

    const SM_ATTRIBUTES_SEARCH_SELECT_ATTRIBUTES = 'attributessearch/source/select_attributes';
    
    /**
     * @var \Magento\Eav\Model\Config
     */
    private $eavConfig;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $jsonSerializer;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context        $context
     * @param \Magento\Eav\Model\Config                    $eavConfig
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
    ) {
        $this->eavConfig = $eavConfig;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::SM_ATTRIBUTES_SEARCH_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed
     */
    public function getCaption()
    {
        return $this->getConfigValue(self::SM_ATTRIBUTES_SEARCH_CAPTION);
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAttributesSelect()
    {
        $attributes = $this->eavConfig->getEntityAttributes(\Magento\Catalog\Model\Product::ENTITY);
        $attributes = array_keys($attributes);
        $return = $_attr = [];
        foreach ($attributes as $attr) {
            $attrData = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attr);
            if (($attrData->getFrontendInput() == 'select' || $attrData->getFrontendInput() =='multiselect')
                && $attrData->getIsFilterable() && $attrData->getIsFilterableInSearch()
            ) {
                $_attr['value'] = $attr;
                $_attr['label'] = $attrData->getFrontend()->getLocalizedLabel()->getText();
                $return[] = $_attr;
            }
        }
        unset($attributes);
        return $return;
    }

    /**
     * @param $configPath
     * @param null       $store
     * @return mixed
     */
    public function getConfigValue($configPath, $store = null)
    {
        return $this->scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @return array|bool
     */
    public function getSerializedConfigValue()
    {
        $value = $this->getConfigValue(self::SM_ATTRIBUTES_SEARCH_SELECT_ATTRIBUTES);
        if (empty($value)) {
            return false;
        }
        $json = $this->jsonSerializer;
        $return = [];
        $attrs = $json->unserialize($value);
        if (!empty($attrs)) {
            foreach ($attrs as $key => $attr) {
                $return[$attr['attributes']]  = $attr;
            }
            unset($attrs);
        }
        return $return;
    }

    /**
     * @param $code
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOptionLabel($code)
    {
        $return = [];
        $attrData = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $code);
        $labels = $attrData->getFrontend()->getSelectOptions();
        if (!empty($labels)) {
            foreach ($labels as $label) {
                if (!empty($label['value'])) {
                    $return[$label['value']] = $label['label'];
                }
            }
            unset($labels);
        }
        return $return;
    }

    /**
     * @param $code
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getLabelAttribute($code)
    {
        $config = $this->getSerializedConfigValue();
        $attrData = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $code);
        return !empty($config[$code]['attribute_title']) ?
            $config[$code]['attribute_title'] :
            __('Select ').$attrData->getFrontend()->getLocalizedLabel()->getText();
    }
}

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
namespace Sm\AttributesSearch\Block\Adminhtml\Form\Field;

/**
 * Class Attributes
 *
 * @package Sm\AttributesSearch\Block\Adminhtml\Form\Field
 */
class Attributes extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Sm\AttributesSearch\Helper\Data
     */
    private $helper;

    /**
     * Attributes constructor.
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param \Sm\AttributesSearch\Helper\Data        $helper
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Sm\AttributesSearch\Helper\Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * @param $value
     * @return mixed
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * @return string
     */
    public function _toHtml()
    {
        $attributes = $this->helper->getAttributesSelect();
        if (!$this->getOptions() && !empty($attributes)) {
            foreach ($attributes as $attribute) {
                $this->addOption($attribute['value'], $attribute['label']);
            }
        }
        return parent::_toHtml();
    }
}

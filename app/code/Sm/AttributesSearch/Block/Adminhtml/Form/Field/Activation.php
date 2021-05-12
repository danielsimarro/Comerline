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
 * Class Activation
 *
 * @package Sm\AttributesSearch\Block\Adminhtml\Form\Field
 */
class Activation extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Magento\Config\Model\Config\Source\Enabledisable
     */
    private $enableDisable;

    /**
     * Activation constructor.
     *
     * @param \Magento\Framework\View\Element\Context           $context
     * @param \Magento\Config\Model\Config\Source\Enabledisable $enableDisable
     * @param array                                             $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Config\Model\Config\Source\Enabledisable $enableDisable,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->enableDisable = $enableDisable;
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
        if (!$this->getOptions()) {
            $attributes = $this->enableDisable->toOptionArray();
            foreach ($attributes as $attribute) {
                $this->addOption($attribute['value'], $attribute['label']);
            }
        }
        return parent::_toHtml();
    }
}

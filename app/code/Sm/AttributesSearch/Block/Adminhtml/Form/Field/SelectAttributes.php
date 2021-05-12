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

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Class SelectAttributes
 *
 * @package Sm\AttributesSearch\Block\Adminhtml\Form\Field
 */
class SelectAttributes extends AbstractFieldArray
{
    /**
     * @var
     */
    private $activation;

    /**
     * @var
     */
    private $attributes;

    /**
     * @var string
     */
    protected $_template = 'Sm_AttributesSearch::system/config/form/field/array.phtml';

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function _getActivationRenderer()
    {
        if (!$this->activation) {
            $this->activation = $this->getLayout()->createBlock(
                '\Sm\AttributesSearch\Block\Adminhtml\Form\Field\Activation',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->activation;
    }

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function _getAttributesRenderer()
    {
        if (!$this->attributes) {
            $this->attributes = $this->getLayout()->createBlock(
                '\Sm\AttributesSearch\Block\Adminhtml\Form\Field\Attributes',
                '',
                ['data' =>  ['is_render_to_js_template' => true]]
            );
        }
        return $this->attributes;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->addColumn('order_attr', ['label' => __('Sort Order')]);
        $this->addColumn(
            'attributes',
            [
            'label' => __('Attributes'),
            'renderer' => $this->_getAttributesRenderer()
            ]
        );
        $this->addColumn('attribute_title', ['label' => __('Title Attribute')]);
        $this->addColumn(
            'activation',
            [
            'label' => __('Active'),
            'renderer' => $this->_getActivationRenderer()
            ]
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Attribute');
    }

    /**
     * @param \Magento\Framework\DataObject $row
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $options = [];
        $activation = $row->getData('activation');
        $attributes = $row->getData('attributes');
        $key_active = 'option_' . $this->_getActivationRenderer()->calcOptionHash($activation);
        $key_attributes = 'option_' . $this->_getAttributesRenderer()->calcOptionHash($attributes);
        $options[$key_active] = 'selected="selected"';
        $options[$key_attributes] = 'selected="selected"';
        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @param string $columnName
     * @return string
     * @throws \Exception
     */
    public function renderCellTemplate($columnName)
    {
        if (empty($this->_columns[$columnName])) {
            throw new \Magento\Framework\Exception\LocalizedException('Wrong column name specified.');
        }
        $column = $this->_columns[$columnName];
        $inputName = $this->_getCellInputElementName($columnName);

        if ($column['renderer']) {
            return $column['renderer']->setInputName(
                $inputName
            )->setInputId(
                $this->_getCellInputElementId('<%- _id %>', $columnName)
            )->setColumnName(
                $columnName
            )->setColumn(
                $column
            )->toHtml();
        } elseif ($columnName == 'order_attr') {
            return '<input  data-role="order"  type="hidden" id="' . $this->_getCellInputElementId(
                '<%- _id %>',
                $columnName
            ) .
                '"' .
                ' name="' .
                $inputName .
                '" value="<%- ' .
                $columnName .
                ' %>" ' .
                ($column['size'] ? 'size="' .
                $column['size'] .
                '"' : '') .
                ' class="' .
                (isset($column['class']) ? $column['class'] : 'input-text') . '"' . (isset($column['style']) ?
                    ' style="' . $column['style'] . '"' : '') . '/>';
        }
        return parent::renderCellTemplate($columnName);
    }
}

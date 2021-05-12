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

namespace Sm\AttributesSearch\Model\Config\Backend\Serialized;

/**
 * Class ArraySerialized
 *
 * @package Sm\AttributesSearch\Model\Config\Backend\Serialized
 */
class ArraySerialized extends \Magento\Config\Model\Config\Backend\Serialized
{
    /**
     * @return \Magento\Config\Model\Config\Backend\Serialized
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if (!empty($value)) {
            $temps = [];
            foreach ($value as $key => $temp) {
                if (isset($temp['attributes']) && !in_array($temp['attributes'], $temps)) {
                    array_push($temps, $temp['attributes']);
                } else {
                    unset($value[$key]);
                }
            }
        }
        if (is_array($value)) {
            unset($value['__empty']);
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}

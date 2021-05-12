<?php
/*------------------------------------------------------------------------
# SM Autostore - Version 1.0.0
# Copyright (c) 2016 YouTech Company. All Rights Reserved.
# @license - Copyrighted Commercial Software
# Author: YouTech Company
# Websites: http://www.magentech.com
-------------------------------------------------------------------------*/

namespace Sm\Autostore\Model\Config\Source;

class ListHeaderStyles implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'header-1', 'label' => __('Header Style 1')],
			['value' => 'header-2', 'label' => __('Header Style 2')],
			['value' => 'header-3', 'label' => __('Header Style 3')],
			['value' => 'header-4', 'label' => __('Header Style 4')],
			['value' => 'header-5', 'label' => __('Header Style 5')],
			['value' => 'header-6', 'label' => __('Header Style 6')],
			['value' => 'header-7', 'label' => __('Header Style 7')],
		];
	}
}
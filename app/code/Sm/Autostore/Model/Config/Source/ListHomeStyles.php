<?php
/*------------------------------------------------------------------------
# SM Autostore - Version 1.0.0
# Copyright (c) 2016 YouTech Company. All Rights Reserved.
# @license - Copyrighted Commercial Software
# Author: YouTech Company
# Websites: http://www.magentech.com
-------------------------------------------------------------------------*/

namespace Sm\Autostore\Model\Config\Source;

class ListHomeStyles implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'home-1', 'label' => __('Home Style 1')],
			['value' => 'home-2', 'label' => __('Home Style 2')],
			['value' => 'home-3', 'label' => __('Home Style 3')],
			['value' => 'home-4', 'label' => __('Home Style 4')],
			['value' => 'home-5', 'label' => __('Home Style 5')],
			['value' => 'home-6', 'label' => __('Home Style 6')],
			['value' => 'home-7', 'label' => __('Home Style 7')],
			['value' => 'home-8', 'label' => __('Home Style 8')],
		];
	}
}
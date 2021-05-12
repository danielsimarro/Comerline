<?php
/*------------------------------------------------------------------------
# SM Autostore - Version 1.0.0
# Copyright (c) 2016 YouTech Company. All Rights Reserved.
# @license - Copyrighted Commercial Software
# Author: YouTech Company
# Websites: http://www.magentech.com
-------------------------------------------------------------------------*/

namespace Sm\Autostore\Model\Config\Source;

class ListLayoutStyles implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'full_width', 'label' => __('Full Width')],
			['value' => 'boxed', 'label' => __('Boxed')],
		];
	}
}
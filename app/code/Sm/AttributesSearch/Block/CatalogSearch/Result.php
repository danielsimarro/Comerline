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

namespace Sm\AttributesSearch\Block\CatalogSearch;

/**
 * Class Result
 *
 * @package Sm\AttributesSearch\Block\CatalogSearch
 */
class Result extends \Magento\CatalogSearch\Block\Result
{

    /**
     * Get search query text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getSearchQueryText()
    {
        $mesg = (int)$this->getRequest()->getParam('as') ?
            __("Search results for: '%1'", 'Attributes Search') :
            __("Search results for: '%1'", $this->catalogSearchData->getEscapedQueryText());
        return $mesg;
    }
}

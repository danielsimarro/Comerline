<?php
/**
 *
 * SM Shop By - Version 2.0.0
 * Copyright (c) 2017 YouTech Company. All Rights Reserved.
 * @license - Copyrighted Commercial Software
 * Author: YouTech Company
 * Websites: http://www.magentech.com
 */
 
namespace Sm\ShopBy\Plugin\ShopByBrand;


use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\Page;

class Brand
{
    protected $_resultJsonFactory;

    public function __construct(JsonFactory $resultJsonFactory){
        $this->_resultJsonFactory = $resultJsonFactory;
    }

    public function aroundExecute(\Aheadworks\ShopByBrand\Controller\Brand\View $subject, \Closure $method){
        $response = $method();
        if($response instanceof Page){
            if($subject->getRequest()->getParam('ajax') == 1){
                $subject->getRequest()->getQuery()->set('ajax', null);
                $requestUri = $subject->getRequest()->getRequestUri();
                $requestUri = preg_replace('/(\?|&)ajax=1/', '', $requestUri);
                $subject->getRequest()->setRequestUri($requestUri);
                $productsBlockHtml = $response->getLayout()->getBlock('aw_sbb.brand.products.list');
				$productsBlockHtml = !empty($productsBlockHtml) ? $productsBlockHtml->toHtml() : '';
				$leftNavBlockHtml = $response->getLayout()->getBlock('catalog.leftnav');
				$leftNavBlockHtml = !empty($leftNavBlockHtml) ? $leftNavBlockHtml->toHtml() : '';
                return $this->_resultJsonFactory->create()->setData(['success' => true, 'html' => [
                    'products_list' => $productsBlockHtml,
                    'filters' => $leftNavBlockHtml
                ]]);
            }
        }
        return $response;
    }
}
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

namespace Sm\AttributesSearch\Controller\CatalogSearch\Result;

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\PopularSearchTerms;

/**
 * Class Index
 *
 * @package Sm\AttributesSearch\Controller\CatalogSearch\Result
 */
class Index extends \Magento\CatalogSearch\Controller\Result\Index
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var QueryFactory
     */
    private $queryFactory;

    /**
     * Catalog Layer Resolver
     *
     * @var Resolver
     */
    private $layerResolver;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $pageFactory;

    /**
     * @var \Magento\CatalogSearch\Helper\Data
     */
    private $catalogSearchHelper;

    /**
     * @var PopularSearchTerms
     */
    private $popularSearchTerms;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    private $cacheInterFace;

    /**
     * @var \Magento\Framework\Json\EncoderInterface
     */
    private $jsonEncoder;

    /**
     * Index constructor.
     * @param Context $context
     * @param Session $catalogSession
     * @param StoreManagerInterface $storeManager
     * @param QueryFactory $queryFactory
     * @param Resolver $layerResolver
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param PopularSearchTerms $popularSearchTerms
     * @param \Magento\Framework\App\CacheInterface $cacheInterFace
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     */
    public function __construct(
        Context $context,
        Session $catalogSession,
        StoreManagerInterface $storeManager,
        QueryFactory $queryFactory,
        Resolver $layerResolver,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\CatalogSearch\Helper\Data $catalogSearchHelper,
        PopularSearchTerms $popularSearchTerms,
        \Magento\Framework\App\CacheInterface $cacheInterFace,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder
    ) {
        $this->storeManager = $storeManager;
        $this->queryFactory = $queryFactory;
        $this->layerResolver = $layerResolver;
        $this->pageFactory = $pageFactory;
        $this->catalogSearchHelper = $catalogSearchHelper;
        $this->popularSearchTerms = $popularSearchTerms;
        $this->cacheInterFace = $cacheInterFace;
        $this->jsonEncoder = $jsonEncoder;
        parent::__construct($context, $catalogSession, $storeManager, $queryFactory, $layerResolver);
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        if ($this->getRequest()->getParam('as') == 1) {
			
            $this->layerResolver->create(Resolver::CATALOG_LAYER_SEARCH);
            $code = $this->getRequest()->getParam('code');
            if ($this->getRequest()->getParam('filtersList') == 1 && $this->getRequest()->isAjax() && $code) {
                $list = [];
                $resultPage = $this->pageFactory->create();
                $layout = $resultPage->getLayout();
                $attributesSearch = $layout->createBlock(\Sm\AttributesSearch\Block\AttributesSearch::class);
                $attributesSearch->setTemplate('Sm_AttributesSearch::default_options.phtml');
				$moduleManager = $this->_objectManager->get('\Magento\Framework\Module\Manager');
				$this->cacheInterFace = $moduleManager->isEnabled('Magento_Csp') ? $this->_objectManager->get('Magento\Csp\Model\BlockCache') : $this->cacheInterFace;
                $cacheKey = $attributesSearch->getCacheKey();
                $cacheData = $this->cacheInterFace->load($cacheKey);
                if (!$cacheData) {
                    $leftNavBlock = $layout->getBlock('catalogsearch.leftnav');
                    $list['options'] = $leftNavBlock->getLayer()->getProductCollection()->getFacetedData($code);
                    $list['code'] = $code;
                }
                $result = ['contents' =>
                    (!$cacheData) ? $attributesSearch->setData('attr', $list)->toHtml() : $cacheData
                ];
                return $this->getResponse()->representJson(
                    $this->jsonEncoder->encode($result)
                );
            }
            $query = $this->queryFactory->get();
            $storeId = $this->storeManager->getStore()->getId();
            $query->setStoreId($storeId);
            $queryText = $query->getQueryText();
            $getAdditionalRequestParameters = $this->getRequest()->getParams();
            unset($getAdditionalRequestParameters[QueryFactory::QUERY_VAR_NAME]);

            if (empty($getAdditionalRequestParameters)
                && $this->popularSearchTerms->isCacheable($queryText, $storeId)
            ) {
                $this->getCacheableResult($this->catalogSearchHelper, $query);
            } else {
                $this->getNotCacheableResult($this->catalogSearchHelper, $query);
            }
        } else {
            return parent::execute();
        }
    }

    /**
     * Return cacheable result
     *
     * @param  \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param  \Magento\Search\Model\Query        $query
     * @return void
     */
    private function getCacheableResult($catalogSearchHelper, $query)
    {
        if (!$catalogSearchHelper->isMinQueryLength()) {
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }

    /**
     * Return not cacheable result
     *
     * @param  \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param  \Magento\Search\Model\Query        $query
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getNotCacheableResult($catalogSearchHelper, $query)
    {
		//var_dump( $query->setId(0)->setIsActive(1)->setIsProcessed(1));die('getNotCacheableResult');
        if ($catalogSearchHelper->isMinQueryLength()) {
            $query->setId(0)->setIsActive(1)->setIsProcessed(1);
        } else {
            $query->saveIncrementalPopularity();
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout();
        $this->getResponse()->setNoCacheHeaders();
        $this->_view->renderLayout();
    }
}

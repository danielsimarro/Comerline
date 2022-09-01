<?php

namespace Comerline\ImprovedMegaMenu\Controller\Render;

use Comerline\ImprovedMegaMenu\Extended\View;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\State;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\View\Result\PageFactory;


class ToFile implements HttpGetActionInterface
{
    private PageFactory $_resultPageFactory;
    private RawFactory $_resultRawFactory;
    private DirectoryList $_directoryList;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        RawFactory $resultRawFactory,
        DirectoryList $directoryList
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultRawFactory = $resultRawFactory;
        $this->_directoryList = $directoryList;
    }

    /**
     * @return Raw
     */
    public function execute()
    {
        $result = $this->_resultRawFactory->create();
        $resultPage = $this->_resultPageFactory->create();
        $block = $resultPage->getLayout()
            ->createBlock('Sm\MegaMenu\Block\MegaMenu\View')
            ->setData('cache_lifetime', null)
            ->setData(View::FULL_LOAD, true)
            ->setTemplate('Sm_MegaMenu::megamenu-horizontal.phtml')
            ->toHtml();

        //Write to file.
        $path = $this->_directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        if (file_put_contents($path . '/' . View::FILE, $block) === false) {
            $result->setHttpResponseCode(599);
        } else {
            $result->setHttpResponseCode(204);
        }
        return $result;
    }
}

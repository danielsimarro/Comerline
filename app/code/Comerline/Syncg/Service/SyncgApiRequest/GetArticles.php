<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\SyncgStatusFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class GetArticles extends SyncgApiService
{

    protected $method = Request::HTTP_METHOD_GET;

    /**
     * @var Config
     */
    protected $config;

    private $syncgStatus;

    private $syncgStatusCollectionFactory;

    private $syncgStatusFactory;

    private $syncgStatusRepository;

    private $order;

    private $productCollectionFactory;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger,
        SyncgStatus $syncgStatus,
        CollectionFactory $syncgStatusCollectionFactory,
        SyncgStatusFactory $syncgStatusFactory,
        SyncgStatusRepository $syncgStatusRepository,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->config = $configHelper;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->syncgStatusFactory = $syncgStatusFactory;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $fields = [
            'campos' => json_encode(array("nombre", "ref_fabricante", "descripcion", "pvp1", "modelo", "si_vender_en_web")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "descripcion", "valor" => "BLACK MIRROR32", "tipo" => 0)
                )
            )),
            'orden' => json_encode(array("campo" => "id", "orden" => "ASC"))
        ];
        $this->endpoint = $this->config->getGeneralConfig('database_id') . '/articulos/catalogo?' . http_build_query($fields);
        $this->order = $fields['orden']; // We will need this to get the products correctly
    }

    public function send()
    {
        $loop = true; // Variable to check if we need to break the loop or keep on it
        $start = 0; // Counter to check from which page we start the query
        $pages = []; // Array where we will store the items, ordered in pages
        while ($loop){
            $this->buildParams($start);
            $response = $this->execute();
            if($response['listado']){
                $pages[] = $response['listado'];
                if (strpos($this->order, 'ASC')){
                    $start = intval($response['listado'][count($response['listado']) - 1]['id'] + 1);// If orden is ASC, the first item that the API gives us
                    // is the first, so we get it for the next query, and we add 1 to avoid duplicating that item

                } else {
                    $start = intval($response['listado'][0]['id']) + 1; // If orden is not ASC, the first item that the API gives us is the one with highest ID,
                    // so we get it for the next query, and we add 1 to avoid duplicating that item
                }
            } else {
                $loop = false;  // If $response['listado'] is empty, we end the while loop
            }
        }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of Object Manager. This is temporal, and will change in the near future
        if ($pages) {
//            $collectionSyncg = $this->syncgStatusCollectionFactory->create();
            $count = 0;
            foreach ($pages as $page){
                for ($i = 0; $i < count($page); $i++){
//                    $count++;
                    $productCollection = $this->productCollectionFactory->create()
                        ->addAttributeToFilter('sku', $page[$i]['ref_fabricante']);// We check if the SKU exists and, as a result, the product
                        foreach ($productCollection as $ps) {
                            if ($ps) {
                                $ps->setSku($page[$i]['ref_fabricante']);
                                $ps->setName($page[$i]['descripcion']);
                                $ps->setAttributeSet($page[$i]['ref_fabricante']);
                                if ($page[$i]['si_vender_en_web'] === true) {
                                    $ps->setStatus(1);
                                } else {
                                    $ps->setStatus(0);
                                }
                                $ps->setTaxClassId(0);
                                $ps->setTypeId('simple');
                                $ps->setPrice($page[$i]['pvp1']);
                                $ps->save();
                            } else {
                                $product = $objectManager->create('\Magento\Catalog\Model\Product');
                                $product->setSku($page[$i]['ref_fabricante']);
                                $product->setName($page[$i]['descripcion']);
                                $product->setAttributeSet($page[$i]['ref_fabricante']);
                                if ($page[$i]['si_vender_en_web'] === true) {
                                    $product->setStatus(1);
                                } else {
                                    $product->setStatus(0);
                                }
                                $product->setTaxClassId(0);
                                $product->setTypeId('simple');
                                $product->setPrice($page[$i]['pvp1']);
                                $product->save();
                            }
                        }
//                    $name = $page[$i]['descripcion'];
//                    $price = $page[$i]['pvp1'];
//                    $code = $page[$i]['cod'];
//                    $gId = $page[$i]['id'];
//                    $model = $page[$i]['modelo'];
//                    $this->syncgStatusRepository->updateEntityStatus($count, $gId, SyncgStatus::TYPE_PRODUCT, SyncgStatus::STATUS_COMPLETED); // Count used as a temporal solution, not final
                }
            }
        }
    }
}

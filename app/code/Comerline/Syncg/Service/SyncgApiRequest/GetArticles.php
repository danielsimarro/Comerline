<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use GuzzleHttp\ClientFactory;
use Comerline\Syncg\Helper\AttributeHelper;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory as ProductFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Filesystem\DirectoryList;
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

    /**
     * @var SyncgStatus
     */
    private $syncgStatus;

    /**
     * @var CollectionFactory
     */
    private $syncgStatusCollectionFactory;

    /**
     * @var SyncgStatusRepository
     */
    private $syncgStatusRepository;

    /**
     * @var
     */
    private $order;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @var AttributeSetFactory
     */
    private $attributeSetFactory;

    private $attributeHelper;

    private $dir;

    public function __construct(
        Config $configHelper,
        Json $json,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger,
        SyncgStatus $syncgStatus,
        CollectionFactory $syncgStatusCollectionFactory,
        SyncgStatusRepository $syncgStatusRepository,
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductResource $productResource,
        AttributeSetFactory $attributeSetFactory,
        AttributeHelper $attributeHelper,
        DirectoryList $dir
    ) {
        $this->config = $configHelper;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productResource = $productResource;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->attributeHelper = $attributeHelper;
        $this->dir = $dir;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $fields = [
            'campos' => json_encode(array("nombre", "ref_fabricante", "descripcion", "pvp1", "modelo", "si_vender_en_web")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "descripcion", "valor" => "Separador simple de 5mm espesor as", "tipo" => 0)
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
        $start = 256; // Counter to check from which page we start the query
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
            $productRelateds =  [];
            foreach ($pages as $page){
                for ($i = 0; $i < count($page); $i++){
                    $attributeSetId = null;  // Variable where we will store the attribute set ID
                    $collectionSyncg = $this->syncgStatusCollectionFactory->create()
                        ->addFieldToFilter('g_id', $page[$i]['cod']); // We check if the product already exists
                    $attributeSetCollectionFactory = $objectManager->get('Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory'); // Temporary use of object manager, this should be a Dependency Injection
                    $attributeSetCollection = $attributeSetCollectionFactory->create();
                    $attributeSets = $attributeSetCollection->getItems();
                    foreach ($attributeSets as $attributeSet) {
                        if ($attributeSet->getAttributeSetName() === $page[$i]['familias'][0]['nombre']) { // If the name of the attribute set is the same as the one on G4100...
                             $attributeSetId = $attributeSet->getAttributeSetId(); // We save the ID to use it later
                        }
                    }
                    if ($collectionSyncg->getSize() > 0) {
                        foreach ($collectionSyncg as $itemSyncg) {
                            $product_id = $itemSyncg->getData('mg_id');
                            $product = $this->productRepository->getById($product_id, true);
                            $product->setStoreId(0);
                            $product->setTaxClassId(0);
                            $product->setAttributeSetId($attributeSetId);
                            $this->insertAttributes($page[$i]['tp_2'][0], $product);
                            $product->setTypeId('simple');
                            $product->setPrice(188);
                            $product->setSku($page[$i]['ref_fabricante']);
                            $product->setName($page[$i]['descripcion']);
                            if ($page[$i]['si_vender_en_web'] === true) {
                                $product->setStatus(1);
                            } else {
                                $product->setStatus(0);
                            }
                            $this->productResource->save($product);
                        }
                    } else {
                        $product = $this->productFactory->create();
                        $product->setStoreId(0);
                        $product->setTaxClassId(0);
                        $product->setAttributeSetId($attributeSetId);
                        $product->setTypeId('simple');
                        $this->insertAttributes($page[$i]['tp_2'][0], $product);
                        $product->setPrice($page[$i]['pvp1']);
                        $product->setSku($page[$i]['ref_fabricante']);
                        $product->setName($page[$i]['descripcion']);
                        if ($page[$i]['si_vender_en_web'] === true) {
                            $product->setStatus(1);
                        } else {
                            $product->setStatus(0);
                        }
                        $product->save();
                    }
                        if ($page[$i]['relacionados']){ // If the product has related products we get it's ID and save it on an array to work later with it
                            $productRelateds['id'] = $product->getEntityId();
                            foreach ($page[$i]['relacionados'] as $r){
                                $productRelateds['related'] .= $r['cod'] . ', ';
                            }
                        }
                        $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $page[$i]['cod'], SyncgStatus::TYPE_PRODUCT, 1);
//                        $this->getImages($page[$i], $product);  Commented at the moment because this is not working as intended
                }
            }

            foreach ($productRelateds as $pr)
            {
                $product = $this->productRepository->getById($pr, true);
            }
        }
    }

    public function insertAttributes($attributes, $product)
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $thickness = $attributes['c_11'];
            if ($thickness !== "") {
                $thicknessId = $this->attributeHelper->createOrGetId('thickness', $thickness);
                $product->setCustomAttribute('thickness', $thicknessId);
            }
            $type = $attributes['c_10'];
            if ($type !== "") {
                $typeId = $this->attributeHelper->createOrGetId('type', $type);
                $product->setCustomAttribute('type', $typeId);
            }
            $diameter = $attributes['c_5'];
            if ($diameter !== "") {
                $diameterId = $this->attributeHelper->createOrGetId('diameter', $diameter);
                $product->setCustomAttribute('diameter', $diameterId);
            }
            $size = $attributes['c_4'];
            if ($size !== "") {
                $sizeId = $this->attributeHelper->createOrGetId('size', $size);
                $product->setCustomAttribute('size', $sizeId);
            }
            $mounting = $attributes['c_9'];
            if ($mounting !== "") {
                $mountingId = $this->attributeHelper->createOrGetId('mounting', $mounting);
                $product->setCustomAttribute('mounting', $mountingId);
            }
            $color = $attributes['c_8'];
            if ($color !== "") {
                $colorId = $this->attributeHelper->createOrGetId('color', $color);
                $product->setCustomAttribute('color', $colorId);
            }
        }
    }

// Commented at the moment because this is not working as intended


//    public function getImages($article, $product)
//    {
//        if($article['imagenes'])
//        {
//            $baseUrl = $this->config->getGeneralConfig('installation_url') . $this->config->getGeneralConfig('database_id');
//            $user = $this->config->getGeneralConfig('email');
//            $pass = $this->config->getGeneralConfig('user_key');
//
//            $ch = curl_init();
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//            curl_setopt($ch, CURLOPT_COOKIESESSION, true);
//            curl_setopt($ch, CURLOPT_COOKIEJAR, 'session');
//
//            curl_setopt($ch, CURLOPT_URL, $baseUrl);
//            $result = curl_exec($ch);
//            $json = json_decode($result, true);
//
//            curl_setopt($ch, CURLOPT_URL, $baseUrl . "/?usr=" . $user . "&clave=" . md5($pass . $json['llave']));
//            curl_exec($ch);
//            foreach ($article['imagenes'] as $image) {
//                $ch = curl_setopt($ch, CURLOPT_URL, $baseUrl . '/imagenes/' . $image);
//                $fp = fopen($this->dir->getPath('media') . '/images/' . $image . '.png', 'wb');
//                curl_setopt($ch, CURLOPT_FILE, $fp);
//                curl_exec($ch);
//                curl_close($ch);
//                fclose($fp);
//                $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $image, SyncgStatus::TYPE_IMAGE, 1);
//            }
//        }
//    }
}

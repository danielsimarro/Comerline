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
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory as ProductFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
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
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->config = $configHelper;
        $this->syncgStatus = $syncgStatus;
        $this->syncgStatusCollectionFactory = $syncgStatusCollectionFactory;
        $this->syncgStatusRepository = $syncgStatusRepository;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productResource = $productResource;
        $this->attributeSetFactory = $attributeSetFactory;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $fields = [
            'campos' => json_encode(array("nombre", "ref_fabricante", "descripcion", "pvp1", "modelo", "si_vender_en_web")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "descripcion", "valor" => "BLACK DIAMOND", "tipo" => 0)
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
                    if ($attributeSetId === null) { // If at this point $attributeSetId remains as null, it means we have to create the attribute set
                        $createAttributeSet = $this->attributeSetFactory->create();
                        $entityType = $objectManager->create('Magento\Eav\Model\Entity\Type')->loadByCode('catalog_product');
                        $defaultSetId = $objectManager->create('Magento\Catalog\Model\Product')->getDefaultAttributeSetid();
                        $data = [ // Data that we will use to create the Attribute Set
                            'attribute_set_name' => $page[$i]['familias'][0]['nombre'],
                            'entity_type_id' => $entityType->getId(),
                            'sort_order' => 200,
                        ];
                        $createAttributeSet->setData($data);
                        $createAttributeSet->validate(); // We need to validate it, or else the Attribute Set will be created but not available to select
                        $createAttributeSet->save();
                        $createAttributeSet->initFromSkeleton($defaultSetId); // We initialize the Attribute Set to make it visible on the frontend
                        $createAttributeSet->save();
                        $attributeSetId = $createAttributeSet->getAttributeSetId(); // We save the ID to use it later
                    }
                    if ($collectionSyncg->getSize() > 0) {
                        foreach ($collectionSyncg as $itemSyncg) {
                            $product_id = $itemSyncg->getData('mg_id');
                            $product = $this->productRepository->getById($product_id, true);
                            $product->setStoreId(0);
                            $product->setTaxClassId(0);
                            $product->setAttributeSetId($attributeSetId);
                            $product->setTypeId('simple');
                            $product->setPrice(188);
                            $product->setSku($page[$i]['ref_fabricante']);
                            $product->setAttributeSet($attributeSetId);
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
                        $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $page[$i]['cod'], SyncgStatus::TYPE_PRODUCT, 1);
                }
            }
        }
    }
}

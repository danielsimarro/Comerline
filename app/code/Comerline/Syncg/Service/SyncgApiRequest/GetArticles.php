<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use GuzzleHttp\ClientFactory;
use Comerline\Syncg\Helper\AttributeHelper;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory as ProductFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Phrase;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavModel;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ObjectManager;
use DateTimeZone;
use DateInterval;
use Safe\DateTime;

class GetArticles extends SyncgApiService
{

    protected $method = Request::HTTP_METHOD_POST;

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

    /**
     * @var AttributeHelper
     */
    private $attributeHelper;

    /**
     * @var DirectoryList
     */
    private $dir;

    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var AttributeCollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * @var Category
     */
    protected $category;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Configurable
     */
    protected $configurable;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Processor
     */
    protected $imageProcessor;

    /**
     * @var EavModel
     */
    private $eavModel;

    private $categories;

    public function __construct(
        Config                     $configHelper,
        Json                       $json,
        ClientFactory              $clientFactory,
        ResponseFactory            $responseFactory,
        LoggerInterface            $logger,
        SyncgStatus                $syncgStatus,
        CollectionFactory          $syncgStatusCollectionFactory,
        SyncgStatusRepository      $syncgStatusRepository,
        ProductFactory             $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductResource            $productResource,
        AttributeSetFactory        $attributeSetFactory,
        AttributeHelper            $attributeHelper,
        DirectoryList              $dir,
        CategoryCollectionFactory  $categoryCollectionFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        Category                   $category,
        CategoryFactory            $categoryFactory,
        StoreManagerInterface      $storeManager,
        Configurable               $configurable,
        Processor                  $imageProcessor,
        EavModel                   $eavModel
    )
    {
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
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->category = $category;
        $this->categoryFactory = $categoryFactory;
        $this->storeManager = $storeManager;
        $this->configurable = $configurable;
        $this->logger = $logger;
        $this->imageProcessor = $imageProcessor;
        $this->eavModel = $eavModel;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $coreConfigData = $this->config->getParamsWithoutSystem('syncg/general/last_date_sync_products')->getValue(); // We get the last sync date

        $timezone = new DateTimeZone('Europe/Madrid');
        $date = new DateTime($coreConfigData, $timezone);
        $hours = $date->getOffset() / 3600; // We have to add the offset, since the date from the API comes in CEST
        $newDate = $date->add(new DateInterval(("PT{$hours}H")));

        $this->endpoint = 'api/g4100/list';
        $this->params = [
            'allow_redirects' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->getTokenFromDatabase()}",
            ],
            'body' => json_encode([
                'endpoint' => 'articulos/catalogo',
                'fields' => json_encode(["nombre", "ref_fabricante", "fecha_cambio", "borrado", "ref_proveedor", "descripcion",
                    "desc_detallada", "envase", "frente", "fondo", "alto", "peso", "diametro", "diametro2", "pvp1", "pvp2", "precio_coste_estimado", "modelo",
                    "si_vender_en_web", "existencias_globales", "grupo", "acotacion", "marca"]),
                'filters' => json_encode([
                    "inicio" => $start,
                    "filtro" => [
                        ["campo" => "si_vender_en_web", "valor" => "1", "tipo" => 0],
                        ["campo" => "fecha_cambio", "valor" => $newDate->format('Y-m-d H:i'), "tipo" => 3]
                    ]
                ]),
                'order' => json_encode(["campo" => "id", "orden" => "ASC"])
            ]),
        ];
        $decoded = json_decode($this->params['body']);
        $decoded = (array)$decoded;
        $this->order = $decoded['order']; // We will need this to get the products correctly
    }

    public function send()
    {
        $loop = true; // Variable to check if we need to break the loop or keep on it
        $start = 0; // Counter to check from which page we start the query
        $pages = []; // Array where we will store the items, ordered in pages
        $this->logger->info(new Phrase('G4100 Sync | Fetching products'));
        while ($loop) {
            $this->buildParams($start);
            $response = $this->execute();
            if ($response !== null && array_key_exists('listado', $response)) {
                if ($response['listado']) {
                    $pages[] = $response['listado'];
                    if (strpos($this->order, 'ASC')) {
                        $start = intval($response['listado'][count($response['listado']) - 1]['id'] + 1);// If orden is ASC, the first item that the API gives us
                        // is the first, so we get it for the next query, and we add 1 to avoid duplicating that item
                    } else {
                        $start = intval($response['listado'][0]['id']) + 1; // If orden is not ASC, the first item that the API gives us is the one with highest ID,
                        // so we get it for the next query, and we add 1 to avoid duplicating that item
                    }
                } else {
                    $loop = false;  // If $response['listado'] is empty, we end the while loop
                }
            } else {
                $loop = false;
                $this->logger->error(new Phrase('G4100 Sync | Error fetching products.'));
            }
        }
        $this->logger->info(new Phrase('G4100 Sync | Fetching products successful.'));
        $objectManager = ObjectManager::getInstance(); // Instance of Object Manager. We need it for some of the operations that didn't work with dependency injection
        if ($pages) {
            $attributeSetId = "";  // Variable where we will store the attribute set ID
            $relatedProducts = []; // Array where we will store the products that have related products
            $relatedAttributes = []; // Array where we will store the attributes that are related
            $relatedProductsSons = []; // Array where we will store the related products
            $this->categories = $this->getMagentoCategories();
            foreach ($pages as $page) {
                for ($i = 0; $i < count($page); $i++) { //We navigate through the products in a page
                    if ($this->checkRequiredData($page[$i])) {
                        if ($page[$i]['descripcion'] !== 'Tasa Envío') {
                            $collectionSyncg = $this->syncgStatusCollectionFactory->create()
                                ->addFieldToFilter('g_id', $page[$i]['cod']); // We check if the product already exists
                            if (array_key_exists('familias', $page[$i])) { // We check if the product has an attribute set. If it does, then checks what it is
                                $attributeSetCollection = $this->attributeCollectionFactory->create();
                                $attributeSets = $attributeSetCollection->getItems();
                                foreach ($attributeSets as $attributeSet) {
                                    if ($attributeSet->getAttributeSetName() === $page[$i]['familias'][0]['nombre']) { // If the name of the attribute set is the same as the one on G4100...
                                        $attributeSetId = $attributeSet->getAttributeSetId(); // We save the ID to use it later
                                    }
                                }
                            }
                            if ($collectionSyncg->getSize() > 0) { // If the product already exists, that means we only have to update it
                                foreach ($collectionSyncg as $itemSyncg) {
                                    $productId = $itemSyncg->getData('mg_id');
                                    $product = $this->productRepository->getById($productId, true); // We load the product in edit mode
                                    $this->createUpdateProduct($product, $page, $attributeSetId, $i);
                                    $this->productRepository->save($product);
                                    $this->logger->info(new Phrase('G4100 Sync | [G4100 Product: ' . $page[$i]['cod'] . '] | [Magento Product: ' . $productId . '] | EDITED.'));
                                }
                            } else {
                                $product = $this->productFactory->create(); // If the product doesn't exists, we create it
                                $this->createUpdateProduct($product, $page, $attributeSetId, $i);
                                $product->save();
                                $this->logger->info(new Phrase('G4100 Sync | [G4100 Product: ' . $page[$i]['cod'] . '] | [Magento Product: ' . $product->getId() . '] | CREATED.'));
                            }
                            if (array_key_exists('relacionados', $page[$i])) { // If the product has related products we get it's ID and save it on an array to work later with it
                                $productId = $product->getId();
                                $magentoId[] = $this->createSimpleProduct($page, $i, $attributeSetId, $productId); // We get the ID since we will create a duplicate of this product to avoid losing options
                                $relatedProducts[] = $product->getEntityId();
                                foreach ($page[$i]['relacionados'] as $r) {
                                    if ($r['cod'] !== $page[$i]['cod']) { // If the related product is the same as the one we created, we skip it, since we already have duplicated it as a simple product
                                        $relatedProductsSons[$product->getEntityId()][] = $r['cod'];
                                        $relatedAttributes[$product->getEntityId()] = $this->getAttributesIds($page[$i]);
                                    }
                                }
                            }
                            $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $page[$i]['cod'], SyncgStatus::TYPE_PRODUCT, SyncgStatus::STATUS_COMPLETED);
                            $this->getImages($page[$i], $product);
                        }
                    } else {
                        $this->logger->error(new Phrase('G4100 Sync | [G4100 Product: ' . $page[$i]['cod'] . '] | PRODUCT NOT VALID.'));
                    }
                }
            }

            foreach ($relatedProducts as $rp) {
                $product = $this->productRepository->getById($rp);
                if (array_key_exists($rp, $relatedAttributes)) {
                    $attributes = $relatedAttributes[$rp]; // Array with the attributes we want to make configurable
                    $attributeModels = [];
                    $count = 0;
                    foreach ($attributes as $attributeId) {
                        $attributeModel = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute');
                        $eavModel = $this->eavModel;
                        $attr = $eavModel->load($attributeId);
                        $data = [
                            'attribute_id' => $attributeId,
                            'product_id' => $product->getId(),
                            'position' => strval($count),
                            'sku' => $product->getSku(),
                            'label' => $attr->getData('frontend_label')
                        ];
                        $count++;
                        $new = $attributeModel->setData($data);
                        array_push($attributeModels, $new);
                        try {
                            $attributeModel->setData($data)->save(); // We create the attribute model
                        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
                            $this->logger->error('G4100 Sync | Attribute model already exists. Skipping creation....'); // If the attribute model already exists, it throws an exception,
                        }                                                       // so we need to catch it to avoid execution from stopping
                    }
                    $product->load('media_gallery');
                    $product->setTypeId("configurable"); // We change the type of the product to configurable
                    $product->setAttributeSetId($product->getData('attribute_set_id'));
                    $this->configurable->setUsedProductAttributeIds($attributes, $product);
                    $product->setNewVariationsAttributeSetId(intval($product->getData('attribute_set_id')));
                    $relatedIds = $this->getRelatedProductsIds($rp, $relatedProductsSons, $magentoId);
                    $extensionConfigurableAttributes = $product->getExtensionAttributes();
                    $extensionConfigurableAttributes->setConfigurableProductLinks($relatedIds); // Linking by ID the products that are related to this configurable
                    $extensionConfigurableAttributes->setConfigurableProductOptions($attributeModels); // Linking the options that are configurable
                    $product->setExtensionAttributes($extensionConfigurableAttributes);
                    $product->setCanSaveConfigurableAttributes(true);
                    try {
                        $this->productRepository->save($product);
                        $this->logger->info(new Phrase('G4100 Sync | [Magento Product: ' . $rp . '] | CHANGED TO CONFIGURABLE.'));
                        $this->setRelatedsVisibility($relatedIds); // We need to make the simple products related to this one hidden
                    } catch (InputException $e) {
                        $this->logger->error(new Phrase('G4100 Sync | [Magento Product: ' . $rp . "] | ERROR CHANGING TO CONFIGURABLE."));
                    }
                } else {
                    $this->logger->error(new Phrase('G4100 Sync | [Magento Product: ' . $rp . "] | CAN'T CHANGE TO CONFIGURABLE (ONLY PARENT OPTION AVAILABLE)."));
                }
            }
        }
    }

    public function checkRequiredData($product): bool
    {
        $valid = false;
        $requiredKeys = ['descripcion', 'existencias_globales', 'modelo', 'marca', 'cod', 'id'];
        $references = [
            'ref_proveedor' => $product['ref_proveedor'],
            'ref_fabricante' => $product['ref_fabricante']
        ];
        $validFields = 0;
        foreach ($requiredKeys as $rk) {
            if (isset($product[$rk]) && ($product[$rk] !== "")) {
                $validFields++;
            }
        }
        if ($references['ref_proveedor'] !== "" || $references['ref_fabricante'] !== "") {
            $validFields++;
        }
        if ($validFields === 7) {
            $valid = true;
        }
        return $valid;
    }

    public function createSimpleProduct($page, $i, $attributeSetId, $productId)
    {
        $simpleProduct[$i] = $page[$i];
        unset($simpleProduct[$i]['relacionados']); // We remove related products as we don't need them
        $sku = "";
        if ($simpleProduct[$i]['ref_fabricante'] !== "") {
            $sku = $simpleProduct[$i]['ref_fabricante'] .= '-' . $simpleProduct[$i]['id']; // We add the ID to the SKU to avoid errors
        } else {                                                                           // If ref_fabricante is empty, we use ref_proveedor
            $sku = $simpleProduct[$i]['ref_proveedor'] .= '-' . $simpleProduct[$i]['id'];
        }
        $originalCod = $simpleProduct[$i]['cod'];
        $simpleProduct[$i]['cod'] .= '-' . $simpleProduct[$i]['id']; // We add the ID to the code to avoid errors
        try {
            $product = $this->productRepository->get($sku); // If the SKU exists, we load the product
        } catch (NoSuchEntityException $e) {
            $product = false; // Otherwise, we have to create it
        }
        if ($product) {
            $this->createUpdateProduct($product, $simpleProduct, $attributeSetId, $i);
            $this->productResource->save($product);
            $this->logger->info(new Phrase('G4100 Sync | [Magento Product: ' . $product->getId() . '] | EDITED.'));
        } else {
            $product = $this->productFactory->create();
            $this->createUpdateProduct($product, $simpleProduct, $attributeSetId, $i);
            $product->save();
            $this->logger->info(new Phrase('G4100 Sync | [G4100 Product: ' . $simpleProduct[$i]['cod'] . '] | [Magento Product: ' . $product->getId() . '] | CREATED.'));
        }
        $this->getImages($simpleProduct[$i], $product);
        $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $originalCod, SyncgStatus::TYPE_PRODUCT, SyncgStatus::STATUS_COMPLETED);
        $magentoId = []; // Here we will store the Magento ID of the new product, to use it later
        $magentoId[$productId] = $product->getId();
        return $magentoId;
    }

    public function createUpdateProduct($product, $page, $attributeSetId, $i)
    {
        $categoryIds = [];
        $id = $page[$i]['id'];
        $manufacturerRef = $page[$i]['ref_fabricante'];
        $manufacturerRefPlusId = $manufacturerRef . '-' . $id;
        $vendorRef = $page[$i]['ref_proveedor'];
        $vendorRefPlusId = $vendorRef . '-' . $id;
        if ($manufacturerRef !== "") {
            if ($product->getSku() !== $manufacturerRef && $product->getSku() !== $manufacturerRefPlusId) {
                $product->setSku($manufacturerRef);
            }
        } else {
            if ($product->getSku() !== $vendorRef && $product->getSku() !== $vendorRefPlusId) {
                $product->setSku($vendorRef);
            }
        }
        $product->setName($page[$i]['descripcion']);
        $product->setStoreId(0);
        $product->setAttributeSetId($attributeSetId);
        $url = strtolower(str_replace(" ", "-", $page[$i]['descripcion']));
        if (!(array_key_exists('relacionados', $page[$i]))) {
            $this->insertAttributes($page[$i], $product);
            $url .= '-' . $id;
        }
        if ($product->getUrlKey() === null) {
            $product->setUrlKey($url);
        }
        if ($page[$i]['si_vender_en_web'] === true) {
            $product->setStatus(1);
        } else {
            $product->setStatus(0);
        }
        $product->setTaxClassId(0);
        if ($product->getTypeId() === 'configurable') {
            $oldRelateds = [];
            $childrenProducts = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($childrenProducts as $cp) {
                $oldRelateds[] = $cp->getID();
            }
            $this->setRelatedsVisibility($oldRelateds, true);
        } else {
            $parentProduct = $this->configurable->getParentIdsByChild($product->getId());
            if (isset($parentProduct[0])) {
                $product->setVisibility(1);
            } else {
                $product->setVisibility(4);
            }
        }
        $product->setTypeId('simple');
        if ($page[$i]['pvp1'] !== "0.00") {
            $product->setPrice($page[$i]['pvp1']);
        } else {
            $product->setPrice($page[$i]['pvp2']);
        }
        $product->setCost($page[$i]['precio_coste_estimado']);
        $product->setWebsiteIds([1]);
        $product->setCustomAttribute('tax_class_id', 2);
        $product->setCustomAttribute('g4100_id', $page[$i]['cod']);
        $product->setWeight($page[$i]['peso']);
        $product->setDescription($page[$i]['desc_detallada']);
        if (array_key_exists('familias', $page[$i])) {
            if (array_key_exists($page[$i]['familias'][0]['nombre'], $this->categories)) {
                array_push($categoryIds, $this->categories[$page[$i]['familias'][0]['nombre']]);
            } else {
                array_push($categoryIds, $this->createCategory($page[$i]['familias'][0]['nombre']));
            }
        }
        if (isset($page[$i]['marca']) && array_key_exists($page[$i]['marca'], $this->categories)) {
            array_push($categoryIds, $this->categories[$page[$i]['marca']]);
        } else {
            array_push($categoryIds, $this->createCategory($page[$i]['marca']));
        }
        $product->setCategoryIds($categoryIds);
        $stock = 0;
        if ($page[$i]['existencias_globales'] > 0) { // If there are no existences, that means there is no stock
            $stock = 1;
        }
        $product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'is_in_stock' => $stock,
                'qty' => $page[$i]['existencias_globales']
        ]);
    }

    public function getCategoryIds($categoryName)
    {
        $categoryId = [];
        $categoryCollection = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', $categoryName); // We check if the category exists
        if ($categoryCollection->getSize()) {
            $categoryId[] = $categoryCollection->getFirstItem()->getId(); // If it does, we get the ID of the category
        }
        return $categoryId;
    }

    public function createCategory($name)
    {
        $parentId = $this->storeManager->getStore()->getRootCategoryId();

        $parentCategory = $this->categoryFactory->create()->load($parentId);
        $category = $this->categoryFactory->create();
        $category->setName($name);
        $category->setIsActive(true);
        $category->setParentId($parentId);
        $category->setPath($parentCategory->getPath());
        $category->save();
        $this->categories[$category->getName()] = $category->getId();

        return $category->getId();
    }


    public function getMagentoCategories()
    {
        $categories = [];
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect('*');
        foreach ($categoryCollection as $c) {
            $categories[$c->getData('name')] = $c->getData('entity_id');
        }
        return $categories;
    }

    public function getRelatedProductsIds($rp, $relatedProductsSons, $magentoId)
    {
        $related = [];
        $arrayCombination = [];
        foreach ($relatedProductsSons[$rp] as $son) {
            $collectionSon = $this->syncgStatusCollectionFactory->create()
                ->addFieldToFilter('g_id', $son); // We get the IDs that are equal to the one we passed form comerline_syncg_status
            foreach ($collectionSon as $itemSon) {
                $productSon = $this->productRepository->getById($itemSon->getData('mg_id')); // For each of them, we save the Magento ID to use it later
                $options = $productSon->getData('width') . $productSon->getData('load') . $productSon->getData('hub') .
                    $productSon->getData('diameter') . $productSon->getData('mounting') . $productSon->getData('offset');
                if (!in_array($options, $arrayCombination) && $itemSon->getData('mg_id') !== $rp) {
                    $related[] = $itemSon->getData('mg_id'); // For each of them, we save the Magento ID to use it later
                    $arrayCombination[] = $options;
                    $this->logger->info(new Phrase('G4100 Sync | [G4100 Product: ' . $itemSon->getData('g_id')
                        . '] | [Magento Product: ' . $itemSon->getData('mg_id') . '] | RELATED TO CONFIGURABLE | [' . 'Magento Product: ' . $rp . '].'));
                }
            }
        }
        foreach ($magentoId as $id) {
            if (array_key_exists($rp, $id)) {
                $related[] = $id[$rp]; // We also add $magentoId, as we need it
                $this->logger->info(new Phrase('G4100 Sync | [Magento Product: ' . $id[$rp]
                    . '] | RELATED TO CONFIGURABLE | [' . 'Magento Product: ' . $rp . '].'));
            }
        }

        return $related;
    }

    public function setRelatedsVisibility($related, $current = null)
    {
        foreach ($related as $r) {
            $product = $this->productRepository->getById($r);
            $product->load('media_gallery');
            if ($current) {
                $product->setVisibility(4);
            } else {
                $product->setVisibility(1);
            }
            $this->productRepository->save($product);
        }
    }

    public function getAttributesIds($attributes)
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $code = []; // Array where we will store the attributes IDs
            $width = $attributes['frente'];
            if ($width !== "" && $width !== "0.00") {
                $code[] = $this->attributeHelper->getAttribute('width')->getAttributeId(); // With this helper we get the ID of the desired attribute
            }
            $offset = $attributes['fondo'];
            if ($offset !== "" && $offset !== "0.00") {
                $code[] = $this->attributeHelper->getAttribute('offset')->getAttributeId();
            }
            $diameter = $attributes['diametro'];
            if ($diameter !== "" && $diameter !== "0.00") {
                $code[] = $this->attributeHelper->getAttribute('diameter')->getAttributeId();
            }
            $hub = $attributes['alto'];
            if ($hub !== "") {
                $code[] = $this->attributeHelper->getAttribute('hub')->getAttributeId();
            }
            $mounting = $attributes['envase'];
            if ($mounting !== "" && $mounting !== "0.00") {
                $code[] = $this->attributeHelper->getAttribute('mounting')->getAttributeId();
            }
            $load = $attributes['diametro2'];
            if ($load !== "" && $load !== "0.00") {
                $code[] = $this->attributeHelper->getAttribute('load')->getAttributeId();
            }
            $variation = $attributes['acotacion'];
            if ($variation !== "") {
                $code[] = $this->attributeHelper->getAttribute('variation')->getAttributeId();
            }
        }
        return $code;
    }

    public function insertAttributes($attributes, $product)
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $width = $attributes['frente'];
            if ($width !== "" && $width !== "0.00") {
                $width .= "cm";
                $widthId = $this->attributeHelper->createOrGetId('width', $width);
                $product->setCustomAttribute('width', $widthId);
            } else {
                $product->setCustomAttribute('width', '');      // We do this to put it on empty in case that is what we get from the API
            }                                                  // Useful when we delete an attribute, this sets it to empty
            $offset = $attributes['fondo'];
            if ($offset !== "" && $offset !== "0.00") {
                $offset .= "mm";
                $offsetId = $this->attributeHelper->createOrGetId('offset', $offset);
                $product->setCustomAttribute('offset', $offsetId);
            } else {
                $product->setCustomAttribute('offset', '');
            }
            $diameter = $attributes['diametro'];
            if ($diameter !== "" && $diameter !== "0.00") {
                $diameter .= "''";
                $diameterId = $this->attributeHelper->createOrGetId('diameter', $diameter);
                $product->setCustomAttribute('diameter', $diameterId);
            } else {
                $product->setCustomAttribute('diameter', '');
            }
            $hub = $attributes['alto'];
            if ($hub !== "") {
                $hub .= "mm";
                $hubId = $this->attributeHelper->createOrGetId('hub', $hub);
                $product->setCustomAttribute('hub', $hubId);
            } else {
                $product->setCustomAttribute('hub', '');
            }
            $mounting = $attributes['envase'];
            if ($mounting !== "" && $mounting !== "0.00") {
                $mountingId = $this->attributeHelper->createOrGetId('mounting', $mounting);
                $product->setCustomAttribute('mounting', $mountingId);
            } else {
                $product->setCustomAttribute('mounting', '');
            }
            $load = $attributes['diametro2'];
            if ($load !== "" && $load !== "0.00") {
                $load .= "kg";
                $loadId = $this->attributeHelper->createOrGetId('load', $load);
                $product->setCustomAttribute('load', strval($loadId));
            } else {
                $product->setCustomAttribute('load', '');
            }
            $variation = $attributes['acotacion'];
            if ($variation !== "") {
                $variationId = $this->attributeHelper->createOrGetId('variation', $variation);
                $product->setCustomAttribute('variation', $variationId);
            } else {
                $product->setCustomAttribute('variation', '');
            }
        }
    }

    public function getImages($article, $product)
    {
        if (array_key_exists('imagenes', $article)) {
            $baseUrl = $this->config->getGeneralConfig('installation_url') . 'api/g4100/image/';
            $header = [
                'Content-Type: application/json',
                'Accept: application/json',
                "Authorization: Bearer {$this->config->getTokenFromDatabase()}",
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

            curl_setopt($ch, CURLOPT_URL, $baseUrl);

            $cached = [];
            $count = 0;
            $g4100CacheFolder = $this->dir->getRoot() . '/g4100_cache/'; // Folder where we will cache all the images
            $mediaImagesFolder = $this->dir->getPath('media') . '/images/'; // Folder where we have to copy the cached images
            foreach ($article['imagenes'] as $image) {
                $imageSplit = str_split($image);  // Here we split all the characters in the image to create the folders
                $cached[$count]['path'] = $g4100CacheFolder;
                $cached[$count]['image'] = $image;
                foreach ($imageSplit as $char) {
                    $cached[$count]['path'] .= $char . '/';
                }
                if (!file_exists($cached[$count]['path'] . $cached[$count]['image'] . '.jpg')) {
                    if (!file_exists($cached[$count]['path'])) {
                        mkdir($cached[$count]['path'], 0777, true); // We create the folders we need
                    }
                    $fp = fopen($cached[$count]['path'] . $cached[$count]['image'] . '.jpg', 'w+');              // Open file handle

                    curl_setopt($ch, CURLOPT_URL, $baseUrl . $image);
                    curl_setopt($ch, CURLOPT_FILE, $fp);          // Output to file
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                    curl_exec($ch);

                    fclose($fp);
                }
                if (!file_exists($mediaImagesFolder  . $image . '.jpg')) {
                    copy($cached[$count]['path'] . $cached[$count]['image'] . '.jpg', $mediaImagesFolder  . $image . '.jpg');
                }
                $count++;
            }
            curl_close($ch);                              // Closing curl handle
            $collectionProducts = $this->syncgStatusCollectionFactory->create()
                ->addFieldToFilter('g_id', $article['cod']);
            if ($collectionProducts->getSize() > 0) { // If the product already exists, that means we only have to update it
                foreach ($collectionProducts as $itemProducts) {
                    $productId = $itemProducts->getData('mg_id');
                    $product = $this->productRepository->getById($productId, true);

                    $existingMediaGalleryEntries = $product->getMediaGalleryEntries();

                    foreach ($existingMediaGalleryEntries as $key => $entry) {
                        unset($existingMediaGalleryEntries[$key]);
                        $magentoImage = $entry->getFile();
                        $this->imageProcessor->removeImage($product, $magentoImage); // We remove the image from Magento
                        $magentoImage = 'pub/media/catalog/product' . $magentoImage;
                        if (file_exists($magentoImage)) {
                            unlink($magentoImage); // We remove the image from the HDD to save storage
                        }
                    }
                    $product->setMediaGalleryEntries($existingMediaGalleryEntries);
                    $this->productResource->save($product);
                    try {
                        foreach ($cached as $key => $c) {
                            if ($key === 0) {
                                $types = ['image', 'small_image', 'thumbnail'];
                            } else {
                                $types = ['small_image'];
                            }
                            $product->addImageToMediaGallery($mediaImagesFolder . $c['image'] . '.jpg', $types, false, false);
                            $this->logger->info(new Phrase('G4100 Sync | [G4100 Product: ' . $article['cod'] . '] | [Magento Product: ' . $product->getId() . '] | Image ' . $key . ' | ADDED IMAGE.'));
                        }
                    } catch (Exception $e) {
                        $this->logger->error('G4100 Sync | ' . $e->getMessage());
                    }
                    $product->save();
                }
            }
            $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $image, SyncgStatus::TYPE_IMAGE, SyncgStatus::STATUS_COMPLETED);
        }
    }
}

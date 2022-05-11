<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\AttributeHelper;
use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Helper\SQLHelper;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\Collection;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Service\SyncgApiService;
use DateInterval;
use DateTimeZone;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory as ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavModel;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Exception;
use Psr\Log\LoggerInterface;
use Safe\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTime as DatetimeGmt;

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

    private $prefixLog;

    private $curlDownloadImage;

    private $baseUrlDownloadImage;
    /**
     * @var DatetimeGmt
     */
    private $dateTime;

    private $description;

    private $shortDescription;

    private $parentG;

    protected $sqlHelper;

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
        EavModel                   $eavModel,
        DatetimeGmt                $dateTime,
        SQLHelper                  $sqlHelper
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
        $this->dateTime = $dateTime;
        $this->sqlHelper = $sqlHelper;
        $this->prefixLog = uniqid() . ' | G4100 Sync |';
        $this->baseUrlDownloadImage = $this->config->getGeneralConfig('installation_url') . 'api/g4100/image/';
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    /**
     * @param $start
     * @return void
     * @throws \Exception
     * @todo Este método es posible que deba ser de la clase padre
     */
    public function buildParams($start, $filters)
    {
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
                    "desc_detallada", "desc_interna", "envase", "frente", "fondo", "alto", "peso", "diametro", "diametro2", "pvp1", "pvp2", "precio_coste_estimado", "modelo",
                    "si_vender_en_web", "existencias_globales", "grupo", "acotacion", "marca", "SEO_description", "SEO_title"]),
                'filters' => json_encode([
                    "salto" => $start,
                    "filtro" => $filters // Filters need to be an empty array, otherwise the API doesn't work
                ]),
                'order' => json_encode(["campo" => "fecha_cambio", "orden" => "ASC"])
            ]),
        ];
        $decoded = json_decode($this->params['body']);
        $decoded = (array)$decoded;
        $this->order = $decoded['order']; // We will need this to get the products correctly
    }

    public function send()
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Init Products sync'));
        $timeStart = microtime(true);
        $start = 0; // Counter to check from which page we start the query
        $fetchLoop = true; // Variable to check if we need to break the loop or keep on it
        $relateLoop = true; // Variable to check if we need to break the loop or keep on it
        $imageLoop = true; // Variable to check if we need to break the loop or keep on it
        $allProductsG4100 = [];
        $counter = 0;
        $page = 1;
        while ($fetchLoop) {
            $allProductsG4100 = array_merge($allProductsG4100, $this->getProductsG4100($start, $page)); // We get all the products from G4100
            $start += 100;
            $page++;
            $counter++;
            if ($counter == 10) {
                $counter = 0;
                if ($allProductsG4100) {
                    $productsG4100 = $this->getModifiableProducts($allProductsG4100); // We filter the products that have 'Si vender en web' setted to 1
                    if ($productsG4100) {
                        $attributeSetCollection = $this->attributeCollectionFactory->create();
                        $attributeSets = $attributeSetCollection->getItems();
                        $attributeSetsMap = [];
                        foreach ($attributeSets as $attributeSet) {
                            $attributeSetsMap[$attributeSet->getAttributeSetName()] = $attributeSet->getAttributeSetId();
                        }
                        $attributeSetId = "";  // Variable where we will store the attribute set ID
                        $this->categories = $this->getMagentoCategories();
                        $countProductsG4100 = count($productsG4100);
                        for ($i = 0; $i < $countProductsG4100; $i++) {
                            $productG4100 = $productsG4100[$i];
                            $this->description = $productG4100['desc_detallada'];
                            $this->shortDescription = $productG4100['desc_interna'];
                            $prefixLog = $this->prefixLog . ' [' . $countProductsG4100 . '/' . ($i + 1) . '][G4100 Product: ' . $productG4100['cod'] . ']';
                            if ($this->checkRequiredData($productG4100)) {
                                $collectionSyncg = $this->syncgStatusCollectionFactory->create()
                                    ->addFieldToFilter('g_id', $productG4100['cod'])
                                    ->addFieldToFilter('mg_id', ['neq' => 'NULL'])
                                    ->addFieldToFilter('type', [['eq' => SyncgStatus::TYPE_PRODUCT], ['eq' => SyncgStatus::TYPE_PRODUCT_SIMPLE]]); // We check if the product already exists
                                if (array_key_exists('familias', $productG4100)) { // We check if the product has an attribute set. If it does, then checks what it is
                                    if (isset($attributeSetsMap[$productG4100['familias'][0]['nombre']])) { // If the name of the attribute set is the same as the one on G4100...
                                        $attributeSetId = $attributeSetsMap[$productG4100['familias'][0]['nombre']]; // We save the ID to use it later
                                    }
                                }
                                if ($collectionSyncg->getSize() > 0) { // If the product already exists, that means we only have to update it
                                    foreach ($collectionSyncg as $itemSyncg) {
                                        $productId = $itemSyncg->getData('mg_id');
                                        $product = $this->productRepository->getById($productId, true); // We load the product in edit mode
                                        $this->createUpdateProduct($product, $productG4100, $attributeSetId);
                                        $this->productRepository->save($product);
                                        $this->logger->info(new Phrase($prefixLog . ' | [Magento Product: ' . $productId . '] | Edited'));
                                        $this->addImagesPending($productG4100, $productId);
                                    }
                                } else {
                                    $product = $this->productFactory->create(); // If the product doesn't exists, we create it
                                    $this->createUpdateProduct($product, $productG4100, $attributeSetId);
                                    $product = $this->productRepository->save($product);
                                    $this->logger->info(new Phrase($prefixLog . ' | [Magento Product: ' . $product->getId() . '] | Created'));
                                    $this->addImagesPending($productG4100, $product->getId());
                                }
                                $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $productG4100['cod'], SyncgStatus::TYPE_PRODUCT, SyncgStatus::STATUS_COMPLETED);
                                $this->config->setLastDateSyncProducts($productG4100['fecha_cambio']);
                                $this->config->setLastG4100ProductId($productG4100['cod']);
                                if (array_key_exists('relacionados', $productG4100)) {
                                    $this->createSimpleProduct($productG4100, $attributeSetId); // We duplicate the product to avoid losing options
                                    $relatedIds = [];
                                    foreach ($productG4100['relacionados'] as $related) {
                                        $relatedIds[] = $related['cod'];
                                    }
                                    $this->sqlHelper->setRelatedProducts($relatedIds, $productG4100['cod'], $product->getId());
                                }
                            } else {
                                $this->logger->error(new Phrase($prefixLog . ' | Product not valid'));
                            }
                        }
                        $allProductsG4100 = [];
                    }
                } else {
                    $fetchLoop = false;
                }
            }
        }
        $this->logger->info(new Phrase($this->prefixLog . ' Finish Products sync ' . $this->getTrackTime($timeStart)));
        $this->logger->info(new Phrase($this->prefixLog . ' Start Product Relations'));
        while ($relateLoop) {
            $relatedProducts = $this->sqlHelper->getRelatedProducts();
            $this->createRelatedProducts($relatedProducts);
            $relateLoop = false;
        }
        while ($imageLoop) {
            $this->saveImages();
            $imageLoop = false;
        }
        $this->sqlHelper->disableProducts($allProductsG4100); // Here we disable all the products that have 'Si vender en web' setted to 0
        $this->logger->info(new Phrase($this->prefixLog . 'Finish All sync ' . $this->getTrackTime($timeStart)));
    }

    private function addImagesPending($productG4100, $magentoProductId)
    {
        if (array_key_exists('imagenes', $productG4100)) {
            foreach ($productG4100['imagenes'] as $image) {
                $this->syncgStatusRepository->updateEntityStatus($magentoProductId, $image, SyncgStatus::TYPE_IMAGE, SyncgStatus::STATUS_PENDING);
            }
        }
    }

    private function deleteImagesFromChild($magentoProductIds)
    {
        foreach ($magentoProductIds as $magentoProductId) {
            $this->syncgStatusRepository->deleteEntity($magentoProductId, SyncgStatus::TYPE_IMAGE);
        }
    }

    private function getProductsG4100($start, $page): array
    {
        $timeStart = microtime(true);
        $productsG4100 = []; // Array where we will store the items, ordered in pages
        $this->logger->info(new Phrase($this->prefixLog . ' Fetching products'));
        $timeStartLoop = microtime(true);
        $filters = [];
        $lastDateSync = $this->config->getParamsWithoutSystem('syncg/general/last_date_sync_products')->getValue(); // We get the last sync date
        $lastG4100Id = $this->config->getParamsWithoutSystem('syncg/general/last_inserted_g4100_product')->getValue(); // We get the last G4100 product inserted
        if ($lastDateSync) {
            $filters = [
                ["campo" => "descripcion", "valor" => "MAK LEIPZIG SILVER", "tipo" => 0],
                ["campo" => "fecha_cambio", "valor" => $lastDateSync, "tipo" => 3]
            ];
        }
        if ($lastDateSync && $lastG4100Id) {
            $filters = [
                ["campo" => "descripcion", "valor" => "MAK LEIPZIG SILVER", "tipo" => 0],
                ["campo" => "cod", "valor" => $lastG4100Id, "tipo" => 3],
                ["campo" => "fecha_cambio", "valor" => $lastDateSync, "tipo" => 3]
            ];
        }
        $this->buildParams($start, $filters);
        $response = $this->execute();
        if ($response !== null && array_key_exists('listado', $response) && $response['listado']) {
            $productsG4100 = array_merge($productsG4100, $response['listado']);
            $this->logger->info(new Phrase($this->prefixLog . ' Cached page ' . $page . '. Products ' . count($productsG4100) . ' ' . $this->getTrackTime($timeStartLoop)));
        } elseif (!isset($response['listado'])) {
            $this->logger->error(new Phrase($this->prefixLog . ' Error fetching products.'));
        }
        $this->logger->info(new Phrase($this->prefixLog . ' Fetching products successful. ' . $this->getTrackTime($timeStart)));
        return $productsG4100;
    }

    /**
     * @throws \Exception
     */
    private function getModifiableProducts($products): array
    {
        $modifiableProducts = [];
        $coreConfigData = $this->config->getParamsWithoutSystem('syncg/general/last_date_sync_products')->getValue(); // We get the last sync date
//        $timezone = new DateTimeZone('Europe/Madrid');
//        $date = new DateTime($coreConfigData, $timezone);
//        $hours = $date->getOffset() / 3600; // We have to add the offset, since the date from the API comes in CEST
//        $newDate = $date->add(new DateInterval(("PT{$hours}H")));
        $lastSync = strtotime($coreConfigData);
        foreach ($products as $product) {
            $lastChange = strtotime($product['fecha_cambio']);
            if ($lastChange >= $lastSync && $product['si_vender_en_web'] === true) {
                $modifiableProducts[] = $product;
            }
        }
        return $modifiableProducts;
    }

    private function checkRequiredData($product): bool
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

    private function createSimpleProduct($productG4100, $attributeSetId)
    {
        $simpleProduct = $productG4100;
        unset($simpleProduct['relacionados']); // We remove related products as we don't need them
        if ($simpleProduct['ref_fabricante'] !== "") {
            $sku = $simpleProduct['ref_fabricante'] .= '-' . $simpleProduct['id']; // We add the ID to the SKU to avoid errors
        } else {                                                                           // If ref_fabricante is empty, we use ref_proveedor
            $sku = $simpleProduct['ref_proveedor'] .= '-' . $simpleProduct['id'];
        }
        $originalCod = $simpleProduct['cod'];
        $simpleProduct['cod'] .= '-' . $simpleProduct['id']; // We add the ID to the code to avoid errors
        try {
            $product = $this->productRepository->get($sku); // If the SKU exists, we load the product
        } catch (NoSuchEntityException $e) {
            $product = false; // Otherwise, we have to create it
        }
        if ($product) {
            $this->createUpdateProduct($product, $simpleProduct, $attributeSetId);
            $this->productResource->save($product);
            $this->logger->info(new Phrase($this->prefixLog . ' [Magento Product: ' . $product->getId() . '] | Edited'));
        } else {
            $product = $this->productFactory->create();
            $this->createUpdateProduct($product, $simpleProduct, $attributeSetId);
            $this->productResource->save($product);
            $this->logger->info(new Phrase($this->prefixLog . ' [G4100 Product: ' . $simpleProduct['cod'] . '] | [Magento Product: ' . $product->getId() . '] | Created'));
        }
        $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $originalCod, SyncgStatus::TYPE_PRODUCT_SIMPLE, SyncgStatus::STATUS_COMPLETED);
    }

    private function createUpdateProduct($product, $productG4100, $attributeSetId)
    {
        $categoryIds = [];
        $id = $productG4100['id'];
        $manufacturerRef = $productG4100['ref_fabricante'];
        $manufacturerRefPlusId = $manufacturerRef . '-' . $id;
        $vendorRef = $productG4100['ref_proveedor'];
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
        $product->setName($productG4100['descripcion']);
        $product->setDescription($this->description);
        $product->setShortDescription($this->shortDescription);
        $product->setMetaTitle($productG4100['SEO_title']);
        $product->setMetaDescription($productG4100['SEO_description']);
        $product->setStoreId(0);
        $product->setAttributeSetId($attributeSetId);
        $url = strtolower(str_replace(" ", "-", $productG4100['descripcion']));
        if (!(array_key_exists('relacionados', $productG4100))) {
            $this->insertAttributes($productG4100, $product);
            $url .= '-' . $id;
        }
        if ($product->getUrlKey() === null) {
            $product->setUrlKey($url);
        }
        if ($productG4100['si_vender_en_web'] === true) {
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
        } else if ($product->getId()) {
            $parentProduct = $this->configurable->getParentIdsByChild($product->getId());
            if (isset($parentProduct[0])) {
                $product->setVisibility(1);
            } else {
                $product->setVisibility(4);
            }
        } else {
            $product->setVisibility(4);
        }
        $product->setTypeId('simple');
        $product->setPrice($productG4100['pvp2']);
        $product->setCost($productG4100['precio_coste_estimado']);
        $product->setWebsiteIds([1]);
        $product->setCustomAttribute('tax_class_id', 2);
        $product->setCustomAttribute('g4100_id', $productG4100['cod']);
        $product->setWeight($productG4100['peso']);
        $product->setDescription($productG4100['desc_detallada']);
        if (array_key_exists('familias', $productG4100)) {
            if (array_key_exists($productG4100['familias'][0]['nombre'], $this->categories)) {
                $categoryIds[] = $this->categories[$productG4100['familias'][0]['nombre']];
            } else {
                $categoryIds[] = $this->createCategory($productG4100['familias'][0]['nombre']);
            }
        }
        if (isset($productG4100['marca']) && array_key_exists($productG4100['marca'], $this->categories)) {
            $categoryIds[] = $this->categories[$productG4100['marca']];
        } else {
            $categoryIds[] = $this->createCategory($productG4100['marca']);
        }
        $product->setCategoryIds($categoryIds);
        $product->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock' => 0,
            'is_in_stock' => 1,
            'qty' => $productG4100['existencias_globales']
        ]);
    }

    private function createRelatedProducts($relatedProducts)
    {
        $objectManager = ObjectManager::getInstance(); // Instance of Object Manager. We need it for some operations that didn't work with dependency injection
        foreach ($relatedProducts as $parent => $sons) {
            $rp = $parent;
            $prefixLog = $this->prefixLog . ' [Magento Product: ' . $rp . ']';
            $product = $this->productRepository->getById($rp);
            $attributes = $this->getAttributesIds($product); // Array with the attributes we want to make configurable
            $attributeModels = [];
            $attributePositions = ['diameter', 'width', 'mounting', 'offset', 'hub', 'load', 'variation'];
            foreach ($attributes as $attributeId) {
                $attributeModel = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute');
                $eavModel = $this->eavModel;
                $attr = $eavModel->load($attributeId);
                $position = array_search($attr->getData('attribute_code'), $attributePositions);
                $data = [
                    'attribute_id' => $attributeId,
                    'product_id' => $product->getId(),
                    'position' => strval($position),
                    'sku' => $product->getSku(),
                    'label' => $attr->getData('frontend_label')
                ];
                $new = $attributeModel->setData($data);
                array_push($attributeModels, $new);
                try {
                    $attributeModel->setData($data)->save(); // We create the attribute model
                } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
                    $this->logger->error($prefixLog . ' | Attribute model already exists. Skipping creation....'); // If the attribute model already exists, it throws an exception,
                }                                                       // so we need to catch it to avoid execution from stopping
            }
            $product->load('media_gallery');
            $product->setTypeId("configurable"); // We change the type of the product to configurable
            $product->setAttributeSetId($product->getData('attribute_set_id'));
            $this->configurable->setUsedProductAttributeIds($attributes, $product);
            $product->setNewVariationsAttributeSetId(intval($product->getData('attribute_set_id')));
            $relatedIds = $sons;
            $extensionConfigurableAttributes = $product->getExtensionAttributes();
            $extensionConfigurableAttributes->setConfigurableProductLinks($relatedIds); // Linking by ID the products that are related to this configurable
            $extensionConfigurableAttributes->setConfigurableProductOptions($attributeModels); // Linking the options that are configurable
            $product->setExtensionAttributes($extensionConfigurableAttributes);
            $product->setCanSaveConfigurableAttributes(true);
            try {
                $this->productRepository->save($product);
                $this->logger->info(new Phrase($prefixLog . ' | Changed to configurable'));
                $this->setRelatedsVisibility($relatedIds); // We need to make the simple products related to this one hidden
            } catch (InputException $e) {
                $this->logger->error(new Phrase($prefixLog . ' | Error changing to configurable'));
            }
            $this->deleteImagesFromChild($relatedIds);
        }
    }

    private function createCategory($name)
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


    private function getMagentoCategories(): array
    {
        $categories = [];
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect('*');
        foreach ($categoryCollection as $c) {
            $categories[$c->getData('name')] = $c->getData('entity_id');
        }
        return $categories;
    }

//    private function getRelatedProductsIds($rp, $relatedProductsSons): array
//    {
//        $related = [];
//        $arrayCombination = [];
////        foreach ($magentoId as $id) {
////            if (array_key_exists($rp, $id)) {
////                $childProductId = intval($id[$rp]);
////                $parentProductId = intval($rp);
////                $options = $this->getProductOptions($childProductId);
////                if (!in_array($options, $arrayCombination)) {
////                    $related[] = $childProductId; // We also add $magentoId, as we need it
////                    $arrayCombination[] = $options;
////                    $this->logger->info(new Phrase($this->prefixLog . ' [Magento Product: ' . $childProductId
////                        . '] | RELATED TO CONFIGURABLE | [' . 'Magento Product: ' . $parentProductId . '].'));
////                } else {
////                    $productSon = $this->productRepository->getById(key($id), true, 0, true);
////                    $this->disableProduct($productSon);
////                }
////            }
////        }
//        foreach ($relatedProductsSons as $son) {
//            $options = $this->getProductOptions($son);
//            if (!in_array($options, $arrayCombination) && $son !== $rp) {
//                $related[] = $son; // For each of them, we save the Magento ID to use it later
//                $arrayCombination[] = $options;
//                $this->logger->info(new Phrase($this->prefixLog . ' | [Magento Product: ' . $son . '] | RELATED TO CONFIGURABLE | [' . 'Magento Product: ' . $rp . '].'));
//            } else {
//                $productSon = $this->productRepository->getById($son, true, 0, true);
//                $this->disableProduct($productSon);
//            }
//        }
//
//        return $related;
//    }
//
//    private function disableProduct($product)
//    {
//        $product->setStatus(2); // If the product can't be related to the configurable, we disable it
//        $product->setVisibility(1); // If the product can't be related to the configurable, we set its visibility to not visible
//        $this->productRepository->save($product);
//        $product->save(); // Second save to avoid a product saving bug in Magento 2
//    }
//
//    private function getProductOptions($productId): string
//    {
//        $productSon = $this->productRepository->getById($productId);
//        return $productSon->getData('width') . $productSon->getData('load') . $productSon->getData('hub') .
//            $productSon->getData('diameter') . $productSon->getData('mounting') . $productSon->getData('offset');
//    }

    private function setRelatedsVisibility($related, $current = null)
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

    private function getAttributesIds($attributes): array
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $code = []; // Array where we will store the attributes IDs
            $width = $attributes['frente'];
            if ($width !== "") {
                $code[] = $this->attributeHelper->getAttribute('width')->getAttributeId(); // With this helper we get the ID of the desired attribute
            }
            $offset = $attributes['fondo'];
            if ($offset !== "") {
                $code[] = $this->attributeHelper->getAttribute('offset')->getAttributeId();
            }
            $diameter = $attributes['diametro'];
            if ($diameter !== "") {
                $code[] = $this->attributeHelper->getAttribute('diameter')->getAttributeId();
            }
            $hub = $attributes['alto'];
            if ($hub !== "") {
                $code[] = $this->attributeHelper->getAttribute('hub')->getAttributeId();
            }
            $mounting = $attributes['envase'];
            if ($mounting !== "") {
                $code[] = $this->attributeHelper->getAttribute('mounting')->getAttributeId();
            }
            $load = $attributes['diametro2'];
            if ($load !== "") {
                $code[] = $this->attributeHelper->getAttribute('load')->getAttributeId();
            }
            $variation = $attributes['acotacion'];
            if ($variation !== "") {
                $code[] = $this->attributeHelper->getAttribute('variation')->getAttributeId();
            }
        }
        return $code;
    }

    private function insertAttributes($attributes, $product)
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $width = $attributes['frente'];
            if ($width !== "") {
                $width .= "cm";
                $widthId = $this->attributeHelper->createOrGetId('width', $width);
                $product->setCustomAttribute('width', $widthId);
            } else {
                $product->setCustomAttribute('width', '');      // We do this to put it on empty in case that is what we get from the API
            }                                                  // Useful when we delete an attribute, this sets it to empty
            $offset = $attributes['fondo'];
            if ($offset !== "") {
                $offset .= "mm";
                $offsetId = $this->attributeHelper->createOrGetId('offset', $offset);
                $product->setCustomAttribute('offset', $offsetId);
            } else {
                $product->setCustomAttribute('offset', '');
            }
            $diameter = $attributes['diametro'];
            if ($diameter !== "") {
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
            if ($mounting !== "") {
                $mountingId = $this->attributeHelper->createOrGetId('mounting', $mounting);
                $product->setCustomAttribute('mounting', $mountingId);
            } else {
                $product->setCustomAttribute('mounting', '');
            }
            $load = $attributes['diametro2'];
            if ($load !== "") {
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
            $brand = $attributes['marca'];
            if ($brand !== "") {
                $brandId = $this->attributeHelper->createOrGetId('brand', $brand);
                $product->setCustomAttribute('brand', $brandId);
            } else {
                $product->setCustomAttribute('brand', '');
            }
        }
    }

    private function downloadImage($path, $image)
    {
        $header = [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$this->config->getTokenFromDatabase()}",
        ];
        $this->curlDownloadImage = curl_init();
        curl_setopt($this->curlDownloadImage, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlDownloadImage, CURLOPT_HTTPHEADER, $header);
        curl_setopt($this->curlDownloadImage, CURLOPT_URL, $this->baseUrlDownloadImage);

        curl_setopt($this->curlDownloadImage, CURLOPT_URL, $this->baseUrlDownloadImage . $image);
        curl_setopt($this->curlDownloadImage, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->curlDownloadImage, CURLOPT_TIMEOUT, 1000);
        curl_setopt($this->curlDownloadImage, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_exec($this->curlDownloadImage);
        $fp = fopen($path, 'w+'); // Open file handle
        curl_setopt($this->curlDownloadImage, CURLOPT_FILE, $fp);          // Output to file
        curl_exec($this->curlDownloadImage);
        // As we checked, we always recieve from the G4100 API a png file, independently of what we upload to it
        // However, as this could change in the future, we have prepared this chunk of code
        // Here, we check the binary data of the image, and, with that, we determine what extension we have to give to it
        $type = getimagesize($path);
        $extension = $path;
        if (str_contains($type['mime'], 'png')) {
            $extension .= '.png';
            rename($path, $extension);
        } elseif (str_contains($type['mime'], 'jpeg')) {
            $extension .= '.jpg';
            rename($path, $extension);
        }

        $this->logger->info(new Phrase($this->prefixLog . ' Download Image ' . $image));

        fclose($fp);
        curl_close($this->curlDownloadImage);
        $this->curlDownloadImage = null;
        $image = explode('/', $extension);
        return end($image);
    }

    private function getPendingImages(): Collection
    {
        return $this->syncgStatusCollectionFactory->create()
            ->addFieldToFilter('type', SyncgStatus::TYPE_IMAGE)
            ->addFieldToFilter('status', SyncgStatus::STATUS_PENDING);
    }

    private function saveImages()
    {
        $pendingImages = $this->getPendingImages();
        if ($pendingImages->getSize() > 0) {
            $this->logger->info(new Phrase($this->prefixLog . ' Init Images sync'));
            $timeStart = microtime(true);
            $g4100CacheFolder = $this->dir->getPath('media') . '/g4100_cache/'; // Folder where we will cache all the images
            $g4100ImagesCache = [];

            foreach ($pendingImages as $pendingImage) { // Download and fill images group by magento product id
                $image = $pendingImage->getData('g_id');
                $magentoProductId = $pendingImage->getData('mg_id');
                $imageSplit = str_split($image);  // Here we split all the characters in the image to create the folders
                $pathImage = $g4100CacheFolder;
                foreach ($imageSplit as $char) {
                    $pathImage .= $char . '/';
                }
                $exists = glob($pathImage . $image . '.*');
                if (!$exists) { // If not exists, get image from API
                    if (!file_exists($pathImage)) {
                        mkdir($pathImage, 0777, true); // We create the folders we need
                    }
                    $image = $this->downloadImage($pathImage . $image, $image);
                } else {
                    $explode = explode('/', $exists[0]);
                    $image = end($explode);
                }
                $g4100ImagesCache[$magentoProductId][] = [
                    'path' => $pathImage,
                    'image' => $image
                ];
            }

            $countImages = 0;
            $countMap = count($g4100ImagesCache);
            foreach ($g4100ImagesCache as $magentoProductId => $images) { // Set images to magento product
                $countImages++;
                $prefixLog = $this->prefixLog . ' [' . $countMap . '/' . $countImages . ']';
                $product = $this->productRepository->getById($magentoProductId, true); // Load product
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
                    foreach ($images as $key => $g4100ImageCache) {
                        if ($key === 0) {
                            $types = ['image', 'small_image', 'thumbnail'];
                        } else {
                            $types = ['small_image'];
                        }
                        $product->addImageToMediaGallery($g4100ImageCache['path'] . $g4100ImageCache['image'], $types, false, false);
                        $this->syncgStatusRepository->updateEntityStatus($product->getId(), $g4100ImageCache['image'], SyncgStatus::TYPE_IMAGE, SyncgStatus::STATUS_COMPLETED);
                        $this->logger->info(new Phrase($prefixLog . ' [Magento Product: ' . $product->getId() . '] | Image ' . $g4100ImageCache['image'] . ' | Add Image'));
                    }
                } catch (Exception $e) {
                    $this->logger->error($prefixLog . ' ' . $e->getMessage());
                }
                $this->productResource->save($product);
            }

            if ($this->curlDownloadImage) {
                curl_close($this->curlDownloadImage);
                $this->curlDownloadImage = null;
            }
            $this->logger->info(new Phrase($this->prefixLog . 'Finish Images sync ' . $this->getTrackTime($timeStart)));
        }
    }
}

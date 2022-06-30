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

    protected $sqlHelper;

    private $useId;

    private $type;

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

    private function buildParams($page)
    {
        $lastDateSync = $this->config->getParamsWithoutSystem('syncg/general/last_date_sync_products')->getValue();
        $lastDateSync = date('Y-m-d H:i', strtotime($lastDateSync));
        $lastG4100Cod = $this->config->getParamsWithoutSystem('syncg/general/last_inserted_g4100_product')->getValue();

        if ($lastG4100Cod) {
            $filters[] = ["campo" => "fecha_cambio", "valor" => $lastDateSync, "tipo" => 0]; // fecha_cambio = last_date_sync
            $filters[] = ["campo" => "cod", "valor" => $lastG4100Cod, "tipo" => 8]; // cod > last_inserted_g4100
        } elseif ($lastDateSync) {
            $filters[] = ["campo" => "fecha_cambio", "valor" => $lastDateSync, "tipo" => 8]; // fecha_cambio > last_date_sync
        }
//        $filters[] = ["campo" => "descripcion", "valor" => 'MAK LEIPZIG GLOSS BLACK', "tipo" => 2]; // For testing @todo test
        $this->logger->info(new Phrase($this->prefixLog . ' Filters | page: ' . $page . ' | fecha_cambio: ' . $lastDateSync . ' | cod: ' . $lastG4100Cod));

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
                    "salto" => $page * 100,
                    "filtro" => $filters
                ]),
                'order' => json_encode(["campo" => "fecha_cambio, cod", "orden" => "ASC, ASC"])
//                'order' => json_encode(["campo" => "cod", "orden" => "ASC"]) // @todo test
            ]),
        ];
        $decoded = json_decode($this->params['body']);
        $decoded = (array)$decoded;
        $this->order = $decoded['order']; // We will need this to get the products correctly
    }

    private function processProductsApi(): bool
    {
        $timeStart = microtime(true);
        $allProductsG4100 = [];
        $counter = 0;
        $fetchLoop = true;
        $finishGetProductsApi = false;
        while ($fetchLoop) {
            $lastG4100Cod = $this->config->getParamsWithoutSystem('syncg/general/last_inserted_g4100_product')->getValue();
            $newProducts = $this->getProductsG4100($counter);
            if (!$newProducts && $lastG4100Cod) {
                $counter = 0; // new filter, reset pagination
                $this->config->setLastG4100ProductId(''); // Clear last product id when no find product to continue pagination
                $newProducts = $this->getProductsG4100($counter); // Get products only filter date_change
            }
            $allProductsG4100 = array_merge($allProductsG4100, $newProducts); // We get all the products from G4100
            $counter++;
            if ($allProductsG4100 && (count($allProductsG4100) >= 1000 || !$newProducts)) {
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
                    $this->storeManager->setCurrentStore(0); // All store views
                    $countProductsG4100 = count($productsG4100);

                    for ($i = 0; $i < $countProductsG4100; $i++) {
                        $productG4100 = $productsG4100[$i];
                        $this->description = $productG4100['desc_detallada'];
                        $this->shortDescription = $productG4100['desc_interna'];
                        $prefixLog = $this->prefixLog . ' [' . ($i + 1) . '/' . $countProductsG4100 . '][G4100 Product: ' . $productG4100['cod'] . ']';
                        if ($this->checkRequiredData($productG4100)) {
                            $collectionSyncg = $this->syncgStatusCollectionFactory->create()
                                ->addFieldToFilter('g_id', $productG4100['cod'])
                                ->addFieldToFilter('mg_id', ['neq' => 0])
                                ->addFieldToFilter('type', ['in' => [SyncgStatus::TYPE_PRODUCT, SyncgStatus::TYPE_PRODUCT_SIMPLE]]) // We check if the product already exists
                                ->addOrder('type', 'asc')
                                ->setPageSize(1)
                                ->setCurPage(0);
                            if (array_key_exists('familias', $productG4100)) { // We check if the product has an attribute set. If it does, then checks what it is
                                if (isset($attributeSetsMap[$productG4100['familias'][0]['nombre']])) { // If the name of the attribute set is the same as the one on G4100...
                                    $attributeSetId = $attributeSetsMap[$productG4100['familias'][0]['nombre']]; // We save the ID to use it later
                                }
                            }
                            $product = null;
                            $productAction = 'Edited';
                            if ($collectionSyncg->getSize() > 0) { // If the product already exists, that means we only have to update it
                                foreach ($collectionSyncg as $itemSyncg) {
                                    $product = $this->productRepository->getById($itemSyncg->getData('mg_id'), true); // We load the product in edit mode
                                }
                            }
                            if (!$product) {
                                $productAction = 'Created';
                                $product = $this->productFactory->create(); // If the product doesn't exists, we create it
                            }
                            $this->createUpdateProduct($product, $productG4100, $attributeSetId);
                            $this->productRepository->save($product);
                            $product = $this->productRepository->get($product->getSku()); // Refresh product after save by sku
                            $productId = $product->getId();
                            $this->logger->info(new Phrase($prefixLog . ' | [Magento Product: ' . $product->getId() . '] | ' . $productAction));
                            if ($this->isProductG4100Configurable($productG4100)) {
                                // @todo debemos insertar solo las imágenes de productos configurables o de single que no tengan padre. De momento solo lo hacemos para los relacionados
                                $this->addImagesPending($productG4100, $productId);
                                $this->createSimpleOptionParentProduct($productG4100, $attributeSetId); // We duplicate the product to avoid losing options
                                $relatedIds = [];
                                foreach ($productG4100['relacionados'] as $related) {
                                    $relatedIds[] = $related['cod'];
                                }
                                $this->sqlHelper->setRelatedProducts($relatedIds, $productG4100['cod']); // Add pending relations
                                $this->syncgStatusRepository->updateEntityStatus($productId, $productG4100['cod'], SyncgStatus::TYPE_PRODUCT, SyncgStatus::STATUS_COMPLETED);
                            } else {
                                $this->syncgStatusRepository->updateEntityStatus($productId, $productG4100['cod'], SyncgStatus::TYPE_PRODUCT_SIMPLE, SyncgStatus::STATUS_COMPLETED);
                            }
                        } else {
                            $this->logger->error(new Phrase($prefixLog . ' | Product not valid'));
                        }
                        $this->config->setLastG4100ProductId($productG4100['cod']);
                        $this->config->setLastDateSyncProducts($productG4100['fecha_cambio']);
                    }
                } else {
                    // No modifiable products, set last product cod and date change to next execution
                    $lastProduct = end($allProductsG4100);
                    $this->config->setLastG4100ProductId($lastProduct['cod']);
                    $this->config->setLastDateSyncProducts($lastProduct['fecha_cambio']);
                }
                $this->sqlHelper->disableProducts($allProductsG4100); // Here we disable all the products that have 'Si vender en web' setted to 0
                $fetchLoop = false;
            } elseif (!$allProductsG4100) {
                $fetchLoop = false;
                $finishGetProductsApi = true;
                $this->logger->info(new Phrase($this->prefixLog . ' Finish Products sync ' . $this->getTrackTime($timeStart)));
            }
        }
        return $finishGetProductsApi;
    }

    private function processRelatedProducts() {
        $finishRelatedProducts = false;
        $this->storeManager->setCurrentStore(0); // All store views
        $this->logger->info(new Phrase($this->prefixLog . ' Start Product Relations'));
        $timeStart = microtime(true);
        $relatedProducts = $this->sqlHelper->getRelatedProducts();
        $relatedCount = 0;
        foreach ($relatedProducts as $rp) {
            $relatedCount += count($rp);
        }
        if ($relatedCount > 0) {
            $this->createRelatedProducts($relatedProducts);
        } else {
            $finishRelatedProducts = true;
        }
        $this->logger->info(new Phrase($this->prefixLog . ' Finish Product Relations ' . $this->getTrackTime($timeStart)));
        return $finishRelatedProducts;
    }

    public function send()
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Init Products sync'));
        $timeStart = microtime(true);
        $finishGetProductsApi = $this->processProductsApi(); // Process product API
        $finishRelatedProducts = false;
        if ($finishGetProductsApi) { // Process Related products
            $finishRelatedProducts = $this->processRelatedProducts();
        }
        if ($finishRelatedProducts) { // Process images
            $this->processImages();
        }
        $this->logger->info(new Phrase($this->prefixLog . ' Finish All sync ' . $this->getTrackTime($timeStart)));
    }

    private function addImagesPending($productG4100, $magentoProductId)
    {
        if (array_key_exists('imagenes', $productG4100)) {
            foreach ($productG4100['imagenes'] as $image) {
                $this->syncgStatusRepository->updateEntityStatus($magentoProductId, $image, SyncgStatus::TYPE_IMAGE, SyncgStatus::STATUS_PENDING);
            }
        }
    }

    private function getProductsG4100($page): array
    {
        $productsG4100 = []; // Array where we will store the items, ordered in pages
        $timeStartLoop = microtime(true);
        $this->buildParams($page);
        $page++;
        $response = $this->execute();
        if ($response !== null && array_key_exists('listado', $response) && $response['listado']) {
            $productsG4100 = $response['listado'];
            $this->logger->info(new Phrase($this->prefixLog . ' Page ' . $page . '. Products ' . count($productsG4100) . ' ' . $this->getTrackTime($timeStartLoop)));
        } elseif (!isset($response['listado'])) {
            $this->logger->error(new Phrase($this->prefixLog . ' Error fetching products.'));
        }
        return $productsG4100;
    }

    /**
     * @throws \Exception
     */
    private function getModifiableProducts($products): array
    {
        $modifiableProducts = [];
        foreach ($products as $product) {
            if ($product['si_vender_en_web'] === true) {
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

    private function createSimpleOptionParentProduct($productG4100, $attributeSetId)
    {
        $simpleProduct = $productG4100;
        unset($simpleProduct['relacionados']); // We remove related products as we don't need them
        if ($simpleProduct['ref_fabricante'] !== "") { // We add the ID to the SKU to avoid errors
            $sku = $simpleProduct['ref_fabricante'] .= '-' . $simpleProduct['id'];
        } else { // If ref_fabricante is empty, we use ref_proveedor
            $sku = $simpleProduct['ref_proveedor'] .= '-' . $simpleProduct['id'];
        }
        $originalCod = $simpleProduct['cod'];
        $simpleProduct['cod'] .= '-' . $simpleProduct['id']; // We add the ID to the code to avoid errors
        try {
            $product = $this->productRepository->get($sku); // If the SKU exists, we load the product
        } catch (NoSuchEntityException $e) {
            $product = false; // Otherwise, we have to create it
        }
        $productAction = 'Edited';
        if (!$product) {
            $product = $this->productFactory->create();
            $productAction = 'Created';
        }
        $this->createUpdateProduct($product, $simpleProduct, $attributeSetId);
        $this->productResource->save($product);
        $this->logger->info(new Phrase($this->prefixLog . ' [Magento Product: ' . $product->getId() . '] | ' . $productAction));
        $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $originalCod, SyncgStatus::TYPE_PRODUCT_SIMPLE, SyncgStatus::STATUS_COMPLETED, $originalCod);
    }

    private function createUpdateProduct($product, $productG4100, $attributeSetId)
    {
        $categoryIds = [];
        $cod = $productG4100['cod'];
        $manufacturerRef = $productG4100['ref_fabricante'];
        $manufacturerRefPlusId = $manufacturerRef . '-' . $cod;
        $vendorRef = $productG4100['ref_proveedor'];
        $vendorRefPlusId = $vendorRef . '-' . $cod;
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
        $product->setAttributeSetId($attributeSetId);
        if ($productG4100['si_vender_en_web'] === true) {
            $product->setStatus(1);
        } else {
            $product->setStatus(0);
        }
        $product->setTaxClassId(0);
        $product->setPrice($productG4100['pvp2']);
        $product->setCost($productG4100['precio_coste_estimado']);
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
        if (!$product->getId()) { // New product
            $this->insertAttributes($productG4100, $product);
            if ($this->isProductG4100Configurable($productG4100)) {
                $product->setVisibility(4);
                $product->setTypeId('configurable');
            } else {
                $product->setVisibility(1);
                $product->setTypeId('simple');
            }
            // Set url
            $url = strtolower(str_replace(" ", "-", $productG4100['descripcion']));
            if (!$this->isProductG4100Configurable($productG4100)) {
                $url .= '-' . $cod;
            }
            if ($product->getUrlKey() === null) {
                $product->setUrlKey($url);
            }
        }

        if (!$this->isProductG4100Configurable($productG4100)) {
            $product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 10 // @todo test harcode 10. Tenemos que concretar existencias con Isbue $productG4100['existencias_globales']
            ]);
        }
    }

    private function isProductG4100Configurable($productG4100) {
        return array_key_exists('relacionados', $productG4100);
    }

    private function createRelatedProducts($relatedProducts)
    {
        $objectManager = ObjectManager::getInstance(); // Instance of Object Manager. We need it for some operations that didn't work with dependency injection
        $timeStart = microtime(true);
        foreach ($relatedProducts as $parentMgId => $sons) {
            $prefixLog = $this->prefixLog . ' [Magento Product: ' . $parentMgId . ']';
            $parentProduct = $this->productRepository->getById($parentMgId);
            $attributes = $this->getAttributesIds($parentProduct); // Array with the attributes we want to make configurable
            $attributeModels = [];
            $attributePositions = ['diameter', 'width', 'mounting', 'offset', 'hub', 'load', 'variation'];
            foreach ($attributes as $attributeId) {
                $attributeModel = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute');
                $eavModel = $this->eavModel;
                $attr = $eavModel->load($attributeId);
                $position = array_search($attr->getData('attribute_code'), $attributePositions);
                $data = [
                    'attribute_id' => $attributeId,
                    'product_id' => $parentProduct->getId(),
                    'position' => strval($position),
                    'sku' => $parentProduct->getSku(),
                    'label' => $attr->getData('frontend_label')
                ];
                $new = $attributeModel->setData($data);
                $attributeModels[] = $new;
                try {
                    $attributeModel->setData($data)->save(); // We create the attribute model
                } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
                    $this->logger->error($prefixLog . ' | Attribute model already exists. Skipping creation....'); // If the attribute model already exists, it throws an exception,
                }                                                       // so we need to catch it to avoid execution from stopping
            }
            $parentProduct->load('media_gallery');
            $parentProduct->setTypeId("configurable"); // We change the type of the product to configurable
            $parentProduct->setAttributeSetId($parentProduct->getData('attribute_set_id'));
            $this->configurable->setUsedProductAttributeIds($attributes, $parentProduct);
            $parentProduct->setNewVariationsAttributeSetId(intval($parentProduct->getData('attribute_set_id')));
            $relatedIds = $this->getRelatedProductsIds($sons, $parentProduct->getId());
            $extensionConfigurableAttributes = $parentProduct->getExtensionAttributes();
            $extensionConfigurableAttributes->setConfigurableProductLinks($relatedIds); // Linking by ID the products that are related to this configurable
            $extensionConfigurableAttributes->setConfigurableProductOptions($attributeModels); // Linking the options that are configurable
            $parentProduct->setExtensionAttributes($extensionConfigurableAttributes);
            $parentProduct->setCanSaveConfigurableAttributes(true);
            try {
                $this->productRepository->save($parentProduct);
                $this->logger->info(new Phrase($prefixLog . ' | Changed to configurable'));
                $this->sqlHelper->updateRelatedProductsStatus($relatedIds, $parentMgId); // We set all the related products status to completed
            } catch (InputException $e) {
                $this->logger->error(new Phrase($prefixLog . ' | Error changing to configurable ' . $e->getMessage()));
            }
            $timeEnd = microtime(true);
            if (round(($timeEnd - $timeStart), 2) > 60) {
                break; // Only process 60 seconds
            }
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

    private function getRelatedProductsIds($sons, $parentId): array
    {
        $related = [];
        $arrayCombination = [];
        foreach ($sons as $son) {
            $options = $this->getProductOptions($son);
            if (!in_array($options, $arrayCombination) && $son !== $parentId) {
                $related[] = $son; // For each of them, we save the Magento ID to use it later
                $arrayCombination[] = $options;
                $this->logger->info(new Phrase($this->prefixLog . ' [Magento Product: ' . $son . '] | [' . 'Magento Product: ' . $parentId . '] | Related to configurable'));
            } else {
                $productSon = $this->productRepository->getById($son, true, 0, true);
                $this->disableProduct($productSon);
                $this->logger->info(new Phrase($this->prefixLog . ' [Magento Product: ' . $son . '] | DISABLED PRODUCT (DUPLICATED).'));
            }
        }

        return $related;
    }

    private function disableProduct($product)
    {
        $product->setStatus(2); // If the product can't be related to the configurable, we disable it
        $product->setVisibility(1); // If the product can't be related to the configurable, we set its visibility to not visible
        $this->productRepository->save($product);
        $product->save(); // Second save to avoid a product saving bug in Magento 2
    }

    private function getProductOptions($productId): string
    {
        $productSon = $this->productRepository->getById($productId);
        return $productSon->getData('width') . $productSon->getData('load') . $productSon->getData('hub') .
            $productSon->getData('diameter') . $productSon->getData('mounting') . $productSon->getData('offset');
    }


    /**
     * Key attribute g4100, value attribute Magento
     * @return string[]
     */
    private function getAttributesMap(): array
    {
        return [
            'frente' => 'width',
            'fondo' => 'offset',
            'diametro' => 'diameter',
            'alto' => 'hub',
            'envase' => 'mounting',
            'diametro2' => 'load',
            'acotacion' => 'variation',
            'marca' => 'brand'
        ];
    }

    private function getAttributesIds($attributes): array
    {
        $code = []; // Array where we will store the attributes IDs
        if ($attributes) { // If this is exists, that means we have attributes
            $attributesMap = $this->getAttributesMap();
            unset($attributesMap['marca']);
            foreach ($attributesMap as $attributeG4100 => $attributeMg) {
                $value = $attributes[$attributeG4100];
                if ($value !== "") {
                    $code[] = $this->attributeHelper->getAttribute($attributeMg)->getAttributeId(); // With this helper we get the ID of the desired attribute
                }
            }
        }
        return $code;
    }

    private function insertAttributes($attributes, $product)
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $attributesMap = $this->getAttributesMap();
            foreach ($attributesMap as $attributeG4100 => $attributeMg) {
                $value = $attributes[$attributeG4100];
                if ($value !== '') {
                    if ($attributeG4100 == 'frente') {
                        $value .= 'cm';
                    } elseif (in_array($attributeG4100, ['fondo', 'alto'])) {
                        $value .= 'mm';
                    } elseif ($attributeG4100 == 'diametro2') {
                        $value .= 'kg';
                    } elseif ($attributeG4100 == 'diametro') {
                        $value .= "''";
                    }
                    $attributeId = $this->attributeHelper->createOrGetId($attributeMg, $value);
                    $product->setCustomAttribute($attributeMg, $attributeId);
                } else {
                    $product->setCustomAttribute('diameter', '');
                }
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
            ->addFieldToFilter('status', SyncgStatus::STATUS_PENDING)
            ->setPageSize(20); // Process only 20 images pending
    }

    private function processImages()
    {
        $this->logger->info(new Phrase($this->prefixLog . ' Init Images sync'));
        $timeStart = microtime(true);$timeStart = microtime(true);
        $pendingImages = $this->getPendingImages();
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
        $this->logger->info(new Phrase($this->prefixLog . ' Finish Images sync ' . $this->getTrackTime($timeStart)));
    }
}

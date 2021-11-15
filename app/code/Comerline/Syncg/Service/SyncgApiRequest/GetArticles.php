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
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
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
        StoreManagerInterface      $storeManager
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
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $coreConfigData = $this->config->getParamsWithoutSystem('syncg/general/last_date_sync_products')->getValue(); // We get the last sync date

        $timezone = new DateTimeZone('Europe/Madrid');
        $date = new DateTime($coreConfigData, $timezone);
        $hours = $date->getOffset() / 3600; // We have to add the offset, since the date from the API comes in CEST
        $newDate = $date->add(new DateInterval(("PT{$hours}H")));

        $fields = [
            'campos' => json_encode(array("nombre", "ref_fabricante", "fecha_cambio", "borrado", "ref_proveedor", "descripcion",
                "desc_detallada", "envase", "frente", "fondo", "alto", "diametro", "diametro2", "precio_coste_estimado", "modelo",
                "si_vender_en_web", "existencias_globales", "grupo", "acotacion", "marca")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "si_vender_en_web", "valor" => "1", "tipo" => 0),
                    array("campo" => "fecha_cambio", "valor" => $newDate->format('Y-m-d H:i'), "tipo" => 3)
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
        while ($loop) {
            $this->buildParams($start);
            $response = $this->execute();
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
        }
        $objectManager = ObjectManager::getInstance(); // Instance of Object Manager. We need it for some of the operations that didn't work with dependency injection
        if ($pages) {
            $attributeSetId = "";  // Variable where we will store the attribute set ID
            $relatedProducts = []; // Array where we will store the products that have related products
            $relatedAttributes = []; // Array where we will store the attributes that are related
            $relatedProductsSons = []; // Array where we will store the related products
            $this->categories = $this->getMagentoCategories();
            foreach ($pages as $page) {
                for ($i = 0; $i < count($page); $i++) { //We navigate through the products in a page
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
                                $product_id = $itemSyncg->getData('mg_id');
                                $product = $this->productRepository->getById($product_id, true); // We load the product in edit mode
                                $this->createUpdateProduct($product, $page, $attributeSetId, $i);
                                $this->productResource->save($product);
                            }
                        } else {
                            $product = $this->productFactory->create(); // If the product doesn't exists, we create it
                            $this->createUpdateProduct($product, $page, $attributeSetId, $i);
                            $product->save();
                        }
                        if (array_key_exists('relacionados', $page[$i])) { // If the product has related products we get it's ID and save it on an array to work later with it
                            $product_id = $product->getId();
                            $magentoId[] = $this->createSimpleProduct($page, $i, $attributeSetId, $product_id); // We get the ID since we will create a duplicate of this product to avoid losing options
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
                }
            }

            foreach ($relatedProducts as $rp) {
                $product = $this->productRepository->getById($rp);
                try {
                    $attributes = $relatedAttributes[$rp]; // Array with the attributes we want to make configurable
                    $attributeModels = [];
                    $count = 0;
                    foreach ($attributes as $attributeId) {
                        $attributeModel = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute');
                        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
                        $attr = $eavModel->load($attributeId);
                        $data = array(
                            'attribute_id' => $attributeId,
                            'product_id' => $product->getId(),
                            'position' => strval($count),
                            'sku' => $product->getSku(),
                            'label' => $attr->getData('frontend_label')
                        );
                        $count++;
                        $new = $attributeModel->setData($data);
                        array_push($attributeModels, $new);
                        try {
                            $attributeModel->setData($data)->save(); // We create the attribute model
                        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
                            $this->logger->info('Syncg | ' . $e->getMessage()); // If the attribute model already exists, it throws an exception,
                        }                                                       // so we need to catch it to avoid execution from stopping
                    }
                    $product->load('media_gallery');
                    $product->setTypeId("configurable"); // We change the type of the product to configurable
                    $product->setAttributeSetId($product->getData('attribute_set_id'));
                    $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable')->setUsedProductAttributeIds($attributes, $product);
                    $product->setNewVariationsAttributeSetId(intval($product->getData('attribute_set_id')));
                    $relatedIds = $this->getRelatedProductsIds($rp, $relatedProductsSons, $magentoId);
                    $extensionConfigurableAttributes = $product->getExtensionAttributes();
                    $extensionConfigurableAttributes->setConfigurableProductLinks($relatedIds); // Linking by ID the products that are related to this configurable
                    $extensionConfigurableAttributes->setConfigurableProductOptions($attributeModels); // Linking the options that are configurable
                    $product->setExtensionAttributes($extensionConfigurableAttributes);
                    $product->setCanSaveConfigurableAttributes(true);
                    $this->productRepository->save($product);
                    $this->setRelatedsNotVisible($relatedIds); // We need to make the simple products related to this one hidden
                } catch (Exception $e) {
                    $this->logger->info('Syncg | ' . $e->getMessage());
                }
            }
        }
    }

    public function createSimpleProduct($page, $i, $attributeSetId, $product_id)
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

        } else {
            $product = $this->productFactory->create();
            $this->createUpdateProduct($product, $simpleProduct, $attributeSetId, $i);
            $product->save();
        }
        $this->getImages($simpleProduct[$i], $product);
        $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $originalCod, SyncgStatus::TYPE_PRODUCT, SyncgStatus::STATUS_COMPLETED);
        $magentoId = []; // Here we will store the Magento ID of the new product, to use it later
        $magentoId[$product_id] = $product->getId();
        return $magentoId;
    }

    public function createUpdateProduct($product, $page, $attributeSetId, $i)
    {
        $categoryIds = [];
        if ($page[$i]['ref_fabricante'] !== "") {
            $product->setSku($page[$i]['ref_fabricante']);
            $url = strtolower(str_replace(" ", "-", $page[$i]['ref_fabricante']) . $page[$i]['cod']);
        } else {
            $product->setSku($page[$i]['ref_proveedor']);
            $url = strtolower(str_replace(" ", "-", $page[$i]['ref_proveedor']) . $page[$i]['cod']);
        }
        $product->setName($page[$i]['descripcion']);
        $product->setStoreId(0);
        $product->setAttributeSetId($attributeSetId);
        if (!(array_key_exists('relacionados', $page[$i]))) {
            $this->insertAttributes($page[$i], $product);
        }
        if ($page[$i]['si_vender_en_web'] === true) {
            $product->setStatus(1);
        } else {
            $product->setStatus(0);
        }
        $product->setTaxClassId(0);
        $product->setTypeId('simple');
        $product->setPrice($page[$i]['precio_coste_estimado']);
        $product->setVisibility(4);
        $product->setWebsiteIds(array(1));
        $product->setUrlKey($url);
        $product->setCustomAttribute('tax_class_id', 2);
        $product->setDescription($page[$i]['desc_detallada']);
        if (array_key_exists('familias', $page[$i]) && array_key_exists($page[$i]['familias'][0]['nombre'], $this->categories)) {
            array_push($categoryIds, $this->categories[$page[$i]['familias'][0]['nombre']]);
        } else {
            array_push($categoryIds, $this->createCategory($page[$i]['familias'][0]['nombre']));

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
        $product->setStockData(
            array(
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'is_in_stock' => $stock,
                'qty' => $page[$i]['existencias_globales']
            )
        );
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
        foreach ($relatedProductsSons[$rp] as $son) {
            $collectionSon = $this->syncgStatusCollectionFactory->create()
                ->addFieldToFilter('g_id', $son); // We get the IDs that are equal to the one we passed form comerline_syncg_status
            foreach ($collectionSon as $itemSon) {
                $related[] = $itemSon->getData('mg_id'); // For each of them, we save the Magento ID to use it later
            }
        }
        foreach ($magentoId as $id) {
            if (array_key_exists($rp, $id)) {
                $related[] = $id[$rp]; // We also add $magentoId, as we need it
            }
        }

        return $related;
    }

    public function setRelatedsNotVisible($related)
    {
        foreach ($related as $r) {
            $product = $this->productRepository->getById($r);
            $product->load('media_gallery');
            $product->setVisibility(1);
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
            if ($hub !== "" && $hub !== "0.00") {
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
                $widthId = $this->attributeHelper->createOrGetId('width', $width);
                $product->setCustomAttribute('width', $widthId);
            } else {
                $product->setCustomAttribute('width', '');      // We do this to put it on empty in case that is what we get from the API
            }                                                  // Useful when we delete an attribute, this sets it to empty
            $offset = $attributes['fondo'];
            if ($offset !== "" && $offset !== "0.00") {
                $offsetId = $this->attributeHelper->createOrGetId('offset', $offset);
                $product->setCustomAttribute('offset', $offsetId);
            } else {
                $product->setCustomAttribute('offset', '');
            }
            $diameter = $attributes['diametro'];
            if ($diameter !== "" && $diameter !== "0.00") {
                $diameterId = $this->attributeHelper->createOrGetId('diameter', $diameter);
                $product->setCustomAttribute('diameter', $diameterId);
            } else {
                $product->setCustomAttribute('diameter', '');
            }
            $hub = $attributes['alto'];
            if ($hub !== "" && $hub !== "0.00") {
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
                $loadId = $this->attributeHelper->createOrGetId('load', $load);
                $product->setCustomAttribute('load', $loadId);
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
            $baseUrl = $this->config->getGeneralConfig('installation_url') . $this->config->getGeneralConfig('database_id');
            $user = $this->config->getGeneralConfig('email');
            $pass = $this->config->getGeneralConfig('user_key');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIESESSION, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, 'session');

            curl_setopt($ch, CURLOPT_URL, $baseUrl);
            $result = curl_exec($ch);
            $json = json_decode($result, true);

            if (array_key_exists('llave', $json)) { // To avoid import crashing if it can't donwload the image for whatever reason (API down, failed login...)
                curl_setopt($ch, CURLOPT_URL, $baseUrl . "/?usr=" . $user . "&clave=" . md5($pass . $json['llave']));
                $result = curl_exec($ch);
                foreach ($article['imagenes'] as $image) {
                    $path = $this->dir->getPath('media') . '/images/' . $image . '.jpg';
                    $fp = fopen($path, 'w+');              // Open file handle

                    curl_setopt($ch, CURLOPT_URL, $baseUrl . "/imagenes/" . $image);
                    curl_setopt($ch, CURLOPT_FILE, $fp);          // Output to file
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                    curl_exec($ch);

                    fclose($fp);
                    $collectionProducts = $this->syncgStatusCollectionFactory->create()
                        ->addFieldToFilter('g_id', $article['cod']);
                    if ($collectionProducts->getSize() > 0) { // If the product already exists, that means we only have to update it
                        foreach ($collectionProducts as $itemProducts) {
                            $product_id = $itemProducts->getData('mg_id');
                            $product = $this->productRepository->getById($product_id, true);

                            $existingMediaGalleryEntries = $product->getMediaGalleryEntries();

                            foreach ($existingMediaGalleryEntries as $key => $entry) {
                                unset($existingMediaGalleryEntries[$key]);
                            }
                            try {
                                $product->addImageToMediaGallery($path, array('image', 'small_image', 'thumbnail'), false, false);
                            } catch (Exception $e) {
                                $this->logger->info('Syncg | ' . $e->getMessage());
                            }
                            $this->productResource->save($product);
                        }
                    }
                    $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $image, SyncgStatus::TYPE_IMAGE, SyncgStatus::STATUS_COMPLETED);
                }
                curl_close($ch);                              // Closing curl handle
            }
        }
    }
}

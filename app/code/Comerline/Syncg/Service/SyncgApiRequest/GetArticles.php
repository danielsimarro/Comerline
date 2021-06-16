<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Comerline\Syncg\Helper\Config;
use Comerline\Syncg\Service\SyncgApiService;
use Comerline\Syncg\Model\SyncgStatus;
use Comerline\Syncg\Model\ResourceModel\SyncgStatus\CollectionFactory;
use Comerline\Syncg\Model\ResourceModel\SyncgStatusRepository;
use Magento\Catalog\Api\ProductRepositoryInterface;
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
use PHPUnit\Exception;
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

    /**
     * @var AttributeHelper
     */
    private $attributeHelper;

    private $dir;

    private $categoryCollectionFactory;

    private $attributeCollectionFactory;

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
        DirectoryList $dir,
        CategoryCollectionFactory $categoryCollectionFactory,
        AttributeCollectionFactory $attributeCollectionFactory
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
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    public function buildParams($start)
    {
        $fields = [
            'campos' => json_encode(array("nombre", "ref_fabricante", "descripcion", "desc_detallada" ,"pvp1", "modelo", "si_vender_en_web", "existencias_globales", "grupo")),
            'filtro' => json_encode(array(
                "inicio" => $start,
                "filtro" => array(
                    array("campo" => "descripcion", "valor" => "asasasa2", "tipo" => 2)
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
            $attributeSetId = "";  // Variable where we will store the attribute set ID
            $relatedProducts =  [];
            $relatedAttributes = [];
            $relatedProductsSons = [];
            foreach ($pages as $page){
                for ($i = 0; $i < count($page); $i++){ //Cada producto.
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
                            $product = $this->productRepository->getById($product_id, true);
                            $this->createUpdateProduct($product, $page, $attributeSetId, $i);
                            $this->productResource->save($product);
                        }
                    } else {
                        $product = $this->productFactory->create(); // If the product doesn't exists, we create it
                        $this->createUpdateProduct($product, $page, $attributeSetId, $i);
                        $product->save();
                    }
                        if (array_key_exists('relacionados', $page[$i])){ // If the product has related products we get it's ID and save it on an array to work later with it
                            $product_id = $product->getId();
                            $magentoId = $this->createSimpleProduct($page, $i, $attributeSetId, $product_id);
                            $relatedProducts[] = $product->getEntityId();
                            foreach ($page[$i]['relacionados'] as $r){
                                $relatedProductsSons[$product->getEntityId()][] = $r['cod'];
                                $relatedAttributes[$product->getEntityId()] = $this->getAttributesIds($page[$i]['tp_2'][0]);
                            }
                        }
                        $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $page[$i]['cod'], SyncgStatus::TYPE_PRODUCT, 1);
                        $this->getImages($page[$i], $product);
                }
            }

            foreach ($relatedProducts as $rp)
            {
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
                            $attributeModel->setData($data)->save();
                        } catch (\Magento\Framework\Exception\AlreadyExistsException $e){
                            $this->logger->info($e->getMessage());
                        }
                    }
                    $product->setTypeId("configurable"); // We change the type of the product to configurable
                    $product->setAffectConfigurableProductAttributes();
                    $product->setAttributeSetId($product->getData('attribute_set_id'));
                    $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable')->setUsedProductAttributeIds($attributes, $product);
                    $product->setNewVariationsAttributeSetId(intval($product->getData('attribute_set_id')));
                    $relatedIds = $this->getRelatedProductsIds($rp, $relatedProductsSons, $magentoId);
                    $extensionConfigurableAttributes = $product->getExtensionAttributes();
                    $extensionConfigurableAttributes->setConfigurableProductLinks($relatedIds);
                    $extensionConfigurableAttributes->setConfigurableProductOptions($attributeModels);
                    $product->setExtensionAttributes($extensionConfigurableAttributes);
                    $product->setCanSaveConfigurableAttributes(true);
                    $this->productRepository->save($product);
                } catch (Exception $e) {
                    $this->logger->info('Comerline Syncg | ' . $e->getMessage());
                }
            }
        }
    }

    public function createSimpleProduct($page, $i, $attributeSetId, $product_id)
    {
        $simpleProduct[$i] = $page[$i];
        unset($simpleProduct[$i]['relacionados']); // We remove related products as we don't need them
        $simpleProduct[$i]['ref_fabricante'] .= '-' . $simpleProduct[$i]['id']; // We add the ID to the SKU to avoid errors
        $simpleProduct[$i]['cod'] .= '-' . $simpleProduct[$i]['id']; // We add the ID to the code to avoid errors
        try {
            $product = $this->productRepository->get($simpleProduct[$i]['ref_fabricante']); // If the SKU exists, we load the product
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
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
        $magentoId = []; // Here we will store the Magento ID of the new product, to use it later
        $magentoId[$product_id] = $product->getId();
        return $magentoId;
    }

    public function createUpdateProduct($product, $page, $attributeSetId, $i)
    {
        $product->setSku($page[$i]['ref_fabricante']);
        $product->setName($page[$i]['descripcion']);
        $product->setStoreId(0);
        $product->setAttributeSetId($attributeSetId);
        if (array_key_exists('tp_2', $page[$i]) && !(array_key_exists('relacionados', $page[$i]))){
            $this->insertAttributes($page[$i]['tp_2'][0], $product);
        }
        if ($page[$i]['si_vender_en_web'] === true) {
            $product->setStatus(1);
        } else {
            $product->setStatus(0);
        }
        $product->setTaxClassId(0);
        $product->setTypeId('simple');
        $product->setPrice($page[$i]['pvp1']);
        $product->setDescription($page[$i]['desc_detallada']);
        $categoryIds = $this->getCategoryIds($page[$i]['familias'][0]['nombre']);
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
                'qty' => intval($page[$i]['existencias_globales'])
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

    public function getRelatedProductsIds($rp, $relatedProductsSons, $magentoId)
    {
        $related = [];
        foreach ($relatedProductsSons[$rp] as $son)
        {
            $collectionSon = $this->syncgStatusCollectionFactory->create()
                ->addFieldToFilter('g_id', $son); // We get the IDs that are equal to the one we passed form comerline_syncg_status
            foreach ($collectionSon as $itemSon)
            {
                $related[] = $itemSon->getData('mg_id'); // For each of them, we save the Magento ID to use it later
            }
            $related[] = $magentoId[$rp]; // We also add $magentoId, as we need it
        }

        return $related;
    }

    public function getAttributesIds($attributes)
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $code = [];
            $thickness = $attributes['c_11'];
            if ($thickness !== "") {
                $code[] = $this->attributeHelper->getAttribute('thickness')->getAttributeId();
            }
            $type = $attributes['c_10'];
            if ($type !== "") {
                $code[] = $this->attributeHelper->getAttribute('type')->getAttributeId();
            }
            $diameter = $attributes['c_5'];
            if ($diameter !== "") {
                $code[] = $this->attributeHelper->getAttribute('diameter')->getAttributeId();
            }
            $size = $attributes['c_4'];
            if ($size !== "") {
                $code[] = $this->attributeHelper->getAttribute('size')->getAttributeId();
            }
            $mounting = $attributes['c_9'];
            if ($mounting !== "") {
                $code[] = $this->attributeHelper->getAttribute('mounting')->getAttributeId();
            }
            $color = $attributes['c_8'];
            if ($color !== "") {
                $code[] = $this->attributeHelper->getAttribute('color')->getAttributeId();
            }
        }
        return $code;
    }

    public function insertAttributes($attributes, $product)
    {
        if ($attributes) { // If this is exists, that means we have attributes
            $thickness = $attributes['c_11'];
            if ($thickness !== "") {
                $thicknessId = $this->attributeHelper->createOrGetId('thickness', $thickness);
                $product->setCustomAttribute('thickness', $thicknessId);
            } else {
                $product->setCustomAttribute('thickness', ''); // We do this to put it on empty in case that is what we get from the API
            }
            $type = $attributes['c_10'];
            if ($type !== "") {
                $typeId = $this->attributeHelper->createOrGetId('type', $type);
                $product->setCustomAttribute('type', $typeId);
            } else {
                $product->setCustomAttribute('type', '');
            }
            $diameter = $attributes['c_5'];
            if ($diameter !== "") {
                $diameterId = $this->attributeHelper->createOrGetId('diameter', $diameter);
                $product->setCustomAttribute('diameter', $diameterId);
            } else {
                $product->setCustomAttribute('diameter', '');
            }
            $size = $attributes['c_4'];
            if ($size !== "") {
                $sizeId = $this->attributeHelper->createOrGetId('size', $size);
                $product->setCustomAttribute('size', $sizeId);
            } else {
                $product->setCustomAttribute('size', '');
            }
            $mounting = $attributes['c_9'];
            if ($mounting !== "") {
                $mountingId = $this->attributeHelper->createOrGetId('mounting', $mounting);
                $product->setCustomAttribute('mounting', $mountingId);
            } else {
                $product->setCustomAttribute('mounting', '');
            }
            $color = $attributes['c_8'];
            if ($color !== "") {
                $colorId = $this->attributeHelper->createOrGetId('color', $color);
                $product->setCustomAttribute('color', $colorId);
            } else {
                $product->setCustomAttribute('color', '');
            }
        }
    }

    public function getImages($article, $product)
    {
        if(array_key_exists('imagenes', $article))
        {
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

            curl_setopt($ch, CURLOPT_URL, $baseUrl . "/?usr=" . $user . "&clave=" . md5($pass . $json['llave']));
            $result = curl_exec($ch);
            foreach ($article['imagenes'] as $image) {
                $path = $this->dir->getPath('media') . '/images/' . $image . '.jpg';
                $fp = fopen ($path, 'w+');              // Open file handle

                curl_setopt($ch, CURLOPT_URL, $baseUrl. "/imagenes/" . $image);
                curl_setopt($ch, CURLOPT_FILE, $fp);          // Output to file
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                curl_exec($ch);

                curl_close($ch);                              // Closing curl handle
                fclose($fp);
                $collectionProducts = $this->syncgStatusCollectionFactory->create()
                    ->addFieldToFilter('g_id', $article['cod']);
                if ($collectionProducts->getSize() > 0) { // If the product already exists, that means we only have to update it
                    foreach ($collectionProducts as $itemProducts) {
                        $product_id = $itemProducts->getData('mg_id');
                        $product = $this->productRepository->getById($product_id, true);
                        try {
                            $product->addImageToMediaGallery($path, array('image', 'small_image', 'thumbnail'), false, false);
                        } catch (Exception $e) {
                            $this->logger->info('Comerline Syncg | ' . $e->getMessage());
                        }
                        $this->productResource->save($product);
                    }
                }
                $this->syncgStatus = $this->syncgStatusRepository->updateEntityStatus($product->getEntityId(), $image, SyncgStatus::TYPE_IMAGE, 1);
            }
        }
    }
}

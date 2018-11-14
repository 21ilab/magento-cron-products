<?php
/**
 * Created by Afroze.S.
 * Date: 30/1/18
 * Time: 12:07 PM
 */

namespace Twentyone\CronProducts\Console;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Helper\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Interceptor;
use Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModel;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection\ConnectionAdapterInterface;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use MagentoEnv\Entity\ConfigEnv;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class CronProductsCommand extends Command
{
    /*
     *
     */

    /**
     * @var string $path
     * @var State $appState
     * @var ConfigEnv $configEnv
     * @var Category $categoryHelper
     * @var \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryState
     * @var CategoryLinkManagementInterface $categoryLinkManagement
     * @var CollectionFactory $collectionFactory
     * @var Product $productModel
     * @var ProductRepository $productRepository
     */
    protected $path,
        $appState,
        $configEnv,
        $eavConfig,
        $categoryHelper,
        $categoryState,
        $categoryLinkManagement,
        $collectionFactory,
        $productModel,
        $productRepository;

    /**
     * @var array
     */
    #private $languages = [2=>'IT', 3=>'EU', 1=>'US'];
    private $languages = [2=>'IT'];

    /**
     * @var array
     */
    private $attributes;
    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    private $attributeRepository;
    /**
     * @var \Magento\Catalog\Setup\CategorySetupFactory
     */
    private $categorySetupFactory;
    /**
     * @var StockRegistry
     */
    private $stockRegistry;
    /**
     * @var ResourceModel
     */
    private $resourceModel;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;
    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    private $directoryList;
    /**
     * @var \Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface
     */
    private $attributeMediaGalleryEntry;
    /**
     * @var \Magento\Framework\Api\Data\ImageContentInterface
     */
    private $imageContent;
    /**
     * @var ProductAttributeMediaGalleryEntryInterfaceFactory
     */
    private $attributeMediaGalleryEntryInterfaceFactory;

    /**
     * Inject CollectionFactory(products) so to query products of magento and filter
     *
     * CronProductsCommand constructor.
     * @param ResourceModel $resourceModel
     * @param State $appState
     * @param ConfigEnv $configEnv
     * @param Config $eavConfig
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     * @param ProductAttributeMediaGalleryEntryInterfaceFactory $attributeMediaGalleryEntryInterfaceFactory
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository
     * @param \Magento\Catalog\Setup\CategorySetupFactory $categorySetupFactory
     * @param Category $categoryHelper
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryState
     * @param CollectionFactory $collectionFactory
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param Product $productModel
     * @param \Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface $attributeMediaGalleryEntry
     * @param \Magento\Framework\Api\Data\ImageContentInterface $imageContent
     * @param ProductRepository $productRepository
     * @param StockRegistry $stockRegistry
     */
    public function __construct(ResourceModel $resourceModel,
                                State $appState,
                                ConfigEnv $configEnv,
                                Config $eavConfig,
                                \Magento\Framework\Filesystem\DirectoryList $directoryList,
                                ProductAttributeMediaGalleryEntryInterfaceFactory $attributeMediaGalleryEntryInterfaceFactory,
                                \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
                                \Magento\Catalog\Setup\CategorySetupFactory $categorySetupFactory,
                                Category $categoryHelper,
                                \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
                                \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryState,
                                CollectionFactory $collectionFactory,
                                CategoryLinkManagementInterface $categoryLinkManagement,
                                Product $productModel,
                                \Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface $attributeMediaGalleryEntry,
                                \Magento\Framework\Api\Data\ImageContentInterface $imageContent,
                                ProductRepository $productRepository,
                                StockRegistry $stockRegistry) {
        try {
            $appState->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
        } catch (LocalizedException $e) {
            var_dump('test');die;
        }
        parent::__construct();
        $this->appState = $appState;
        $this->configEnv = $configEnv;
        $this->eavConfig = $eavConfig;
        $this->categoryHelper = $categoryHelper;
        $this->categoryState = $categoryState;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->collectionFactory = $collectionFactory;
        $this->productModel = $productModel;
        $this->productRepository = $productRepository;
        $this->attributeRepository = $attributeRepository;
        $this->categorySetupFactory = $categorySetupFactory;
        $this->stockRegistry = $stockRegistry;
        $this->resourceModel = $resourceModel;

        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->directoryList = $directoryList;
        $this->attributeMediaGalleryEntry = $attributeMediaGalleryEntry;
        $this->imageContent = $imageContent;
        $this->attributeMediaGalleryEntryInterfaceFactory = $attributeMediaGalleryEntryInterfaceFactory;

    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context )
    {
        $setup->startSetup();
        $setup->endSetup();
    }

    /**
     * Configure console command and arguments and options required
     */
    protected function configure() {
        $this->setName('Twentyone:CronProducts');
        $this->setDescription('Update products from atelier file');
        $this->setHelp("This command helps to update products from atelier CSV");
    }

    /**
     * Returns list of categories nested as array
     * with its parents
     *
     * @return array
     */
    private function getCategoriesArray() {
        $categoriesArray = [];
        //$categories = $this->getStoreCategories();
        $categories = $this->getCategoryCollection();
        foreach ($categories->getItems() as $category) {
            $categoriesArray[strtolower($category->getName())] = [
                'id' => $category->getId()
            ];
            $childCategories = $this->getCategoryCollection($category->getId());
            foreach ($childCategories->getItems() as $childCategory) {
                $subCategories = $this->getCategoryCollection($childCategory->getId());
                $categoriesArray[strtolower($category->getName())]['children'][strtolower($childCategory->getName())] = [
                    'id' => $childCategory->getId()
                ];
                foreach ($subCategories->getItems() as $subCategory) {
                    $categoriesArray[strtolower($category->getName())]['children'][strtolower($childCategory->getName())]['children'][strtolower($subCategory->getName())] = [
                        'id' => $subCategory->getId()
                    ];
                }
            }
        }
        return $categoriesArray;
    }

    /**
     * @param array $categories
     * @param array $csvRow
     * @return null|int
     */
    private function getCategoryId($categories, $csvRow) {
        $categoryId = null;
        $parent = null;
        if (strtolower($csvRow[5]) == 'uomo') {
            $parent = 'men';
        } elseif (strtolower($csvRow[5]) == 'donna') {
            $parent = 'women';
        }
        if ($parent) {

            switch (strtolower($csvRow[7])) {
                case 'camicia':
                  if ($parent == 'men')
                      $categoryId = $categories[$parent]['children']['clothes']['children']['shirts']['id'];
                  if ($parent == 'women')
                      $categoryId = $categories[$parent]['children']['clothes']['children']['blouses']['id'];
                    break;
                case 'cappello':
                    $categoryId = $categories[$parent]['children']['accessories']['children']['hats']['id'];
                    break;
                case 'cappotto':
                      $categoryId = $categories[$parent]['children']['clothes']['children']['coats']['id'];
                    break;
                case 'cintura':
                    if ($parent == 'men')
                      $categoryId = $categories[$parent]['children']['accessories']['children']['belts']['id'];
                    break;
                case 'costume':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['shirts']['id'];
                    break;
                case 'cravatta':
                    $categoryId = $categories[$parent]['children']['accessories']['children']['ties']['id'];
                    break;
                case 'giacca':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['jackets']['id'];
                    break;
                case 'giaccone':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['outerwear']['id'];
                    break;
                case 'giubbetto':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['outerwear']['id'];
                    break;
                case 'gonna':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['skirts']['id'];
                    break;
                case 'guanto':
                    $categoryId = $categories[$parent]['children']['accessories']['children']['gloves']['id'];
                    break;
                case 'impermeabile':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['coats']['id'];
                    break;
                case 'maglieria':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['knitwear']['id'];
                    break;
                case 'pantalone':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['trousers']['id'];
                    break;
                case 'scarpe':
                    $categoryId = $categories[$parent]['children']['accessories']['children']['shoes']['id'];
                    break;
                case 'profumo':
                    if ($parent == 'men') {
                        $categoryId = $categories[$parent]['children']['accessories']['children']['perfume']['id'];
                    } else {
                        $categoryId = $categories[$parent]['children']['accessories']['children']['perfumes']['id'];
                    }
                    break;
                case 'accessori':
                    switch(strtolower($csvRow[8])) {
                        case 'portafoglio':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['wallet']['id'];
                            }
                            break;
                    }
                    break;
                case 'calze':
                    if ($parent == 'men') {
                        $categoryId = $categories[$parent]['children']['accessories']['children']['socks']['id'];
                    }
                    break;
                case 'borse':
                case 'borsa':
                    switch(strtolower($csvRow[8])) {
                        case 'bauletto':
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['handbags']['id'];
                            }
                            break;
                        case 'borsone':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            break;
                        case 'borsa':
                        case 'borsa borsa':
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['handbags']['id'];
                            }
                            break;
                        case 'cartella':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            break;
                        case 'pochette':
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['handbags']['id'];
                            }
                            break;
                        case 'porta computer':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            break;
                        case 'sacca':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['handbags']['id'];
                            }
                            break;
                        case 'shopping':
                        case 'borsa shopping':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['handbags']['id'];
                            }
                            break;
                        case 'tracolla':
                        case 'borsa tracolla':
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['handbags']['id'];
                            }
                            break;
                        case 'trolley':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            break;
                        case 'valigia':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            break;
                        case 'zaino':
                        case 'borsa zaino':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
                            }
                            break;
                    }
                    break;
                case 'sciarpa':
                    $categoryId = $categories[$parent]['children']['accessories']['children']['scarves']['id'];
                    break;
                case 'tailleur':
                    if ($parent == 'women') {
                        $categoryId = $categories[$parent]['children']['clothes']['children']['suits']['id'];
                    }
                    break;
                case 'bretella':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['suspenders']['id'];
                    break;
                case 'abito':
                    if ($parent == 'men') {
                        $categoryId = $categories[$parent]['children']['clothes']['children']['suits']['id'];
                    }
                    if ($parent == 'women') {
                        $categoryId = $categories[$parent]['children']['clothes']['children']['dresses']['id'];
                    }
                    break;
            }
        }
        return $categoryId;
    }

    /**
     * Check if data should be proceeded to upload or not
     * Check if values of attrbites(dropdown) and categories exist
     * otherwise print error
     *
     * @param array $categories
     * @param array $attributeColumns
     * @param array $csvRow
     * @param int $csvKey
     * @param OutputInterface $output
     * @return bool
     */
    private function checkIfDataIsValid($categories, $attributeColumns, $csvRow, $csvKey, OutputInterface $output) {
        $returnFlag = true;
        foreach ($attributeColumns as $columnKey => $column) {
            $attrValue = null;
            if ($column['type'] == 2) {
                //values are 1 or 2, 1 for text value 2 for select and other multiple type input values
                $attrValue = $this->getAttributeValueId($column['code'], $csvRow[$columnKey]);
                if ($attrValue == null && trim($csvRow[$columnKey]) != "") {
                    $returnFlag = true;
                    //value doesn't exist in attribute send error output on terminal
                    //$output->writeln("Error: Attribute value{" . $csvRow[$columnKey] . "} code{" . $column['code'] . "} doesn't exist, row: " . ($csvKey+1));
                }
            }
        }

        $attributeSet = $this->getAttributeSetId($csvRow);
        $attributeSize = $this->getSizeAttributeCode($attributeSet);

        if ($attributeSet == null) {
            $returnFlag = false;
            $output->writeln("Error : Attribute set doesn't exits {".$csvRow[44]."} row: ".($csvKey+1));
        }
        if ($this->getCategoryId($categories, $csvRow) == null) {
            $returnFlag = false;
            $output->writeln("Error: Category doesn't exist, row: " . ($csvKey+1));
        }

        return $returnFlag;
    }

    private function checkIfDataIsValidForAvailability() {

    }

    private function getAttributeIdByCode($code) {
        $id = null;
        if ($this->resourceModel && $code) {
            if (!isset($this->attributes[$code])) {
                $attr = $this->resourceModel->getAttribute($code);
                $id = $attr->getId();
                $this->attributes[$code] = $attr;
            } else {
                //so you dont search in database every time just search ones and save in attributes array
                $id = $this->attributes[$code]->getId();
            }
        }
        return $id;
    }

    private function getAttributeLabelByCode($code) {
        $id = null;
        if ($this->resourceModel && $code) {
            if (!isset($this->attributes[$code])) {
                $attr = $this->resourceModel->getAttribute($code);
                $id = $attr->getDefaultFrontendLabel();
                $this->attributes[$code] = $attr;
            } else {
                //so you dont search in database every time just search ones and save in attributes array
                $id = $this->attributes[$code]->getDefaultFrontendLabel();
            }
        }
        return $id;
    }


     /**
     * @param string $imageSrc
     * @param Product $product
     */
     private function addImageToproduct($imageSrc, Product $product, $order) {
         $mySaveDir = $this->directoryList->getPath('media') . DIRECTORY_SEPARATOR . 'catalog' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
         $mySaveDir2 = $this->directoryList->getPath('media') . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'catalog' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR;
         $filename = basename($imageSrc);
         $completeSaveLoc = $mySaveDir.$filename;
         $completeSaveLoc2 = $mySaveDir2.$filename;
         if(!file_exists($completeSaveLoc)){
             try {
                 file_put_contents($completeSaveLoc,file_get_contents($imageSrc));
                 file_put_contents($completeSaveLoc2,file_get_contents($imageSrc));
             }catch (Exception $e){
             }
         } else {
             $i = 1;
             $completeSaveLoc = $mySaveDir.$i."_".$filename;
             $completeSaveLoc2 = $mySaveDir2.$i."_".$filename;
             while (file_exists($completeSaveLoc)) {
                 $i++;
                 $completeSaveLoc = $mySaveDir.$i."_".$filename;
                 $completeSaveLoc2 = $mySaveDir2.$i."_".$filename;
             }
             file_put_contents($completeSaveLoc,file_get_contents($imageSrc));
             file_put_contents($completeSaveLoc2,file_get_contents($imageSrc));
         }
         if ($order == 0) {
             $mgEntries = [];
             $fileok = @fopen($imageSrc, "r");
             if ($fileok) {
                 $fileData = file_get_contents($completeSaveLoc);
                 $fileType = mime_content_type($completeSaveLoc);
                 if ($fileData) {
                     $imageLabel = explode('.', $filename);
                     $imageLabel = $imageLabel[0];

                     $mediaEntry = $this->attributeMediaGalleryEntry;
                     $mediaEntry->setLabel($imageLabel);
                     $mediaEntry->setDisabled(false)
                         ->setFile(basename($completeSaveLoc))
                         ->setTypes(["image", "small_image", "thumbnail"])
                         ->setLabel($imageLabel)
                         ->setName($imageLabel)
                         ->setMediaType('image')
                         ->setPosition(0);

                     $imageData = base64_encode($fileData);
                     $this->imageContent->setName($imageLabel);
                     $this->imageContent->setType($fileType);
                     $this->imageContent->setBase64EncodedData($imageData);
                     $mediaEntry->setContent($this->imageContent);
                     $product->setMediaGalleryEntries([$mediaEntry]);
                     @fclose($fileok);
                     var_dump($this->directoryList->getRoot());
                     $product->save();
                 }
             }
             $this->addProductVariations($product, ObjectManager::getInstance());
             //$product->setMediaGalleryEntries([$this->attributeMediaGalleryEntry]);
         }else {
             $product->addImageToMediaGallery($completeSaveLoc,null, false, false);
             $product->save();
             $this->addProductVariations($product, ObjectManager::getInstance());
         }
     }

    /**
     * This function is executed after the console command is types in terminal
     * get the user entered arguments and options and do the magic
     *
     * @param InputInterface $input
     * @param OutputInterface $outputf
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        var_dump($this->appState->getAreaCode());
        /** @var $product Product */
        //$product = Bootstrap::getObjectManager()->create(Product::class);
        /** @var Factory $optionsFactory */

        $objectManager = ObjectManager::getInstance();
        //$this->productModel = $objectManager->create(Product::class);
        $optionsFactory = $objectManager->create(Factory::class);

        $columns = $this->configEnv->getEnv('coloumns');
        $output->writeln('start');

        //Read Prodotti.txt

        $fileProdotti = $this->configEnv->getEnv('csv');

        $fileDisponibilita = $this->configEnv->getEnv('availability_csv');
        $fileImages = $this->configEnv->getEnv('images_csv');

        foreach ($this->languages as $lang_id=>$language) {

          $nameFileProdotti = str_replace('[lang]', $language, $fileProdotti);


          // Limit search when the lang is US
          // They have same ID but it is different products
          if ($lang_id == 1) {
            $us_lang = 1;
          } else {
            $us_lang = null;
          }

          $errorProducts = '';



            try {

                if (file_exists($nameFileProdotti)) {

                $categories = $this->getCategoriesArray();
                $csvArray = $this->readCsvFile($nameFileProdotti);

                if (count($csvArray[0][0]) > 0) {
                $output->writeln(' -------------------- Prodotti -------------------- ');
                foreach ($csvArray as $key => $csvRow) {
                    $attributeSet = $this->getAttributeSetId($csvRow);
                    $sku = $language . '-' . $csvRow[0] . '-' . $csvRow[3] . $csvRow[4];

                    if ($this->checkIfDataIsValid($categories, $columns, $csvRow, $key, $output)) {
                        if ($this->isProductOfThisEnv($csvRow)) {
                            //check product if it belongs to current environment
                            //check if the product is from US lang (EU and IT are same products)
                            //Configurable::TYPE_CODE
                            #if ($language == 'IT')
                            #$products = $this->getProductByAtelierId((string)$csvRow[0]);
                            #else

                            $products = $this->getProductBySku((string)$sku);

                            $versionUE = null;
                            // case is Italian, save UE english version aswell
                            if ($language == 'IT') {
                              $versionUE['description'] = $csvRow[26];
                              $versionUE['short_description'] = $csvRow[27];
                            }

                            if ($products->count() < 1) {
                              // if no exists, search for SIMPLE TYPE
                              $products = $this->getProductBySku((string)$sku, Type::TYPE_SIMPLE);
                            }

                            if ($csvRow[15] != '') {

                              if ($products->count() < 1) {

                                  $output->writeln( 'new: ' . $csvRow[15]);
                                  #$output->writeln( 'sku: ' . $sku);

                                  //Product doesn't exist create new product with ProductModel

                                  $productByName = $this->getProductByName($csvRow[15]);

                                  if ($productByName->count() < 1) {

                                    $productModel = clone $this->productModel;
                                    $productModel->setName($csvRow[15])
                                        ->setStoreId(2)
                                        ->setTypeId(Configurable::TYPE_CODE)
                                    ;

                                    /*
                                     *
                                     * set attribute_set for the new product
                                      */
                                    $productModel->setAttributeSetId($attributeSet)
                                        ->setSku($sku)
                                        ->setStoreId($lang_id)
                                        ->setTaxClassId(2)
                                        ->setTypeId(Configurable::TYPE_CODE)
                                        ->setVisibility(Visibility::VISIBILITY_BOTH)
                                        ->setStatus(Status::STATUS_DISABLED)
                                        ->setData('id_atelier', $csvRow[0])
                                        ->setData('name', $csvRow[15])
                                        ->setData('meta_title', $csvRow[30])
                                        ->setData('meta_description', $csvRow[31])
                                        //->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1])
                                    ;

                                    $productModel->isInStock();

                                    $productModel->save();

                                    $con = $this->resourceModel->getConnection();
                                    $con->query("DELETE FROM url_rewrite WHERE entity_type = 'product' AND entity_id = ".$productModel->getId());

                                    $productResource = $productModel->getResource();

                                    $this->setProductAttributes($categories, $columns, $csvRow, $productModel, $productResource);

                                    $this->setWebsiteIds($productModel, $lang_id, $versionUE);

                                    $output->writeln('created: ' . $productModel->getSku());


                                  } else {
                                    $errorProducts .= '| sku: '.$sku.'  |';
                                    $output->writeln(' ----> product with different sku: ' . $sku);
                                  }

                              } else {


                                  /** @var Product $product */



                                  foreach ($products->getItems() as $product) {
                                      $product->reindex();


                                      if (strtolower($product->getTypeId()) != 'simple') {
                                        $output->writeln('read: ' . $sku . ' - ' . $product->getData('meta_title'));

                                          //$product->getData('category_ids');
                                          $productResource = $product->getResource();

                                          $this->setProductAttributes($categories, $columns, $csvRow, $product, $productResource, $lang_id);

                                          #$this->addProductVariations($product, $objectManager, $lang_id);


                                        #  $this->setWebsiteIds($product, $lang_id, $versionUE);
                                      }

                                  }


                              }



                            } else {
                              $output->writeln(' ----> product with no name: ' . $sku);
                            }

                        }
                    }
                }
                }

                if ($errorProducts != '')
                mail('oriana.potente@21ilab.com, davi.leichsenring@21ilab.com' , 'Error log Cenci' , $errorProducts );
                }

                $fileProdottiMetaTag = $this->configEnv->getEnv('meta_csv');

                if (file_exists($fileProdottiMetaTag)) {

                    $csvArrayMeta = $this->readCsvFile($fileProdottiMetaTag);

                    if (!empty($csvArrayMeta) && count($csvArrayMeta[0][0]) > 0) {
                        $output->writeln(' -------------------- Meta Tags -------------------- ');
                      if (count($csvArrayMeta[0][0]) > 0) {

                          foreach ($csvArrayMeta as $key => $csvRow) {

                            $name = $csvRow[5];
                            $metaIT = utf8_decode((string)$csvRow[6]);
                            $metaUE = utf8_decode((string)$csvRow[7]);

                            $products = $this->getProductByName($name);

                            $output->writeln($name);

                            foreach ($products->getItems() as $product) {


                              if (strtolower($product->getTypeId()) == 'configurable') {

                                  if ($product->getName() == $name) {
                                      $product
                                      ->setStoreId(2)
                                      ->setData('meta_title', $name)
                                      ->setData('meta_description', $metaIT);
                                      $product->save();

                                      $product
                                      ->setStoreId(3)
                                      ->setData('meta_description', $metaUE);
                                      $product->save();

                                    }
                                }
                            }
                          }

                      }
                    }
                }
                //Read Disponibilita.txt
                $nameFileDisponibilita = str_replace('_[lang]', '', $fileDisponibilita);

                if (file_exists($nameFileDisponibilita))
                  $csvArraySimple = $this->readCsvFile($nameFileDisponibilita);
                else
                  $csvArraySimple = null;

                if (!empty($csvArraySimple) && count($csvArraySimple[0][0]) > 0) {
$output->writeln(' -------------------- Disponibilita -------------------- ');
                foreach ($csvArraySimple as $key => $csvRow) {
                    $id = $csvRow[0];
                    $unique_atelier[$id] = $id;

                    $sizeString = $csvRow[1];
                    if (!is_numeric(substr($csvRow[1], strlen($csvRow[1])-1, 1)) && is_numeric(substr($csvRow[1], strlen($csvRow[1])-3, 1))) {
                        //strange 1/2 encoding considered as two chars so replace it and add 0.5 and use it in logic below
                        if (strpos($csvRow[1], 'X') || !strpos($csvRow[1], 'L') || !strpos($csvRow[1], 'M') || !strpos($csvRow[1], 'S') || !strpos($csvRow[1], 'UNI')) {
                            $sizeString = $csvRow[1];
                        } else {
                            $sizeString = (substr($csvRow[1], strlen($csvRow[1])-3, strlen($csvRow[1])-2)+0.5);
                        }

                    }
                    $configProduct = null;
                    $productModel = null;
                    $addNewSimple = true;
                    $addVariationFlag = false;
                    $products = $this->getProductByAtelierId((string)$csvRow[0], Configurable::TYPE_CODE);
                    #$products = $this->getProductBySku((string)$sku, Configurable::TYPE_CODE);
                    foreach ($products as $product) {
                        $configProduct = $product;
                    }

                    if ($configProduct) {

                      $sku = $configProduct->getSku()."_".$sizeString;

                        $sizeAttributeCode = $this->getSizeAttributeCode($configProduct->getAttributeSetId());
                        #$products = $this->getProductByAtelierId((string)$csvRow[0], Type::TYPE_SIMPLE);
                        $products = $this->getProductBySku((string)$sku, Type::TYPE_SIMPLE);

                        $output->writeln('read: ' . $sku);
                        /**
                         * add size if doesn't exist
                         */
                        if ($this->getAttributeValueId($sizeAttributeCode, $sizeString) == null) {
                            $this->addOptionToAttribute($sizeAttributeCode, $sizeString);
                        }
                        foreach ($products as $product) {

                            if ($product->getSku() == $sku) {

                              $output->writeln(' -- simple: ' . $sizeString . ' - ' . $sizeAttributeCode. ' | price:' . $configProduct->getData('price'));

                                $productModel = $product;
                                $productResource = $productModel->getResource();
                                $productModel->setData($sizeAttributeCode, $this->getAttributeValueId($sizeAttributeCode, $sizeString));
                                $productResource->saveAttribute($productModel, $sizeAttributeCode);
                                $addNewSimple = false;
                                $addVariationFlag = true;


                                /**
                                 * @var \Magento\CatalogInventory\Api\StockRegistryInterface
                                 */
                                $productStockData = $this->stockRegistry->getStockItem($productModel->getId());
                                $productStockData->setQty($csvRow[2])
                                    ->setManageStock(1)
                                    ->setMinQty(1)
                                    ->setIsQtyDecimal(false)
                                ;

                                if (intval($csvRow[2]) > 0) {
                                    $productStockData->setIsInStock(true);
                                } else {
                                    $productStockData->setIsInStock(false);
                                }

                                $productStockData->setData('qty', $csvRow[2])
                                    ->setData('manage_stock', 1)
                                ;
                                $this->stockRegistry->updateStockItemBySku($productModel->getSku(), $productStockData);

                                $this->setSimpleProductAttributes($productModel, $productResource, $configProduct, $output);
                                $this->setWebsiteIds($productModel, $lang_id);

                                $this->setPrice($productModel, $productResource->getConnection(), $configProduct->getData('price'), $lang_id);


                            }
                        }
                        if ($addNewSimple) {

                            $addVariationFlag = true;
                            $productModel = clone $this->productModel;
                            $productResource = $productModel->getResource();

                            $productModel->setTypeId(Type::TYPE_SIMPLE)
                                ->setAttributeSetId($configProduct->getAttributeSetId())
                                //->setWebsiteIds([1])
                                ->setName($configProduct->getName())
                                ->setSku($configProduct->getSku().'_' . $sizeString)
                                ->setQty($csvRow[2])
                                ->setData('qty',$csvRow[2])
                                ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE)
                                ->setStatus($configProduct->getStatus())
                                ->setManageStock(1)
                                //->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
                            ;
                            $productModel->setData('id_atelier', $csvRow[0]);
                            $productModel->setData($sizeAttributeCode, $this->getAttributeValueId($sizeAttributeCode, $sizeString));
                            //$productModel->setData('qty',$csvRow[2]);
                            //$productModel->setQty($csvRow[2]);
                            $productModel->save();

                            $con = $this->resourceModel->getConnection();
                            $con->query("DELETE FROM url_rewrite WHERE entity_type = 'product' AND entity_id = ".$productModel->getId());

                            $this->setSimpleProductAttributes($productModel, $productResource, $configProduct, $output);
                            /**
                             * @var \Magento\CatalogInventory\Api\StockRegistryInterface
                             */
                            $productStockData = $this->stockRegistry->getStockItem($productModel->getId());
                            $productStockData->setQty($csvRow[2])
                                ->setManageStock(1)
                                ->setMinQty(1)
                                ->setIsQtyDecimal(false)
                            ;

                            if ($csvRow[2] > 0) {
                                $productStockData->setIsInStock(true);
                            } else {
                                $productStockData->setIsInStock(false);
                            }

                            $productStockData->setData('qty', $csvRow[2])
                                ->setData('manage_stock', 1)
                            ;
                            $this->stockRegistry->updateStockItemBySku($productModel->getSku(), $productStockData);

                            $this->setWebsiteIds($productModel, $lang_id);
                            $this->setPrice($productModel, $productResource->getConnection(), $configProduct->getSpecialPrice(), $lang_id);

                        }
                        if ($addVariationFlag) {
                            $output->writeln(' -- variation ');
                            $this->addProductVariations($configProduct, $objectManager, $sku);
                        }
                    } else {
                        $output->writeln("Error: No configurable product for id_atelier: ".$csvRow[0]);
                    }
                }


                foreach ($unique_atelier as $id_at) {
                  $output->writeln("Variation:  ".$id_at);
                  $products = $this->getProductByAtelierId((string)$id_at, Configurable::TYPE_CODE);

                  foreach ($products as $product) {
                      $configProduct = $product;
                  }
                  if ($configProduct) {
                    $this->addProductVariations($configProduct, $objectManager);
                  }


                }
                }
                //Import images
                if (file_exists($fileImages))
                  $csvArrayImages = $this->readCsvFile($fileImages);
                else
                  $csvArrayImages = null;


                if (count($csvArrayImages[0]) > 0) {
                $output->writeln(' -------------------- Images -------------------- ');
                foreach ($csvArrayImages as $csvArrayImage) {

                    $imageSrc = $this->directoryList->getRoot().DIRECTORY_SEPARATOR."atelier".DIRECTORY_SEPARATOR."images".DIRECTORY_SEPARATOR.$csvArrayImage[1];

                    if(file_exists($imageSrc)) {

                        $products = $this->getProductByAtelierId($csvArrayImage[0]);

                        foreach ($products as $product) {

                            $output->writeln('read: ' . $product->getSku());
                            if (strtolower($product->getTypeId()) == 'configurable') {


                                //$con = $this->resourceModel->getConnection();
                                //$con->query("DELETE FROM catalog_product_entity_varchar WHERE value = '".$imageSrc."' AND store = ".$lang_id." AND entity_id = ".$productModel->getId());

                                $this->addImageToproduct($imageSrc, $product, $csvArrayImage[2]);
                                $output->writeln('read: ' . $product->getSku());
                            }
                        }
                    }
                }
                }

            } catch (Exception $e) {
                $output->writeln("Error: ".$e->getMessage());
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                $output->writeln("Error: ".$e->getMessage());
            } catch (LocalizedException $e) {
                $output->writeln("Error: ".$e->getMessage());
            } catch (\Exception $e) {
                $output->writeln("Error: ".$e->getMessage());
            }

        }
    }



    /**
     * @param Product $product
     */
    private function setWebsiteIds(Product $product, $lang_id = 3, $versionUE = null) {
        $con = $this->resourceModel->getConnection();
        //$websiteIds = $this->configEnv->getEnv('website_ids');
        $websiteIds = array($lang_id);

        //if store is IT(3) add for EU(2)
        if ($lang_id == 2) {
          array_push($websiteIds, 3);

          // insert english text for EU store
          if ($versionUE != null) {
              $tableTexts = $this->resourceModel->getTable('catalog_product_entity_text');

              $rt = $this->resourceModel->getConnection()->query("SELECT * FROM ".$tableTexts." WHERE `attribute_id` = '75' AND `entity_id` = '".$product->getId()."' AND `store_id` = '3'");

              if ($rt->rowCount() == 0) {
                $con->query("INSERT INTO `".$tableTexts."` (`attribute_id`,`entity_id`,`store_id`, `value`) VALUES ('75', '".$product->getId()."', '3', '".$versionUE['description']."')");
                $con->query("INSERT INTO `".$tableTexts."` (`attribute_id`,`entity_id`,`store_id`, `value`) VALUES ('76', '".$product->getId()."', '3', '".$versionUE['short_description']."')");

              } else {
                $con->query("UPDATE `".$tableTexts."` SET `value` = '".$versionUE['description']."' WHERE `attribute_id` = '75' AND `entity_id` = '".$product->getId()."' AND `store_id` = '3'");
                $con->query("UPDATE `".$tableTexts."` SET `value` = '".$versionUE['short_description']."' WHERE `attribute_id` = '76' AND `entity_id` = '".$product->getId()."' AND `store_id` = '3'");


              }

          }
        }
        $wIds = [];
        $tableWebsite = $this->resourceModel->getTable('catalog_product_website');
        $r = $this->resourceModel->getConnection()->query("SELECT * FROM ".$tableWebsite." WHERE product_id = ".$product->getId());

        foreach($r->fetchAll() as $wId) {
            $wIds[] = (int)$wId['website_id'];
        }

        foreach ($websiteIds as $websiteId) {

            if (!in_array($websiteId, $wIds)) {
                //add those website Ids which were not associated previously
                $con->query("INSERT INTO `".$tableWebsite."` (`product_id`,`website_id`) VALUES ('".$product->getId()."', '".$websiteId."')");

            }
        }
    }

    /**
     * @param Product $product
     * @param ObjectManager $objectManager
     * @return void
     * @throws \Exception
     */
    private function addProductVariations($product, $objectManager) {

      if ($product != null) {
        $attributeSet = $product->getAttributeSetId();
        $sizeAttributeCode = $this->getSizeAttributeCode($attributeSet);
        $id_atelier = $product->getData('id_atelier');
        #$sku = $product->getData('sku');
        #$lang_id = $product->getStore()->getId();
        }

        #$simpleProducts = $this->getProductBySku($sku, Type::TYPE_SIMPLE);

        $simpleProducts = $this->getProductByAtelierId($id_atelier, Type::TYPE_SIMPLE);

        $attributeValues = [];
        $productIds = [];
        $optionsFactory = $objectManager->create(Factory::class);

        foreach ($simpleProducts as $k=>$simpleProduct) {

            if ($simpleProduct->getData($sizeAttributeCode)) {
                $attributeValues[] = [
                    'label' => $this->getAttributeLabelByCode($sizeAttributeCode),
                    'attribute_id' => $this->getAttributeIdByCode($sizeAttributeCode),
                    'value_index' => $simpleProduct->getData($sizeAttributeCode),
                ];
                $productIds[$simpleProduct->getId()] = $simpleProduct->getId();
            }
        }

        $configurableAttributesData = [
            [
                'attribute_id' => $this->getAttributeIdByCode($sizeAttributeCode),
                'code' => $this->getAttributeLabelByCode($sizeAttributeCode),
                'label' => $this->getAttributeLabelByCode($sizeAttributeCode),
                //'position' => '0',
                'values' => $attributeValues,
            ],
        ];

        if (count($productIds) > 0) {

            $configurableOptions = $optionsFactory->create($configurableAttributesData);
            $extensionConfigurableAttributes = $product->getExtensionAttributes();
            $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
            $extensionConfigurableAttributes->setConfigurableProductLinks($productIds);
            $product->setExtensionAttributes($extensionConfigurableAttributes);

            $product->save();
        }

    }

    /**
     * @param int $productId
     * @param string $lang
     * @param string $type
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function getProductByAtelierId($productId, $type = null) {

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*');
        //identify column of id_atelier with id_atelier_key from ConfigEnv.php
        if ($type != null)
        $collection->addAttributeToFilter('type_id',['in'=> $type]);
        $collection->addAttributeToFilter('id_atelier',['in'=> $productId]);

        return $collection;
    }

    /**
     * @param string Sku
     * @param string $lang
     * @param string $type
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function getProductBySku($sku, $type = null) {

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*');
        //identify column of id_atelier with id_atelier_key from ConfigEnv.php


        $collection->addAttributeToFilter('sku',['like'=> ''.$sku.'']);
        //if ($lang_id != null) $collection->addStoreFilter($lang_id);

        if ($type != null)
        $collection->addAttributeToFilter('type_id',['in'=> $type]);

        return $collection;
    }

    /**
     * @param string Sku
     * @param string $lang
     * @param string $type
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function getProductByName($name, $type = null) {

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*');
        //identify column of id_atelier with id_atelier_key from ConfigEnv.php

        $collection->addAttributeToFilter('name',['like'=> ''.$name.'']);
        //if ($lang_id != null) $collection->addStoreFilter($lang_id);

        if ($type != null)
        $collection->addAttributeToFilter('type_id',['in'=> $type]);

        return $collection;
    }


    /**
     * @param $attributeCode
     * @param $attributeValue
     * @return null|string|int
     */
    private function getAttributeValueId($attributeCode, $attributeValue) {

        $option = null;
        try {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);
            $attribute->setStoreId(2);
            $option = $attribute->getSource()->getOptionId($attributeValue);
        } catch (LocalizedException $e) {
            print('Errore: '.$e->getMessage());
        }
        return $option;
    }

    /**
     * @param string $csvPath
     * @return array
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function readCsvFile($csvPath) {

        $csv = new Csv();
        $csv->setInputEncoding('iso-8859-1');
        $csv->setDelimiter($this->configEnv->getEnv('delimiter'));
        //$csv->setDelimiter(",");
        $csv->setEnclosure('"');
        $csv->setSheetIndex(0);
        $csvData = $csv->load($csvPath);
        $sheet = $csvData->getActiveSheet();
        $array = $sheet->toArray();
        return $array;
    }

    /**
     * @param array $categories
     * @param array $attributeColumns
     * @param array $csvRow
     * @param Product $productModel
     * @param Interceptor $productResource
     * @return void
     */
    private function setProductAttributes($categories, $attributeColumns, $csvRow, Product $productModel, Interceptor $productResource, $lang_id = 0) {

        foreach ($attributeColumns as $columnKey => $column) {
            $attrValue = null;
            if ($column['type'] == 2) {
                //values are 1 or 2, 1 for text value 2 for select and other multiple type input values
                $attrValue = $this->getAttributeValueId($column['code'], $csvRow[$columnKey]);
                if($attrValue == null) {
                    //if there is no option add it
                    //this shoudl be deleted later if not required so it will raise an error at staring check validation
                    if ($csvRow[$columnKey] != null) {
                        $this->addOptionToAttribute($column['code'], $csvRow[$columnKey]);
                        $attrValue = $this->getAttributeValueId($column['code'], $csvRow[$columnKey]);
                    }
                }
            } else {
                $attrValue = $csvRow[$columnKey];
            }
            if ($attrValue != null) {
                $productModel->setData($column['code'], $attrValue);
                $productResource->saveAttribute($productModel, $column['code']);
            }
        }

        /* *
         * Special cases that cant be generalised
         * will be listed here with if and else statements
         * */


        if (count($csvRow) > 0) {

            if (isset($csvRow[3]) && isset($csvRow[4])) {
                //atelier model varient ( atelier_model_variant )
                $atelier_model_varient = $csvRow[3] . ' ' . $csvRow[4];
                $productModel->setData('atelier_model_variant', $atelier_model_varient);
                $productResource->saveAttribute($productModel, 'atelier_model_variant');
            }


            if (isset($csvRow[22])) {
              $status = 'enable';

                if ( ($csvRow[22] == 0 && $csvRow[48] == 1) || $csvRow[22] == 2) {
                  $status = 'enable';
                } else {
                  $status = 'disable';
                }

                if ($status == 'enable') {
                  $productModel->setStatus(Status::STATUS_ENABLED, 2);
                } else {
                  $productModel->setStatus(Status::STATUS_DISABLED);
                }
                $productResource->saveAttribute($productModel, 'status');
            }

        }

        if (isset($csvRow[14])) {
            $productModel->setData('short_description', (string)$csvRow[14]);
            $productResource->saveAttribute($productModel, 'short_description');


        }

        if (isset($csvRow[30])) {
          $productModel->setData('meta_title', (string)$csvRow[30]);
          $productResource->saveAttribute($productModel, 'meta_title');

        }
        if (isset($csvRow[31])) {
          $productModel->setData('meta_description', (string)$csvRow[31]);
          $productResource->saveAttribute($productModel, 'meta_description');

        }

        $categoryId = $this->getCategoryId($categories, $csvRow);
        if ($categoryId) {
            $this->categoryLinkManagement->assignProductToCategories($productModel->getSku(),
                [$categoryId]);
        }
        if (isset($csvRow[15])) {
            //set name
            $productModel->setData('name', $csvRow[15]);
            $productResource->saveAttribute($productModel, 'name');
        }

        //set pattern
        if (isset($csvRow[12])) {
          $attr = $productResource->getAttribute('pattern');
          $pattern = $attr->getSource()->getOptionId($csvRow[12]);
          $productModel->setData('pattern', $pattern);
          $productResource->saveAttribute($productModel, 'pattern');

        }

        //set colours
        if (isset($csvRow[23])) {

          $attr = $productResource->getAttribute('color');
          $color = $attr->getSource()->getOptionId($csvRow[23]);

          if ($color) {
            $productModel->setData('color', $color);
            $productResource->saveAttribute($productModel, 'color');
          }

        }


        $price = 0;
        if (isset($csvRow[16])) {
           // remove currency symbol
            $price = $csvRow[16];
            $price = str_replace('$', '', $price);
            $price = str_replace('€', '', $price);
        }
        if ($price < 0 || $price == '') {
            $price = 0;
        }


        $con = $this->resourceModel->getConnection();
        $this->setPrice($productModel, $con, $price, $lang_id);



        if (isset($csvRow[18])) {
          $weight = str_replace('gr','',$csvRow[18]);
          $weight = intval($weight)/1000;
          $productModel->setData('weight',$weight);
          $productResource->saveAttribute($productModel, 'weight');
        }

        $productModel->save();


    }

    /**
     * @param Product $product
     * @param Interceptor $productResource
     * @param Product $configProduct
     * @throws \Exception
     */
    private function setSimpleProductAttributes($product, $productResource, $configProduct, OutputInterface $output) {

        if ($product->getName() != $configProduct->getName() && $configProduct->getName() != null) {
            $product->setName($configProduct->getName());
            $product->save();
        }


        $catIds = $product->getCategoryIds();
        $configCatIds = $configProduct->getCategoryIds();


        foreach ($configCatIds as $configCatId) {

            if (!in_array($configCatId, $catIds)) {
                $this->categoryLinkManagement->assignProductToCategories($product->getSku(),
                    [$configCatId]);

            }


        }

        $columns = $this->configEnv->getEnv('coloumns');

        foreach ($columns as $column) {
            if ($configProduct->getData($column['code']) != null) {

                $product->setData($column['code'], $configProduct->getData($column['code']));
                $productResource->saveAttribute($product, $column['code']);
            }
        }

        if ($configProduct->getData('atelier_model_variant') != null) {

            $productResource->saveAttribute($product, 'atelier_model_variant');
        }
        if ($configProduct->getData('atelier_model_variant') != null) {
            $product->setData('short_description', $configProduct->getData('short_description'));
            $productResource->saveAttribute($product, 'short_description');
        }

        if ($configProduct->getStatus() != null) {
            $product->setStatus($configProduct->getStatus());
            $productResource->saveAttribute($product, 'status');
        }
    }

    private function isProductOfThisEnv($csvRow) {
        $returnFlag = true;
        if (isset($csvRow[22]) && isset($csvRow[42])) {
            if (strtolower($this->configEnv->getEnv('environment')) == 'dev' && strtolower($this->configEnv->getEnv('environment')) == 'stage') {
                if($csvRow[22] == 0 && $csvRow[42] == 0) {
                    $returnFlag = true;
                }
            } elseif (($this->configEnv->getEnv('environment')) == 'prod') {
                if($csvRow[22] == 0 && $csvRow[42] == 1) {
                    $returnFlag = true;
                }
            }
        }

        return $returnFlag;
    }

    /**
     * Retrieve current store categories
     *
     * @param bool|string $sorted
     * @param bool $asCollection
     * @param bool $toLoad
     * @return \Magento\Framework\Data\Tree\Node\Collection|\Magento\Catalog\Model\Resource\Category\Collection|array
     */
    public function getStoreCategories($sorted = false, $asCollection = false, $toLoad = true)
    {
        return $this->categoryHelper->getStoreCategories($sorted , $asCollection, $toLoad);
    }

    /**
     * Retrieve current store categories
     *
     * @param int $parent
     * @param bool $isActive
     * @param bool $level
     * @param bool $sortBy
     * @param bool $pageSize
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     * @throws LocalizedException
     */
    public function getCategoryCollection($parent = null, $isActive = true, $level = false, $sortBy = false, $pageSize = false)
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        if (is_numeric($parent)) {
            $collection->addAttributeToFilter('parent_id', $parent);
        } else {
            $collection->addLevelFilter(2);
        }

        // select only active categories
        if ($isActive) {
            $collection->addIsActiveFilter();
        }

        // select categories of certain level
        if ($level) {
            $collection->addLevelFilter($level);
        }

        // sort categories by some value
        if ($sortBy) {
            $collection->addOrderField($sortBy);
        }
        $collection->setStore($collection->getDefaultStoreId());

        // select certain number of categories
        if ($pageSize) {
            $collection->setPageSize($pageSize);
        }
        return $collection;
    }

    /**
     * Retrieve child store categories
     * @param $category
     * @return array
     */
    public function getChildCategories($category)
    {
        if ($this->categoryState->isFlatEnabled() && $category->getUseFlatResource()) {
            $subcategories = (array)$category->getChildrenNodes();
        } else {
            $subcategories = $category->getChildren();
        }
        return $subcategories;
    }

    /**
     * @param Product $product
     * @param Mysql $con
     * @param float $price
     * @throws LocalizedException
     * @throws \Zend_Db_Adapter_Exception
     */
    private function setPrice($product, $con, $price, $lang_id) {
        $tablePrice = $this->resourceModel->getTable('catalog_product_entity_decimal');

        if ($product->getSpecialPrice() == null) {
        #  $con->query("INSERT INTO `" . $tablePrice . "` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('" . $this->getAttributeIdByCode('price') . "', '0', '" . $product->getId() . "', '" . $price . "')");
        #  $con->query("INSERT INTO `" . $tablePrice . "` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('" . $this->getAttributeIdByCode('cost') . "', '0', '" . $product->getId() . "', '" . $price . "')");
        #  $con->query("INSERT INTO `" . $tablePrice . "` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('" . $this->getAttributeIdByCode('special_price') . "', '0', '" . $product->getId() . "', '" . $price . "')");

            $rt = $this->resourceModel->getConnection()->query("SELECT * FROM ".$tablePrice." WHERE `attribute_id` = ".$this->getAttributeIdByCode('price')." AND `entity_id` = '".$product->getId()."' AND store_id = 0");
            if ($rt->rowCount() == 0)
              $con->query("INSERT INTO `" . $tablePrice . "` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('" . $this->getAttributeIdByCode('price') . "', '0', '" . $product->getId() . "', '" . $price . "')");
            else
              $con->query("UPDATE ".$tablePrice." SET `value` = '".$price."' WHERE attribute_id = ".$this->getAttributeIdByCode('price')." AND entity_id = ".$product->getId());

            $rt = $this->resourceModel->getConnection()->query("SELECT * FROM ".$tablePrice." WHERE `attribute_id` = ".$this->getAttributeIdByCode('special_price')." AND `entity_id` = '".$product->getId()."' AND store_id = 0");
            if ($rt->rowCount() == 0)
              $con->query("INSERT INTO `" . $tablePrice . "` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('" . $this->getAttributeIdByCode('special_price') . "', '0', '" . $product->getId() . "', '" . $price . "')");
            else
              $con->query("UPDATE ".$tablePrice." SET `value` = '".$price."' WHERE attribute_id = ".$this->getAttributeIdByCode('special_price')." AND entity_id = ".$product->getId());

            $rt = $this->resourceModel->getConnection()->query("SELECT * FROM ".$tablePrice." WHERE `attribute_id` = ".$this->getAttributeIdByCode('cost')." AND `entity_id` = '".$product->getId()."' AND store_id = 0");
            if ($rt->rowCount() == 0)
              $con->query("INSERT INTO `" . $tablePrice . "` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('" . $this->getAttributeIdByCode('cost') . "', '0', '" . $product->getId() . "', '" . $price . "')");
            else
              $con->query("UPDATE ".$tablePrice." SET `value` = '".$price."' WHERE attribute_id = ".$this->getAttributeIdByCode('cost')." AND entity_id = ".$product->getId());

        } else {
            $con->query("UPDATE ".$tablePrice." SET `value` = '".$price."' WHERE attribute_id = ".$this->getAttributeIdByCode('price')." AND entity_id = ".$product->getId());
            $con->query("UPDATE ".$tablePrice." SET `value` = '".$price."' WHERE attribute_id = ".$this->getAttributeIdByCode('special_price')." AND entity_id = ".$product->getId());
            $con->query("UPDATE ".$tablePrice." SET `value` = '".$price."' WHERE attribute_id = ".$this->getAttributeIdByCode('cost')." AND entity_id = ".$product->getId());
        }
    }

    /**
     * Add Option to attribute dropdown
     *
     * @param string $attributeCode
     * @param string $value
     * @return void
     */
    private function addOptionToAttribute($attributeCode, $value) {

        $optionId = $this->getAttributeValueId($attributeCode, $value);
        if ($optionId == null) {
            $attribute = $this->attributeRepository->get($attributeCode);

            $categorySetup = $this->categorySetupFactory->create();
            $categorySetup->addAttributeOption(
                [
                    'attribute_id'  => $attribute->getAttributeId(),
                    'order'         => [0],
                    'value'         => [
                        [
                            0 => $value, // store_id => label
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Get Details of attribute sets and its size attribute
     *
     * @param array $csvRow
     * @return int|null
     */
    private function getAttributeSetId($csvRow) {

        $attributeSetId = null;
        if (isset($csvRow[44])) {
            $brand = strtolower($csvRow[2]);
            $category = strtolower($csvRow[7]);
            $sizeName = strtolower($csvRow[44]);

            switch ($sizeName) {
                case "uomo":
                    if ($brand == "jacob cohen") {
                        $attributeSetId = 21;
                    } elseif ($category == 'pantalone') {
                        $attributeSetId = 16;
                    } else {
                        $attributeSetId = 15;
                    }
                    break;
                case "unica":
                    $attributeSetId = 4;
                    break;
                case "donna":
                    $attributeSetId = 14;
                    break;
                case "americana":
                    $attributeSetId = 12;
                    break;
                case "camicie estesa 37/50":
                    $attributeSetId = 11;
                    break;
                case "scarpe inglesi":
                    $attributeSetId = 10;
                    break;
                case "scarpe donna":
                    $attributeSetId = 9;
                    break;
                case "scarpe uomo":
                    $attributeSetId = 10;
                    break;
                case "cinture uomo":
                    $attributeSetId = 22;
                    break;
                case "cinture donna":
                    $attributeSetId = 22;
                    break;
                case "jeans uomo":
                    $attributeSetId = 21;
                    break;
            }
            if ($brand == 'jacob cohen') {
                $attributeSetId = 21;
            }
        }

        return $attributeSetId;
    }

    /**
     * @param string $attributeSetId
     * @return string|null
     */
    private function getSizeAttributeCode($attributeSetId) {

        $attributeCode = null;
        switch ($attributeSetId) {
                case 4:
                    $attributeCode = "size_clothes";
                    break;
                case 21:
                    $attributeCode = "jeans_size";
                    break;
                case 16:
                    $attributeCode = "jeans_size";
                    break;
                case 15:
                    $attributeCode = "size_clothes_men_top";
                    break;
                case 14:
                    $attributeCode = "size_clothes";
                    break;
                case 12:
                    $attributeCode = "size_letters";
                    break;
                case 11:
                    $attributeCode = "size_shirts";
                    break;
                case 10:
                    $attributeCode = "size_shoes_men";
                    break;
                case 9:
                    $attributeCode = "size_shoes_women";
                    break;
                case 22:
                    $attributeCode = "size_belt";
                    break;
            }

        return $attributeCode;
    }
}

<?php
/**
 * Created by Afroze.S.
 * Date: 30/1/18
 * Time: 12:07 PM
 */

namespace Twentyone\CronProducts\Console;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Helper\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Interceptor;
use Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModel;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
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
     * @var ResourceModel
     * @var array
     * @var Attribute
     */
    private $resourceMode,
        $attributes,
        $attributeModel;

    /**
     * Inject CollectionFactory(products) so to query products of magento and filter
     *
     * CronProductsCommand constructor.
     * @param ResourceModel $resourceModel
     * @param State $appState
     * @param ConfigEnv $configEnv
     * @param Config $eavConfig
     * @param Category $categoryHelper
     * @param \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryState
     * @param CollectionFactory $collectionFactory
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param Product $productModel
     * @param ProductRepository $productRepository
     */
    public function __construct(ResourceModel $resourceModel,
                                State $appState,
                                ConfigEnv $configEnv,
                                Config $eavConfig,
                                Category $categoryHelper,
                                \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryState,
                                CollectionFactory $collectionFactory,
                                CategoryLinkManagementInterface $categoryLinkManagement,
                                Product $productModel,
                                ProductRepository $productRepository) {
        try {
            $appState->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
        } catch (LocalizedException $e) {
            var_dump('test');die;
        }
        $this->appState = $appState;
        $this->configEnv = $configEnv;
        $this->eavConfig = $eavConfig;
        $this->categoryHelper = $categoryHelper;
        $this->categoryState = $categoryState;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->collectionFactory = $collectionFactory;
        $this->productModel = $productModel;
        $this->productRepository = $productRepository;
        $this->resourceModel = $resourceModel;
        parent::__construct();
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
        $categories = $this->getStoreCategories();
        foreach ($categories as $category) {
            $categoriesArray[strtolower($category->getName())] = [
                'id' => $category->getId()
            ];
            $childCategories = $this->getChildCategories($category);
            foreach ($childCategories as $childCategory) {
                $subCategories = $this->getChildCategories($childCategory);
                $categoriesArray[strtolower($category->getName())]['children'][strtolower($childCategory->getName())] = [
                    'id' => $childCategory->getId()
                ];
                foreach ($subCategories as $subCategory) {
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
        if ($parent == 'uomo') {
            $parent = 'men';
        } elseif (strtolower($csvRow[5]) == 'donna') {
            $parent = 'women';
        }
        if ($parent) {
            switch (strtolower($csvRow[7])) {
                case 'camica':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['shirts']['id'];
                    break;
                case 'cappello':
                    $categoryId = $categories[$parent]['children']['accessories']['children']['hats']['id'];
                    break;
                case 'capotto':
                    $categoryId = $categories[$parent]['children']['clothes']['children']['shirts']['id'];
                    break;
                case 'cintura':
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
                    $categoryId = $categories[$parent]['children']['clothes']['children']['caots']['id'];
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
                case 'biancheria intima':
                    switch(strtolower($csvRow[8])) {
                        case 'calza':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['perfumes']['id'];
                            }
                            break;
                    }
                    break;
                case 'borse':
                    switch(strtolower($csvRow[8])) {
                        case 'bauletto':
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['perfumes']['id'];
                            }
                            break;
                        case 'borsone':
                            if ($parent == 'men') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['bags']['id'];
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
                            break;
                        case 'shopping':
                            if ($parent == 'women') {
                                $categoryId = $categories[$parent]['children']['accessories']['children']['hangbags']['id'];
                            }
                            break;
                        case 'tracolla':
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
                if ($attrValue == null) {
                    $returnFlag = false;
                    //value doesn't exist in attribute send error output on terminal
                    $output->writeln("Error: Attribute value{" . $csvRow[$columnKey] . "} code{" . $column['code'] . "} doesn't exist, row: " . ($csvKey+1));
                }
            }
        }

        if ($this->getCategoryId($categories, $csvRow) == null) {
            $returnFlag = false;
            $output->writeln("Error: Category doesn't exist, row: " . ($csvKey+1));
        }

        return $returnFlag;
    }

    private function getAttributeIdByCode($code) {
        $id = null;
        if ($this->resourceModel) {
            if (!isset($this->attributes[$code])) {
                $attr = $this->resourceModel->getAttribute($code);
                $id = $attr->getId();
                $this->attributes[$code] = $id;
            } else {
                //so you dont search in database every time just search ones and save in attributes array
                $id = $this->attributes[$code];
            }
        }
        return $id;
    }

    /**
     * This function is executed after the console command is types in terminal
     * get the user entered arguments and options and do the magic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        var_dump($this->appState->getAreaCode());
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $attributeModel = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute');
        $columns = $this->configEnv->getEnv('coloumns');
        $output->writeln('start');
        $output->writeln($this->configEnv->getEnv('csv'));
        try {
            $categories = $this->getCategoriesArray();
            $csvArray = $this->readCsvFile($this->configEnv->getEnv('csv'));
            foreach ($csvArray as $key => $csvRow) {
                if ($this->checkIfDataIsValid($categories, $columns, $csvRow, $key, $output)) {
                    if ($this->isProductOfThisEnv($csvRow)) {
                        //check product if it belongs to current environment
                        $products = $this->getProductByAtelierId((string)$csvRow[$this->configEnv->getEnv('id_atelier_key')]);
                        if ($products->count() < 1) {
                            //Product doesn't exist create new product with ProductModel
                            $this->productModel->setName($csvRow[14])
                                ->setStoreId(2)
                                ->setTypeId('configurable')->afterSave();
                            ;
                            //$this->productModel->setTypeId('simple');
                            /*
                             *
                             * set attribute_set for the new product
                            */
                            $this->productModel->setAttributeSetId(4)
                                ->setSku($this->configEnv->getEnv('product_country') . '-' . $csvRow[$this->configEnv->getEnv('id_atelier_key')] . '-' . $csvRow[3] . ' ' . $csvRow[4])
                                ->setStoreId(2)//->setWebsiteIds([2])
                                ->setTaxClassId(2)
                                ->setData('id_atelier', $csvRow[$this->configEnv->getEnv('id_atelier_key')])
                                ->save()
                            ;

                            //$this->productRepository->save($this->productModel);
                            //$this->productModel->unlockAttributes()->save()->unlockAttribute('website_ids')->unlockAttributes();
                            $productResource = $this->productModel->getResource();
                            $this->setProductAttributes($categories, $columns, $csvRow, $this->productModel, $productResource);
                            $con = $this->resourceModel->getConnection();
                            $tableWebsite = $this->resourceModel->getTable('catalog_product_website');
                            $tablePrice = $this->resourceModel->getTable('catalog_product_entity_decimal');
                            $con->query("INSERT INTO `".$tableWebsite."` (`product_id`,`website_id`) VALUES ('".$this->productModel->getId()."', '2')");
                            $con->query("INSERT INTO `".$tablePrice."` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('".$this->getAttributeIdByCode('price')."', '0', '".$this->productModel->getId()."', '".$csvRow[16]."')");
                            //$p = $this->productModel->load($this->productModel->getId());
                            //$p->setWebsiteIds([2])->afterSave();
                            /** @var \Magento\ConfigurableProduct\Api\Data\OptionInterface $option */
                            $option = $objectManager->create(\Magento\ConfigurableProduct\Api\Data\OptionInterface::class);
                            $option->setLabel('Size');
                            $option->setAttributeId($this->getAttributeIdByCode('size_clothes'));
                            $option->setValues([$this->getAttributeValueId('size_clothes', '30')]);

                            $exteAttrs = $this->productModel->getExtensionAttributes();
                            $exteAttrs->setConfigurableProductLinks([2625]);
                            $exteAttrs->setConfigurableProductOptions([
                                $option
                            ]);

                            $this->productModel->save();

                        } else {
                            foreach ($products->getItems() as $product) {
                                if (strtolower($product->getTypeId()) != 'simjple') {
                                    //$product->getData('category_ids');
                                    $productResource = $product->getResource();
                                    $this->setProductAttributes($categories, $columns, $csvRow, $product, $productResource);
                                    //$product->save();
                                }
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

    /**
     * @param int $productId
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function getProductByAtelierId($productId) {

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*');
        //identify column of id_atelier with id_atelier_key from ConfigEnv.php
        $collection->addAttributeToFilter('id_atelier',['in'=> $productId]);
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
            $option = $attribute->getSource()->getOptionId($attributeValue);
        } catch (LocalizedException $e) {
            print('Error: '.$e->getMessage());
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
        $csv->setDelimiter(',');
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
     * @throws \Exception
     */
    private function setProductAttributes($categories, $attributeColumns, $csvRow, Product $productModel, Interceptor $productResource) {
        foreach ($attributeColumns as $columnKey => $column) {
            $attrValue = null;
            if ($column['type'] == 2) {
                //values are 1 or 2, 1 for text value 2 for select and other multiple type input values
                $attrValue = $this->getAttributeValueId($column['code'], $csvRow[$columnKey]);
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
                if ($csvRow[22] == 1) {
                    $productModel->setData('status', 2);
                    $productResource->saveAttribute($productModel, 'status');
                } elseif ($csvRow[22] == 0) {
                    $productModel->setData('status', 1);
                    $productResource->saveAttribute($productModel, 'status');
                }
            }
        }
        if (isset($csvRow[15])) {
            $productModel->setData('short_description', (string)$csvRow[15]);
            $productResource->saveAttribute($productModel, 'short_description');
            //$productModel->setData('price', $csvRow[16]);
            //$productResource->saveAttribute($productModel, 'price');
        }
        $categoryId = $this->getCategoryId($categories, $csvRow);
        if ($categoryId) {
            $this->categoryLinkManagement->assignProductToCategories($productModel->getSku(),
                [$categoryId]);
        }
        if (isset($csvRow[14])) {
            //set name
            $productModel->setName($csvRow[14]);
            //$productModel->setPrice($csvRow[16]);
            $productModel->save();
        }

        $price = 0;
        if (isset($csvRow[16])) {
            $price = $csvRow[16];
        }
        if ($price < 0 || $price == '') {
            $price = 0;
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
}
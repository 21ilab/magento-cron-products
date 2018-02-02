<?php
/**
 * Created by Afroze.S.
 * Date: 30/1/18
 * Time: 12:07 PM
 */

namespace Twentyone\CronProducts\Console;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use MagentoEnv\Entity\ConfigEnv;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class CronProductsCommand extends Command
{

    protected $path, $appState, $attributes, $labels, $delimiter, $encapsulator, $configEnv, $eavConfig, $collectionFactory;

    /**
     * Inject CollectionFactory(products) so to query products of magento and filter
     *
     * CronProductsCommand constructor.
     * @param State $appState
     * @param ConfigEnv $configEnv
     * @param Config $eavConfig
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(State $appState, ConfigEnv $configEnv, Config $eavConfig, CollectionFactory $collectionFactory) {
        $this->appState = $appState;
        try {
            $this->appState->setAreaCode('crontab');
        } catch (LocalizedException $e) {
            print_r($e->getMessage()."\n");
            print_r("Area code not set\n");
        }
        $this->configEnv = $configEnv;
        $this->eavConfig = $eavConfig;
        $this->collectionFactory = $collectionFactory;

        parent::__construct();
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
     * This function is executed after the console command is types in terminal
     * get the user entered arguments and options and do the magic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        $columns = $this->configEnv->getEnv('coloumns');
        $output->writeln('start');
        $output->writeln($this->configEnv->getEnv('csv'));
        try {
            $csvArray = $this->readCsvFile($this->configEnv->getEnv('csv'));

            foreach ($csvArray as $key => $csvRow) {
                $products = $this->getProductByAtelierId($csvRow[$this->configEnv->getEnv('id_atelier_key')]);
                foreach ($products as $product) {
                    if (strtolower($product->getTypeId()) != 'simple') {
                        foreach ($columns as $columnKey => $column) {
                            $attrValue = null;
                            if ($column['type'] == 2) {
                                //values are 1 or 2, 1 for text value 2 for select and other multiple type input values
                                $attrValue = $this->getAttributeValueId($column['code'], $csvRow[$columnKey]);
                                if ($attrValue == null) {
                                    //value doesn't exist in attribute send error output on terminal
                                    $output->writeln("Error: Attribute value{" . $csvRow[$columnKey] . "} code{" . $column['code'] . "} doesn't exist, row: " . $key);
                                }
                            } else {
                                $attrValue = $csvRow[$columnKey];
                            }
                            if ($attrValue != null) {
                                $product->setData($column['code'], $attrValue);
                            }
                        }
                        $product->save();
                    }
                }
            }
        } catch (Exception $e) {
            $output->writeln("Error: ".$e->getMessage());
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            $output->writeln("Error: ".$e->getMessage());
        } catch (LocalizedException $e) {
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
        $collection->addAttributeToFilter('id_atelier', $productId);

        return $collection;
    }

    /**
     * @param $attributeCode
     * @param $attributeValue
     * @return null|string|int
     */
    private function getAttributeValueId($attributeCode, $attributeValue) {

        try {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);
        } catch (LocalizedException $e) {
            var_dump($attributeCode);die;
        }
        try {
            $option = $attribute->getSource()->getOptionId($attributeValue);
        } catch (LocalizedException $e) {
            var_dump($attributeValue);die;
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
}
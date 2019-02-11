CronProducts
===

Requirements
---
This project uses ConfigEnv(ahmed-oz/config-env) so to have environment specific variables

Setup
---
* ```php bin/magento module:enable Twentyone_CronProducts```
* ```php bin/magento setup:upgrade```
* ```php bin/magento setup:di:compile```

Working around
---
This is magento 2 module, it is supposed to be used as cron to import products to magento from CSV/TXT files of atelier.
Please check Console/CronProductsCommand class execute function this is symfony command which takes three csv files of atelier and imports them to magento, to indentify columns of Prodotti.txt it uses <b>ConfigEnv.php(app/etc/ConfigEnv.php)</b>
 
Usage
---
```php bin/magento Twentyone:CronProducts```
Use above command in your bash script which you will add as cron


Usable attributes
---
```
private $languages = [2='IT'];
````
Array of languages to be insert. Key number is the Store number from Magento

```protected function execute(InputInterface $input, OutputInterface $output)```

Get the files for Produtcs, disponibilitaty and images
```
$fileProdotti = $this->configEnv->getEnv('csv');
$fileDisponibilita = $this->configEnv->getEnv('availability_csv');
$fileImages = $this->configEnv->getEnv('images_csv');
```

sku creation:
```$sku = $language . '-' . $csvRow[0] . '-' . $csvRow[3] . $csvRow[4];```

```private function setProductAttributes($categories, $attributeColumns, $csvRow, Product $productModel, Interceptor $productResource, $lang_id = 0)```
Save information about product attributes

Usable CSV Row positions:
---
[0]  = ID atelier
[3]  = atelier model variant part 1
[4]  = atelier model variant part 2
[5]  = Category
[8]  = Subcategory
[12] = Pattern
[14] = short description
[15] = name
[16] = Price
[18] = Weight
[22] = Enable/Disable (based on [48])
[23] = Color
[48] = Enable/Disable




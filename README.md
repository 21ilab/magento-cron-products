ExportProducts
===

Setup
---
* ```php bin/magento module:enable Twentyone_ExportProducts```
* ```php bin/magento setup:upgrade```
* ```php bin/magento setup:di:compile```

Working around
---
This is magento 2 module, it exports products of magento 2 as csv, it uses symfony console command to do the job.
Check ```app/code/Twentyone/ExportProducts/Console/ExportProductsCommand.php```
 this class extends Commad
 
Usage
---
```php bin/magento Twentyone:Exportroducts "{path}" "{store}" "{attributes}" "{labels}" "{delimiter}" "{encapsulator}"```
 
Composer Require
---
Please require below packages in magento 2(composer json)
```composer require phpoffice/phpspreadsheet```
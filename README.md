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
Please check Console/CronProductsCommand class execute function this is symfony command which takes three csv files of atelier and imports them to magento, to indentify columns of Prodotti.txt it uses ConfigEnv.php(app/etc/ConfigEnv.php)
 
Usage
---
```php bin/magento Twentyone:CronProducts```
Use above command in your bash script which you will add as cron
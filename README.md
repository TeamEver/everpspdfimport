# Ever PS mass import module for files attached to products (Prestashop 1.6 - 1.7)

This module will allow you to mass add documents to your store and associate them with products automatically.

It also cleans the database of all saved attachments, but does not delete them from your server.

https://www.team-ever.com/prestashop-module-import-en-masse-pdf-produits/

## Prestashop mass import module for files attached to products
This free module allows you to bulk import files attached to products on Prestashop 1.6 up to 1.7

[You can make a donation to support the development of free modules by clicking on this link](https://www.paypal.com/donate?hosted_button_id=3CM3XREMKTMSE)

## Files naming
Your files must be named with a unique code that can be found on your store. Or two types of codes:
- the EAN13 code
- the product reference
You can add a prefix preceding the name of the file, the module proposing to manage this in order to avoid too tedious manual manipulation

## Storage of files attached to products
Once your files are properly named, open your Filezilla or WinSCP software, and create a directory dedicated to these files. Be sure to name it without accents, special characters, or spaces, as the web directory naming standards imply.

The directory must imperatively be at the root of your store.

Place all of your files there so that the module can be able to detect and import them into your Prestashop store, and associate them with your products.

## Module configuration and import
Now go to the configuration of the module in order to configure the various fields.

First determine whether the name of your files is based on the product's EAN13 or its reference, from a simple "Yes / No" button.

Then enter the name of the directory in which the module should search, and which should be at the root of your store.

If, however, your files have a prefix - which can happen with some providers - fill in the prefix used. The module will use it to manage the association with the products.

You can also specify the prefix of your product references, if by chance you have one that consequently makes a difference with the name of your files (whether they have prefixes or not)

Click to save, or directly on the import module which will immediately process the detected documents. Be careful though, if you have a lot of files, make sure you have a large value on your server for the max_execution_time. This will allow the module to operate for a longer period and associate more documents with your products.
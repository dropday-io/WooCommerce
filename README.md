WooCommerce Plugin for Dropday
===============

## How to install the module?

* Download the .zip file from GitHub repository;
* Go to the admin area of your WooCommerce (WordPress) webshop;
* In the admin area, go to 'Plugins' and then 'Add New' and choose 'Upload Plugin';
* Click on 'select file', upload the .zip file and click on 'Install Now';
* Click on 'Activate Plugin'.

## Configuration

Settings &rarr; Dropday Order Sync

* Enter your API-key and Account ID from your Dropday Dashboard;
* Check 'Live mode' if you tested the Plugin.

## Adcanced usage: Custom variables

To pass your customer variable to the API, you can use a filter. In, for example, `functions.php` you can add the following:

```php
/**
 * Get the first product tag of the product
 * 
 * Alert: this only works well if you have one product tag
 *        since the Dropday API only allows a string type
 * 
 * @param   $supplier_name  Orginal input of the Dropday plugin
 * @param   $item           Line item of order
 * */
function dropday_change_supplier($supplier_name, $item)
{
    foreach($item->get_product()->tag_ids as $tag) {
       // returns the first item of all tags directly.
       return get_term($tag)->name;
    }
}

add_filter('dropday_get_supplier', 'dropday_change_supplier', 10, 2);
```

This example manipulates the supplier. In this case, the _supplier_ name is converted to first _product_tag_.  

## Developing

How to get started developing or testing this plugin?

### Requirements

- Docker
- Git

### Installation

1. Make a directory: `mkdir dropday-woocommerce`
2. Enter it: `cd dropday-woocommerce`
3. Clone the repo in a directory `git clone https://github.com/dropday-io/woocommerce.git .`
4. Run `make install`
5. Once the container is ready, go to http://localhost:8080
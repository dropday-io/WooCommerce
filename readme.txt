=== Dropday - Dropship Order Automation ===
Contributors: dropday
Tags: dropshipping, automation, order automation, email to suppliers
Requires at least: 5.0
Tested up to: 6.9
WC requires at least: 3.2
WC tested up to: 9.4
Requires PHP: 7.0
Requires Plugins: woocommerce
Stable tag: 1.1.1
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Automatically sync your WooCommerce orders to Dropday for dropship order automation.

== Description ==

Dropday - Automatically synchronizes your WooCommerce orders to the Dropday platform, enabling seamless dropship order processing and automation.

= Features =

* Automatic order synchronization when orders are paid
* Support for various cost-of-goods plugins
* Configurable product meta data whitelist
* Delivery date support from popular delivery date plugins
* Live/Test mode toggle

== Installation ==

1. Download the Dropday plugin
2. Go to the admin area of your store
3. In the admin area, go to 'Plugins' and then 'Add New' and choose 'Upload Plugin'
4. Click on 'select file', upload the .zip file and click on 'Install Now'
5. Click on 'Activate Plugin'

== Configuration ==

Go to Settings → Dropday

1. Enter your API Key and Account ID from your Dropday Dashboard
2. Check 'Live mode' when you are ready to process real orders

== External Services ==

This plugin connects to the Dropday API to synchronize your WooCommerce orders for dropship order automation.
Endpoint: https://dropday.io/api/v1
Documentation: https://docs.dropday.io/

= What data is sent =

When an order is marked as paid in WooCommerce, the following data is sent to the Dropday API:

* Order ID and total amount
* Customer shipping address (name, company, address, city, postcode, country, phone)
* Customer email address
* Shipping method and cost
* Customer notes
* Delivery date (if available from delivery date plugins)
* Product information (name, SKU, quantity, price, image, brand, category)
* Product purchase price (if available from cost-of-goods plugins)

= When data is sent =

Order data is sent automatically when:

* An order status changes to a paid status in WooCommerce

= Service Provider =

This service is provided by Dropday.

* Service website: https://get.dropday.io
* Terms of Service: https://get.dropday.io/terms-of-service
* Privacy Policy: https://get.dropday.io/terms-of-service

== Frequently Asked Questions ==

= Where do I find my API Key and Account ID? =

You can find your API Key and Account ID in your Dropday Dashboard at https://dropday.io

= Does this plugin require WooCommerce? =

Yes, this plugin requires WooCommerce to be installed and activated.

== Changelog ==

= 1.1.0 =
* Updated plugin name for compliance
* Added Requires Plugins header for WooCommerce dependency
* Improved internationalization with proper text domain
* Security improvements with proper escaping
* Updated compatibility to WordPress 6.9 and WooCommerce 9.4

= 1.0.3 =
* Added configurable delivery date meta fields
* Added configurable purchase price meta fields
* Added product meta data whitelist feature

= 1.0.2 =
* Initial public release

== Upgrade Notice ==

= 1.1.0 =
This version includes important security and compliance updates. Please update immediately.

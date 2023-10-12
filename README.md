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

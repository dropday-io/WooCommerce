Dropday - Order Automation
===============

## How to install the module?

* Download the .zip file from GitHub repository;
* Go to the admin area of your store;
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

### Docker

For the latest WordPress 7.x:

```
git clone https://github.com/dropday-io/woocommerce.git dropday-woocommerce
cd dropday-woocommerce
docker compose up
```

For another version, use any [WordPress image tag](https://hub.docker.com/_/wordpress/tags):

```
docker compose down --volumes && export TAG=6.1 && docker compose up
```

### Setup

1. Once the container is ready, go to http://localhost:8080
2. Complete WordPress installation by setting admin credentials.
3. Log in with your new credentials.
4. In Plugins → Add New, install and activate WooCommerce.
5. Make sure Dropday is activated in Plugins.
6. Go to Settings → Dropday Order Sync and enter your API-key and Account ID.




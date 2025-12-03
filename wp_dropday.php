<?php

/**
 * Plugin Name: Dropday - Dropship Order Automation
 * Plugin URI: https://get.dropday.io/
 * Description: Automate dropship orders by email, FTP, EDI or API to your suppliers. Seamless integration for your webshop.
 * Version: 1.1.0
 * Tested up to: 6.7
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 * Author: Dropday
 * Author URI: https://get.dropday.io/
 * Developer: Dropday
 * Developer URI: https://get.dropday.io/
 * Text Domain: wp_dropday
 * Domain Path: /languages
 *
 * Copyright: © 2024 Dropday, Nl.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Dropday\WooCommerce\Order;

defined('ABSPATH') || exit;
require_once(__DIR__ . '/inc/Plugin.php');
	
(new Plugin(
        __FILE__, 
        'DropdayOrder', 
        '', 
        '1.1.0'
    )
)->register();

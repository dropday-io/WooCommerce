<?php

/**
 * Plugin Name: Dropday for WooCommerce
 * Plugin URI: https://get.dropday.io/
 * Description: Order synchronisation with Dropday drop-shipping automation.
 * Version: 1.0.2
 * Tested up to: 5.6
 * WC requires at least: 3.2
 * WC tested up to: 4.8
 * Author: Contact Dropday Support
 * Author URI: https://get.dropday.io/contact
 * Developer: Sergio Talom
 * Developer URI: https://get.dropday.io/
 * Text Domain: wp_dropday
 * Domain Path: /languages
 *
 * Copyright: © 2021 Dropday, Nl.
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
        '1.0.2'
    )
)->register();

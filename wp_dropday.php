<?php

/**
 * Plugin Name: Dropday for WooCommerce
 * Plugin URI: https://dropday.nl
 * Description: Order synchronisation with Dropday drop-shipping automation.
 * Version: 1.0.1
 * Tested up to: 5.6
 * WC requires at least: 3.2
 * WC tested up to: 4.8
 * Author: Dropday support@dropday.n
 * Author URI: https://dropday.nl/
 * Developer: Sergio Talom
 * Developer URI: http://dropday.nl/
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
        sprintf('%s<br/><a href="%s" target="_blank">%s</a><br/><a href="%s" target="_blank">%s</a>', 
            __('Dropday Order Sync', 'wp_dropday'),
            'https://dropday.com/contact-us/',
            __('Do you have any questions or requests?', 'wp_dropday'),
            'https://wordpress.org/plugins/wc-wp-dropday/', 
            __('Do you like our plugin and can recommend to others?', 'wp_dropday')),
        '1.0.2'
    )
)->register();
<?php
/**
 * Description of Plugin
 *
 * @author Sergio
 */
namespace Dropday\WooCommerce\Order;

if (!class_exists('\\Dropday\\WooCommerce\\Order\\Plugin')):
    
    class Plugin
    {
        protected $id;
    	protected $mainMenuId;
    	protected $adapterName;
    	protected $title;
    	protected $description;
    	protected $optionKey;
    	protected $settings;
    	protected $adapter;
    	protected $pluginPath;
    	protected $version;
        protected $image_format = 'full';
        protected $api_uri = 'https://dropday.io/api/v1/';
        
        public function __construct($pluginPath, $adapterName, $description = '', $version = null) {
            $this->id = str_replace('-pro', '', basename($pluginPath, '.php'));
            $this->pluginPath = $pluginPath;
            $this->adapterName = $adapterName;
            $this->description = '';
            $this->version = $version;
            $this->optionKey = '';
            $this->settings = array(
                'live' => '1',
                'accountId' => '',
                'apiKey' => '',
                'notifyForStatus' => array(),
                'completeOrderForStatuses' => array()
            );

            $this->mainMenuId = 'options-general.php';
            $this->title = 'Dropday';
        }
        
        private function test()
        {
            $this->handleOrder(26);
        }

        public function getApiUrl($type = '') {
            if ($type) {
                return trim($this->api_uri, '/') . '/' . $type;
            } else {
                return trim($this->api_uri, '/');
            }
        }
        
        public function register()
	    {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');

            // do not register when WooCommerce is not enabled
            if (!is_plugin_active('woocommerce/woocommerce.php')) {
                return;
            }

            $proPluginName = preg_replace('/(\.php|\/)/i', '-pro\\1', plugin_basename($this->pluginPath));
            if (is_plugin_active($proPluginName)) {
                return;
            }

            if (is_admin()) {
                add_action('admin_menu', array($this, 'onAdminMenu'));
                add_action( 'admin_init', array($this, 'dropdaySettingsInit') );
            }

            add_filter('plugin_action_links_' . plugin_basename($this->pluginPath), array($this, 'onPluginActionLinks'), 1, 1);
            add_filter('plugin_row_meta', array($this, 'onPluginRowMeta'), 10, 2);
            add_action('init', array($this, 'onInit'), 5);
            add_action('woocommerce_order_status_changed', array($this, 'onOrderStatusChanged'), 10, 3);
	   }
	
    	public function onAdminMenu()
    	{
            add_submenu_page($this->mainMenuId, $this->title, $this->title, 'manage_options', 'admin-' . $this->id, array($this, 'displaySettingForm'));
	   }

    	public function onPluginActionLinks($links)
    	{
            $link = sprintf('<a href="%s">%s</a>', admin_url('options-general.php?page=admin-' . esc_attr($this->id)), __('Settings', 'dropday-for-woocommerce'));
            array_unshift($links, $link);
            return $links;
    	}

        public function onPluginRowMeta($links, $file)
        {
            if (plugin_basename($this->pluginPath) === $file) {
                $links[] = sprintf('<a href="%s" target="_blank">%s</a>', 'https://get.dropday.io/contact', __('Questions or requests?', 'dropday-for-woocommerce'));
                $links[] = sprintf('<a href="%s" target="_blank">%s</a>', 'https://wordpress.org/plugins/dropday-for-woocommerce/', __('Rate this plugin', 'dropday-for-woocommerce'));
            }
            return $links;
        }

        public function onInit()
    	{
            $this->loadSettings();
    	}
        
        protected function loadSettings()
    	{		
            $this->settings = get_option( $this->id );
    	}
        
        public function dropdaySettingsInit() {
            register_setting(
                $this->id,
                $this->id,
                array( $this, 'sanitize' )
            );

            add_settings_section(
                $this->id.'_section_developers',
                __( 'API Settings', 'dropday-for-woocommerce' ), array($this, 'dropdaySectionDevelopers'),
                $this->id
            );

            add_settings_field(
                $this->id.'_live',
                __( 'Live mode', 'dropday-for-woocommerce' ),
                array($this, 'dropdayFieldLiveModeCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_live',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );
            add_settings_field(
                $this->id.'_apiKey',
                __( 'API Key', 'dropday-for-woocommerce' ),
                array($this, 'dropdayFieldApiKeyCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_apiKey',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );
            
            add_settings_field(
                $this->id.'_accountId',
                __( 'Account ID', 'dropday-for-woocommerce' ),
                array($this, 'dropdayFieldAccountIdCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_accountId',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );

            add_settings_field(
                $this->id.'_metaWhitelist',
                __( 'Product Meta Data Whitelist', 'dropday-for-woocommerce' ),
                array($this, 'dropdayFieldMetaWhitelistCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_metaWhitelist',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );

            add_settings_field(
                $this->id.'_purchasePriceMeta',
                __( 'Purchase Price Meta Data', 'dropday-for-woocommerce' ),
                array($this, 'dropdayFieldPurchasePriceMetaCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_purchasePriceMeta',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );

            add_settings_field(
                $this->id.'_deliveryDateMeta',
                __( 'Delivery Date Meta Data', 'dropday-for-woocommerce' ),
                array($this, 'dropdayFieldDeliveryDateMetaCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_deliveryDateMeta',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );
        }
        
        public function sanitize( $input )
        {
            $new_input = array();
            if (isset($input['apiKey'])) {
                $new_input['apiKey'] = sanitize_text_field($input['apiKey']);
            }

            if (isset($input['accountId'])) {
                $new_input['accountId'] = sanitize_text_field($input['accountId']);
            }

            if (isset($input['live'])) {
                $new_input['live'] = absint($input['live']);
            }

            if (isset($input['metaWhitelist'])) {
                $new_input['metaWhitelist'] = sanitize_text_field($input['metaWhitelist']);
            }

            if (isset($input['purchasePriceMeta'])) {
                $new_input['purchasePriceMeta'] = sanitize_text_field($input['purchasePriceMeta']);
            }

            if (isset($input['deliveryDateMeta'])) {
                $new_input['deliveryDateMeta'] = sanitize_text_field($input['deliveryDateMeta']);
            }

            return $new_input;
        }
        
        public function dropdaySectionDevelopers( $args ) {
            ?>
                <p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Please enter your API settings below:', 'dropday-for-woocommerce' ); ?></p>
            <?php
        }
        
        public function dropdayFieldLiveModeCb()
        {
            $id_escaped = esc_attr($this->id);
            printf(
                '<input type="checkbox" id="%s_live" name="%s[live]" value="1" %s />',
                $id_escaped,
                $id_escaped,
                ( isset( $this->settings['live'] ) && $this->settings['live'] ) ? 'checked' : ''
            );
        }
        
        public function dropdayFieldApiKeyCb()
        {
            $id_escaped = esc_attr($this->id);
            printf(
                '<input type="text" class="large-text" id="%s_apiKey" name="%s[apiKey]" value="%s" />',
                $id_escaped,
                $id_escaped,
                isset( $this->settings['apiKey'] ) ? esc_attr( $this->settings['apiKey']) : ''
            );
        }
        
        public function dropdayFieldAccountIdCb()
        {
            $id_escaped = esc_attr($this->id);
            printf(
                '<input type="text" class="small-text" id="%s_accountId" name="%s[accountId]" value="%s" />',
                $id_escaped,
                $id_escaped,
                isset( $this->settings['accountId'] ) ? esc_attr( $this->settings['accountId']) : ''
            );
        }

        public function dropdayFieldMetaWhitelistCb()
        {
            $id_escaped = esc_attr($this->id);
            printf(
                '<input type="text" class="large-text" id="%s_metaWhitelist" name="%s[metaWhitelist]" value="%s" placeholder="supplier_code, purchase_price" />
                <p class="description">%s</p>',
                $id_escaped,
                $id_escaped,
                isset( $this->settings['metaWhitelist'] ) ? esc_attr( $this->settings['metaWhitelist']) : '',
                esc_html__('Comma-separated list of product meta field names to include in orders sent to Dropday.', 'dropday-for-woocommerce')
            );
        }

        public function dropdayFieldPurchasePriceMetaCb()
        {
            $id_escaped = esc_attr($this->id);
            $default_keys = '_wc_cog_cost, _alg_wc_cog_cost, _wcj_purchase_price, _purchase_price';
            printf(
                '<input type="text" class="large-text" id="%s_purchasePriceMeta" name="%s[purchasePriceMeta]" value="%s" placeholder="%s" />
                <p class="description">%s</p>',
                $id_escaped,
                $id_escaped,
                isset( $this->settings['purchasePriceMeta'] ) ? esc_attr( $this->settings['purchasePriceMeta']) : esc_attr($default_keys),
                esc_attr($default_keys),
                esc_html__('Comma-separated list of meta field names to check for purchase price (checked in order). Common keys: _wc_cog_cost (Cost of Goods by SkyVerge), _alg_wc_cog_cost (WPFactory), _wcj_purchase_price (Booster), _purchase_price (custom).', 'dropday-for-woocommerce')
            );
        }

        public function dropdayFieldDeliveryDateMetaCb()
        {
            $id_escaped = esc_attr($this->id);
            $default_keys = 'Delivery Date, _orddd_lite_timestamp, delivery_date, _delivery_date, jckwds_date, order_delivery_date';
            printf(
                '<input type="text" class="large-text" id="%s_deliveryDateMeta" name="%s[deliveryDateMeta]" value="%s" placeholder="%s" />
                <p class="description">%s</p>',
                $id_escaped,
                $id_escaped,
                isset( $this->settings['deliveryDateMeta'] ) ? esc_attr( $this->settings['deliveryDateMeta']) : esc_attr($default_keys),
                esc_attr($default_keys),
                esc_html__('Comma-separated list of order meta field names to check for delivery date (checked in order). Common keys: Delivery Date (Order Delivery Date Lite), jckwds_date (Iconic Delivery Slots), delivery_date (generic).', 'dropday-for-woocommerce')
            );
        }
        
        public function displaySettingForm() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( isset( $_GET['settings-updated'] ) ) {
                // add settings saved message with the class of "updated"
                add_settings_error( 'wporg_messages', 'wporg_message', __( 'Settings Saved', 'dropday-for-woocommerce' ), 'updated' );
            }

            settings_errors( 'wporg_messages' );
            $this->settings = get_option( $this->id );
            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <form action="options.php" method="post">
                    <?php
                    settings_fields( $this->id );
                    do_settings_sections( $this->id );
                    // output save settings button
                    submit_button( 'Save Settings' );
                    ?>
                </form>
            </div>
            <?php
        }
        
        public function onOrderStatusChanged($order_id, $old_status, $new_status)
        {
            $this->handleOrder($order_id);
        }
        
        public function handleOrder($order_id)
        {
            if (!$order_id ) {
                return false;
            }

            $order = wc_get_order( $order_id );
            if ( $order && $order->is_paid()) {
                $order_data = array(
                    'external_id' => ''.$order_id,
                    'source' => get_bloginfo('name'),
                    'total' => $order->get_total(),
                    'shipping' => $this->getShippingData($order),
                    'email' => $order->get_billing_email(),
                    'shipping_address' => array(
                        'first_name' => $order->get_shipping_first_name(),
                        'last_name' => $order->get_shipping_last_name(),
                        'company_name' => $order->get_shipping_company(),
                        'address1' => $order->get_shipping_address_1(),
                        'address2' => ($order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_shipping_address_2()),
                        'postcode' => $order->get_shipping_postcode(),
                        'city' => $order->get_shipping_city(),
                        'state' => $order->get_shipping_state(),
                        'country' => $order->get_shipping_country() && isset(WC()->countries->countries[$order->get_shipping_country()]) ? WC()->countries->countries[$order->get_shipping_country()] : '',
                        'phone' => $order->get_billing_phone(),
                    ),
                    'products' => array()
                );

                if (!$this->settings['live']) {
                    $order_data['test'] = true;
                }

                $products = $order->get_items();
                foreach ($products as $item_id => $item) {
                    $product = $item->get_product();
                    $product_id = $product->get_id();
                    
                    $terms = get_the_terms( $product_id, 'product_cat' );
                    $cat = 'Home';
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        $cat = $terms[0]->name;
                    }
                    $terms = get_the_terms( $product_id, 'product_brand' );
                    $brand_name = '';
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        $brand_name = $terms[0]->name;
                    }
                    
                    $image_url = wp_get_attachment_image_url( $product->get_image_id(), $this->image_format );
                    $p = array(
                        'external_id' => ''.$item->get_id(),
                        'name' => ''.$item->get_name(),
                        'reference' => ''.$product->get_sku(),
                        'quantity' => (int) $item->get_quantity(),
                        'price' => (float) $product->get_price(),
                        'image_url' => $image_url ? $image_url : '',
                        'brand' => ''.$brand_name,
                        'category' => ''.$cat,
                        'supplier' => '',
                    );

                    // Add purchase price if available from various cost-of-goods plugins
                    $purchase_price = $this->getProductPurchasePrice($product_id);
                    if ($purchase_price !== null) {
                        $p['purchase_price'] = $purchase_price;
                    }

                    // Get whitelisted meta fields and add to 'custom' field
                    $whitelist = array();
                    if (!empty($this->settings['metaWhitelist'])) {
                        $whitelist = array_filter(array_map('trim', explode(',', $this->settings['metaWhitelist'])));
                    }
                    
                    if (!empty($whitelist)) {
                        $custom_data = array();
                        foreach ($whitelist as $field_name) {
                            $value = get_post_meta($product_id, $field_name, true);
                            if ($value !== '' && $value !== false) {
                                $custom_data[$field_name] = $value;
                            }
                        }
                        
                        if (!empty($custom_data)) {
                            $p['custom'] = $custom_data;
                        }
                    }

                    $order_data['products'][] = $p;
                }

                $response = $this->postOrder($order_data);

                $context = array( 'source' => $this->id );
                $logger = wc_get_logger();

                if (is_wp_error($response)) {
                    $logger->info( '[dropday] error order#'.$order_id.': ' . $response->get_error_message(), $context );
                    $order->add_order_note( $response->get_error_message() );
                } else {
                    $result = json_decode($response['body']);

                    if ($response['response']['code'] == 200) {
                        $logger->info( '[dropday] Order created :#'.$order_id.': ', $context );
                    } elseif ($response['response']['code'] == 422) {
                        $logger->warning( '[dropday] error order#'.$order_id.': ' . json_encode($result->errors), $context );
                        if (isset($result->errors) && !empty($result->errors)) {
                            foreach ($result->errors as $key => $error) {
                                foreach ($error as $message) {
                                    $order->add_order_note( $message );
                                }
                            }
                        }
                    } else {
                        $logger->warning( '[dropday] error order#'.$order_id.': response code ' . $response['response']['code'], $context );
                        $order->add_order_note( 'Unknown error in Dropday API, response code ' . $response['response']['code'] );
                    }
                }
            }
        }

        /**
         * Get the purchase/cost price for a product from various cost-of-goods plugins.
         * 
         * Checks meta keys configured in settings (in order of priority).
         * Default keys: _wc_cog_cost, _alg_wc_cog_cost, _wcj_purchase_price, _purchase_price
         *
         * @param int $product_id The product ID to get the purchase price for
         * @return float|null The purchase price or null if not found
         */
        protected function getProductPurchasePrice($product_id)
        {
            $default_keys = '_wc_cog_cost, _alg_wc_cog_cost, _wcj_purchase_price, _purchase_price';
            $meta_keys_string = isset($this->settings['purchasePriceMeta']) && !empty($this->settings['purchasePriceMeta']) 
                ? $this->settings['purchasePriceMeta'] 
                : $default_keys;
            
            $meta_keys = array_filter(array_map('trim', explode(',', $meta_keys_string)));

            foreach ($meta_keys as $meta_key) {
                $value = get_post_meta($product_id, $meta_key, true);
                if ($value !== '' && $value !== false && is_numeric($value)) {
                    return (float) $value;
                }
            }

            return null;
        }

        public function postOrder($order_data)
        {
            $order_data = json_encode($order_data);
            $headers = array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Expect' => '100-Continue',
                'Api-Key' => ''.$this->settings['apiKey'],
                'Account-Id' => ''.$this->settings['accountId'],
            );

            $args = array(
                'body'        => $order_data,
                'headers'     => $headers,
            );
            
            // Yes, this will be logged in Docker if WP_DEBUG is enabled, because error_log() writes to STDERR, which is captured by the container logs.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Dropday Plugin Debug] postOrder() triggered');
            }
            
            return wp_remote_post( $this->getApiUrl('orders'), $args );
        }

        /**
         * Get shipping data from order for Dropday API.
         *
         * @param WC_Order $order The WooCommerce order.
         * @return array Shipping data array.
         */
        protected function getShippingData($order)
        {
            $shipping_data = array(
                'cost' => (float) $order->get_shipping_total(),
            );

            // Get shipping method name and description from order shipping items
            $shipping_methods = $order->get_shipping_methods();
            if (!empty($shipping_methods)) {
                $shipping_method = reset($shipping_methods);
                $shipping_data['name'] = $shipping_method->get_method_title();
                
                // Use method_id as description if different from name
                $method_id = $shipping_method->get_method_id();
                if ($method_id && $method_id !== $shipping_data['name']) {
                    $shipping_data['description'] = $method_id;
                }
            }

            // Add customer note as shipping note if available
            $customer_note = $order->get_customer_note();
            if (!empty($customer_note)) {
                $shipping_data['note'] = $customer_note;
            }

            // Check for delivery date in order meta (common meta keys used by delivery date plugins)
            $delivery_date = $this->getDeliveryDate($order);
            if ($delivery_date) {
                $shipping_data['delivery_date'] = $delivery_date;
            }

            return $shipping_data;
        }

        /**
         * Get delivery date from order meta.
         * Supports common delivery date plugins and custom meta fields.
         *
         * @param WC_Order $order The WooCommerce order.
         * @return string|null Delivery date in DD-MM-YYYY format or null.
         */
        protected function getDeliveryDate($order)
        {
            $default_keys = 'Delivery Date, _orddd_lite_timestamp, delivery_date, _delivery_date, jckwds_date, order_delivery_date';
            $meta_keys_string = isset($this->settings['deliveryDateMeta']) && !empty($this->settings['deliveryDateMeta']) 
                ? $this->settings['deliveryDateMeta'] 
                : $default_keys;
            
            $meta_keys = array_filter(array_map('trim', explode(',', $meta_keys_string)));

            foreach ($meta_keys as $key) {
                $date = $order->get_meta($key);
                if (!empty($date)) {
                    return $this->formatDeliveryDate($date);
                }
            }

            return null;
        }

        /**
         * Format delivery date to DD-MM-YYYY format.
         *
         * @param string $date The date string or Unix timestamp.
         * @return string Formatted date in DD-MM-YYYY format.
         */
        protected function formatDeliveryDate($date)
        {
            // Handle Unix timestamps (numeric values)
            if (is_numeric($date)) {
                return date('d-m-Y', (int) $date);
            }

            // Try to parse the date string
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                // Unparseable, return as-is
                return $date;
            }

            return date('d-m-Y', $timestamp);
        }
    }

endif;

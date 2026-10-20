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
        protected $webhook_events = array('supplier_order.shipped');
        
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

            add_action('before_woocommerce_init', array($this, 'declareWooCommerceCompatibility'));
            register_deactivation_hook($this->pluginPath, array($this, 'onDeactivate'));

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
                add_action('admin_init', array($this, 'maybeInstallWebhook'));
                add_filter('pre_update_option_' . $this->id, array($this, 'onSettingsSave'));
                add_action('admin_post_dropday_install_webhook', array($this, 'onInstallWebhookRequest'));
            }

            add_filter('plugin_action_links_' . plugin_basename($this->pluginPath), array($this, 'onPluginActionLinks'), 1, 1);
            add_filter('plugin_row_meta', array($this, 'onPluginRowMeta'), 10, 2);
            add_action('init', array($this, 'onInit'), 5);
            add_action('rest_api_init', array($this, 'registerWebhookRoute'));
            add_action('plugins_loaded', array($this, 'enableFulfillments'));
            add_action('woocommerce_order_status_changed', array($this, 'onOrderStatusChanged'), 10, 3);
	   }

        public function declareWooCommerceCompatibility()
        {
            if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', $this->pluginPath, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', $this->pluginPath, true);
            }
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
                $this->id.'_sendOrderStatus',
                __( 'Send orders on status', 'dropday-for-woocommerce' ),
                array($this, 'dropdayFieldSendOrderStatusCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_sendOrderStatus',
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

            if (isset($input['sendOrderStatus'])) {
                $status = sanitize_key($input['sendOrderStatus']);
                $new_input['sendOrderStatus'] = array_key_exists($status, $this->getSendableOrderStatuses()) ? $status : '';
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

        public function dropdayFieldSendOrderStatusCb()
        {
            $id_escaped = esc_attr($this->id);
            $selected = isset( $this->settings['sendOrderStatus'] ) ? $this->settings['sendOrderStatus'] : '';
            printf(
                '<select id="%s_sendOrderStatus" name="%s[sendOrderStatus]"><option value="">%s</option>',
                $id_escaped,
                $id_escaped,
                esc_html__('Any paid status', 'dropday-for-woocommerce')
            );
            foreach ($this->getSendableOrderStatuses() as $status => $label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($status),
                    selected($selected, $status, false),
                    esc_html($label)
                );
            }
            printf(
                '</select><p class="description">%s</p>',
                esc_html__('Orders are sent to Dropday when they get this status. With "Any paid status", orders are sent as soon as they are paid.', 'dropday-for-woocommerce')
            );
        }

        /**
         * Order statuses a merchant can choose to send orders to Dropday on.
         *
         * @return array Status labels keyed by status without the "wc-" prefix.
         */
        protected function getSendableOrderStatuses()
        {
            $excluded = array('pending', 'failed', 'cancelled', 'refunded', 'checkout-draft');
            $statuses = array();
            foreach (wc_get_order_statuses() as $key => $label) {
                $status = preg_replace('/^wc-/', '', $key);
                if (!in_array($status, $excluded, true)) {
                    $statuses[$status] = $label;
                }
            }
            return $statuses;
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

            $webhook_result = isset($_GET['dropday_webhook']) ? sanitize_key(wp_unslash($_GET['dropday_webhook'])) : '';
            $webhook_error = get_transient($this->id . '_webhook_error');
            if ($webhook_result === 'installed') {
                add_settings_error( 'wporg_messages', 'dropday_webhook', __( 'Webhook installed in Dropday.', 'dropday-for-woocommerce' ), 'updated' );
            } elseif ($webhook_error && ($webhook_result === 'failed' || !$this->getStoredWebhook())) {
                /* translators: %s: error message from the Dropday API */
                add_settings_error( 'wporg_messages', 'dropday_webhook', sprintf(__( 'The webhook could not be installed in Dropday: %s', 'dropday-for-woocommerce' ), $webhook_error), 'error' );
            }

            settings_errors( 'wporg_messages' );
            $this->settings = get_option( $this->id );
            $webhook = $this->getStoredWebhook();
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

                <h2><?php esc_html_e( 'Track and trace', 'dropday-for-woocommerce' ); ?></h2>
                <p>
                    <?php
                    if (!$this->hasApiCredentials()) {
                        esc_html_e( 'Save your API key and account ID to install the webhook that sends track and trace to this store.', 'dropday-for-woocommerce' );
                    } elseif ($webhook) {
                        /* translators: %s: webhook URL */
                        printf(esc_html__( 'Installed. Dropday sends track and trace to %s.', 'dropday-for-woocommerce' ), '<code>' . esc_html($webhook['url']) . '</code>');
                    } else {
                        esc_html_e( 'Not installed. Dropday can\'t send track and trace to this store until the webhook is installed.', 'dropday-for-woocommerce' );
                    }
                    ?>
                </p>
                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                    <input type="hidden" name="action" value="dropday_install_webhook" />
                    <?php
                    wp_nonce_field( 'dropday_install_webhook' );
                    submit_button(
                        $webhook ? __( 'Reinstall webhook', 'dropday-for-woocommerce' ) : __( 'Install webhook', 'dropday-for-woocommerce' ),
                        'secondary',
                        'dropday-install-webhook',
                        false,
                        $this->hasApiCredentials() ? array() : array( 'disabled' => 'disabled' )
                    );
                    ?>
                </form>
                <?php if ($webhook) : ?>
                    <p class="description"><?php esc_html_e( 'Reinstalling replaces the webhook in Dropday with a new one, for example after rotating its secret in Dropday.', 'dropday-for-woocommerce' ); ?></p>
                <?php endif; ?>
            </div>
            <?php
        }
        
        public function onOrderStatusChanged($order_id, $old_status, $new_status)
        {
            $send_status = isset($this->settings['sendOrderStatus']) ? $this->settings['sendOrderStatus'] : '';
            if ($send_status !== '') {
                if ($new_status !== $send_status) {
                    return;
                }
            } else {
                $order = wc_get_order($order_id);
                if (!$order || !$order->is_paid()) {
                    return;
                }
            }

            $this->handleOrder($order_id);
        }
        
        public function handleOrder($order_id)
        {
            if (!$order_id ) {
                return false;
            }

            $order = wc_get_order( $order_id );
            if ( $order ) {
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

                if (empty($this->settings['live'])) {
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

                    $labels = $this->getProductTagLabels($product);
                    if (!empty($labels)) {
                        $p['labels'] = $labels;
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

        /**
         * Get the product tag names to send as order line labels.
         * Variations inherit the tags of their parent product.
         *
         * @param WC_Product $product The product of the order line.
         * @return string[] Tag names.
         */
        protected function getProductTagLabels($product)
        {
            $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
            $terms = get_the_terms($product_id, 'product_tag');
            if (!$terms || is_wp_error($terms)) {
                return array();
            }

            $labels = array();
            foreach ($terms as $term) {
                // Dropday rejects labels longer than 255 characters
                $label = mb_substr(html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'), 0, 255);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }

            return array_values(array_unique($labels));
        }

        public function postOrder($order_data)
        {
            $order_data = json_encode($order_data);
            $headers = array_merge($this->getApiHeaders(), array('Expect' => '100-Continue'));

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

        protected function getApiHeaders()
        {
            return array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Api-Key' => isset($this->settings['apiKey']) ? ''.$this->settings['apiKey'] : '',
                'Account-Id' => isset($this->settings['accountId']) ? ''.$this->settings['accountId'] : '',
            );
        }

        /**
         * Send a request to the Dropday API.
         *
         * @param string     $method HTTP method.
         * @param string     $path   Path relative to the API URL, e.g. "webhooks".
         * @param array|null $data   JSON body.
         * @return array|\WP_Error Decoded response body, or an error for non-2xx responses.
         */
        protected function apiRequest($method, $path, $data = null)
        {
            $args = array(
                'method' => $method,
                'headers' => $this->getApiHeaders(),
            );
            if ($data !== null) {
                $args['body'] = json_encode($data);
            }

            $response = wp_remote_request($this->getApiUrl($path), $args);
            if (is_wp_error($response)) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code >= 200 && $code < 300) {
                return is_array($body) ? $body : array();
            }

            $messages = array();
            if (isset($body['errors']) && is_array($body['errors'])) {
                foreach ($body['errors'] as $field_errors) {
                    foreach ((array) $field_errors as $message) {
                        $messages[] = $message;
                    }
                }
            } elseif (!empty($body['message'])) {
                $messages[] = $body['message'];
            }
            if (empty($messages)) {
                $messages[] = 'response code ' . $code . '.';
            }

            return new \WP_Error('dropday_api_error', implode(' ', $messages));
        }

        protected function hasApiCredentials()
        {
            return !empty($this->settings['apiKey']) && !empty($this->settings['accountId']);
        }

        public function getWebhookUrl()
        {
            return rest_url('dropday/v1/webhook');
        }

        /**
         * Get the webhook this site installed for the current account and URL.
         *
         * @return array|null Array with id, secret, url and account_id, or null when not installed.
         */
        protected function getStoredWebhook()
        {
            $webhook = get_option($this->id . '_webhook');
            if (!is_array($webhook) || empty($webhook['id']) || empty($webhook['secret'])
                || !isset($webhook['url'], $webhook['account_id'], $this->settings['accountId'])
                || $webhook['url'] !== $this->getWebhookUrl()
                || (string) $webhook['account_id'] !== (string) $this->settings['accountId']
            ) {
                return null;
            }
            return $webhook;
        }

        /**
         * Installs the webhook for sites that have API credentials but no webhook yet,
         * e.g. after updating the plugin or moving the site to another URL.
         */
        public function maybeInstallWebhook()
        {
            if (wp_doing_ajax() || !$this->hasApiCredentials() || $this->getStoredWebhook()) {
                return;
            }
            // Failed attempts are retried after the error expires, when the settings are saved, or with the "Install webhook" button
            if (get_transient($this->id . '_webhook_error')) {
                return;
            }
            $this->installWebhook();
        }

        /**
         * (Re)installs the webhook with the submitted settings whenever the settings are saved.
         *
         * @param array $value The sanitized settings.
         * @return array The settings, unchanged.
         */
        public function onSettingsSave($value)
        {
            $this->settings = $value;
            delete_transient($this->id . '_webhook_error');
            $this->installWebhook();
            return $value;
        }

        /**
         * Handles the "Install webhook" button on the settings page.
         */
        public function onInstallWebhookRequest()
        {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You are not allowed to do this.', 'dropday-for-woocommerce'), 403);
            }
            check_admin_referer('dropday_install_webhook');

            $installed = $this->installWebhook(true);
            wp_safe_redirect(add_query_arg(
                'dropday_webhook',
                $installed ? 'installed' : 'failed',
                admin_url('options-general.php?page=admin-' . $this->id)
            ));
            exit;
        }

        /**
         * @param bool $recreate Replace the webhook in Dropday instead of updating it, which gives it a new secret.
         * @return bool Whether the webhook is installed.
         */
        public function installWebhook($recreate = false)
        {
            if (!$this->hasApiCredentials()) {
                return false;
            }

            $webhook = $this->registerWebhook($recreate);
            if (is_wp_error($webhook)) {
                wc_get_logger()->warning('[dropday] webhook installation failed: ' . $webhook->get_error_message(), array('source' => $this->id));
                set_transient($this->id . '_webhook_error', $webhook->get_error_message(), HOUR_IN_SECONDS);
                return false;
            }

            update_option($this->id . '_webhook', $webhook);
            delete_transient($this->id . '_webhook_error');
            return true;
        }

        /**
         * Create or update the webhook in Dropday.
         *
         * @param bool $recreate See installWebhook().
         * @return array|\WP_Error The webhook to store, see getStoredWebhook().
         */
        protected function registerWebhook($recreate = false)
        {
            $url = $this->getWebhookUrl();
            $webhooks = $this->apiRequest('GET', 'webhooks');
            if (is_wp_error($webhooks)) {
                return $webhooks;
            }

            $stored = $this->getStoredWebhook();
            foreach (isset($webhooks['data']) ? (array) $webhooks['data'] : array() as $existing) {
                if (!isset($existing['id'], $existing['url']) || $existing['url'] !== $url) {
                    continue;
                }

                if (!$recreate && $stored && (int) $stored['id'] === (int) $existing['id']) {
                    $response = $this->apiRequest('PATCH', 'webhooks/' . (int) $existing['id'], array('events' => $this->webhook_events));
                    return is_wp_error($response) ? $response : $stored;
                }

                // The secret is only returned on creation, so replace a webhook we don't know the secret of
                $response = $this->apiRequest('DELETE', 'webhooks/' . (int) $existing['id']);
                if (is_wp_error($response)) {
                    return $response;
                }
            }

            $response = $this->apiRequest('POST', 'webhooks', array(
                'name' => 'WooCommerce',
                'description' => 'Installed by the Dropday plugin on ' . home_url(),
                'url' => $url,
                'events' => $this->webhook_events,
            ));
            if (is_wp_error($response)) {
                return $response;
            }
            if (empty($response['data']['id']) || empty($response['data']['secret'])) {
                return new \WP_Error('dropday_api_error', 'No webhook ID or secret in the response.');
            }

            return array(
                'id' => (int) $response['data']['id'],
                'secret' => (string) $response['data']['secret'],
                'url' => $url,
                'account_id' => (string) $this->settings['accountId'],
            );
        }

        public function onDeactivate()
        {
            $this->loadSettings();
            $webhook = $this->getStoredWebhook();
            if ($webhook) {
                $this->apiRequest('DELETE', 'webhooks/' . (int) $webhook['id']);
            }
            delete_option($this->id . '_webhook');
            delete_transient($this->id . '_webhook_error');
        }

        public function registerWebhookRoute()
        {
            register_rest_route('dropday/v1', '/webhook', array(
                'methods' => 'POST',
                'callback' => array($this, 'onWebhook'),
                // Deliveries are authenticated by their signature
                'permission_callback' => '__return_true',
            ));
        }

        public function onWebhook(\WP_REST_Request $request)
        {
            $webhook = get_option($this->id . '_webhook');
            $body = $request->get_body();
            $signature = (string) $request->get_header('X-Dropday-Signature');
            if (empty($webhook['secret']) || !hash_equals(hash_hmac('sha256', $body, $webhook['secret']), $signature)) {
                return new \WP_REST_Response(array('message' => 'Invalid signature.'), 401);
            }

            $payload = json_decode($body, true);
            if (!isset($payload['event'])) {
                return new \WP_REST_Response(array('message' => 'Invalid payload.'), 400);
            }

            if ($payload['event'] !== 'supplier_order.shipped') {
                return new \WP_REST_Response(array('message' => 'Event ignored.'), 200);
            }

            try {
                $message = $this->addShipment(isset($payload['data']) ? (array) $payload['data'] : array());
            } catch (\Exception $e) {
                wc_get_logger()->error('[dropday] could not add shipment: ' . $e->getMessage(), array('source' => $this->id));
                return new \WP_REST_Response(array('message' => $e->getMessage()), 500);
            }
            return new \WP_REST_Response(array('message' => $message), 200);
        }

        /**
         * WooCommerce has no setting for Order Fulfillments yet, so the plugin turns it on
         * to store shipments as fulfillments.
         */
        public function enableFulfillments()
        {
            if (class_exists(\Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment::class)
                && get_option('woocommerce_feature_fulfillments_enabled') !== 'yes'
            ) {
                update_option('woocommerce_feature_fulfillments_enabled', 'yes', true);
            }
        }

        /**
         * Add a shipped supplier order to the WooCommerce order as a fulfillment,
         * with its products, tracking code and tracking URL.
         *
         * @param array $data The data of a supplier_order.shipped event.
         * @return string Result message for the webhook response.
         * @throws \Exception When the fulfillment can't be saved.
         */
        protected function addShipment($data)
        {
            if (!class_exists(\Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment::class)
                || !\Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('fulfillments')
            ) {
                return 'WooCommerce Fulfillments is not available, it requires WooCommerce 10.1 or newer.';
            }

            $dropday_order = isset($data['order']) ? (array) $data['order'] : array();
            $order_id = isset($dropday_order['external_id']) ? (string) $dropday_order['external_id'] : '';
            $order = preg_match('/^\d+$/', $order_id) ? wc_get_order((int) $order_id) : false;
            if (!$order) {
                return 'Order not found.';
            }

            // Order lines were sent with the order item ID as external_id. Matching them also
            // guards against orders from other sources in the same Dropday account.
            $order_items = $order->get_items();
            $items = array();
            foreach (isset($dropday_order['order_lines']) ? (array) $dropday_order['order_lines'] : array() as $line) {
                $item_id = isset($line['external_id']) ? (int) $line['external_id'] : 0;
                $quantity = isset($line['quantity']) ? (int) $line['quantity'] : 0;
                if (isset($order_items[$item_id]) && $quantity > 0) {
                    $items[] = array('item_id' => $item_id, 'qty' => $quantity);
                }
            }
            if (empty($items)) {
                return 'No products of this order found.';
            }

            $supplier_order_id = isset($data['supplier_order_id']) ? (int) $data['supplier_order_id'] : 0;
            $tracking_code = isset($data['tracking_code']) ? sanitize_text_field((string) $data['tracking_code']) : '';
            $tracking_url = isset($data['tracking_url']) ? esc_url_raw((string) $data['tracking_url'], array('http', 'https')) : '';

            // Stored the way the fulfillment editor in the WooCommerce admin stores it
            $tracking = array(
                '_shipping_option' => $tracking_code === '' && $tracking_url === '' ? 'no-info' : 'manual-entry',
                '_tracking_number' => $tracking_code,
                '_tracking_url' => $tracking_url,
            );
            // Without a carrier, one the merchant chose in WooCommerce is kept
            if (!empty($data['carrier']['code'])) {
                $tracking = array_merge($tracking, $this->getShipmentProviderMeta($data['carrier']));
            }
            $fulfillment = $this->getShipmentFulfillment($order, $supplier_order_id);
            $is_new = $fulfillment === null;
            if ($is_new) {
                $fulfillment = new \Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment();
                $fulfillment->set_entity_type(\WC_Order::class);
                $fulfillment->set_entity_id((string) $order->get_id());
                $fulfillment->set_status('fulfilled');
                $fulfillment->set_items($items);
                $fulfillment->update_meta_data('_dropday_supplier_order_id', $supplier_order_id);
            } else {
                $changed = false;
                foreach ($tracking as $key => $value) {
                    $changed = $changed || (string) $fulfillment->get_meta($key) !== $value;
                }
                if (!$changed) {
                    return 'Shipment already added.';
                }
            }

            foreach ($tracking as $key => $value) {
                if ($value === '') {
                    $fulfillment->delete_meta_data($key);
                } else {
                    $fulfillment->update_meta_data($key, $value);
                }
            }
            $fulfillment->save();

            // Sends the customer the same email as when the merchant fulfills an order in WooCommerce
            if ($is_new) {
                do_action('woocommerce_fulfillment_created_notification', $order->get_id(), $fulfillment, $order);
                return 'Shipment added.';
            }
            do_action('woocommerce_fulfillment_updated_notification', $order->get_id(), $fulfillment, $order);
            return 'Shipment updated.';
        }

        /**
         * Map a Dropday carrier to a WooCommerce shipping provider. Carriers WooCommerce doesn't know
         * are stored as "other" with their name, like the fulfillment editor does.
         *
         * @param array $carrier Dropday carrier with code and name.
         * @return array Meta values keyed by meta key. Empty values are to be deleted.
         */
        protected function getShipmentProviderMeta($carrier)
        {
            $code = sanitize_key((string) $carrier['code']);
            $providers = \Automattic\WooCommerce\Admin\Features\Fulfillments\FulfillmentUtils::get_shipping_providers();
            if (isset($providers[$code])) {
                return array('_shipment_provider' => $code, '_provider_name' => '');
            }

            $name = isset($carrier['name']) ? sanitize_text_field((string) $carrier['name']) : '';
            return array('_shipment_provider' => 'other', '_provider_name' => $name !== '' ? $name : $code);
        }

        /**
         * Find the fulfillment that was added for a supplier order.
         *
         * @param \WC_Order $order
         * @param int       $supplier_order_id
         * @return \Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment|null
         */
        protected function getShipmentFulfillment($order, $supplier_order_id)
        {
            $fulfillments = \WC_Data_Store::load('order-fulfillment')->read_fulfillments(\WC_Order::class, (string) $order->get_id());
            foreach ($fulfillments as $fulfillment) {
                if ((int) $fulfillment->get_meta('_dropday_supplier_order_id') === $supplier_order_id) {
                    return $fulfillment;
                }
            }
            return null;
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

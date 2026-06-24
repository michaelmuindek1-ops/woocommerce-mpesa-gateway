<?php
class WC_MPesa_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_mpesa_settings', [$this, 'settings_tab']);
        add_action('woocommerce_update_options_mpesa_settings', [$this, 'update_settings']);
        
        // Add custom settings fields
        add_action('woocommerce_admin_field_mpesa_status', [$this, 'render_status_field']);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('M-PESA Transactions', 'wc-mpesa-gateway'),
            __('M-PESA Transactions', 'wc-mpesa-gateway'),
            'manage_woocommerce',
            'mpesa-transactions',
            [$this, 'transactions_page']
        );
    }
    
    public function add_settings_tab($tabs) {
        $tabs['mpesa_settings'] = __('M-PESA API', 'wc-mpesa-gateway');
        return $tabs;
    }
    
    public function settings_tab() {
        woocommerce_admin_fields($this->get_api_settings());
    }
    
    public function update_settings() {
        woocommerce_update_options($this->get_api_settings());
        
        // Test API connection after saving
        if (isset($_POST['test_mpesa_connection'])) {
            $this->test_connection();
        }
    }
    
    private function get_api_settings() {
        return [
            'api_section' => [
                'name' => __('Daraja API Configuration', 'wc-mpesa-gateway'),
                'type' => 'title',
                'desc' => __('Configure your Safaricom Daraja API credentials', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_api_section'
            ],
            'environment' => [
                'name' => __('Environment', 'wc-mpesa-gateway'),
                'type' => 'select',
                'options' => [
                    'sandbox' => __('Sandbox', 'wc-mpesa-gateway'),
                    'production' => __('Production', 'wc-mpesa-gateway')
                ],
                'desc' => __('Select sandbox for testing or production for live payments', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_environment',
                'default' => 'sandbox'
            ],
            'shortcode' => [
                'name' => __('Shortcode', 'wc-mpesa-gateway'),
                'type' => 'text',
                'desc' => __('Your Paybill or Till number', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_shortcode'
            ],
            'passkey' => [
                'name' => __('Passkey', 'wc-mpesa-gateway'),
                'type' => 'text',
                'desc' => __('Your Lipa Na M-PESA Online Passkey', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_passkey'
            ],
            'consumer_key' => [
                'name' => __('Consumer Key', 'wc-mpesa-gateway'),
                'type' => 'text',
                'desc' => __('Your Daraja API Consumer Key', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_consumer_key'
            ],
            'consumer_secret' => [
                'name' => __('Consumer Secret', 'wc-mpesa-gateway'),
                'type' => 'password',
                'desc' => __('Your Daraja API Consumer Secret', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_consumer_secret'
            ],
            'callback_url' => [
                'name' => __('Callback URL', 'wc-mpesa-gateway'),
                'type' => 'text',
                'desc' => __('Your callback URL (auto-generated)', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_callback_url',
                'default' => rest_url('wc-mpesa/v1/callback'),
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'test_connection' => [
                'name' => __('Test Connection', 'wc-mpesa-gateway'),
                'type' => 'mpesa_status',
                'desc' => __('Test your API connection', 'wc-mpesa-gateway'),
                'id' => 'wc_mpesa_test_connection'
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => 'wc_mpesa_api_section'
            ]
        ];
    }
    
    public function render_status_field($value) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($value['id']); ?>">
                    <?php echo esc_html($value['name']); ?>
                </label>
            </th>
            <td class="forminp">
                <button type="button" class="button" id="test-mpesa-connection">
                    <?php _e('Test Connection', 'wc-mpesa-gateway'); ?>
                </button>
                <span id="test-connection-status"></span>
                <p class="description"><?php echo esc_html($value['desc']); ?></p>
                
                <script>
                jQuery(document).ready(function($) {
                    $('#test-mpesa-connection').click(function() {
                        var button = $(this);
                        var statusEl = $('#test-connection-status');
                        
                        button.prop('disabled', true);
                        statusEl.html('<span class="spinner is-active"></span> Testing...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'test_mpesa_connection',
                                nonce: '<?php echo wp_create_nonce("mpesa_test_connection"); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    statusEl.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                                } else {
                                    statusEl.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                                }
                                button.prop('disabled', false);
                            },
                            error: function() {
                                statusEl.html('<span style="color:red;">✗ Connection test failed</span>');
                                button.prop('disabled', false);
                            }
                        });
                    });
                });
                </script>
            </td>
        </tr>
        <?php
    }
    
    private function test_connection() {
        try {
            $mpesa_api = new WC_MPesa_API();
            // Test authentication
            $mpesa_api->authenticate();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function transactions_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('M-PESA Transactions', 'wc-mpesa-gateway'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="order_id">
                        <option value=""><?php _e('All Orders', 'wc-mpesa-gateway'); ?></option>
                        <?php
                        $orders = wc_get_orders(['limit' => -1]);
                        foreach ($orders as $order) {
                            echo '<option value="' . $order->get_id() . '">#' . $order->get_order_number() . '</option>';
                        }
                        ?>
                    </select>
                    <input type="submit" class="button" value="<?php _e('Filter', 'wc-mpesa-gateway'); ?>">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'wc-mpesa-gateway'); ?></th>
                        <th><?php _e('Order', 'wc-mpesa-gateway'); ?></th>
                        <th><?php _e('Phone', 'wc-mpesa-gateway'); ?></th>
                        <th><?php _e('Amount', 'wc-mpesa-gateway'); ?></th>
                        <th><?php _e('Receipt', 'wc-mpesa-gateway'); ?></th>
                        <th><?php _e('Status', 'wc-mpesa-gateway'); ?></th>
                        <th><?php _e('Date', 'wc-mpesa-gateway'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $transactions = WC_MPesa_Database::get_transactions();
                    
                    if ($transactions) {
                        foreach ($transactions as $transaction) {
                            $order = wc_get_order($transaction->order_id);
                            $status_class = $transaction->status === 'completed' ? 'success' : 
                                          ($transaction->status === 'failed' ? 'error' : 'warning');
                            ?>
                            <tr>
                                <td><?php echo $transaction->id; ?></td>
                                <td>
                                    <?php if ($order) : ?>
                                        <a href="<?php echo get_edit_post_link($transaction->order_id); ?>">
                                            #<?php echo $order->get_order_number(); ?>
                                        </a>
                                    <?php else : ?>
                                        #<?php echo $transaction->order_id; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($transaction->phone_number); ?></td>
                                <td><?php echo wc_price($transaction->amount); ?></td>
                                <td><?php echo esc_html($transaction->mpesa_receipt_number ?? '—'); ?></td>
                                <td>
                                    <mark class="<?php echo $status_class; ?>">
                                        <?php echo esc_html($transaction->status); ?>
                                    </mark>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at)); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="7"><?php _e('No transactions found.', 'wc-mpesa-gateway'); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mpesa') !== false) {
            wp_enqueue_style(
                'wc-mpesa-admin',
                WC_MPESA_PLUGIN_URL . 'assets/css/admin-style.css',
                [],
                WC_MPESA_VERSION
            );
            
            wp_enqueue_script(
                'wc-mpesa-admin',
                WC_MPESA_PLUGIN_URL . 'assets/js/admin-script.js',
                ['jquery'],
                WC_MPESA_VERSION,
                true
            );
        }
    }
}

// Initialize admin
new WC_MPesa_Admin();

// AJAX handler for testing connection
add_action('wp_ajax_test_mpesa_connection', function() {
    check_ajax_referer('mpesa_test_connection', 'nonce');
    
    try {
        $mpesa_api = new WC_MPesa_API();
        $token = $mpesa_api->authenticate();
        wp_send_json_success(['message' => 'Connection successful']);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});
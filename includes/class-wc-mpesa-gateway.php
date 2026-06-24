<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_MPesa_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'mpesa';
        $this->icon               = WC_MPESA_PLUGIN_URL . 'assets/images/mpesa-logo.png';

        // Prevent broken icons from impacting front-end: if image missing, Woo will still show gateway name.
        // (404 in browser console is expected when the file isn't present in /assets/images)

        $this->has_fields         = true;
        $this->method_title       = __('M-PESA', 'wc-mpesa-gateway');
        $this->method_description = __('Accept payments via M-PESA STK Push', 'wc-mpesa-gateway');

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->enabled      = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);

        // AJAX handlers
        add_action('wp_ajax_process_mpesa_payment', [$this, 'ajax_process_payment']);
        add_action('wp_ajax_nopriv_process_mpesa_payment', [$this, 'ajax_process_payment']);
        add_action('wp_ajax_check_mpesa_payment_status', [$this, 'ajax_check_payment_status']);
        add_action('wp_ajax_nopriv_check_mpesa_payment_status', [$this, 'ajax_check_payment_status']);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);

        // Ensure hooks run only when gateway is enabled
        if ($this->enabled !== 'yes') {
            $this->enabled = 'no';
        }
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'wc-mpesa-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable M-PESA Payments', 'wc-mpesa-gateway'),
                'default' => 'no'
            ],
            'title' => [
                'title'       => __('Title', 'wc-mpesa-gateway'),
                'type'        => 'text',
                'description' => __('Payment method title shown to customers', 'wc-mpesa-gateway'),
                'default'     => __('M-PESA', 'wc-mpesa-gateway'),
                'desc_tip'    => true
            ],
            'description' => [
                'title'       => __('Description', 'wc-mpesa-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description shown to customers', 'wc-mpesa-gateway'),
                'default'     => __('Pay via M-PESA. Enter your phone number and click pay to receive an STK push notification.', 'wc-mpesa-gateway')
            ],
            'instructions' => [
                'title'       => __('Instructions', 'wc-mpesa-gateway'),
                'type'        => 'textarea',
                'description' => __('Instructions shown on the thank you page', 'wc-mpesa-gateway'),
                'default'     => __('Please check your phone and enter your M-PESA PIN to complete the payment.', 'wc-mpesa-gateway')
            ],
        ];
    }

    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        ?>
        <div class="mpesa-payment-fields">
            <p class="form-row form-row-wide">
                <label for="mpesa_phone"><?php _e('M-PESA Phone Number', 'wc-mpesa-gateway'); ?> <span class="required">*</span></label>
                <input
                    type="tel"
                    class="input-text"
                    id="mpesa_phone"
                    name="mpesa_phone"
                    placeholder="0712345678"
                    pattern="0[17][0-9]{8}"
                    title="Enter a valid Safaricom number (e.g., 0712345678)"
                    required
                />
                <small><?php _e('Enter your Safaricom M-PESA registered phone number', 'wc-mpesa-gateway'); ?></small>
            </p>
        </div>
        <?php
    }

    public function validate_fields() {
        if (empty($_POST['mpesa_phone'])) {
            wc_add_notice(__('Phone number is required', 'wc-mpesa-gateway'), 'error');
            return false;
        }

        $phone = sanitize_text_field($_POST['mpesa_phone']);

        if (!preg_match('/^0[17][0-9]{8}$/', $phone)) {
            wc_add_notice(__('Please enter a valid Safaricom phone number', 'wc-mpesa-gateway'), 'error');
            return false;
        }

        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return [
                'result'   => 'fail',
                'redirect' => wc_get_checkout_url()
            ];
        }

        $phone = isset($_POST['mpesa_phone']) ? sanitize_text_field($_POST['mpesa_phone']) : '';
        update_post_meta($order_id, '_mpesa_phone', $phone);

        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        ];
    }

    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $phone = get_post_meta($order_id, '_mpesa_phone', true);

        // Render template ONLY (no inline UI duplication)
        wc_get_template(
            'payment-instructions.php',
            [
                'order'    => $order,
                'phone'    => $phone,
                'order_id' => $order_id,
            ],
            'woocommerce/mpesa/',
            WC_MPESA_PLUGIN_DIR . 'templates/'
        );
    }

    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
    }

    public function ajax_process_payment() {
        // If nonce is missing/invalid, return a clean JSON error instead of a 400.
        if (empty($_POST['nonce'])) {
            wp_send_json_error(['message' => 'Missing nonce']);
        }

        // Avoid HTTP 400 on invalid nonce; return JSON so we can see the real issue.
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        if (!wp_verify_nonce($nonce, 'wc_mpesa_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }


        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $phone    = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';


        if (!$order_id || !$phone) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        try {
            $mpesa_api = new WC_MPesa_API();

            $account_reference = 'Order#' . $order->get_order_number();
            $transaction_desc  = 'Payment for Order #' . $order->get_order_number();

            // STK Push: PartyA=Phone, PartyB=BusinessShortCode (Paybill/Till)
            // For "Buy Goods" style flows, Daraja still uses CustomerPayBillOnline.
            $response = $mpesa_api->stk_push(
                $phone,
                $order->get_total(),
                $account_reference,
                $transaction_desc
            );

            if (isset($response['ResponseCode']) && (string)$response['ResponseCode'] === '0') {
                WC_MPesa_Database::log_transaction([
                    'order_id'              => $order_id,
                    'merchant_request_id'  => $response['MerchantRequestID'] ?? null,
                    'checkout_request_id'  => $response['CheckoutRequestID'] ?? null,
                    'phone_number'          => $phone,
                    'amount'                => $order->get_total(),
                    'status'                => 'pending',
                    'raw_response'          => json_encode($response),
                ]);

                wp_send_json_success([
                    'checkout_request_id' => $response['CheckoutRequestID'],
                    'redirect_url'        => $this->get_return_url($order)
                ]);
            }

            $error_message = $response['ResponseDescription'] ?? 'Failed to initiate payment';
            wp_send_json_error(['message' => $error_message]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_check_payment_status() {
        check_ajax_referer('wc_mpesa_nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $checkout_request_id = isset($_POST['checkout_request_id']) ? sanitize_text_field($_POST['checkout_request_id']) : '';

        if (!$order_id || !$checkout_request_id) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        $transaction = WC_MPesa_Database::get_transaction_by_checkout_id($checkout_request_id);
        if (!$transaction) {
            wp_send_json_error(['message' => 'Transaction not found']);
        }

        wp_send_json_success([
            'status'  => $transaction->status,
            'receipt' => $transaction->mpesa_receipt_number,
        ]);
    }

    public function payment_scripts() {
        if (!is_checkout() && !is_checkout_pay_page()) {
            return;
        }

        wp_enqueue_script(
            'wc-mpesa-payment',
            WC_MPESA_PLUGIN_URL . 'assets/js/payment.js',
            ['jquery'],
            WC_MPESA_VERSION,
            true
        );

        // On the checkout payment page the order id isn't always in query vars.
        // Best-effort: use the order-pay query var, otherwise 0 (JS can still read phone from form).
        $order_id = absint(get_query_var('order-pay'));

        wp_localize_script('wc-mpesa-payment', 'wc_mpesa_params', [
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('wc_mpesa_nonce'),
            'order_id'        => $order_id,
            'redirect_url'   => $order_id ? $this->get_return_url(wc_get_order($order_id)) : '',
            'i18n_processing' => __('Processing...', 'wc-mpesa-gateway'),
            'i18n_pay'        => __('Pay with M-PESA', 'wc-mpesa-gateway'),
            'i18n_resend'     => __('Resend STK Push', 'wc-mpesa-gateway'),
            'i18n_complete'  => __('Payment Complete!', 'wc-mpesa-gateway'),
            'i18n_failed'    => __('Payment Failed', 'wc-mpesa-gateway'),
        ]);
    }
}


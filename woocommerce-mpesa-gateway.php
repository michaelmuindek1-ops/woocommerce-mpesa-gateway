<?php
/**
 * Plugin Name: WooCommerce M-PESA Payment Gateway
 * Plugin URI:https://github.com/michaelmuindek1-ops/woocommerce-mpesa-gateway
 * Description: Custom M-PESA STK Push payment gateway for WooCommerce using Safaricom Daraja API
 * Version: 1.0.2
 * Author: Michael Muinde
 * Author URI: https://github.com/michaelmuindek1-ops
 * Text Domain: wc-mpesa-gateway
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('WC_MPESA_VERSION', '1.0.2');
define('WC_MPESA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_MPESA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_MPESA_PLUGIN_BASENAME', plugin_basename(__FILE__));

function wc_mpesa_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error">
                <p><?php _e('WooCommerce M-PESA Gateway requires WooCommerce to be installed and activated.', 'wc-mpesa-gateway'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

add_action('plugins_loaded', 'wc_mpesa_init', 0);
function wc_mpesa_init() {
    if (!wc_mpesa_check_woocommerce()) {
        return;
    }

    load_plugin_textdomain(
        'wc-mpesa-gateway',
        false,
        dirname(WC_MPESA_PLUGIN_BASENAME) . '/languages'
    );

    require_once WC_MPESA_PLUGIN_DIR . 'includes/class-wc-mpesa-logger.php';
    require_once WC_MPESA_PLUGIN_DIR . 'includes/class-wc-mpesa-database.php';
    require_once WC_MPESA_PLUGIN_DIR . 'includes/class-wc-mpesa-api.php';
    require_once WC_MPESA_PLUGIN_DIR . 'includes/class-wc-mpesa-callback.php';
    require_once WC_MPESA_PLUGIN_DIR . 'includes/class-wc-mpesa-gateway.php';

    if (is_admin()) {
        require_once WC_MPESA_PLUGIN_DIR . 'includes/class-wc-mpesa-admin.php';
    }

    // Register gateway
    add_filter('woocommerce_payment_gateways', 'wc_mpesa_add_gateway');

    // Initialize REST callback routes
    if (class_exists('WC_MPesa_Callback')) {
        new WC_MPesa_Callback();
    }
}

function wc_mpesa_add_gateway($gateways) {
    $gateways[] = 'WC_MPesa_Gateway';
    return $gateways;
}

register_activation_hook(__FILE__, 'wc_mpesa_activate');
function wc_mpesa_activate() {
    require_once WC_MPESA_PLUGIN_DIR . 'includes/class-wc-mpesa-database.php';
    WC_MPesa_Database::create_tables();

    if (!get_option('wc_mpesa_settings')) {
        update_option('wc_mpesa_settings', [
            'environment' => 'sandbox',
            'shortcode' => '',
            'passkey' => '',
            'consumer_key' => '',
            'consumer_secret' => '',
            'debug' => 'no',
        ]);
    }

    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'wc_mpesa_deactivate');
function wc_mpesa_deactivate() {
    flush_rewrite_rules();
}


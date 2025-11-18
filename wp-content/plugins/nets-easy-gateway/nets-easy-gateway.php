<?php
/*
Plugin Name: DIBS Payment Gateway
Description: WooCommerce payment gateway with split payout support using DIBS Payment API.
Version: 1.0
Author: ChatGPT
*/

defined('ABSPATH') || exit;

define('NETS_EASY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NETS_EASY_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        require_once NETS_EASY_PLUGIN_PATH . 'includes/class-gibbs-dibs-payment-gateway.php';
        require_once NETS_EASY_PLUGIN_PATH . 'includes/class-gibbs-dibs-payment-frontend.php';
        require_once NETS_EASY_PLUGIN_PATH . 'includes/class-gibbs-dibs-payment-api.php';
        require_once NETS_EASY_PLUGIN_PATH . 'includes/class-gibbs-dibs-payment-webhook-handler.php';
        
        add_filter('woocommerce_payment_gateways', function($gateways) {
            $gateways[] = 'WC_Gateway_Gibbs_DIBS_Payment';
            return $gateways;
        });
        
        // Initialize frontend class
        new Gibbs_DIBS_Payment_Frontend();
        // Initialize webhook handler
        new Gibbs_DIBS_Payment_Webhook_Handler();
        
    }
});

// Enqueue frontend styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('nets-easy-frontend', NETS_EASY_PLUGIN_URL . 'assets/css/nets-easy-frontend.css', array(), '1.0.0');
});

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function() {
    // Add rewrite rule for webhook
    add_rewrite_rule(
        '^dibs-payment-webhook/?$',
        'index.php?dibs_payment_webhook=1',
        'top'
    );
    add_rewrite_tag('%dibs_payment_webhook%', '([^&]+)');
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Flush rewrite rules on plugin deactivation
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});


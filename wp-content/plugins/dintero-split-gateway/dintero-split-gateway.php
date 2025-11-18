<?php
/*
Plugin Name: Dintero Split Gateway
Description: WooCommerce payment gateway with split payout support using Dintero.
Version: 1.3
Author: ChatGPT
*/

defined('ABSPATH') || exit;

define('DINTERO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DINTERO_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        require_once DINTERO_PLUGIN_PATH . 'includes/class-dintero-gateway.php';
        require_once DINTERO_PLUGIN_PATH . 'includes/class-dintero-frontend.php';
        require_once DINTERO_PLUGIN_PATH . 'includes/class-dintero-api.php';
        require_once DINTERO_PLUGIN_PATH . 'includes/class-dintero-webhook-handler.php';
        
        add_filter('woocommerce_payment_gateways', function($gateways) {
            $gateways[] = 'WC_Gateway_Dintero';
            return $gateways;
        });
        
        // Initialize frontend class
        new Dintero_Frontend();
        // Initialize webhook handler
        new Dintero_Webhook_Handler();
        
    }
});

// Enqueue frontend styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dintero-frontend', DINTERO_PLUGIN_URL . 'assets/css/dintero-frontend.css', array(), '1.0.0');
});

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function() {
    // Add rewrite rule for webhook
    add_rewrite_rule(
        '^dintero-webhook/?$',
        'index.php?dintero_webhook=1',
        'top'
    );
    add_rewrite_tag('%dintero_webhook%', '([^&]+)');
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

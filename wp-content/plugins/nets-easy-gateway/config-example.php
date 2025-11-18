<?php
/**
 * Configuration Example for Nets Easy Gateway Plugin
 * 
 * This file shows example configurations for the plugin.
 * Copy the relevant settings to your wp-config.php or use the admin interface.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Example configuration constants (add to wp-config.php)
 * 
 * These are optional and can be overridden in the admin settings
 */

// Test environment credentials
define('NETS_EASY_TEST_SECRET_KEY', 'your_test_secret_key_here');
define('NETS_EASY_TEST_CHECKOUT_KEY', 'your_test_checkout_key_here');

// Live environment credentials
define('NETS_EASY_LIVE_SECRET_KEY', 'your_live_secret_key_here');
define('NETS_EASY_LIVE_CHECKOUT_KEY', 'your_live_checkout_key_here');

// Merchant ID (same for test and live)
define('NETS_EASY_MERCHANT_ID', 'your_merchant_id_here');

// Webhook secret for security
define('NETS_EASY_WEBHOOK_SECRET', 'your_webhook_secret_here');

// Default split percentage (admin gets this percentage)
define('NETS_EASY_DEFAULT_SPLIT_PERCENTAGE', 20);

/*
 * Example webhook configuration
 * 
 * Configure this URL in your Nets Easy merchant dashboard:
 * https://yoursite.com/nets-easy-webhook/
 */

/*
 * Example API endpoints (for reference)
 * 
 * Test Environment:
 * - Base URL: https://test.api.nets.com/
 * - Auth: /v1/merchants/{merchantId}/auth/token
 * - Payments: /v1/payments
 * 
 * Live Environment:
 * - Base URL: https://api.nets.com/
 * - Auth: /v1/merchants/{merchantId}/auth/token
 * - Payments: /v1/payments
 */

/*
 * Example webhook events handled by the plugin:
 * 
 * - payment.checkout.completed: Payment successfully completed
 * - payment.charge.created: Payment captured
 * - payment.charge.failed: Payment failed
 * - payment.refund.created: Payment refunded
 */

/*
 * Example order metadata stored by the plugin:
 * 
 * - _nets_easy_payment_id: The Nets Easy payment ID
 * - _nets_easy_seller_id: The seller's payout destination ID
 * - _nets_easy_split_data: Array containing split payment information
 * - _nets_easy_amount: Payment amount
 * - _nets_easy_currency: Payment currency
 * - _nets_easy_status: Payment status from Nets Easy
 * - _nets_easy_webhook_data: Complete webhook data
 */

/*
 * Example usage in custom code:
 * 
 * // Check if Nets Easy payment is enabled for a user
 * if (Nets_Easy_Frontend::is_nets_easy_enabled($user_id)) {
 *     // User has Nets Easy enabled
 * }
 * 
 * // Get user's Nets Easy settings
 * $settings = Nets_Easy_Frontend::get_nets_easy_settings($user_id);
 * 
 * // Test API connection
 * $api = new Nets_Easy_API();
 * $result = $api->testConnection();
 * 
 * // Get payment details
 * $payment = $api->get_payment($payment_id);
 * 
 * // Capture payment
 * $capture = $api->capture_payment($payment_id, $amount);
 * 
 * // Refund payment
 * $refund = $api->refund_payment($payment_id, $amount);
 */

/*
 * Example database queries for troubleshooting:
 * 
 * -- Find orders with Nets Easy payments
 * SELECT p.ID, p.post_title, pm.meta_value as payment_id
 * FROM wp_posts p
 * JOIN wp_postmeta pm ON p.ID = pm.post_id
 * WHERE pm.meta_key = '_nets_easy_payment_id'
 * AND p.post_type = 'shop_order';
 * 
 * -- Find user Nets Easy settings
 * SELECT u.ID, u.user_login, um.meta_value as settings
 * FROM wp_users u
 * JOIN wp_usermeta um ON u.ID = um.user_id
 * WHERE um.meta_key = 'nets_easy_settings';
 * 
 * -- Find webhook data for an order
 * SELECT meta_value as webhook_data
 * FROM wp_postmeta
 * WHERE post_id = {order_id}
 * AND meta_key = '_nets_easy_webhook_data';
 */

/*
 * Security considerations:
 * 
 * 1. Always use HTTPS in production
 * 2. Keep API credentials secure and never commit them to version control
 * 3. Use webhook signature verification
 * 4. Regularly rotate API keys
 * 5. Monitor webhook logs for suspicious activity
 * 6. Use strong, unique webhook secrets
 */

/*
 * Performance considerations:
 * 
 * 1. Cache API responses when appropriate
 * 2. Use background processing for heavy operations
 * 3. Implement proper error handling and retry logic
 * 4. Monitor API rate limits
 * 5. Use database indexes for frequently queried meta fields
 */

/*
 * Troubleshooting checklist:
 * 
 * 1. Check if WooCommerce is active
 * 2. Verify API credentials are correct
 * 3. Ensure webhook URL is accessible
 * 4. Check WordPress error logs
 * 5. Verify rewrite rules are flushed
 * 6. Test with Nets Easy test environment first
 * 7. Check if all required PHP extensions are installed
 * 8. Verify file permissions are correct
 */

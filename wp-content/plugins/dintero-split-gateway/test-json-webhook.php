<?php
/**
 * Test script for Dintero webhook JSON responses
 * Access this file directly in your browser to test the webhook
 */

// Include WordPress
require_once('../../../wp-load.php');

// Check if webhook handler exists
if (!class_exists('Dintero_Webhook_Handler')) {
    die('Dintero Webhook Handler not found. Make sure the plugin is activated.');
}

echo '<h1>Dintero Webhook JSON Response Test</h1>';

// Test the webhook URL
$webhook_url = Dintero_Webhook_Handler::get_webhook_url();
echo '<p><strong>Webhook URL:</strong> <code>' . esc_html($webhook_url) . '</code></p>';

// Test cases
$test_cases = array(
    'success' => array(
        'orderid' => '123',
        'status' => 'captured',
        'transaction_id' => 'test_txn_' . time(),
        'amount' => '1000',
        'currency' => 'NOK'
    ),
    'no_order_id' => array(
        'status' => 'captured',
        'amount' => '1000'
    ),
    'invalid_order' => array(
        'orderid' => '999999',
        'status' => 'captured',
        'amount' => '1000'
    )
);

foreach ($test_cases as $test_name => $test_data) {
    echo '<h2>Test: ' . esc_html($test_name) . '</h2>';
    
    $get_url = $webhook_url . '?' . http_build_query($test_data);
    echo '<p><strong>URL:</strong> <code>' . esc_html($get_url) . '</code></p>';
    
    $response = wp_remote_get($get_url);
    
    if (is_wp_error($response)) {
        echo '<p style="color: red;">Error: ' . esc_html($response->get_error_message()) . '</p>';
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        echo '<p><strong>Response Code:</strong> ' . esc_html($status_code) . '</p>';
        echo '<p><strong>Content-Type:</strong> ' . esc_html($headers['content-type'] ?? 'not set') . '</p>';
        
        // Try to decode JSON response
        $json_data = json_decode($body, true);
        if ($json_data) {
            echo '<p><strong>JSON Response:</strong></p>';
            echo '<div style="background: #f1f1f1; padding: 10px; border-radius: 4px;">';
            echo '<pre>' . esc_html(json_encode($json_data, JSON_PRETTY_PRINT)) . '</pre>';
            echo '</div>';
            
            if (isset($json_data['success'])) {
                if ($json_data['success']) {
                    echo '<p style="color: green;">✅ Success response received</p>';
                } else {
                    echo '<p style="color: orange;">⚠️ Error response received</p>';
                }
            }
        } else {
            echo '<p><strong>Raw Response:</strong></p>';
            echo '<div style="background: #f1f1f1; padding: 10px; border-radius: 4px;">';
            echo '<pre>' . esc_html($body) . '</pre>';
            echo '</div>';
        }
    }
    
    echo '<hr>';
}

echo '<h2>Expected JSON Response Format</h2>';
echo '<h3>Success Response:</h3>';
echo '<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px;">';
echo esc_html(json_encode(array(
    'success' => true,
    'message' => 'Payment captured successfully',
    'timestamp' => '2024-01-01 12:00:00',
    'data' => array(
        'order_id' => '123',
        'status' => 'processing'
    )
), JSON_PRETTY_PRINT));
echo '</pre>';

echo '<h3>Error Response:</h3>';
echo '<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px;">';
echo esc_html(json_encode(array(
    'success' => false,
    'error' => 'Order not found: 999999',
    'code' => 404,
    'timestamp' => '2024-01-01 12:00:00'
), JSON_PRETTY_PRINT));
echo '</pre>'; 
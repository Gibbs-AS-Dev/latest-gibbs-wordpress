<?php
/**
 * Test URL for slot-booking-confirmation
 * 
 * This file can be accessed directly to test if the URL routing is working
 */

// Include WordPress
require_once('../../../wp-load.php');

// Test the URL routing
$test_url = home_url('/slot-booking-confirmation/test-booking-123/');

echo "<h1>Testing Slot Booking Confirmation URL</h1>";
echo "<p>Test URL: <a href='{$test_url}' target='_blank'>{$test_url}</a></p>";

// Test the rewrite rules
echo "<h2>Current Rewrite Rules</h2>";
global $wp_rewrite;
echo "<pre>";
print_r($wp_rewrite->rules);
echo "</pre>";

// Test query vars
echo "<h2>Query Vars</h2>";
echo "<pre>";
print_r(get_query_vars());
echo "</pre>";

// Test if the plugin is active
echo "<h2>Plugin Status</h2>";
if (class_exists('ReactModulesPlugin')) {
    echo "<p style='color: green;'>✅ ReactModulesPlugin class exists</p>";
} else {
    echo "<p style='color: red;'>❌ ReactModulesPlugin class not found</p>";
}

// Test if the template exists
$template_path = RMP_PLUGIN_PATH . 'templates/booking-confirmation.php';
echo "<h2>Template Status</h2>";
if (file_exists($template_path)) {
    echo "<p style='color: green;'>✅ Template exists: {$template_path}</p>";
} else {
    echo "<p style='color: red;'>❌ Template not found: {$template_path}</p>";
}

// Test the booking confirmation URL generation
if (class_exists('ReactModulesPlugin')) {
    $plugin = new ReactModulesPlugin();
    $confirmation_url = $plugin->get_booking_confirmation_url('test-booking-123');
    echo "<h2>Generated URL</h2>";
    echo "<p>Generated URL: <a href='{$confirmation_url}' target='_blank'>{$confirmation_url}</a></p>";
}
?> 
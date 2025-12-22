<?php
/*
Plugin Name: Gibbs React booking
Description: A WordPress plugin that provides React.js modules accessible via shortcodes with API data integration
Version: 1.0.0
Author: sk
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RMP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RMP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RMP_PLUGIN_VERSION', '1.0.2');

// Include the main plugin class
require_once RMP_PLUGIN_PATH . 'includes/class-react-modules-plugin.php';

// Include customer columns definition (global)
require_once RMP_PLUGIN_PATH . 'includes/class-customer-columns.php';

// Include customer actions definition (global)
require_once RMP_PLUGIN_PATH . 'includes/class-customer-actions.php';

// Include the customer role admin class
require_once RMP_PLUGIN_PATH . 'includes/class-customer-role-admin.php';

// Initialize the plugin
function rmp_init_plugin() {
    $plugin = new ReactModulesPlugin();
    
    // Initialize customer role admin (only in admin area)
    if (is_admin()) {
        new Customer_Role_Admin();
    }
}

add_action('plugins_loaded', 'rmp_init_plugin');

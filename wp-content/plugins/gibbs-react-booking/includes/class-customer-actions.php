<?php
/**
 * Customer Actions Definition
 * Global definition of customer actions/permissions
 * 
 * @package GibbsReactBooking
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer Actions Class
 * Provides global access to customer action definitions
 */
class Customer_Actions {

    /**
     * Get all available actions with their keys and labels
     * 
     * @return array Array of actions with 'key' => 'label' format
     */
    public static function get_all_actions() {
        return array(
            'edit_customer' => __('Edit Customer', 'gibbs-react-booking'),
            'edit_usergroup' => __('Edit Usergroup', 'gibbs-react-booking'),
            'create_customer' => __('Create Customer', 'gibbs-react-booking'),
            'delete_customer' => __('Delete Customer', 'gibbs-react-booking'),
            'switch_customer' => __('Switch Customer', 'gibbs-react-booking'),
            'change_superadmin' => __('Change Superadmin', 'gibbs-react-booking'),
            'update_group_licenses' => __('Update Group Licenses', 'gibbs-react-booking'),
            'update_next_invoice' => __('Update Next Invoice', 'gibbs-react-booking'),
            'update_mrr_arr' => __('Update MRR/ARR', 'gibbs-react-booking'),
            'total_arr' => __('Total ARR', 'gibbs-react-booking'),
            'total_mrr' => __('Total MRR', 'gibbs-react-booking'),
            'manage_preferences' => __('Manage Preferences', 'gibbs-react-booking'),
            'view_customer_list' => __('View Customer List', 'gibbs-react-booking')
        );
    }

    /**
     * Get action keys only
     * 
     * @return array Array of action keys
     */
    public static function get_action_keys() {
        return array_keys(self::get_all_actions());
    }

    /**
     * Get action label by key
     * 
     * @param string $key Action key
     * @return string Action label or empty string if not found
     */
    public static function get_action_label($key) {
        $actions = self::get_all_actions();
        return isset($actions[$key]) ? $actions[$key] : '';
    }

    /**
     * Check if action key exists
     * 
     * @param string $key Action key
     * @return bool True if action exists
     */
    public static function action_exists($key) {
        $actions = self::get_all_actions();
        return isset($actions[$key]);
    }

    /**
     * Get actions as JSON (for JavaScript use)
     * 
     * @return string JSON encoded actions array
     */
    public static function get_actions_json() {
        $actions = self::get_all_actions();
        $formatted = array();
        
        foreach ($actions as $key => $label) {
            $formatted[] = array(
                'key' => $key,
                'label' => $label
            );
        }
        
        return json_encode($formatted);
    }

    /**
     * Get actions as array for JavaScript (with key and label)
     * 
     * @return array Array of actions with 'key' and 'label'
     */
    public static function get_actions_array() {
        $actions = self::get_all_actions();
        $formatted = array();
        
        foreach ($actions as $key => $label) {
            $formatted[] = array(
                'key' => $key,
                'label' => $label
            );
        }
        
        return $formatted;
    }

    /**
     * Get capability name for an action
     * 
     * @param string $action_key Action key
     * @return string Capability name
     */
    public static function get_capability_name($action_key) {
        return 'gibbs_customer_' . $action_key;
    }

    /**
     * Filter actions by keys
     * 
     * @param array $keys Array of action keys to filter
     * @return array Filtered actions array
     */
    public static function filter_actions($keys) {
        $all_actions = self::get_all_actions();
        $filtered = array();
        
        foreach ($keys as $key) {
            if (isset($all_actions[$key])) {
                $filtered[$key] = $all_actions[$key];
            }
        }
        
        return $filtered;
    }
}


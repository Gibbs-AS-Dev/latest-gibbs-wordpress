<?php
/**
 * Customer Columns Definition
 * Global definition of customer list columns
 * 
 * @package GibbsReactBooking
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer Columns Class
 * Provides global access to customer list column definitions
 */
class Customer_Columns {

    /**
     * Get all available columns with their keys and labels
     * 
     * @return array Array of columns with 'key' => 'label' format
     */
    public static function get_all_columns() {
        return array(
            'company_name' => __('Company Name', 'gibbs-react-booking'),
            'superadmin' => __('Superadmin', 'gibbs-react-booking'),
            'email' => __('Email', 'gibbs-react-booking'),
            'phone' => __('Phone', 'gibbs-react-booking'),
            'company_country' => __('Country', 'gibbs-react-booking'),
            'company_industry' => __('Industry', 'gibbs-react-booking'),
            'mrr' => __('MRR', 'gibbs-react-booking'),
            'arr' => __('ARR', 'gibbs-react-booking'),
            'gibbs_licenses' => __('Gibbs Licenses', 'gibbs-react-booking'),
            'created_at' => __('Created', 'gibbs-react-booking'),
            'stripe_license' => __('Stripe License', 'gibbs-react-booking'),
            'payment' => __('Payment', 'gibbs-react-booking'),
            'next_invoice' => __('Next Invoice', 'gibbs-react-booking'),
            'customer_notes' => __('Customer Notes', 'gibbs-react-booking'),
            'created_by' => __('Created By', 'gibbs-react-booking'),
            'canceled_at' => __('Cancel Date', 'gibbs-react-booking')
        );
    }

    /**
     * Get column keys only
     * 
     * @return array Array of column keys
     */
    public static function get_column_keys() {
        return array_keys(self::get_all_columns());
    }

    /**
     * Get column label by key
     * 
     * @param string $key Column key
     * @return string Column label or empty string if not found
     */
    public static function get_column_label($key) {
        $columns = self::get_all_columns();
        return isset($columns[$key]) ? $columns[$key] : '';
    }

    /**
     * Check if column key exists
     * 
     * @param string $key Column key
     * @return bool True if column exists
     */
    public static function column_exists($key) {
        $columns = self::get_all_columns();
        return isset($columns[$key]);
    }

    /**
     * Get columns as JSON (for JavaScript use)
     * 
     * @return string JSON encoded columns array
     */
    public static function get_columns_json() {
        $columns = self::get_all_columns();
        $formatted = array();
        
        foreach ($columns as $key => $label) {
            $formatted[] = array(
                'key' => $key,
                'label' => $label
            );
        }
        
        return json_encode($formatted);
    }

    /**
     * Get columns as array for JavaScript (with key and label)
     * 
     * @return array Array of columns with 'key' and 'label'
     */
    public static function get_columns_array() {
        $columns = self::get_all_columns();
        $formatted = array();
        
        foreach ($columns as $key => $label) {
            $formatted[] = array(
                'key' => $key,
                'label' => $label
            );
        }
        
        return $formatted;
    }

    /**
     * Filter columns by keys
     * 
     * @param array $keys Array of column keys to filter
     * @return array Filtered columns array
     */
    public static function filter_columns($keys) {
        $all_columns = self::get_all_columns();
        $filtered = array();
        
        foreach ($keys as $key) {
            if (isset($all_columns[$key])) {
                $filtered[$key] = $all_columns[$key];
            }
        }
        
        return $filtered;
    }
}


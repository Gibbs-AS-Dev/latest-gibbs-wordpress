<?php
defined('ABSPATH') || exit;

class Dintero_Frontend {

    public function __construct() {
        add_action('wp_ajax_save_dintero_settings', array($this, 'save_dintero_settings'));
        add_action('wp_ajax_nopriv_save_dintero_settings', array($this, 'save_dintero_settings'));
        add_action('wp_ajax_save_dintero_payment_status', array($this, 'save_dintero_payment_status'));
        add_action('wp_ajax_nopriv_save_dintero_payment_status', array($this, 'save_dintero_payment_status'));
        add_action('wp_ajax_create_dintero_seller', array($this, 'create_dintero_seller'));
        add_action('wp_ajax_nopriv_create_dintero_seller', array($this, 'create_dintero_seller'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Save Dintero settings via AJAX
     */
    public function save_dintero_settings() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dintero_settings_nonce')) {
            wp_die('Security check failed');
        }

        // Get current user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }

        $this->create_dintero_seller();

        // Get group admin for currency user
        // $group_admin = $this->get_group_admin();
        // $currency_user_id = ($group_admin != "") ? $group_admin : $user_id;

        // // Sanitize and save form data
        // $dintero_settings = array(
        //     'bank_name' => sanitize_text_field($_POST['dintero_bank_name'] ?? ''),
        //     'bank_account_number' => sanitize_text_field($_POST['dintero_bank_account_number'] ?? ''),
        //     'bank_account_number_type' => sanitize_text_field($_POST['dintero_bank_account_number_type'] ?? ''),
        //     'bank_account_country_code' => sanitize_text_field($_POST['dintero_bank_account_country_code'] ?? ''),
        //     'bank_account_currency' => sanitize_text_field($_POST['dintero_bank_account_currency'] ?? ''),
        //     'payout_currency' => sanitize_text_field($_POST['dintero_payout_currency'] ?? ''),
        //     'bank_identification_code' => sanitize_text_field($_POST['dintero_bank_identification_code'] ?? '')
        // );

        // // Save each setting as user meta
        // foreach ($dintero_settings as $key => $value) {
        //     update_user_meta($currency_user_id, 'dintero_' . $key, $value);
        // }

        // // Also save the complete settings array
        // update_user_meta($currency_user_id, 'dintero_settings', $dintero_settings);

        // wp_send_json_success('Dintero settings saved successfully');
    }

    public function get_seller_data($seller_id){
        try {
            $api = new Dintero_API();
            $result = $api->getSeller($seller_id);
            if ($result['success']) {

                //echo '<pre>'; print_r($result); die;
                return $result['data'];
            } else {
                return $result['message'];
            }
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Save Dintero payment status via AJAX
     */
    public function save_dintero_payment_status() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dintero_payment_nonce')) {
            wp_die('Security check failed');
        }

        // Get current user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }

        // Get group admin for currency user
        $group_admin = $this->get_group_admin();
        $currency_user_id = ($group_admin != "") ? $group_admin : $user_id;

        // Get the checkbox value
        $payment_enabled = isset($_POST['dintero_payment_enabled']) && $_POST['dintero_payment_enabled'] == 'on' ? 'on' : 'off';

        $user_payout_destination_id = get_user_meta($currency_user_id, 'user_payout_destination_id', true);

        if(empty($user_payout_destination_id)){
            wp_send_json_success('');
            return;
        }

        // Save the payment status
        update_user_meta($currency_user_id, 'dintero_payment', $payment_enabled);

        wp_send_json_success('Dintero payment status updated successfully');
    }

    /**
     * Create Dintero seller via AJAX
     */
    public function create_dintero_seller() {
        // Verify nonce for security
    

        // Get current user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }

        $group_admin = $this->get_group_admin();
        $cr_user_id = ($group_admin != "") ? $group_admin : $user_id;

        // $user_dintero_id = get_user_meta($cr_user_id,"user_dintero_id",true);

        // if($user_dintero_id && $user_dintero_id != ""){

        //     wp_send_json_error("Already generated seller account.");

        // }

        //echo "<pre>"; print_r($dintero_seller_data); die;

        try {
            $api = new Dintero_API($user_id);
            $result = $api->createSeller($_POST);
            
            if ($result['success'] && isset($result["data"]["id"])) {

                $user_payout_destination_id = get_user_meta($cr_user_id, 'user_payout_destination_id', true);
                $user_dintero_id = get_user_meta($cr_user_id, 'user_dintero_id', true);
                
                if($user_payout_destination_id && $user_payout_destination_id != ""){
                    add_user_meta($cr_user_id, 'old_user_payout_destination_id', $user_payout_destination_id);
                }

                if($user_dintero_id && $user_dintero_id != ""){
                   add_user_meta($cr_user_id, 'old_user_dintero_id', $user_dintero_id);
                }
               
                update_user_meta($cr_user_id, 'dintero_seller_data', $result["data"]);
                update_user_meta($cr_user_id, 'user_payout_destination_id', $result["data"]["payout_destination_id"]);
                update_user_meta($cr_user_id, 'user_dintero_id', $result["data"]["id"]);
                
                wp_send_json_success($result['message']);

            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Seller creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Get Dintero settings for a user
     */
    public static function get_dintero_settings($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // if (!$user_id) {
        //     return self::get_default_settings();
        // }

        try {
            // Get group admin for currency user
            $group_admin = self::get_group_admin();
            $currency_user_id = ($group_admin != "") ? $group_admin : $user_id;

            $user_dintero_id = get_user_meta($currency_user_id, 'user_dintero_id', true);

            if($user_dintero_id && $user_dintero_id != ""){
                $api = new Dintero_API($currency_user_id);
                $result = $api->fetchSeller($user_dintero_id);

                if($result['success'] && isset($result['data']['payout_destination_id'])){
                    return $result['data'];
                }
            }

            return null;

            // Try to get complete settings array first
            // $settings = get_user_meta($currency_user_id, 'dintero_settings', true);
            
            // if (!$settings) {
            //     // Fallback to individual meta fields
            //     $settings = array(
            //         'bank_name' => get_user_meta($currency_user_id, 'dintero_bank_name', true),
            //         'bank_account_number' => get_user_meta($currency_user_id, 'dintero_bank_account_number', true),
            //         'bank_account_number_type' => get_user_meta($currency_user_id, 'dintero_bank_account_number_type', true),
            //         'bank_account_country_code' => get_user_meta($currency_user_id, 'dintero_bank_account_country_code', true),
            //         'bank_account_currency' => get_user_meta($currency_user_id, 'dintero_bank_account_currency', true),
            //         'payout_currency' => get_user_meta($currency_user_id, 'dintero_payout_currency', true),
            //         'bank_identification_code' => get_user_meta($currency_user_id, 'dintero_bank_identification_code', true)
            //     );
            // }

            // return $settings;
        } catch (Exception $e) {
            return null;
            // Return default settings if there's any error
            //return self::get_default_settings();
        }
    }

    /**
     * Check if Dintero payment is enabled for user
     */
    public static function is_dintero_enabled($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Get group admin for currency user
        $group_admin = self::get_group_admin();
        $currency_user_id = ($group_admin != "") ? $group_admin : $user_id;

        return get_user_meta($currency_user_id, 'dintero_payment', true) === 'on';
    }

    /**
     * Get group admin function (copied from existing code)
     */
    public static function get_group_admin() {
        try {
            global $wpdb;
            
            $active_group_id = get_user_meta(get_current_user_id(), '_gibbs_active_group_id', true);
            
            if ($active_group_id != "" && is_numeric($active_group_id)) {
                $users_groups_table = $wpdb->prefix . 'users_groups';
                
                // Check if table exists
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$users_groups_table'");
                if (!$table_exists) {
                    return "";
                }
                
                $sql_user_group_modal = $wpdb->prepare("SELECT group_admin FROM `$users_groups_table` WHERE id = %d", $active_group_id);
                $user_group_data_modal = $wpdb->get_row($sql_user_group_modal);
                
                if (isset($user_group_data_modal->group_admin) && $user_group_data_modal->group_admin != "") {
                    return $user_group_data_modal->group_admin;
                }
            }
            
            return "";
        } catch (Exception $e) {
            return "";
        }
    }

    /**
     * Enqueue scripts for frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_script('dintero-frontend', DINTERO_PLUGIN_URL . 'assets/js/dintero-frontend.js', array('jquery'), '1.0.0', true);
        wp_localize_script('dintero-frontend', 'dintero_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dintero_settings_nonce'),
            'payment_nonce' => wp_create_nonce('dintero_payment_nonce'),
            'seller_nonce' => wp_create_nonce('dintero_seller_nonce')
        ));
    }

    /**
     * Get default Dintero settings
     */
    public static function get_default_settings() {
        return array(
            'bank_name' => 'DNB',
            'bank_account_number' => 'NO9386011117947',
            'bank_account_number_type' => 'IBAN',
            'bank_account_country_code' => 'NO',
            'bank_account_currency' => 'NOK',
            'payout_currency' => 'NOK',
            'bank_identification_code' => 'DNBANOKKXXX'
        );
    }
} 
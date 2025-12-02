<?php

class Class_Gibbs_Subscription 
{
    private $stripe;
    private $publishableKey;
    private $secretKey;
    private $stripe_webhook;
    private $stripe_custom_plan_product_id;
    private $taxId;
    private $stripe_mode;

    public function action_init() {

        $mode = get_option('stripe_mode');

        if ($mode === 'test') {

            $this->stripe_mode = "test";

            $this->publishableKey = get_option('stripe_test_publish_key');
            $this->secretKey = get_option('stripe_test_secret_key');
            $this->stripe_webhook = get_option('stripe_test_webhook');
            $this->stripe_custom_plan_product_id = get_option('stripe_test_custom_plan_product_id');
            $this->taxId = get_option('stripe_test_custom_tax_id');
        } else {
            $this->stripe_mode = "live";
            $this->publishableKey = get_option('stripe_live_publish_key');
            $this->secretKey = get_option('stripe_live_secret_key');
            $this->stripe_webhook = get_option('stripe_live_webhook');
            $this->stripe_custom_plan_product_id = get_option('stripe_live_custom_plan_product_id');
            $this->taxId = get_option('stripe_live_custom_tax_id');
        }
        // Load Stripe PHP Library
        require_once GIBBS_STRIPE_PATH . 'library/stripe/vendor/autoload.php'; // Adjust the path if necessary
        if($this->secretKey){
            $this->stripe = new \Stripe\StripeClient($this->secretKey);
        }else{
            $this->stripe = "";
        }
         // Replace with your secret key
        $this->register_post_type();
        // Add actions
        add_action('wp_ajax_create_checkout_session', [$this, 'create_checkout_session']);
        add_action('wp_ajax_nopriv_create_checkout_session', [$this, 'create_checkout_session']);
        add_action('wp_ajax_save_checkout_contact_info', [$this, 'save_checkout_contact_info']);
        add_action('wp_ajax_nopriv_save_checkout_contact_info', [$this, 'save_checkout_contact_info']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_webhook']);
        add_action('wp_ajax_update_subscription', [$this, 'update_subscription']);
        add_action('wp_ajax_cancel_subscription', [$this, 'cancel_subscription']);
        add_shortcode('package_view', [$this, 'render_package_view']);
        add_shortcode('user_stripe_subscription', [$this, 'render_subscription_management']);

        add_shortcode('subscription_register', [$this, 'subscription_register']);
        add_shortcode('user-dashboard', [$this, 'user_dashboard_func']);

        add_action('wp_ajax_stripe_dashboard', [$this, 'stripe_dashboard']);
        add_action('wp_ajax_nopriv_stripe_dashboard', [$this, 'stripe_dashboard']);

        add_action('wp_ajax_sms_payment', [$this, 'sms_payment']);
        add_action('wp_ajax_nopriv_sms_payment', [$this, 'sms_payment']);

        add_action('admin_menu', [new Class_Gibbs_Subscription_Admin, 'add_stripe_packages_submenu']);

       // $this->update_group_licence(get_current_user_id(),1);

        add_action( 'rest_api_init', function () {
            register_rest_route( 'v1', '/add_licence_id', array(
                'methods' => 'GET',
                'callback' => array( $this, 'add_licence_id' ),
            ) );
        } );
    }

    public function add_funds($user_id, $amount, $record_transaction_id, $back_url){
        $user_data = get_userdata($user_id);
        if($this->stripe_mode == "test"){
            $stripe_customer_id = get_user_meta($user_id, 'stripe_test_customer_id', true);
        }else{
            $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
        }

        
        // Create Stripe customer if it doesn't exist
        if (!$stripe_customer_id) {
            try {
                $customer = $this->stripe->customers->create([
                    'name' => $user_data->display_name,
                    'email' => $user_data->user_email,
                ]);
                if($this->stripe_mode == "test"){
                    update_user_meta($user_id, 'stripe_test_customer_id', $customer->id);
                }else{
                    update_user_meta($user_id, 'stripe_customer_id', $customer->id);
                }
                $stripe_customer_id = $customer->id;
            } catch (Exception $e) {
                return ['error' => 'Failed to create customer: ' . $e->getMessage()];
                wp_die();
            }
        }
        
        try {
            
            // Create a one-time payment checkout session for SMS
            //$back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url('/gibbs-wallet');
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'mode' => 'payment', // One-time payment, not subscription
                'customer' => $stripe_customer_id,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'NOK',
                        'product_data' => [
                            'name' => 'Wallet Payment',
                            'description' => sprintf('Payment for %d NOK', $amount),
                        ],
                        'unit_amount' => round($amount * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => add_query_arg(['wallet_payment' => 'success'], $back_url),
                'cancel_url' => add_query_arg(['wallet_payment' => 'cancelled'], $back_url),
                'metadata' => [
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'record_transaction_id' => $record_transaction_id,
                    'payment_type' => 'wallet'
                ]
            ]);
            
            return ['id' => $session->id, 'url' => $session->url];
            
        } catch (Exception $e) {
            return ['error' => 'Failed to create checkout session: ' . $e->getMessage()];
        }
        wp_die();
    }

    public function get_sms_count($group_admin, $type = ""){
        global $wpdb;
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type = 'listing'",
            $group_admin
        ));
        //echo "<pre>"; print_r($post_ids); die;
    
        $table_name = $wpdb->prefix . 'access_management';
        // Prepare placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE listing_id IN ($placeholders) AND created_at >= %s AND sms_payment_status != '1' AND sms_status LIKE %s order by created_at desc",
            array_merge($post_ids, array('2024-08-01', '%accepted_at%'))
        );
        $access_management_data = $wpdb->get_results($query);
    
        $total_sms_count = 0;
        $sms_length_counts = array();
        $access_ids = array();
        
        foreach($access_management_data as &$access_management){
            $content = isset($access_management->sms_content) ? $access_management->sms_content : '';
            $content_length = strlen($content);
    
            if($content_length == 0){
                $content_length = 1;
            }
            
            $message_count = ceil($content_length / 160);
            $access_management->sms_count = $message_count;
            $total_sms_count += $message_count;
            $access_ids[] = $access_management->id;
        }
        if($type == "get_access_ids"){
            return $access_ids;
        }else{
            return $total_sms_count;
        }
    }
    public function sms_payment() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            global $wpdb;
            $group_admin = get_group_admin();
            $total_sms_count = $this->get_sms_count($group_admin);
            $total_sms_price = $total_sms_count * 1;
            //$total_sms_price = 1;
            
            if ($total_sms_count <= 0) {
                echo json_encode(['error' => 'No SMS to pay for']);
                wp_die();
            }


            try{

                $gibbs_wallet_database_path = ABSPATH . 'wp-content/plugins/gibbs-react-booking/server/wallet/WalletDatabase.php';
                $gibbs_wallet_response_path = ABSPATH . 'wp-content/plugins/gibbs-react-booking/server/Response.php';
                $gibbs_wallet_api_path = ABSPATH . 'wp-content/plugins/gibbs-react-booking/server/wallet/WalletApi.php';
                if ( file_exists( $gibbs_wallet_database_path ) ) {
                    require_once( $gibbs_wallet_database_path );
                    require_once( $gibbs_wallet_response_path );
                    require_once( $gibbs_wallet_api_path );
                } else {
                    echo json_encode(['error' => 'Files not found']);
                    wp_die();
                }
                $wallet_database = new WalletDatabase();
                $cr_user_id = get_current_user_id();


                $wallet_data = $wallet_database->getWalletByUserId($cr_user_id);

                if(isset($wallet_data["balance"]) && $wallet_data["balance"] > 0){
                    $wallet_balance = $wallet_data["balance"];
                }else{
                    $wallet_balance = 0;
                }

                //$wallet_balance = floatval($wallet_balance) - floatval($total_sms_price);
                

                // if($wallet_balance < $total_sms_price){
                //     echo json_encode(['error' => 'Insufficient balance. Please add funds to your wallet.']);
                //     wp_die();
                // }

                // die;

                $deductFunds = $wallet_database->deductFunds($cr_user_id, $total_sms_price);

                if($deductFunds){

                    //$wallet_database->updateBalance($cr_user_id, $wallet_balance - $total_sms_price);

                    $wallet_database->recordTransaction($cr_user_id, 'debit', $total_sms_price, 'SMS Payment', 'SMS Payment', 'success');

                    $access_ids = $this->get_sms_count($group_admin, "get_access_ids");
                
                    if (!empty($access_ids)) {
                        $placeholders = implode(',', array_fill(0, count($access_ids), '%d'));
                        $query = $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}access_management 
                            SET sms_payment_status = '1' 
                            WHERE id IN ($placeholders) 
                            AND sms_payment_status != '1' 
                            AND sms_status LIKE %s",
                            array_merge($access_ids, array('%accepted_at%'))
                        );
                        
                        $wpdb->query($query);

                        echo json_encode(['success' => 'SMS payment successful']);
                        wp_die();
                    }else{
                        echo json_encode(['error' => 'No SMS to pay for']);
                        wp_die();
                    }

                }else{ 
                    echo json_encode(['error' => 'Failed to deduct funds']);
                    wp_die();
                }


            }catch(Exception $e){
                echo json_encode(['error' => 'Failed to deduct funds: ' . $e->getMessage()]);
                wp_die();
            }

            // $sql = "SELECT * FROM {$wpdb->prefix}access_management WHERE group_admin = %d AND sms_payment_status != '1' AND sms_status LIKE %s order by created_at desc";
            // $access_management_data = $wpdb->get_results($wpdb->prepare($sql, $group_admin, '%accepted_at%'));
            // echo "<pre>"; print_r($access_management_data); die;



            // echo "<pre>"; print_r($total_sms_count); die;
            
            // $user_id = $this->get_super_admin();
            // if (!$user_id) {
            //     echo json_encode(['error' => 'User not found']);
            //     wp_die();
            // }
          
            
            
            // if($this->stripe_mode == "test"){
            //     $stripe_customer_id = get_user_meta($user_id, 'stripe_test_customer_id', true);
            // }else{
            //     $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
            // }
            
            // // Create Stripe customer if it doesn't exist
            // if (!$stripe_customer_id) {
            //     try {
            //         $customer = $this->stripe->customers->create([
            //             'name' => $user_data->display_name,
            //             'email' => $user_data->user_email,
            //         ]);
            //         if($this->stripe_mode == "test"){
            //             update_user_meta($user_id, 'stripe_test_customer_id', $customer->id);
            //         }else{
            //             update_user_meta($user_id, 'stripe_customer_id', $customer->id);
            //         }
            //         $stripe_customer_id = $customer->id;
            //     } catch (Exception $e) {
            //         echo json_encode(['error' => 'Failed to create customer: ' . $e->getMessage()]);
            //         wp_die();
            //     }
            // }
            
            // try {
                
            //     // Create a one-time payment checkout session for SMS
            //     $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url('/gibbs-lisens');
            //     $session = $this->stripe->checkout->sessions->create([
            //         'payment_method_types' => ['card'],
            //         'mode' => 'payment', // One-time payment, not subscription
            //         'customer' => $stripe_customer_id,
            //         'line_items' => [[
            //             'price_data' => [
            //                 'currency' => 'NOK',
            //                 'product_data' => [
            //                     'name' => 'SMS Payment',
            //                     'description' => sprintf('Payment for %d SMS messages', $total_sms_count),
            //                 ],
            //                 'unit_amount' => round($total_sms_price * 100), // Convert to cents
            //             ],
            //             'quantity' => 1,
            //         ]],
            //         'success_url' => add_query_arg(['sms_payment' => 'success'], $back_url),
            //         'cancel_url' => add_query_arg(['sms_payment' => 'cancelled'], $back_url),
            //         'metadata' => [
            //             'sms_count' => $total_sms_count,
            //             'group_admin' => $group_admin,
            //             'payment_type' => 'sms'
            //         ]
            //     ]);
                
            //     echo json_encode(['id' => $session->id, 'url' => $session->url]);
                
            // } catch (Exception $e) {
            //     echo json_encode(['error' => 'Failed to create checkout session: ' . $e->getMessage()]);
            // }
            
            wp_die();
        }
    }

    public function get_super_admin() {

        $current_user = wp_get_current_user();

        $active_group_id = get_user_meta( $current_user->ID, '_gibbs_active_group_id',true );

        global $wpdb;
        $users_groups = $wpdb->prefix . 'users_groups';  // table name
        $sql_user_group = "select * from `$users_groups` where id = ".$active_group_id; 
        $user_group_data = $wpdb->get_row($sql_user_group);

        if(isset($user_group_data->superadmin) && $user_group_data->superadmin > 0){
            return $user_group_data->superadmin;
        }
        return null;
       
    }

    public function update_group_licence($user_id,$status) {

        global $wpdb;
        $sql = "SELECT id, group_admin FROM ". $wpdb->prefix . "users_groups WHERE superadmin  = ".$user_id."";
        $groups = $wpdb->get_results($sql);

        $table_name = $wpdb->prefix."users_and_users_groups_licence";

        foreach($groups as $group){

            $sql2 = "SELECT id FROM ". $table_name . " where licence_id = 10 AND users_groups_id  = ".$group->id;
            $group_exist = $wpdb->get_row($sql2);

            $sql3 = "SELECT ID FROM ". $wpdb->prefix . "posts WHERE post_type = 'listing' AND `post_author` = ".$group->group_admin;
            $listings = $wpdb->get_results($sql3);

            if($status == 1){
                foreach($listings as $listing){
                    update_post_meta($listing->ID, 'licence_is_active', "true");
                }
            }else{
                foreach($listings as $listing){
                    update_post_meta($listing->ID, 'licence_is_active', "false");
                }
            }

            if(isset($group_exist->id)){

                $where = array('id' => $group_exist->id);
                $data = array(
                    'licence_is_active' => $status,
                );
                $wpdb->update($table_name, $data, $where);

            }else{
                $data = array(
                    'users_groups_id' => $group->id,
                    'licence_id' => 10,
                    'licence_is_active' => $status,
                );
                $wpdb->insert($table_name, $data);
            }
            
            
        }
        $myfile = fopen(ABSPATH."/update_group_licence.txt", "w");

        $text2 = json_encode($wpdb);

        fwrite($myfile, $text2);

        fclose($myfile);
        return true;
       
    }

    public function add_licence_id(){
        // global $wpdb;
        // $data = array(
        //     'licence_id' => 10,
        //     'licence_is_active' => 0,
        // );
        // $sql = "
        //     UPDATE {$wpdb->prefix}users_and_users_groups_licence
        //     SET licence_id = %d, licence_is_active = %d
        // ";
        
        // $updated = $wpdb->query( $wpdb->prepare( $sql, $data['licence_id'], $data['licence_is_active'] ) );
    
        // echo "<pre>"; print_r($wpdb); die;
    }
    


    public function getLocks($user_id){
        global $wpdb;
        $sql = "SELECT count(*) FROM ". $wpdb->prefix . "access_management_match WHERE `owner_id` = ".$user_id." AND `provider`in ('igloohome','locky','unloc')";
        return $count_lock = $wpdb->get_var($sql);
    }
    public function getShally($user_id){
        global $wpdb;
        $sql = "SELECT count(*) FROM ". $wpdb->prefix . "access_management_match WHERE `owner_id` = ".$user_id." AND `provider`in ('shelly')";
        return $count_lock = $wpdb->get_var($sql);
    }

    public function update_price($user_id) {

        $package_id = get_user_meta($user_id, 'package_id', true);

        if($package_id != ""){

            $listing_count = $this->get_listing_count($user_id);

            $locks = $this->getLocks($user_id);
            $shelly = $this->getShally($user_id);
            
            $price_id = $this->getPriceId($package_id,$listing_count,$locks,$shelly);

            $license_status = get_user_meta($user_id, 'license_status', true);

            if($price_id != "" && $license_status == "active"){
                    $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

                    $subscriptions = $this->stripe->subscriptions->all(['customer' => $stripe_customer_id]);
                    if (count($subscriptions->data) > 0) {
                        // Update existing subscription
                        $subscription = $subscriptions->data[0]; // Get the first subscription (modify as needed)

                        $price_amount = $subscription->items->data[0]->price->unit_amount;

                        if($price_amount > 0){

                            $updated_subscription = $this->stripe->subscriptions->update($subscription->id, [
                                'items' => [[
                                    'id' => $subscription->items->data[0]->id,
                                    'price' => $price_id,
                                ]],
                            ]);

                        }


                    }

            }
        }    
    }
  
    
    public function register_post_type() {
        $args = [
            'labels'      => [
                'name'          => __('Stripe Packages', 'textdomain'),
                'singular_name' => __('Stripe Package', 'textdomain'),
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'rewrite' => ['slug' => 'stripe-packages'],
            'menu_position' => 6,
            'menu_icon' => 'dashicons-list-view',
        ];
        register_post_type('stripe-packages', $args);

        // Debug output
       // error_log('Stripe Packages post type registered');
    }

    public function stripe_dashboard(){
        $user_id = $this->get_super_admin();

        $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

        $customerId = $stripe_customer_id;

        try {
            // Create a session for the customer portal
            $session = $this->stripe->billingPortal->sessions->create([
                'customer' => $customerId,
                'return_url' => home_url(), // URL to redirect to after the portal
            ]);
           if(isset($session->url)){
            wp_redirect($session->url);
            exit;
           }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Handle error from Stripe API
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        wp_redirect(home_url());
        exit;
    }
    public function get_listing_count($user_id) {

        $count_listing = 0;

        global $wpdb;
        $sql = "SELECT id, group_admin FROM ". $wpdb->prefix . "users_groups WHERE superadmin  = ".$user_id."";
        $groups = $wpdb->get_results($sql);

        foreach($groups as $group){
            $cr_cuser = $group->group_admin;
            if($cr_cuser && $cr_cuser != "" && $cr_cuser > 0){
                $sql = "SELECT count(*) FROM ". $wpdb->prefix . "posts WHERE post_type = 'listing' AND `post_author` = ".$cr_cuser." AND `post_status` = 'publish'";
                $count_listing += $wpdb->get_var($sql);
            }
        }
        return $count_listing;
       
    }
    

    public function user_dashboard_func(){
        

    
        ob_start();

        require GIBBS_STRIPE_PATH . 'user-dashboard.php'; 

        return ob_get_clean();

    }
    public function subscription_register($atts){

        $redirect = "";
        if(isset($atts["redirect"])){
            $redirect = $atts["redirect"];
        }

        ob_start();

        require GIBBS_STRIPE_PATH . 'get-started.php'; 

        return ob_get_clean();

    }

    



    public function remove_active_package($user_id){

        $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
        $active_subscription_price_id = null;

        $has_active = false;

        if ($stripe_customer_id) {
            try {
                $subscriptions = $this->stripe->subscriptions->all(['customer' => $stripe_customer_id]);
                if (count($subscriptions->data) > 0) {
                }else{
                    update_user_meta($user_id, 'license_status', "inactive");
                }
            } catch (Exception $e) {
                error_log('Error fetching subscriptions: ' . $e->getMessage());
            }
        }

        return $has_active;

    }

    public function render_package_view() {
        if(!$this->stripe){
         return;
        }
        if(!is_user_logged_in()){
            wp_redirect(home_url());
        }
        ob_start();
        require GIBBS_STRIPE_PATH . 'packages.php'; 
        return ob_get_clean();
        exit;
        
    }



    public function enqueue_scripts() {
        wp_enqueue_style('stripe-plugin-css', GIBBS_STRIPE_URL . '/css/styles.css');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/');
        wp_enqueue_script('stripe-plugin-js', GIBBS_STRIPE_URL . '/js/stripe-plugin.js', ['jquery'], time(), true);

        wp_localize_script('stripe-plugin-js', 'stripePlugin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'publishableKey' => $this->publishableKey,
            'savingText' => __("Saving...", "gibbs"),
            'errorSavingText' => __("Error saving contact information", "gibbs"),
        ]);
    }

    public function create_checkout_session() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $package_id = $data['package_id']; 

            $user_id = $this->get_super_admin();

            $company_name = get_user_meta($user_id, 'billing_company', true);
            $organization_number = get_user_meta($user_id, 'company_number', true);
            $zip_code = get_user_meta($user_id, 'billing_postcode', true);
            $city = get_user_meta($user_id, 'billing_city', true);
            $street_address = get_user_meta($user_id, 'billing_address_1', true);



            
            $user_data = get_userdata($user_id);
            $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

            if(!$package_id){
                echo json_encode(['error' => "package id not found"]);
                wp_die();
            }

            $stripe_product_id = get_post_meta($package_id, 'stripe_product_id', true);
            $price = get_post_meta($package_id, 'start_price_id', true);

            if(!$price){
                echo json_encode(['error' => "price not found"]);
                wp_die();
            }
            $listing_count = $this->get_listing_count($user_id);

            $locks = $this->getLocks($user_id);
            $shelly = $this->getShally($user_id);

            $price_id = $this->getPriceId($package_id,$listing_count,$locks,$shelly);

            if(!$price_id){
                echo json_encode(['error' => "price_id not found"]);
                wp_die();
            }

            // Create Stripe customer if it doesn't exist
            if (!$stripe_customer_id) {
                try {
                    $customer = $this->stripe->customers->create([
                        'name' => $user_data->display_name,
                        'email' => $user_data->user_email,
                        'business_name' => $company_name,
                        'address' => [
                            'line1' => $street_address,
                            'postal_code' => $zip_code,
                            'city' => $city,
                        ],
                        'metadata' => [
                            'company_name' => $company_name,
                            'organization_number' => $organization_number,
                        ],
                    ]);
                    update_user_meta($user_id, 'stripe_customer_id', $customer->id);
                    $stripe_customer_id = $customer->id;
                } catch (Exception $e) {
                    echo json_encode(['error' => $e->getMessage()]);
                    wp_die();
                }
            }

            try {

                $subscriptions = $this->stripe->subscriptions->all(['customer' => $stripe_customer_id]);
                if (count($subscriptions->data) > 0) {
                    // Update existing subscription
                    $subscription = $subscriptions->data[0]; // Get the first subscription (modify as needed)
                    $updated_subscription = $this->stripe->subscriptions->update($subscription->id, [
                        'items' => [[
                            'id' => $subscription->items->data[0]->id,
                            'price' => $price_id,
                        ]],
                    ]);

                    update_user_meta($user_id, 'package_id', $package_id);
                    // Store the updated subscription ID in the user meta
                    update_user_meta($user_id, 'subscription_id', $updated_subscription->id);
                    echo json_encode(['status' => 'success', 'subscription_id' => $updated_subscription->id]);
                } else {

                    $trail = get_user_meta($user_id, 'stripe_trail', true);

                    if($trail == "true"){
                      $sub_dataa = array();
                    }else{
                      $sub_dataa = [
                            'trial_settings' => ['end_behavior' => ['missing_payment_method' => 'cancel']],
                            'trial_period_days' => 7,
                      ];
                    }

                    $lineitems = [
                        'price' => $price_id,
                        'quantity' => 1
                    ];

                    if($this->taxId != ""){
                        $lineitems["tax_rates"] = [$this->taxId];
                    }
                    // Create a new subscription
                    $session = $this->stripe->checkout->sessions->create([
                        'payment_method_types' => ['card'],
                        'mode' => 'subscription',
                        'customer' => $stripe_customer_id,
                        'line_items' => [$lineitems],
                        'subscription_data' => $sub_dataa,
                        'payment_method_collection' => 'if_required',
                        'success_url' => home_url('/thank-you'), // URL to redirect on success
                        'cancel_url' => home_url('/dashboard'),   // URL to redirect on cancel
                        'locale' => 'nb',
                    ]);

                    update_user_meta($user_id, 'package_id', $package_id);
                    // No need to store the subscription ID here, it'll be done in the webhook
                    echo json_encode(['id' => $session->id]);
                }
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            wp_die();
        }
    }

    public function save_checkout_contact_info() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if(class_exists('Class_Gibbs_Subscription')){
                $Class_Gibbs_Subscription = new Class_Gibbs_Subscription();
        
                $super_admin = $Class_Gibbs_Subscription->get_super_admin();
                if($super_admin != ""){
                    $info_user_id = $super_admin;
                }else{
                    $info_user_id = get_current_user_id();
                }
            }else{
                $info_user_id = get_current_user_id();
            }
            
            if (!$info_user_id) {
                echo json_encode(['success' => false, 'error' => __("User not found", "gibbs")]);
                wp_die();
            }
            
            // Save Company Information
            if(isset($data['package_company_name'])){
                update_user_meta($info_user_id, 'package_company_name', sanitize_text_field($data['package_company_name']));
            }
            if(isset($data['package_street_address'])){
                update_user_meta($info_user_id, 'package_street_address', sanitize_text_field($data['package_street_address']));
            }
            if(isset($data['package_zip_code'])){
                update_user_meta($info_user_id, 'package_zip_code', sanitize_text_field($data['package_zip_code']));
            }
            if(isset($data['package_city'])){
                update_user_meta($info_user_id, 'package_city', sanitize_text_field($data['package_city']));
            }
            if(isset($data['package_organization_number'])){
                update_user_meta($info_user_id, 'package_organization_number', sanitize_text_field($data['package_organization_number']));
            }
            
            echo json_encode(['success' => true]);
            wp_die();
        }
    }

    public function getPriceId($package_id,$listing_count,$locks,$shelly){
        
        

        $price = 0;

        $lock_price = get_post_meta($package_id,"lock_price",true);
        $shally_price = get_post_meta($package_id,"shally_price",true);
        $stripe_product_id = get_post_meta($package_id, 'stripe_product_id', true);

        if($listing_count >= 2 && $listing_count <= 5){
            $price = get_post_meta($package_id,"listing_2_to_5_price_id",true);
        }elseif($listing_count >= 6 && $listing_count <= 20){
            $price = get_post_meta($package_id,"listing_6_to_20_price_id",true);
        }elseif($listing_count >= 20){
            $price = get_post_meta($package_id,"listing_20_plus_price_id",true);
        }else{
            $price = get_post_meta($package_id,"start_price_id",true);
        }
       
        if($lock_price != ""){
            $lock_price = $lock_price * $locks;
            $price = $price + $lock_price;
        }
    
        if($shally_price != ""){
            $shally_price = $shally_price * $shelly;
            $price = $price + $shally_price;
        }
        $price_id = $this->getStripePriceId($price,$stripe_product_id); 

        if($price_id == ""){
            $price_id = $this->createStripePriceId($price,$stripe_product_id); 
        }
        return $price_id;

    }

    public function createStripePriceId($amount,$stripe_product_id){
        $price_id = "";
        try {
            $amount = $amount * 100;

            $priceData = [
                'unit_amount' => $amount,
                'currency' => "NOK",
                'product' => $stripe_product_id,
            ];

            $priceData['recurring'] = [
                'interval' => "month"
            ];

            // Create the price
            $price = $this->stripe->prices->create($priceData);

            if(isset($price->id)){
                $price_id = $price->id;
            }

        } catch (\Stripe\Exception\ApiErrorException $e) {
        }
        return $price_id;
        
    }
    public function getStripePriceId($amount,$stripe_product_id){
        try {
            $amount = $amount * 100;
            $prices = $this->stripe->prices->all(["product"=>$stripe_product_id]);

            $filteredPrices = array_filter($prices->data, function($price) use ($amount) {
                return $price->unit_amount == $amount;
            });

            $price_id = "";

            foreach($filteredPrices as $pricee){
                $price_id = $pricee->id;
            }

            return $price_id;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return "";
        }
        
    }

    public function register_webhook() {
        register_rest_route('stripe/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_stripe_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_stripe_webhook(WP_REST_Request $request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('Stripe-Signature');
        $endpoint_secret = $this->stripe_webhook; // Your webhook secret from Stripe

        try {
            $event =  \Stripe\Webhook::constructEvent(
                            $payload, $sig_header, $endpoint_secret
                        );

            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object; // Contains the session details

                    $myfile = fopen(ABSPATH."/customer_session_completed3.txt", "w");

                    $text2 = json_encode($session);

                    fwrite($myfile, $text2);

                    fclose($myfile);

                    // Check if this is an SMS payment
                    if (isset($session->metadata->payment_type) && $session->metadata->payment_type === 'sms') {
                        $this->handle_sms_payment_success($session);
                    }else if(isset($session->metadata->payment_type) && $session->metadata->payment_type === 'wallet'){
                        $this->handle_wallet_payment_success($session);
                    }elseif (isset($session->subscription)) {
                        $subscription_id = $session->subscription;
                        $customer_id = $session->customer;

                        // Retrieve the user by Stripe customer ID
                        $user = $this->get_user_by_stripe_customer_id($customer_id);
                        if ($user) {
                            
                            update_user_meta($user->ID, 'license_status', "active");
                            update_user_meta($user->ID, 'stripe_trail', "true");
                            update_user_meta($user->ID, 'subscription_id', $subscription_id);
                            $this->update_group_licence($user->ID,1);

                            
                        }
                    }
                    break;

                case 'customer.subscription.created':

                    $session = $event->data->object; 

                    $myfile = fopen(ABSPATH."/customer_subscription_created.txt", "w");

                    $text2 = json_encode($session);

                    fwrite($myfile, $text2);

                    fclose($myfile);


                    if (isset($session->customer)) {
                        $customer_id = $session->customer;

                        $user = $this->get_user_by_stripe_customer_id($customer_id);
                        if ($user) {

                            $subscriptions = $this->stripe->subscriptions->all(['customer' => $customer_id]);
                            if (count($subscriptions->data) > 0) {

                                    $subscription = $subscriptions->data[0]; 
                                    update_user_meta($user->ID, 'license_status', "active");
                                    update_user_meta($user->ID, 'stripe_trail', "true");
                                    update_user_meta($user->ID, 'subscription_id', $subscription->id);
                                    $this->update_group_licence($user->ID,1);

                                
                            }else{
                                update_user_meta($user->ID, 'license_status', "inactive");
                                update_user_meta($user->ID, 'subscription_id', "");
                                $this->update_group_licence($user->ID,0);
                            }
                        }
                        
                    }
                    break;
                case 'customer.subscription.updated':
                        $session = $event->data->object; 

                        $myfile = fopen(ABSPATH."/customer_subscription_updated.txt", "w");

                        $text2 = json_encode($session);

                        fwrite($myfile, $text2);

                        fclose($myfile);
    
    
                        if (isset($session->customer)) {
                            $customer_id = $session->customer;
    
                            $user = $this->get_user_by_stripe_customer_id($customer_id);
                            if ($user) {
    
                                $subscriptions = $this->stripe->subscriptions->all(['customer' => $customer_id]);
                                if (count($subscriptions->data) > 0) {
    
                                        $subscription = $subscriptions->data[0]; 
                                        update_user_meta($user->ID, 'license_status', "active");
                                        update_user_meta($user->ID, 'stripe_trail', "true");
                                        update_user_meta($user->ID, 'subscription_id', $subscription->id);
                                        $this->update_group_licence($user->ID,1);
    
                                    
                                }else{
                                    update_user_meta($user->ID, 'license_status', "inactive");
                                    update_user_meta($user->ID, 'subscription_id', "");
                                    $this->update_group_licence($user->ID,0);
                                }
                            }
                            
                        }
                        break;    

                case 'customer.subscription.deleted':
                    $session = $event->data->object; // Contains the session details

                    $myfile = fopen(ABSPATH."/customer_subscription_deleted.txt", "w");

                    $text2 = json_encode($session);

                    fwrite($myfile, $text2);

                    fclose($myfile);
                    


                    if (isset($session->customer)) {
                        $customer_id = $session->customer;

                        $user = $this->get_user_by_stripe_customer_id($customer_id);
                        if ($user) {

                            $subscriptions = $this->stripe->subscriptions->all(['customer' => $customer_id]);
                            if (count($subscriptions->data) > 0) {

                                    $subscription = $subscriptions->data[0]; 
                                    update_user_meta($user->ID, 'license_status', "active");
                                    update_user_meta($user->ID, 'stripe_trail', "true");
                                    update_user_meta($user->ID, 'subscription_id', $subscription->id);
                                    $this->update_group_licence($user->ID,1);

                                
                            }else{
                                update_user_meta($user->ID, 'license_status', "inactive");
                                update_user_meta($user->ID, 'subscription_id', "");
                                $this->update_group_licence($user->ID,0);
                            }
                        }
                        
                    }
                    break;

                // Add more event types as needed
            }
        } catch (Exception $e) {
            error_log('Webhook error: ' . $e->getMessage());
            return new WP_Error('invalid_webhook', 'Invalid webhook signature', ['status' => 400]);
        }

        return new WP_REST_Response(['status' => 'success'], 200);
    }

    // Helper function to get user by Stripe customer ID
    private function get_user_by_stripe_customer_id($stripe_customer_id) {
        $args = [
            'meta_key' => 'stripe_customer_id',
            'meta_value' => $stripe_customer_id,
            'number' => 1,
        ];
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        
        return !empty($users) ? $users[0] : null;
    }

    // Handle successful SMS payment
    private function handle_sms_payment_success($session) {
        global $wpdb;
        
        if (isset($session->metadata->group_admin) && isset($session->metadata->sms_count)) {
            $group_admin = $session->metadata->group_admin;
            $sms_count = $session->metadata->sms_count;

            $access_ids = $this->get_sms_count($group_admin, "get_access_ids");
           
            
            if (!empty($access_ids)) {
                $placeholders = implode(',', array_fill(0, count($access_ids), '%d'));
                $query = $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}access_management 
                     SET sms_payment_status = '1' 
                     WHERE id IN ($placeholders) 
                     AND sms_payment_status != '1' 
                     AND sms_status LIKE %s",
                    array_merge($access_ids, array('%accepted_at%'))
                );
                
                $wpdb->query($query);
                // Log the successful payment
                error_log("SMS payment successful for user $user_id: $sms_count SMS messages paid");
            }
        }
    }
    public function handle_wallet_payment_success($session) {
        global $wpdb;
        
        if (isset($session->metadata->user_id) && isset($session->metadata->amount)) {
            $user_id = $session->metadata->user_id;
            $amount = $session->metadata->amount;
            $record_transaction_id = $session->metadata->record_transaction_id;
            
            // echo "<pre>";
            // print_r($session);
            // echo "</pre>";
            // die;

            $gibbs_wallet_database_path = ABSPATH . 'wp-content/plugins/gibbs-react-booking/server/wallet/WalletDatabase.php';
            $gibbs_wallet_response_path = ABSPATH . 'wp-content/plugins/gibbs-react-booking/server/Response.php';
            $gibbs_wallet_api_path = ABSPATH . 'wp-content/plugins/gibbs-react-booking/server/wallet/WalletApi.php';
            if ( file_exists( $gibbs_wallet_database_path ) ) {
                require_once( $gibbs_wallet_database_path );
                require_once( $gibbs_wallet_response_path );
                require_once( $gibbs_wallet_api_path );
            } else {
                error_log("wallet-endpoint.php class not found");
            }

            $payment_intent = $session->payment_intent;

            $wallet_payment = new WalletApi();
            $wallet_payment->save_wallet_payment($user_id, $amount,  $record_transaction_id, $payment_intent);

            return true;
        }
    }



    public function cancel_subscription() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_id = $this->get_super_admin();
            $data = json_decode(file_get_contents('php://input'), true);
            $subscription_id = $data['subscription_id'];

            try {
                // Retrieve the subscription and cancel it
                $subscription = $this->stripe->subscriptions->retrieve($subscription_id);
                $subscription->cancel();
                update_user_meta($user_id, 'subscription_id', "");
                update_user_meta($user_id, 'license_status', "inactive");

                echo json_encode(['status' => 'success', 'message' => 'Subscription canceled']);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            wp_die();
        }
    }
    public function cancel_subscription_direct($subscription_id,$user_id) {
        

            try {
                // Retrieve the subscription and cancel it
                $subscription = $this->stripe->subscriptions->retrieve($subscription_id);
                $subscription->cancel();
                update_user_meta($user_id, 'subscription_id', "");
                update_user_meta($user_id, 'license_status', "inactive");

                return true;
            } catch (Exception $e) {
                return false;
            }
            return false;
    }

    public function render_subscription_management() {
        ob_start();
        require GIBBS_STRIPE_PATH . 'active-packages.php'; 
        return ob_get_clean();
        exit;
    }
}

<?php
defined('ABSPATH') || exit;

class WC_Gateway_Dintero extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'dintero';
        $this->method_title = 'Dintero Payment Gateway';
        $this->method_description = 'Pay with Dintero';
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', 'Dintero');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'label' => 'Enable Dintero Gateway',
                'type' => 'checkbox',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'Dintero Split Gateway'
            ],
            'environment' => [
                'title' => 'Environment',
                'type' => 'select',
                'description' => 'Choose between Test and Live mode.',
                'default' => 'test',
                'options' => [
                    'test' => 'Test',
                    'live' => 'Live'
                ]
            ],
            'test_client_id' => [
                'title' => 'Test Client ID',
                'type' => 'text'
            ],
            'test_client_secret' => [
                'title' => 'Test Client Secret',
                'type' => 'password'
            ],
            'test_account_id' => [
                'title' => 'Test Account ID',
                'type' => 'text'
            ],
            'test_profile_id' => [
                'title' => 'Test Profile ID',
                'type' => 'text'
            ],
            'test_payout_id' => [
                'title' => 'Test Payout ID',
                'type' => 'text'
            ],
            'live_client_id' => [
                'title' => 'Live Client ID',
                'type' => 'text'
            ],
            'live_client_secret' => [
                'title' => 'Live Client Secret',
                'type' => 'password'
            ],
            'live_account_id' => [
                'title' => 'Live Account ID',
                'type' => 'text'
            ],
            'live_profile_id' => [
                'title' => 'Live Profile ID',
                'type' => 'text'
            ],
            'live_payout_id' => [
                'title' => 'Live Payout ID',
                'type' => 'text'
            ]
        ];
    }

    public function process_payment($order_id, $return_data_only = false) {
        global $wpdb;

        $order = wc_get_order($order_id);
        $booking_table = $wpdb->prefix . "bookings_calendar";


        $booking = $wpdb->get_row("SELECT * FROM ".$booking_table." WHERE order_id = '".$order->get_id()."'", ARRAY_A);

        $seller_id = "";

        if(isset($booking["id"])){

            $postData = get_post($booking["listing_id"]);

            //$user_payout_destination_id = get_user_meta($postData->post_author,"user_payout_destination_id", true);
            $user_payout_destination_id = "seller_2353_1750763778";
            //$dintero_payment = get_user_meta($postData->post_author,"dintero_payment", true);
            $dintero_payment = "on";

            if($dintero_payment != "on"){

                wc_add_notice(__('Dintero payment method not enabled.'), 'error');
                return ['result' => 'failure', 'message' => 'Dintero payment method not enabled.'];

            }

            if($user_payout_destination_id != ""){

                $seller_id = $user_payout_destination_id;

            }


        }else{

            wc_add_notice(__('Booking not found.'), 'error');
            return ['result' => 'failure', 'message' => 'Booking not found.'];

        }

        if($seller_id == ""){

            wc_add_notice(__('Seller not found.'), 'error');
            return ['result' => 'failure', 'message' => 'Seller not found.'];

        }

        $settings = get_option('woocommerce_dintero_settings');
        $this->environment = isset($settings['environment']) ? $settings['environment'] : 'test';
        if ($this->environment === 'live') {
            $admin_payout_id = isset($settings['live_payout_id']) ? $settings['live_payout_id'] : '';
            $profile_id = isset($settings['live_profile_id']) ? $settings['live_profile_id'] : '';
        } else {
            $admin_payout_id = isset($settings['test_payout_id']) ? $settings['test_payout_id'] : '';
            $profile_id = isset($settings['test_profile_id']) ? $settings['test_profile_id'] : '';
        }

        if($admin_payout_id == ""){
            wc_add_notice(__('Admin payout id not defined.'), 'error');
            return ['result' => 'failure', 'message' => 'Admin payout id not defined.'];
        }
        if($profile_id == ""){
            wc_add_notice(__('Profile id not defined.'), 'error');
            return ['result' => 'failure', 'message' => 'Profile id not defined.'];
        }

        //$admin_payout_id = "dflksjdlkjsldksd";


        // Gather order items and build splits by seller
        $splits = [];
        $seller_amounts = [];
        $items = [];
        $line_ids = [];
        $product_ids = [];
        $item_names = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_ids[] = (string)$item->get_product_id();
            $line_ids[] = (string)$item_id;
            $item_names[] = $item->get_name();
            
        }

        $order_amount = $order->get_total(); // e.g., 99.99

        $second_split_amount = $order_amount * 0.20;
        $first_split_amount = $order_amount - $second_split_amount;

        // Convert to integer minor units (e.g., cents)
        $first_split_amount_int  = intval(round($first_split_amount * 100));
        $second_split_amount_int = intval(round($second_split_amount * 100));
        $order_amount_int        = intval(round($order_amount * 100));

        // echo "<pre>"; print_r($split_amount); 
        // echo "<pre>"; print_r($first_split_amount); 
        // echo "<pre>"; print_r($second_split_amount); die;

        // Create the splits array
        $splits = [
            [
                'payout_destination_id' => $user_payout_destination_id,
                'amount' => $first_split_amount_int
            ],
            [
                'payout_destination_id' => $admin_payout_id,
                'amount' => $second_split_amount_int
            ]
        ];
        $items[] = [
            'amount' => $order_amount_int,
            'quantity' => 1,
            'line_id' => implode(",",$line_ids),
            'description' => implode(",",$item_names),
           // 'vat' => 0,
            'id' => implode(",",$product_ids),
            'splits' => $splits
        ];
        // foreach ($seller_amounts as $payout_destination_id => $amount) {
        //     $splits[] = [
        //         'payout_destination_id' => "seller_test_001",
        //         'amount' => intval($amount)
        //     ];
        // }
        

        // Build session_data for Dintero
        $session_data = [
            'profile_id' => $profile_id,
            'order' => [
                'amount' => $order_amount_int,
                'currency' => $order->get_currency(),
                'items' => $items,
                //'splits' => $splits,
                'merchant_reference' => (string)$order->get_id(),
            ],
            'url' => [
                'return_url' => $this->get_return_url($order),
                'callback_url' => Dintero_Webhook_Handler::get_webhook_url(),
            ],
            'customer' => [
                'email' => $order->get_billing_email(),
                'phone_number' => $order->get_billing_phone(),
                'customer_id' => (string) $order->get_customer_id(),
            ],
            // Add more fields as required by Dintero API
        ];
        $dintero_api = new Dintero_API();

        if($return_data_only){
            return [
                'result' => 'success',
                'session' => $session_data,
                'access_token' => $dintero_api->getAccessToken(),
            ];
        }

        //echo "<pre>"; print_r($session_data); die;

        
        try {
            $session = $dintero_api->create_checkout_session($session_data);

            if (!empty($session['checkout_url']) && !empty($session['id'])) {
                return [
                    'result' => 'success',
                    'redirect' => $session['checkout_url'],
                    'id' => $session['id']
                ];
            } else {
                wc_add_notice(__('Could not initiate Dintero payment. Please try again.'), 'error');
                return ['result' => 'failure', 'message' => 'Could not initiate Dintero payment. Please try again.'];
            }
        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'woocommerce') . $e->getMessage(), 'error');
            return ['result' => 'failure', 'message' => $e->getMessage()];
        }
    }

    public function process_direct_checkout_payment(){

        $postData = get_post(37888);
        $user_payout_destination_id = "seller_2353_1750763778";

       


        $settings = get_option('woocommerce_dintero_settings');
        $this->environment = isset($settings['environment']) ? $settings['environment'] : 'test';
        if ($this->environment === 'live') {
            $admin_payout_id = isset($settings['live_payout_id']) ? $settings['live_payout_id'] : '';
            $profile_id = isset($settings['live_profile_id']) ? $settings['live_profile_id'] : '';
        } else {
            $admin_payout_id = isset($settings['test_payout_id']) ? $settings['test_payout_id'] : '';
            $profile_id = isset($settings['test_profile_id']) ? $settings['test_profile_id'] : '';
        }

        if($admin_payout_id == ""){
            wc_add_notice(__('Admin payout id not defined.'), 'error');
            return ['result' => 'failure', 'message' => 'Admin payout id not defined.'];
        }
        if($profile_id == ""){
            wc_add_notice(__('Profile id not defined.'), 'error');
            return ['result' => 'failure', 'message' => 'Profile id not defined.'];
        }

        //$admin_payout_id = "dflksjdlkjsldksd";


       

        $order_amount = 200; // e.g., 99.99

        $second_split_amount = $order_amount * 0.20;
        $first_split_amount = $order_amount - $second_split_amount;

        // Convert to integer minor units (e.g., cents)
        $first_split_amount_int  = intval(round($first_split_amount * 100));
        $second_split_amount_int = intval(round($second_split_amount * 100));
        $order_amount_int        = intval(round($order_amount * 100));

        // echo "<pre>"; print_r($split_amount); 
        // echo "<pre>"; print_r($first_split_amount); 
        // echo "<pre>"; print_r($second_split_amount); die;

        // Create the splits array
        $splits = [
            [
                'payout_destination_id' => $user_payout_destination_id,
                'amount' => $first_split_amount_int
            ],
            [
                'payout_destination_id' => $admin_payout_id,
                'amount' => $second_split_amount_int
            ]
        ];
        $items[] = [
            'amount' => $order_amount_int,
            'quantity' => 1,
            'line_id' => "1",
            'description' => "Test",
           // 'vat' => 0,
            'id' => "1",
            'splits' => $splits
        ];
        // foreach ($seller_amounts as $payout_destination_id => $amount) {
        //     $splits[] = [
        //         'payout_destination_id' => "seller_test_001",
        //         'amount' => intval($amount)
        //     ];
        // }
        

        // Build session_data for Dintero
        $session_data = [
            'profile_id' => $profile_id,
            'order' => [
                'amount' => $order_amount_int,
                'currency' => "NOK",
                'items' => $items,
                //'splits' => $splits,
                'merchant_reference' => (string)37888,
            ],
            'url' => [
                'return_url' => Dintero_Webhook_Handler::get_webhook_url(),
                'callback_url' => Dintero_Webhook_Handler::get_webhook_url(),
            ]
            // Add more fields as required by Dintero API
        ];

        //echo "<pre>"; print_r($session_data); die;

        $dintero_api = new Dintero_API();
        try {
            $session = $dintero_api->create_checkout_session($session_data);

            if (!empty($session['checkout_url']) && !empty($session['id'])) {
                return [
                    'result' => 'success',
                    'redirect' => $session['checkout_url'],
                    'id' => $session['id']
                ];
            } else {
                wc_add_notice(__('Could not initiate Dintero payment. Please try again.'), 'error');
                return ['result' => 'failure', 'message' => 'Could not initiate Dintero payment. Please try again.'];
            }
        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'woocommerce') . $e->getMessage(), 'error');
            return ['result' => 'failure', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get order data from Dintero transaction
     */
    public function get_order_data_from_dintero($transaction_id) {
        try {
            $dintero_api = new Dintero_API();
            $transaction_data = $dintero_api->get_transaction($transaction_id);
            
            if ($transaction_data && isset($transaction_data['order'])) {
                return $transaction_data['order'];
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Error getting order data from Dintero: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get payment status from Dintero
     */
    public function get_payment_status_from_dintero($transaction_id) {
        try {
            $dintero_api = new Dintero_API();
            $transaction_data = $dintero_api->get_transaction($transaction_id);
            
            if ($transaction_data && isset($transaction_data['status'])) {
                return $transaction_data['status'];
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Error getting payment status from Dintero: ' . $e->getMessage());
            return null;
        }
    }
}

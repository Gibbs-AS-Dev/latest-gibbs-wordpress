<?php
defined('ABSPATH') || exit;

class Dintero_Webhook_Handler {

    public function __construct() {
        // Register webhook endpoint
        add_action('init', array($this, 'register_webhook_endpoint'));
        add_action('parse_request', array($this, 'handle_webhook_request'));
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule(
            '^dintero-webhook/?$',
            'index.php?dintero_webhook=1',
            'top'
        );
        
        add_rewrite_tag('%dintero_webhook%', '([^&]+)');
    }

    /**
     * Handle webhook request
     */
    public function handle_webhook_request($wp) {
        if (isset($wp->query_vars['dintero_webhook'])) {
            $this->process_webhook();
            exit;
        }
    }

    /**
     * Process webhook data from Dintero
     */
    public function process_webhook() {
       
        $data = $_GET;

        if(isset($data['transaction_id']) && isset($data['session_id'])){

            global $wpdb;
            $session_id = $data['session_id'];
            $order_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                    'dintero_payment_id',
                    $session_id
                )
            );

            if($order_id){
                $order = wc_get_order($order_id);
                if($order){
                    
                    $order->update_status('completed', 'Payment captured by Dintero');
                    $order->update_meta_data('transaction_id', $data['transaction_id']);
                    $order->update_meta_data('session_id', $data['session_id']);
                    $order->save_meta_data();

                    $sql = "UPDATE {$wpdb->prefix}bookings_calendar SET status = 'paid' WHERE order_id = $order_id";
                    $wpdb->query($sql);

                    $order_received_url =  $order->get_checkout_order_received_url();
                    $this->return_success_response('Payment captured successfully', array('redirect_url' => $order_received_url));
                }else{
                    error_log('Dintero : Order not found: ' . $order_id);
                    $this->return_error_response(400, 'Order not found');
                }
            }else{
                error_log('Dintero : Order id  not found: ' . $order_id);
                $this->return_error_response(400, 'Order id  not found');
            }
        }else{
            error_log('Invalid request from dintero');
            $this->return_error_response(400, 'Invalid request');
        }
    }

    /**
     * Handle payment reserved event
     */
    private function handle_payment_reserved($data) {
        $order_id = $this->get_order_id_from_data($data);
        if (!$order_id) {
            error_log('No order ID found in webhook data');
            return array(
                'success' => false,
                'code' => 400,
                'message' => 'No order ID found in webhook data'
            );
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return array(
                'success' => false,
                'code' => 404,
                'message' => 'Order not found: ' . $order_id
            );
        }

        // Store webhook data
        $this->store_webhook_data($order, $data);
        
        // Update order status to on-hold
        $order->update_status('on-hold', 'Payment reserved by Dintero');

        error_log('Order ' . $order_id . ' status updated to on-hold');
        
        return array(
            'success' => true,
            'message' => 'Payment reserved successfully',
            'data' => array(
                'order_id' => $order_id,
                'status' => 'on-hold'
            )
        );
    }

    /**
     * Handle payment captured event
     */
    private function handle_payment_captured($data) {
        $order_id = $this->get_order_id_from_data($data);
        if (!$order_id) {
            error_log('No order ID found in webhook data');
            return array(
                'success' => false,
                'code' => 400,
                'message' => 'No order ID found in webhook data'
            );
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return array(
                'success' => false,
                'code' => 404,
                'message' => 'Order not found: ' . $order_id
            );
        }

        // Store webhook data
        $this->store_webhook_data($order, $data);
        
        // Update order status to processing
        $order->payment_complete();
        $order->update_status('processing', 'Payment captured by Dintero');

        error_log('Order ' . $order_id . ' payment completed');
        
        return array(
            'success' => true,
            'message' => 'Payment captured successfully',
            'data' => array(
                'order_id' => $order_id,
                'status' => 'processing'
            )
        );
    }

    /**
     * Handle payment failed event
     */
    private function handle_payment_failed($data) {
        $order_id = $this->get_order_id_from_data($data);
        if (!$order_id) {
            error_log('No order ID found in webhook data');
            return array(
                'success' => false,
                'code' => 400,
                'message' => 'No order ID found in webhook data'
            );
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return array(
                'success' => false,
                'code' => 404,
                'message' => 'Order not found: ' . $order_id
            );
        }

        // Store webhook data
        $this->store_webhook_data($order, $data);
        
        // Update order status to failed
        $order->update_status('failed', 'Payment failed in Dintero');
        
        error_log('Order ' . $order_id . ' payment failed');
        
        return array(
            'success' => true,
            'message' => 'Payment failed status updated',
            'data' => array(
                'order_id' => $order_id,
                'status' => 'failed'
            )
        );
    }

    /**
     * Handle payment cancelled event
     */
    private function handle_payment_cancelled($data) {
        $order_id = $this->get_order_id_from_data($data);
        if (!$order_id) {
            error_log('No order ID found in webhook data');
            return array(
                'success' => false,
                'code' => 400,
                'message' => 'No order ID found in webhook data'
            );
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return array(
                'success' => false,
                'code' => 404,
                'message' => 'Order not found: ' . $order_id
                );
        }

        // Store webhook data
        $this->store_webhook_data($order, $data);
        
        // Update order status to cancelled
        $order->update_status('cancelled', 'Payment cancelled in Dintero');
        
        error_log('Order ' . $order_id . ' payment cancelled');
        
        return array(
            'success' => true,
            'message' => 'Payment cancelled status updated',
            'data' => array(
                'order_id' => $order_id,
                'status' => 'cancelled'
            )
        );
    }

    /**
     * Handle general payment update (when event type is not recognized)
     */
    private function handle_general_payment_update($data) {
        $order_id = $this->get_order_id_from_data($data);
        if (!$order_id) {
            error_log('No order ID found in general payment update');
            return array(
                'success' => false,
                'code' => 400,
                'message' => 'No order ID found in webhook data'
            );
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found in general payment update: ' . $order_id);
            return array(
                'success' => false,
                'code' => 404,
                'message' => 'Order not found: ' . $order_id
            );
        }

        // Store all relevant data from the webhook
        $this->store_webhook_data($order, $data);
        
        // Try to determine status from data
        $status = $this->determine_payment_status($data);
        if ($status) {
            $order->update_status($status, 'Payment status updated via Dintero webhook');
            error_log('Order ' . $order_id . ' status updated to ' . $status . ' via general update');
            
            return array(
                'success' => true,
                'message' => 'Payment status updated successfully',
                'data' => array(
                    'order_id' => $order_id,
                    'status' => $status
                )
            );
        } else {
            error_log('Order ' . $order_id . ' received webhook data but status could not be determined');
            
            return array(
                'success' => true,
                'message' => 'Webhook data received but status could not be determined',
                'data' => array(
                    'order_id' => $order_id,
                    'received_data' => $data
                )
            );
        }
    }

    /**
     * Store webhook data in order meta
     */
    private function store_webhook_data($order, $data) {
        // Store transaction ID
        if (isset($data['id'])) {
            $order->update_meta_data('_dintero_transaction_id', $data['id']);
        }
        
        // Store amount
        if (isset($data['amount'])) {
            $order->update_meta_data('_dintero_amount', $data['amount']);
        }
        
        // Store currency
        if (isset($data['currency'])) {
            $order->update_meta_data('_dintero_currency', $data['currency']);
        }
        
        // Store status
        if (isset($data['status'])) {
            $order->update_meta_data('_dintero_status', $data['status']);
        }
        
        // Store the complete webhook data
        $order->update_meta_data('_dintero_webhook_data', $data);
        
        $order->save();
    }

    /**
     * Determine payment status from webhook data
     */
    private function determine_payment_status($data) {
        $status = $data['status'] ?? $data['state'] ?? $data['result'] ?? null;
        
        if (!$status) {
            return null;
        }
        
        switch (strtolower($status)) {
            case 'captured':
            case 'completed':
            case 'success':
            case 'approved':
            case 'ok':
                return 'processing';
            case 'reserved':
            case 'pending':
            case 'authorized':
                return 'on-hold';
            case 'failed':
            case 'error':
            case 'declined':
            case 'rejected':
                return 'failed';
            case 'cancelled':
            case 'canceled':
            case 'aborted':
                return 'cancelled';
            default:
                return null;
        }
    }

    /**
     * Extract order ID from webhook data
     */
    private function get_order_id_from_data($data) {
        error_log('Dintero Webhook - Extracting order ID from data: ' . print_r($data, true));
        
        // Try different possible locations for order ID
        $possible_locations = [
            'order.merchant_reference',
            'merchant_reference',
            'order_id',
            'order.id',
            'reference',
            'merchant_reference_id',
            // Common GET parameter names
            'orderid',
            'order-id',
            'order_number',
            'order_number',
            'ref',
            'reference_id',
            'transaction_id',
            'txn_id',
            'payment_id',
            'session_id'
        ];
        
        foreach ($possible_locations as $location) {
            $value = $this->get_nested_value($data, $location);
            if ($value) {
                error_log('Dintero Webhook - Found order ID: ' . $value . ' in location: ' . $location);
                return $value;
            }
        }
        
        error_log('Dintero Webhook - No order ID found in data');
        return null;
    }
    
    /**
     * Get nested value from array using dot notation
     */
    private function get_nested_value($array, $key) {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    /**
     * Return success JSON response
     */
    private function return_success_response($message = 'Success', $data = null) {
        http_response_code(200);
        header('Content-Type: application/json');
        
        $response = array(
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        );
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }

    /**
     * Return error JSON response
     */
    private function return_error_response($code = 400, $message = 'Error') {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = array(
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        );
        
        echo json_encode($response);
        exit;
    }

    /**
     * Verify webhook signature (implement based on Dintero's documentation)
     */
    private function verify_webhook_signature($raw_data) {
        // Get the signature from headers
        $signature = $_SERVER['HTTP_X_DINTERO_SIGNATURE'] ?? '';
        
        if (empty($signature)) {
            error_log('No Dintero signature found in webhook');
            return false;
        }

        // Get your webhook secret from settings
        $settings = get_option('woocommerce_dintero_settings');
        $webhook_secret = $settings['webhook_secret'] ?? '';
        
        if (empty($webhook_secret)) {
            error_log('No webhook secret configured');
            return false;
        }

        // Verify signature (implement according to Dintero's documentation)
        $expected_signature = hash_hmac('sha256', $raw_data, $webhook_secret);
        
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Get webhook URL
     */
    public static function get_webhook_url() {
        return home_url('/dintero-webhook/');
    }
} 
<?php
defined('ABSPATH') || exit;

class Gibbs_DIBS_Payment_Webhook_Handler {

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
            '^dibs-payment-webhook/?$',
            'index.php?dibs_payment_webhook=1',
            'top'
        );
        
        add_rewrite_tag('%dibs_payment_webhook%', '([^&]+)');
    }

    /**
     * Handle webhook request
     */
    public function handle_webhook_request($wp) {
        if (isset($wp->query_vars['dibs_payment_webhook'])) {
            $this->process_webhook();
            exit;
        }
    }

    /**
     * Process webhook data from DIBS Payment
     */
    public function process_webhook() {
        // Get raw input
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true);

        // Log webhook data to file for debugging
        $log_data = array(
            'timestamp' => date('Y-m-d H:i:s')
        );
        
        $log_file = WP_CONTENT_DIR . '/dibs-webhook-logs.txt';
        $log_entry = "[" . $log_data['timestamp'] . "] DIBS Webhook Data:\n";
        $log_entry .= "Raw Data: " . $raw_data . "\n";
        $log_entry .= str_repeat('-', 80) . "\n\n";
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // $raw_data = '{"id":"093e81d78ac7416487ca215562d9f9c1","merchantId":100021639,"timestamp":"2025-09-30T10:35:36.2237+00:00","event":"payment.charge.created","data":{"chargeId":"093e81d78ac7416487ca215562d9f9c1","orderItems":[{"grossTotalAmount":48800,"name":"Dropin - Sarpsborg badstua","netTotalAmount":48800,"quantity":1.0,"reference":"37887","taxRate":0,"taxAmount":0,"unit":"pcs","unitPrice":48800}],"reservationId":"5e4fd44301de409897a40e765f4a2262","reconciliationReference":"tbemdp3LeeS9kyEFnL7pY9n4U","amount":{"amount":48800,"currency":"NOK"},"surchargeAmount":0,"paymentId":"aa377dceef7f4f6ca085c08e7fba090b"}}';
        

        if(isset($data['event']) && $data['event'] == 'payment.charge.created'){

            if(isset($data['data']['chargeId']) && isset($data['data']['paymentId'])){
                $this->handle_payment_completed($data['data']['paymentId'],$data['data']['chargeId']);
            }
        }

        //echo "<pre>"; print_r($data); die("kkk");

        // die;

        // // Verify webhook signature if configured
        // if (!$this->verify_webhook_signature($raw_data)) {
        //     error_log('DIBS Payment: Invalid webhook signature');
        //     $this->return_error_response(401, 'Invalid signature');
        //     return;
        // }

        // if (!$data) {
        //     error_log('DIBS Payment: Invalid JSON data received');
        //     $this->return_error_response(400, 'Invalid JSON data');
        //     return;
        // }

        // // Log webhook data for debugging
        // error_log('DIBS Payment Webhook received: ' . print_r($data, true));

        // // Handle different webhook events
        // $event_type = $data['eventType'] ?? $data['event'] ?? 'unknown';
        
        // switch ($event_type) {
        //     case 'payment.checkout.completed':
        //     case 'payment.charge.created':
        //         $this->handle_payment_completed($data);
        //         break;
        //     case 'payment.charge.failed':
        //         $this->handle_payment_failed($data);
        //         break;
        //     case 'payment.refund.created':
        //         $this->handle_payment_refunded($data);
        //         break;
        //     default:
        //         $this->handle_general_payment_update($data);
        //         break;
        // }
    }

    /**
     * Handle payment completed event
     */
    private function handle_payment_completed($payment_id,$charge_id) {
        

        global $wpdb;
        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'nets_easy_payment_id',
                $payment_id
            )
        );

        

        if (!$order_id) {
            error_log('DIBS Payment: Order not found for payment ID: ' . $payment_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('DIBS Payment: Order not found: ' . $order_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        $order->update_meta_data('_dibs_charge_id', $charge_id);
        
        $order->save();
        
        // Update order status
        $order->payment_complete();
        $order->update_status('completed', 'Payment completed via DIBS Nets Easy Payment');
        
        // Update booking status if exists
        $booking_table = $wpdb->prefix . "bookings_calendar";
        $sql = "UPDATE {$booking_table} SET status = 'paid' WHERE order_id = $order_id";
        $wpdb->query($sql);

        error_log('DIBS Payment: Order ' . $order_id . ' payment completed');
        
        $this->return_success_response('Payment completed successfully', [
            'order_id' => $order_id,
            'status' => 'processing'
        ]);
    }

    /**
     * Handle payment failed event
     */
    private function handle_payment_failed($data) {
        $payment_id = $this->get_payment_id_from_data($data);
        if (!$payment_id) {
            error_log('DIBS Payment: No payment ID found in webhook data');
            $this->return_error_response(400, 'No payment ID found');
            return;
        }

        global $wpdb;
        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'nets_easy_payment_id',
                $payment_id
            )
        );

        if (!$order_id) {
            error_log('DIBS Payment: Order not found for payment ID: ' . $payment_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('DIBS Payment: Order not found: ' . $order_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        // Store webhook data
        $this->store_webhook_data($order, $data);
        
        // Update order status
        $order->update_status('failed', 'Payment failed via DIBS Payment');
        
        error_log('DIBS Payment: Order ' . $order_id . ' payment failed');
        
        $this->return_success_response('Payment failed status updated', [
            'order_id' => $order_id,
            'status' => 'failed'
        ]);
    }

    /**
     * Handle payment refunded event
     */
    private function handle_payment_refunded($data) {
        $payment_id = $this->get_payment_id_from_data($data);
        if (!$payment_id) {
            error_log('DIBS Payment: No payment ID found in webhook data');
            $this->return_error_response(400, 'No payment ID found');
            return;
        }

        global $wpdb;
        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'nets_easy_payment_id',
                $payment_id
            )
        );

        if (!$order_id) {
            error_log('DIBS Payment: Order not found for payment ID: ' . $payment_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('DIBS Payment: Order not found: ' . $order_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        // Store webhook data
        $this->store_webhook_data($order, $data);
        
        // Update order status
        $order->update_status('refunded', 'Payment refunded via DIBS Payment');
        
        error_log('DIBS Payment: Order ' . $order_id . ' payment refunded');
        
        $this->return_success_response('Payment refunded successfully', [
            'order_id' => $order_id,
            'status' => 'refunded'
        ]);
    }

    /**
     * Handle general payment update (when event type is not recognized)
     */
    // private function handle_general_payment_update($data) {
    //     $payment_id = $this->get_payment_id_from_data($data);
    //     if (!$payment_id) {
    //         error_log('DIBS Payment: No payment ID found in general payment update');
    //         $this->return_error_response(400, 'No payment ID found');
    //         return;
    //     }

    //     global $wpdb;
    //     $order_id = $wpdb->get_var(
    //         $wpdb->prepare(
    //             "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
    //             'nets_easy_payment_id',
    //             $payment_id
    //         )
    //     );

    //     if (!$order_id) {
    //         error_log('DIBS Payment: Order not found for payment ID: ' . $payment_id);
    //         $this->return_error_response(404, 'Order not found');
    //         return;
    //     }

    //     $order = wc_get_order($order_id);
    //     if (!$order) {
    //         error_log('DIBS Payment: Order not found: ' . $order_id);
    //         $this->return_error_response(404, 'Order not found');
    //         return;
    //     }

    //     // Store webhook data
    //     $this->store_webhook_data($order, $data);
        
    //     // Try to determine status from data
    //     $status = $this->determine_payment_status($data);
    //     if ($status) {
    //         $order->update_status($status, 'Payment status updated via DIBS Payment webhook');
    //         error_log('DIBS Payment: Order ' . $order_id . ' status updated to ' . $status . ' via general update');
    //     } else {
    //         error_log('DIBS Payment: Order ' . $order_id . ' received webhook data but status could not be determined');
    //     }
        
    //     $this->return_success_response('Webhook processed successfully', [
    //         'order_id' => $order_id,
    //         'status' => $status ?: 'unknown'
    //     ]);
    // }

    /**
     * Store webhook data in order meta
     */
    private function store_webhook_data($order, $data) {
        // Store payment ID
        // if (isset($data['paymentId'])) {
        //     $order->update_meta_data('_dibs_payment_id', $data['paymentId']);
        // }
        
        // // Store amount
        // if (isset($data['order']['amount'])) {
        //     $order->update_meta_data('_dibs_payment_amount', $data['order']['amount']);
        // }
        
        // // Store currency
        // if (isset($data['order']['currency'])) {
        //     $order->update_meta_data('_dibs_payment_currency', $data['order']['currency']);
        // }
        
        // // Store status
        // if (isset($data['summary']['state'])) {
        //     $order->update_meta_data('_dibs_payment_status', $data['summary']['state']);
        // }
        
        // // Store the complete webhook data
        // $order->update_meta_data('_dibs_payment_webhook_data', $data);
        
        // $order->save();
    }

    /**
     * Determine payment status from webhook data
     */
    private function determine_payment_status($data) {
        $status = $data['summary']['state'] ?? $data['state'] ?? $data['status'] ?? null;
        
        if (!$status) {
            return null;
        }
        
        switch (strtolower($status)) {
            case 'charged':
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
            case 'refunded':
                return 'refunded';
            default:
                return null;
        }
    }

    /**
     * Extract payment ID from webhook data
     */
    private function get_payment_id_from_data($data) {
        // Try different possible locations for payment ID
        $possible_locations = [
            'paymentId',
            'payment.id',
            'id',
            'payment_id',
            'transaction_id',
            'txn_id'
        ];
        
        foreach ($possible_locations as $location) {
            $value = $this->get_nested_value($data, $location);
            if ($value) {
                return $value;
            }
        }
        
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
     * Verify webhook signature (implement based on DIBS Payment's documentation)
     */
    private function verify_webhook_signature($raw_data) {
        // Get the signature from headers
        $signature = $_SERVER['HTTP_X_DIBS_SIGNATURE'] ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        
        if (empty($signature)) {
            // If no signature is provided, we'll still process the webhook
            // but log a warning
            error_log('DIBS Payment: No webhook signature found');
            return true; // Allow processing for now
        }

        // Get your webhook secret from settings
        $settings = get_option('woocommerce_nets_easy_settings');
        $webhook_secret = $settings['webhook_secret'] ?? '';
        
        if (empty($webhook_secret)) {
            error_log('DIBS Payment: No webhook secret configured');
            return true; // Allow processing for now
        }

        // Verify signature (implement according to DIBS Payment's documentation)
        $expected_signature = hash_hmac('sha256', $raw_data, $webhook_secret);
        
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Get webhook URL
     */
    public static function get_webhook_url() {
        return home_url('/dibs-payment-webhook/');
    }
}

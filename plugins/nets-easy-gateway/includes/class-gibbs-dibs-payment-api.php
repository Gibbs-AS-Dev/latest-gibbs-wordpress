<?php
defined('ABSPATH') || exit;

class Gibbs_DIBS_Payment_API {

    private $secret_key;
    private $checkout_key;
    private $merchant_id;
    private $environment;
    private $base_url;

    public function __construct($user_id = null) {
        try {
            $settings = get_option('woocommerce_nets_easy_settings');
            $this->environment = isset($settings['environment']) ? $settings['environment'] : 'test';
            $this->merchant_id = isset($settings['merchant_id']) ? $settings['merchant_id'] : '';
            
            if ($this->environment === 'live') {
                $this->secret_key = isset($settings['live_secret_key']) ? $settings['live_secret_key'] : '';
                $this->checkout_key = isset($settings['live_checkout_key']) ? $settings['live_checkout_key'] : '';
                $this->base_url = 'https://api.dibspayment.eu/';
            } else {
                $this->secret_key = isset($settings['test_secret_key']) ? $settings['test_secret_key'] : '';
                $this->checkout_key = isset($settings['test_checkout_key']) ? $settings['test_checkout_key'] : '';
                $this->base_url = 'https://test.api.dibspayment.eu/';
            }
        } catch (Exception $e) {
            // Set default test credentials if there's an error
            $this->environment = 'test';
            $this->secret_key = '';
            $this->checkout_key = '';
            $this->merchant_id = '';
            $this->base_url = 'https://test.api.dibspayment.eu/';
        }
    }

    /**
     * Get access token for API requests
     */
    private function getAccessToken() {
        if (!$this->secret_key) {
            throw new Exception('DIBS Payment credentials not configured');
        }

        // For DIBS Payment, the secret key is used directly as the access token
        // In some cases, you might need to exchange it for a bearer token
        return $this->secret_key;
    }

    /**
     * Create DIBS Payment checkout
     */
    public function create_checkout($checkout_data) {
        if (!$this->secret_key) {
            throw new Exception('DIBS Payment credentials not configured');
        }

        $url = $this->base_url . 'v1/payments';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: $this->secret_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($checkout_data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        $result = json_decode($response, true);
        

        if (in_array($status, [200, 201]) && !empty($result['paymentId'])) {
            return [
                'paymentId' => $result['paymentId'],
                'checkoutKey' => $this->checkout_key,
                'mode' => $this->environment
            ];
        } else {
            throw new Exception("Error creating DIBS Payment checkout: " . (json_encode($result['errors'] ?? $result) ?? $response));
        }
    }

    /**
     * Get payment details
     */
    public function get_payment($payment_id) {
        $accessToken = $this->getAccessToken();
        $url = $this->base_url . 'v1/payments/' . $payment_id;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        $result = json_decode($response, true);

        if (in_array($status, [200, 201])) {
            return $result;
        } else {
            $message = isset($result["errors"][0]["message"]) ? $result["errors"][0]["message"] : $response;
            throw new Exception('Error fetching payment: ' . $message);
        }
    }

    /**
     * Capture payment
     */
    public function capture_payment($payment_id, $amount = null) {
        $accessToken = $this->getAccessToken();
        $url = $this->base_url . 'v1/payments/' . $payment_id . '/charges';

        $capture_data = [];
        if ($amount !== null) {
            $capture_data['amount'] = $amount;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($capture_data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        $result = json_decode($response, true);

        if (in_array($status, [200, 201])) {
            return $result;
        } else {
            $message = isset($result["errors"][0]["message"]) ? $result["errors"][0]["message"] : $response;
            throw new Exception('Error capturing payment: ' . $message);
        }
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Order not found');
            }

            // Get the payment ID from order meta
            $payment_id = $order->get_meta('nets_easy_payment_id');
            if (!$payment_id) {
                $payment_id = $order->get_meta('_dibs_payment_id');
            }

            if (!$payment_id) {
                throw new Exception('No DIBS Payment ID found for this order');
            }

            // Convert amount to cents if provided
            $refund_amount = null;
            if ($amount !== null) {
                $refund_amount = intval(round($amount * 100));
            }

            // Process the refund through DIBS Payment API
            $refund_result = $this->refund_payment($payment_id, $refund_amount);

            if ($refund_result) {
                // Add order note about the refund
                $order->add_order_note(
                    sprintf(
                        __('DIBS Payment refund processed. Amount: %s. Reason: %s', 'woocommerce'),
                        wc_price($amount),
                        $reason
                    )
                );
                
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log('DIBS Payment refund error: ' . $e->getMessage());
            return new WP_Error('refund_error', $e->getMessage());
        }
    }

    /**
     * Refund payment
     */
    public function refund_payment($payment_id, $amount = null) {
        $accessToken = $this->getAccessToken();
        $url = $this->base_url . 'v1/payments/' . $payment_id . '/refunds';

        $refund_data = [];
        if ($amount !== null) {
            $refund_data['amount'] = $amount;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($refund_data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        $result = json_decode($response, true);

        if (in_array($status, [200, 201])) {
            return $result;
        } else {
            $message = isset($result["errors"][0]["message"]) ? $result["errors"][0]["message"] : $response;
            throw new Exception('Error refunding payment: ' . $message);
        }
    }

    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $accessToken = $this->getAccessToken();
            return [
                'success' => true,
                'message' => 'Connection successful',
                'access_token' => substr($accessToken, 0, 20) . '...'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create seller/merchant account (placeholder for future implementation)
     */
    public function createSeller($seller_data) {
        // This would be implemented based on DIBS Payment's merchant onboarding API
        // For now, return a placeholder response
        return [
            'success' => false,
            'message' => 'Seller creation not implemented yet'
        ];
    }

    /**
     * Get seller data (placeholder for future implementation)
     */
    public function getSeller($seller_id) {
        // This would be implemented based on DIBS Payment's merchant management API
        // For now, return a placeholder response
        return [
            'success' => false,
            'message' => 'Seller retrieval not implemented yet'
        ];
    }
}

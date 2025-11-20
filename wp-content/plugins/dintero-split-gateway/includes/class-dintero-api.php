<?php
defined('ABSPATH') || exit;

class Dintero_API {

    private $client_id;
    private $client_secret;
    private $account_id;
    private $environment;

    public function __construct($user_id = null) {
        try {
            $settings = get_option('woocommerce_dintero_settings');
            $this->environment = isset($settings['environment']) ? $settings['environment'] : 'test';
            if ($this->environment === 'live') {
                $this->client_id = isset($settings['live_client_id']) ? $settings['live_client_id'] : '';
                $this->client_secret = isset($settings['live_client_secret']) ? $settings['live_client_secret'] : '';
                $this->account_id = isset($settings['live_account_id']) ? $settings['live_account_id'] : '';
            } else {
                $this->client_id = isset($settings['test_client_id']) ? $settings['test_client_id'] : '';
                $this->client_secret = isset($settings['test_client_secret']) ? $settings['test_client_secret'] : '';
                $this->account_id = isset($settings['test_account_id']) ? $settings['test_account_id'] : '';
            }
        } catch (Exception $e) {
            // Set default test credentials if there's an error
            $this->environment = '';
            $this->client_id = '';
            $this->client_secret = '';
            $this->account_id = '';
        }
    }

    /**
     * Get Dintero access token
     */
    public function getAccessToken() {
        
        if (!$this->client_id || !$this->client_secret || !$this->account_id) {
            throw new Exception('Dintero credentials not configured');
        }

        $url = "https://api.dintero.com/v1/accounts/{$this->account_id}/auth/token";

        $postData = [
            'grant_type' => 'client_credentials',
            'audience' => "https://api.dintero.com/v1/accounts/{$this->account_id}",
            "scope" => "admin:accounts write:accounts write:accounts:/management/settings/approvals"
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => "{$this->client_id}:{$this->client_secret}",
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($postData),
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

        $data = json_decode($response, true);

        if ($status === 200 && isset($data['access_token'])) {
            return $data['access_token'];
        }

        throw new Exception("Error fetching token: HTTP $status – " . ($data['error_description'] ?? $response));
    }

    /**
     * Create Dintero seller
     */
    public function createSeller($seller_data) {
        $accessToken = $this->getAccessToken();

        

        $seller_data = $this->getSellerData($seller_data);

        //echo "<pre>"; print_r($seller_data); die;

       

        $url = "https://api.dintero.com/v1/accounts/{$this->account_id}/management/settings/approvals/payout-destinations";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($seller_data),
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

        //echo "<pre>"; print_r($result); die;

        if (in_array($status, [200, 201])) {
            return [
                'success' => true,
                'status' => $status,
                'data' => $result,
                'message' => 'Seller creation request sent successfully'
            ];
        } else {

            $message = "Unknown error";

            if(isset($result["error"])){
                $message = $result["error"]["message"];
            }
            return [
                'success' => false,
                'status' => $status,
                'data' => $result,
                'message' => 'Error creating seller: ' . $message
            ];
        }
    }

    public function fetchSeller($seller_id){
        $accessToken = $this->getAccessToken();
        $url = "https://api.dintero.com/v1/accounts/{$this->account_id}/management/settings/approvals/payout-destinations/{$seller_id}";
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
            return [
                'success' => true,
                'status' => $status,
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'status' => $status,
                'data' => $result,
                'message' => 'Error fetching seller: ' . $result["error"]["message"]
            ];
        }
    }

    /**
     * Get default seller data from user settings
     */
    private function getSellerData($seller_data) {

        $user_id = get_current_user_id();
        $group_admin = Dintero_Frontend::get_group_admin();
        $currency_user_id = ($group_admin != "") ? $group_admin : $user_id;


        // Use saved settings or defaults
        $bank_name = $seller_data['dintero_bank_name'] ?? $seller_data['dintero_bank_name'];
        $bank_account_number = $seller_data['dintero_bank_account_number'] ?? $seller_data['dintero_bank_account_number'];
        $bank_account_number_type = $seller_data['dintero_bank_account_number_type'] ?? $seller_data['dintero_bank_account_number_type'];
        $bank_account_country_code = $seller_data['dintero_bank_account_country_code'] ?? $seller_data['dintero_bank_account_country_code'];
        $bank_account_currency = $seller_data['dintero_bank_account_currency'] ?? $seller_data['dintero_bank_account_currency'];
        $payout_currency = $seller_data['dintero_payout_currency'] ?? $seller_data['dintero_payout_currency'];
        $bank_identification_code = $seller_data['dintero_bank_identification_code'] ?? $seller_data['dintero_bank_identification_code'];

        // Get user info
        $user_info = get_userdata($currency_user_id);
        $user_email = $user_info->user_email;
        $user_name = $user_info->display_name;


        // Generate unique seller ID
        $seller_id = 'seller_' . $currency_user_id . '_' . time();

        return [
            "payout_destination_id" => $seller_id,
            "payout_reference" => "Gibbs.no",
            "bank_accounts" => [
                [
                    "bank_name" => $bank_name,
                    "bank_account_number" => $bank_account_number,
                    "bank_account_number_type" => $bank_account_number_type,
                    "bank_account_country_code" => $bank_account_country_code,
                    "bank_account_currency" => $bank_account_currency,
                    "payout_currency" => $payout_currency,
                    "bank_identification_code" => $bank_identification_code
                ]
            ],
            "payout_destination_name" => $user_name,
            "payout_destination_description" => "AUTO_APPROVE",
            "country_code" => $bank_account_country_code,
            "type" => "individual",
            "individual" => [
                "name" => $user_name,
                "email" => $user_email,
            ],
            "payout_interval_type" => "monthly",
            "form_submitter" => [
                "email" => $user_email,
                "name" => $user_name,
                "title" => "Owner"
            ],
            "settlement_report_configuration" => [
                "emails" => [$user_email]
            ],
            "report_configuration" => [
                "create_report_configuration" => true,
                "email" => $user_email,
                "schedule" => "monthly",
                "reference" => "payout-report-" . $currency_user_id,
                "content_types" => ["application/pdf"]
            ]
        ];
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
     * Create Dintero Checkout session
     */
    public function create_checkout_session($session_data) {
        $accessToken = $this->getAccessToken();
        $url = "https://checkout.dintero.com/v1/sessions-profile";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($session_data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
           // echo "<pre>"; print_r($status);
           // echo "<pre>"; print_r($error); die;
            throw new Exception("cURL Error: $error");
        }

        $result = json_decode($response, true);
    //    echo "<pre>"; print_r($status); 
    //    echo "<pre>"; print_r($response);
    //    echo "<pre>"; print_r($result); die;

        if (in_array($status, [200, 201]) && !empty($result['url']) && !empty($result['id'])) {
            return ['checkout_url' => $result['url'], 'id' => $result['id']];
        } else {
            throw new Exception("Error creating Dintero session: " . (json_encode($result['error']) ?? $response));
        }
    }

    /**
     * Get Dintero seller (payout-destination) data by seller_id
     * Optionally accepts query parameters for filtering.
     */
    public function getSeller($seller_id) {
        $accessToken = $this->getAccessToken();

        $params["payout_destination_id"] = $seller_id;
        $url = "https://api.dintero.com/v1/accounts/{$this->account_id}/management/settings/approvals/payout-destinations/";
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
       // echo "<pre>"; print_r($url); die;

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

        //echo "<pre>"; print_r($response); die;

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        $result = json_decode($response, true);

        if (in_array($status, [200, 201])) {
            return [
                'success' => true,
                'status' => $status,
                'data' => $result
            ];
        } else {
            $message = isset($result["error"]["message"]) ? $result["error"]["message"] : $response;
            return [
                'success' => false,
                'status' => $status,
                'data' => $result,
                'message' => 'Error fetching seller: ' . $message
            ];
        }
    }

    /**
     * Get list of Dintero sellers (payout-destinations) with optional filters
     * $params can include payout_destination_id, case_status, etc.
     */
    public function getSellers($params = []) {
        $accessToken = $this->getAccessToken();
        $url = "https://api.dintero.com/v1/accounts/{$this->account_id}/management/settings/approvals/payout-destinations";
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

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
            return [
                'success' => true,
                'status' => $status,
                'data' => $result
            ];
        } else {
            $message = isset($result["error"]["message"]) ? $result["error"]["message"] : $response;
            return [
                'success' => false,
                'status' => $status,
                'data' => $result,
                'message' => 'Error fetching sellers: ' . $message
            ];
        }
    }

    /**
     * Get transaction data from Dintero
     */
    public function get_transaction($transaction_id) {
        $accessToken = $this->getAccessToken();
        $url = "https://api.dintero.com/v1/accounts/{$this->account_id}/transactions/{$transaction_id}";

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
            $message = isset($result["error"]["message"]) ? $result["error"]["message"] : $response;
            throw new Exception('Error fetching transaction: ' . $message);
        }
    }

    /**
     * Get checkout session data from Dintero
     */
    public function get_checkout_session($session_id) {
        $accessToken = $this->getAccessToken();
        $url = "https://api.dintero.com/v1/accounts/{$this->account_id}/sessions/{$session_id}";

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
            $message = isset($result["error"]["message"]) ? $result["error"]["message"] : $response;
            throw new Exception('Error fetching checkout session: ' . $message);
        }
    }
} 
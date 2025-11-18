<?php
/**
 * Wallet API Class
 * Handles all wallet-related API requests
 * Follows the same error handling pattern as SlotBookingApi
 */

class WalletApi {
    private $db;
    private $response;
    private $current_user_id = null;
    private $current_user = null;

    public function __construct() {
        try {
            $this->db = new WalletDatabase();
            $this->response = new CoreResponse();
        } catch (Exception $e) {
            CoreResponse::serverError('Failed to initialize wallet API: ' . $e->getMessage());
        }
    }

    /**
     * Main method to handle wallet API requests
     */
    public function handleWalletRequest() {
        try {
            $method = CoreResponse::getRequestMethod();
            $data = CoreResponse::getRequestData();

            $this->authenticateUser();
            
            switch ($method) {
                case 'GET':
                    $this->handleWalletGetRequest($data);
                    break;
                case 'POST':
                    $this->handleWalletPostRequest($data);
                    break;
                case 'PUT':
                    $this->handleWalletPutRequest($data);
                    break;
                case 'DELETE':
                    $this->handleWalletDeleteRequest($data);
                    break;
                default:
                    CoreResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            CoreResponse::serverError('Wallet API error: ' . $e->getMessage());
        }
    }

    /**
     * Handle GET requests for wallet operations
     */
    private function handleWalletGetRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'get_balance':
                $this->requireAuth();
                $this->getWalletBalance($data);
                break;
            case 'getTransactionHistory':
                $this->requireAuth();
                $this->getTransactionHistory($data);
                break;
            case 'check_funds':
                $this->requireAuth();
                $this->checkSufficientFunds($data);
                break;
            case 'get_wallet_stats':
                $this->requireAuth();
                $this->getWalletStats($data);
                break;
            case 'getSmsLogs':
                $this->requireAuth();
                $this->getSmsLogs($data);
                break;
            default:
                CoreResponse::error('Invalid action for GET request', 400);
                break;
        }
    }

    /**
     * Handle POST requests for wallet operations
     */
    private function handleWalletPostRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'addFunds':
                $this->requireAuth();
                $this->addFunds($data);
                break;
            case 'deduct_funds':
                $this->deductFunds($data);
                break;
            case 'create_wallet':
                $this->createWallet($data);
                break;
            default:
                CoreResponse::error('Invalid action for POST request', 400);
                break;
        }
    }

    /**
     * Handle PUT requests for wallet operations
     */
    private function handleWalletPutRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'update_wallet':
                $this->updateWallet($data);
                break;
            case 'reactivate_wallet':
                $this->reactivateWallet($data);
                break;
            default:
                CoreResponse::error('Invalid action for PUT request', 400);
                break;
        }
    }

    /**
     * Handle DELETE requests for wallet operations
     */
    private function handleWalletDeleteRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'deactivate_wallet':
                $this->deactivateWallet($data);
                break;
            case 'delete_wallet':
                $this->deleteWallet($data);
                break;
            default:
                CoreResponse::error('Invalid action for DELETE request', 400);
                break;
        }
    }

    /**
     * Get wallet balance for a user
     */
    private function getWalletBalance($input) {
        try {
            

            $userId = $this->getCurrentUserId();
            $wallet = $this->db->getWalletByUserId($userId);
            
            if (!$wallet) {
                // Create wallet if it doesn't exist
                $walletId = $this->db->createWallet($userId);
                if ($walletId) {
                    $wallet = $this->db->getWalletByUserId($userId);
                } else {
                    CoreResponse::error('Failed to create wallet', 500);
                    return;
                }
            }

            CoreResponse::success([
                'wallet_id' => $wallet['id'],
                'user_id' => $wallet['user_id'],
                'balance' => floatval($wallet['balance']),
                'currency' => $wallet['currency'],
                'status' => $wallet['status']
            ], 'Wallet balance retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get wallet balance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add funds to wallet
     */
    private function addFunds($input) {
        try {
            

            $this->validateRequiredFields($input, ['amount','back_url']);
            
            $userId = $this->getCurrentUserId();

            $source = isset($input['source']) ? $input['source'] : 'from_customer';

            

            $amount = floatval($input['amount']);
            $description = isset($input['description']) ? $input['description'] : 'Funds added';
            $reference = isset($input['reference']) ? $input['reference'] : '';
            
            if ($amount <= 0) {
                CoreResponse::error('Amount must be greater than 0', 400);
                return;
            }

            // Get or create wallet
            $wallet = $this->db->getWalletByUserId($userId);
            if (!$wallet) {
                $walletId = $this->db->createWallet($userId);
                if (!$walletId) {
                    CoreResponse::error('Failed to create wallet', 500);
                    return;
                }
            }
            if ( ! function_exists( 'get_current_user_id' ) ) {
                $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
                if ( file_exists( $wp_load_path ) ) {
                    require_once( $wp_load_path );
                } else {
                    CoreResponse::error('WordPress core not found', 400);
                }
            }
            

           

            if(!class_exists('Class_Gibbs_Subscription')){
                $gibbs_subscription_path = dirname( __FILE__, 6 ) . '/wp-content/plugins/gibbs-subscription/includes/class-gibbs-subscription.php';
                if ( file_exists( $gibbs_subscription_path ) ) {
                    require_once( $gibbs_subscription_path );
                } else {
                    CoreResponse::error('Gibbs Subscription class not found', 400);
                }
            }

            // $session = '{"id":"cs_test_a1qEiZmeaF3r0CQFuo8Zi3ZUuCZYAdVvTmEP4luRcMHNoGGueiYXmO9zk4","object":"checkout.session","adaptive_pricing":{"enabled":true},"after_expiration":null,"allow_promotion_codes":null,"amount_subtotal":10000,"amount_total":10000,"automatic_tax":{"enabled":false,"liability":null,"provider":null,"status":null},"billing_address_collection":null,"cancel_url":"https:\/\/staging5.dev.gibbs.no\/gibbs-wallet?wallet_payment=cancelled","client_reference_id":null,"client_secret":null,"collected_information":{"shipping_details":null},"consent":null,"consent_collection":null,"created":1755069255,"currency":"nok","currency_conversion":null,"custom_fields":[],"custom_text":{"after_submit":null,"shipping_address":null,"submit":null,"terms_of_service_acceptance":null},"customer":"cus_SrGF0hsmvoNplO","customer_creation":null,"customer_details":{"address":{"city":null,"country":"IN","line1":null,"line2":null,"postal_code":null,"state":null},"email":"no_reply@gibbs.no","name":"K Gibbs","phone":null,"tax_exempt":"none","tax_ids":[]},"customer_email":null,"discounts":[],"expires_at":1755155655,"invoice":null,"invoice_creation":{"enabled":false,"invoice_data":{"account_tax_ids":null,"custom_fields":null,"description":null,"footer":null,"issuer":null,"metadata":[],"rendering_options":null}},"livemode":false,"locale":null,"metadata":{"record_transaction_id":"3","user_id":"1","payment_type":"wallet","amount":"100"},"mode":"payment","origin_context":null,"payment_intent":"pi_3RvZ3FRo22oYmnC41HIxDYrB","payment_link":null,"payment_method_collection":"if_required","payment_method_configuration_details":null,"payment_method_options":{"card":{"request_three_d_secure":"automatic"}},"payment_method_types":["card"],"payment_status":"paid","permissions":null,"phone_number_collection":{"enabled":false},"recovered_from":null,"saved_payment_method_options":{"allow_redisplay_filters":["always"],"payment_method_remove":"disabled","payment_method_save":null},"setup_intent":null,"shipping_address_collection":null,"shipping_cost":null,"shipping_details":null,"shipping_options":[],"status":"complete","submit_type":null,"subscription":null,"success_url":"https:\/\/staging5.dev.gibbs.no\/gibbs-wallet?wallet_payment=success","total_details":{"amount_discount":0,"amount_shipping":0,"amount_tax":0},"ui_mode":"hosted","url":null,"wallet_options":null}';

            

            // $session = json_decode($session);

            

            // $Class_Gibbs_Subscription = new Class_Gibbs_Subscription();
            // $Class_Gibbs_Subscription->action_init();
            // $result = $Class_Gibbs_Subscription->handle_wallet_payment_success($session);
            // if(isset($result['error'])){
            //     CoreResponse::error($result['error'], 400);
            // }
            // if(isset($result['url'])){
            //     CoreResponse::success($result, 'redirect');
            //     return;
            // }



            // die;

            $record_transaction_id = $this->db->recordTransaction($userId, 'credit', $amount, $description, $reference);
            if(!$record_transaction_id){
                CoreResponse::error('Failed to record transaction', 500);
                return;
            }

            $user_id = get_current_user_id();


            if($source == 'from_admin'){
                if(!user_can(get_current_user_id(), 'administrator')){
                    CoreResponse::error('User is not an admin', 400);
                    return;
                }

                $this->save_wallet_payment($user_id, $amount,  $record_transaction_id);


                CoreResponse::success([
                    'message' => 'Funds added successfully',
                    "admin" => "true",
                ], 'Funds added successfully');
                return;


                
            }


            //$this->save_wallet_payment($userId, $amount, $record_transaction_id);

           
            $Class_Gibbs_Subscription = new Class_Gibbs_Subscription();
            $Class_Gibbs_Subscription->action_init();
            $result = $Class_Gibbs_Subscription->add_funds($user_id, $amount, $record_transaction_id, $input['back_url']);
            if(isset($result['error'])){
                CoreResponse::error($result['error'], 400);
            }

            if(isset($result['url'])){
                CoreResponse::success($result, 'redirect');
                return;
            }

            CoreResponse::error('Failed to add funds', 400);

           
            // Add funds
            // $success = $this->db->addFunds($userId, $amount);
            // if (!$success) {
            //     CoreResponse::error('Failed to add funds', 500);
            //     return;
            // }

            // // Record transaction
            // $this->db->recordTransaction($userId, 'credit', $amount, $description, $reference);

            // // Get updated balance
            // $updatedWallet = $this->db->getWalletByUserId($userId);
            
            // CoreResponse::success([
            //     'message' => 'Funds added successfully',
            //     'new_balance' => floatval($updatedWallet['balance']),
            //     'amount_added' => $amount,
            //     'currency' => $updatedWallet['currency']
            // ], 'Funds added successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to add funds: ' . $e->getMessage(), 500);
        }
    }

    public function save_wallet_payment($user_id, $amount, $record_transaction_id, $payment_id = "manual", $description = "Funds added", $reference = ""){
        try{

            $success = $this->db->addFunds($user_id, $amount);

            $data = array(
                'status' => 'success',
                'payment_id' => $payment_id,
            );

            $this->db->updateTransactionRecord($record_transaction_id, $data);

            CoreResponse::success([
                'message' => 'Funds added successfully',
                'admin' => "true",
            ], 'Funds added successfully');

            //$this->db->save_wallet_payment($user_id, $amount, $payment_intent);
        }catch(Exception $e){
            CoreResponse::error('Failed to save wallet payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Deduct funds from wallet
     */
    private function deductFunds($input) {
        try {
            

            $this->validateRequiredFields($input, ['amount']);
            
            $userId = $this->getCurrentUserId();
            $amount = floatval($input['amount']);
            $description = isset($input['description']) ? $input['description'] : 'Funds deducted';
            $reference = isset($input['reference']) ? $input['reference'] : '';
            
            if ($amount <= 0) {
                CoreResponse::error('Amount must be greater than 0', 400);
                return;
            }

            // Check if user has sufficient funds
            if (!$this->db->hasSufficientFunds($userId, $amount)) {
                CoreResponse::error('Insufficient funds', 400);
                return;
            }

            // Deduct funds
            $success = $this->db->deductFunds($userId, $amount);
            if (!$success) {
                CoreResponse::error('Failed to deduct funds', 500);
                return;
            }

            // Record transaction
            $this->db->recordTransaction($userId, 'debit', $amount, $description, $reference);

            // Get updated balance
            $updatedWallet = $this->db->getWalletByUserId($userId);
            
            CoreResponse::success([
                'message' => 'Funds deducted successfully',
                'new_balance' => floatval($updatedWallet['balance']),
                'amount_deducted' => $amount,
                'currency' => $updatedWallet['currency']
            ], 'Funds deducted successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to deduct funds: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new wallet for a user
     */
    private function createWallet($input) {
        try {
            

            $userId = $this->getCurrentUserId();
            $initialBalance = isset($input['initial_balance']) ? floatval($input['initial_balance']) : 0.00;
            $currency = isset($input['currency']) ? $input['currency'] : 'NOK';
            
            // Check if wallet already exists
            $existingWallet = $this->db->getWalletByUserId($userId);
            if ($existingWallet) {
                CoreResponse::error('Wallet already exists for this user', 400);
                return;
            }

            // Create wallet
            $walletId = $this->db->createWallet($userId, $initialBalance, $currency);
            if (!$walletId) {
                CoreResponse::error('Failed to create wallet', 500);
                return;
            }

            // Record initial transaction if balance > 0
            if ($initialBalance > 0) {
                $this->db->recordTransaction($userId, 'credit', $initialBalance, 'Initial wallet balance', 'wallet_creation');
            }

            CoreResponse::success([
                'message' => 'Wallet created successfully',
                'wallet_id' => $walletId,
                'user_id' => $userId,
                'initial_balance' => $initialBalance,
                'currency' => $currency
            ], 'Wallet created successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to create wallet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get transaction history for a user
     */
    private function getTransactionHistory($input) {
        try {
            $userId = $this->getCurrentUserId();
            
            $limit = isset($input['limit']) ? intval($input['limit']) : 50;
            
            // Ensure limit is reasonable
            if ($limit > 100) {
                $limit = 100;
            }
            
            $transactions = $this->db->getTransactionHistory($userId, $limit);
            
            CoreResponse::success([
                'user_id' => $userId,
                'transactions' => $transactions,
                'count' => count($transactions)
            ], 'Transaction history retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get transaction history: ' . $e->getMessage(), 500);
        }
    }

    private function getSmsLogs($input) {
        try {
            $listing_ids = $this->db->getAllListingIds($input['owner_id']);
            $userId = $this->getCurrentUserId();
            $smsLogs = $this->db->getSmsLogsWithPagination($input['page'], $input['per_page'], $input['search'], $listing_ids);
            CoreResponse::success($smsLogs, 'SMS logs retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get SMS logs: ' . $e->getMessage(), 500);
        }
    }   

    /**
     * Check if user has sufficient funds
     */
    private function checkSufficientFunds($input) {
        try {
            

            $this->validateRequiredFields($input, ['amount']);
            
            $userId = $this->getCurrentUserId();
            $amount = floatval($input['amount']);
            
            if ($amount <= 0) {
                CoreResponse::error('Amount must be greater than 0', 400);
                return;
            }

            $hasFunds = $this->db->hasSufficientFunds($userId, $amount);
            $wallet = $this->db->getWalletByUserId($userId);
            
            CoreResponse::success([
                'has_sufficient_funds' => $hasFunds,
                'current_balance' => $wallet ? floatval($wallet['balance']) : 0.00,
                'required_amount' => $amount,
                'currency' => $wallet ? $wallet['currency'] : 'NOK'
            ], 'Fund check completed successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to check sufficient funds: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get wallet statistics (admin function)
     */
    private function getWalletStats($input) {
        try {
            // Optional: Add admin check here if needed
            $stats = $this->db->getWalletStats();
            
            CoreResponse::success($stats, 'Wallet statistics retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get wallet statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Deactivate wallet
     */
    private function deactivateWallet($input) {
        try {
           

            $userId = $this->getCurrentUserId();
            
            // Check if wallet exists
            $wallet = $this->db->getWalletByUserId($userId);
            if (!$wallet) {
                CoreResponse::error('Wallet not found for this user', 404);
                return;
            }

            $success = $this->db->deactivateWallet($userId);
            if (!$success) {
                CoreResponse::error('Failed to deactivate wallet', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Wallet deactivated successfully',
                'user_id' => $userId
            ], 'Wallet deactivated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to deactivate wallet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update wallet information
     */
    private function updateWallet($input) {
        try {
            
            $userId = $this->getCurrentUserId();
            $currency = isset($input['currency']) ? $input['currency'] : null;
            $status = isset($input['status']) ? $input['status'] : null;
            
            // Check if wallet exists
            $wallet = $this->db->getWalletByUserId($userId);
            if (!$wallet) {
                CoreResponse::error('Wallet not found for this user', 404);
                return;
            }

            // Update wallet
            $success = $this->db->updateWallet($userId, $currency, $status);
            if (!$success) {
                CoreResponse::error('Failed to update wallet', 500);
                return;
            }

            // Get updated wallet
            $updatedWallet = $this->db->getWalletByUserId($userId);
            
            CoreResponse::success([
                'message' => 'Wallet updated successfully',
                'wallet_id' => $updatedWallet['id'],
                'user_id' => $updatedWallet['user_id'],
                'balance' => floatval($updatedWallet['balance']),
                'currency' => $updatedWallet['currency'],
                'status' => $updatedWallet['status']
            ], 'Wallet updated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to update wallet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reactivate a deactivated wallet
     */
    private function reactivateWallet($input) {
        try {
           

            $userId = $this->getCurrentUserId();
            
            // Check if wallet exists
            $wallet = $this->db->getWalletByUserId($userId);
            if (!$wallet) {
                CoreResponse::error('Wallet not found for this user', 404);
                return;
            }

            if ($wallet['status'] === 'active') {
                CoreResponse::error('Wallet is already active', 400);
                return;
            }

            $success = $this->db->reactivateWallet($userId);
            if (!$success) {
                CoreResponse::error('Failed to reactivate wallet', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Wallet reactivated successfully',
                'user_id' => $userId,
                'status' => 'active'
            ], 'Wallet reactivated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to reactivate wallet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete wallet permanently
     */
    private function deleteWallet($input) {
        try {
            

            $userId = $this->getCurrentUserId();
            
            // Check if wallet exists
            $wallet = $this->db->getWalletByUserId($userId);
            if (!$wallet) {
                CoreResponse::error('Wallet not found for this user', 404);
                return;
            }

            // Check if wallet has balance
            if (floatval($wallet['balance']) > 0) {
                CoreResponse::error('Cannot delete wallet with remaining balance', 400);
                return;
            }

            $success = $this->db->deleteWallet($userId);
            if (!$success) {
                CoreResponse::error('Failed to delete wallet', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Wallet deleted successfully',
                'user_id' => $userId
            ], 'Wallet deleted successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to delete wallet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate required fields in input
     */
    private function validateRequiredFields($input, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                CoreResponse::error("Missing required field: {$field}", 400);
                return false;
            }
        }
        return true;
    }

    /**
     * Centralized authentication method
     * Sets current user if authenticated, returns false if not
     */
    private function authenticateUser() {
        try {
            // Extract JWT token from Authorization header
            $authHeader = $this->getAuthorizationHeader();
            if (!$authHeader) {
                return false;
            }

            $token = $this->extractBearerToken($authHeader);
            if (!$token) {
                return false;
            }

            // Validate JWT token and get user ID
            $userData = $this->validateJWTToken($token);
            
            if(!isset($userData['user_id'])){
                return false;
            }

            // Set current user
            $this->current_user_id = $userData['user_id'];
            $this->current_user = $userData;
            
            return true;
        } catch (Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId() {
        return $this->current_user_id;
    }

    /**
     * Get current authenticated user object
     */
    private function getCurrentUser() {
        return $this->current_user;
    }

    /**
     * Check if user is authenticated
     */
    private function isAuthenticated() {
        return $this->current_user_id !== null;
    }

    /**
     * Require authentication for protected operations
     */
    private function requireAuth() {
        if (!$this->isAuthenticated()) {
            CoreResponse::error('Authentication required', 401);
            return false;
        }
        return true;
    }

    /**
     * Get Authorization header
     */
    private function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }

    /**
     * Extract Bearer token from Authorization header
     */
    private function extractBearerToken($authHeader) {
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Validate JWT token and return user ID
     */
    private function validateJWTToken($token) {
        try {
            // Include the custom JWT class
            $jwt_file = dirname( __FILE__, 3 ) . '/includes/class-custom-jwt.php';
            if (file_exists($jwt_file)) {
                require_once $jwt_file;
                
                $custom_jwt = new Custom_JWT();
                $decoded = $custom_jwt->validate_token($token);
                
                if ($decoded && isset($decoded['user_id'])) {
                    return $decoded;
                }
            }
            
            return false;
        } catch (Exception $e) {
            die($e->getMessage());
            return false;
        }
    }
} 
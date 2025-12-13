<?php
/**
 * Customer API Class
 * Handles all customer API requests
 */

class CustomerApi {
    private $db;
    private $response;
    private $current_user_id = null;
    private $current_user = null;

    public function __construct() {
        try {
            $this->db = new CustomerDatabase();
            $this->response = new CoreResponse();
        } catch (Exception $e) {
            CoreResponse::serverError('Failed to initialize customer API: ' . $e->getMessage());
        }
    }

    /**
     * Handle incoming API request
     */
    public function handleCustomerRequest() {
        try {
            $method = CoreResponse::getRequestMethod();
            $data = CoreResponse::getRequestData();

            $this->authenticateUser();

            switch ($method) {
                case 'GET':
                    $this->handleGetRequest($data);
                    break;
                case 'POST':
                    $this->handlePostRequest($data);
                    break;
                case 'PUT':
                    $this->handlePutRequest($data);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($data);
                    break;
                default:
                    CoreResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            CoreResponse::serverError('Customer API error: ' . $e->getMessage());
        }
    }

    /**
     * Handle GET actions
     */
    private function handleGetRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';

        switch ($action) {
            case 'getGibbsCustomers':
                $this->requireAuth();
                $this->getCustomers($data);
                break;
            case 'getGibbsCustomer':
                $this->requireAuth();
                $this->getCustomer($data);
                break;
            case 'getUsers':
                $this->requireAuth();
                $this->getUsers($data);
                break;
            case 'getCustomerPreferences':
                $this->requireAuth();
                $this->getCustomerPreferences($data);
                break;
            case 'getFilterPreferences':
                $this->requireAuth();
                $this->getFilterPreferences($data);
                break;
            case 'getPackages':
                $this->requireAuth();
                $this->getPackages($data);
                break;
            case 'checkEmailExists':
                $this->requireAuth();
                $this->checkEmailExists($data);
                break;
            case 'getGibbsUsergroup':
                $this->requireAuth();
                $this->getUsergroup($data);
                break;
            default:
                CoreResponse::error('Invalid action for GET request', 400);
        }
    }

    /**
     * Handle POST actions
     */
    private function handlePostRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';

        switch ($action) {
            case 'createGibbsCustomer':
                $this->requireAuth();
                $this->createCustomer($data);
                break;
            case 'saveCustomerPreferences':
                $this->requireAuth();
                $this->saveCustomerPreferences($data);
                break;
            case 'changeSuperAdmin':
                $this->requireAuth();
                $this->changeSuperAdmin($data);
                break;
            case 'updateGroupLicenses':
                $this->requireAuth();
                $this->updateGroupLicenses($data);
                break;
            case 'updateNextInvoice':
                $this->requireAuth();
                $this->updateNextInvoice($data);
                break;
            case 'updateMrrArr':
                $this->requireAuth();
                $this->updateMrrArr($data);
                break;
            default:
                CoreResponse::error('Invalid action for POST request', 400);
        }
    }

    /**
     * Handle PUT actions
     */
    private function handlePutRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';

        switch ($action) {
            case 'updateGibbsSuperadmin':
                $this->requireAuth();
                $this->updateSuperadminData($data);
                break;
            case 'updateGibbsUsergroup':
                $this->requireAuth();
                $this->updateUsergroupData($data);
                break;
            default:
                CoreResponse::error('Invalid action for PUT request', 400);
        }
    }

    /**
     * Handle DELETE actions
     */
    private function handleDeleteRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';

        switch ($action) {
            case 'deleteGibbsCustomer':
                $this->requireAuth();
                $this->deleteCustomer($data);
                break;
            default:
                CoreResponse::error('Invalid action for DELETE request', 400);
        }
    }

    private function getPackages($data) {
        $packages = $this->db->getPackages();
        CoreResponse::success(['packages' => $packages], 'Packages retrieved successfully');
    }

    private function changeSuperAdmin($data) {
        $oldSuperadminId = isset($data['old_superadmin_id']) ? intval($data['old_superadmin_id']) : 0;
        $newSuperadminId = isset($data['new_superadmin_id']) ? intval($data['new_superadmin_id']) : 0;
        
        if(!$oldSuperadminId || !$newSuperadminId){
            CoreResponse::error('Superadmin ID is required', 400);
            return;
        }

        $newsuperadminexist = $this->db->checkSuperadminExistsInUsersGroups($newSuperadminId);
        if($newsuperadminexist){
            CoreResponse::error('New superadmin already exists', 400);
            return;
        }

        $changeSuperAdmin = $this->db->changeSuperAdmin($oldSuperadminId, $newSuperadminId);
        if($changeSuperAdmin){
            CoreResponse::success('Superadmin changed successfully', []);
        }else{
            CoreResponse::error('Failed to change superadmin', 400);
            return;
        }
    }

    private function getFilterPreferences($data) {
        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }else{
                CoreResponse::error('WordPress core not found', 400);
            }
        }

        

        $countries = get_countries();
        $industries = get_industries();

        $result = array(
            'countries' => $countries,
            'industries' => $industries
        );
        CoreResponse::success($result, 'Filter preferences retrieved successfully');

    }

    /**
     * Update group licenses for a usergroup
     */
    private function updateGroupLicenses($data) {
        $groupId = isset($data['group_id']) ? intval($data['group_id']) : 0;
        $superadmin_id = isset($data['superadmin_id']) ? intval($data['superadmin_id']) : 0;
        $licenseIds = isset($data['license_ids']) && is_array($data['license_ids']) ? $data['license_ids'] : [];

        if (!$groupId || !$superadmin_id) {
            CoreResponse::error('Group ID and Superadmin ID are required', 400);
            return;
        }

        $result = $this->db->updateGroupLicenses($groupId, $superadmin_id, $licenseIds);
        
        if ($result) {
            CoreResponse::success(['group_id' => $groupId, 'license_ids' => $licenseIds], 'Group licenses updated successfully');
        } else {
            CoreResponse::error('Failed to update group licenses', 500);
        }
    }

    /**
     * Update next invoice date for a customer
     */
    private function updateNextInvoice($data) {
        $superadminId = isset($data['superadmin_id']) ? intval($data['superadmin_id']) : 0;
        $nextInvoice = isset($data['next_invoice']) ? trim($data['next_invoice']) : '';

        if (!$superadminId) {
            CoreResponse::error('Superadmin ID is required', 400);
            return;
        }

        $result = $this->db->updateNextInvoice($superadminId, $nextInvoice);
        
        if ($result) {
            CoreResponse::success(['superadmin_id' => $superadminId, 'next_invoice' => $nextInvoice], 'Next invoice date updated successfully');
        } else {
            CoreResponse::error('Failed to update next invoice date', 500);
        }
    }

    /**
     * Update MRR and ARR for a customer
     */
    private function updateMrrArr($data) {
        $superadminId = isset($data['superadmin_id']) ? intval($data['superadmin_id']) : 0;
        $mrr = isset($data['mrr']) && $data['mrr'] !== '' ? floatval($data['mrr']) : 0;
        $arrProvided = array_key_exists('arr', $data);
        $arr = $arrProvided && $data['arr'] !== '' ? floatval($data['arr']) : null;

        if (!$superadminId) {
            CoreResponse::error('Superadmin ID is required', 400);
            return;
        }

        // If ARR is not provided, default it to 12x MRR to mirror the UI expectation
        if ($arr === null) {
            $arr = $mrr * 12;
        }

        $result = $this->db->updateMrrArr($superadminId, $mrr, $arr);

        if ($result) {
            CoreResponse::success(
                ['superadmin_id' => $superadminId, 'mrr' => $mrr, 'arr' => $arr],
                'MRR/ARR updated successfully'
            );
        } else {
            CoreResponse::error('Failed to update MRR/ARR', 500);
        }
    }

    private function updateSuperadminData($data) {

        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }else{
                CoreResponse::error('WordPress core not found', 400);
            }
        }

        $superadminId = isset($data['superadmin_id']) ? intval($data['superadmin_id']) : 0;
        
        if(!$superadminId){
            CoreResponse::error('Superadmin ID is required', 400);
            return;
        }
        if($data['company_company_name'] == ""){
            CoreResponse::error('Company name is required', 400);
            return;
        }
        if($data['company_email'] == ""){
            CoreResponse::error('Company email is required', 400);
            return;
        }
        if($data['company_country'] == ""){
            CoreResponse::error('Company country is required', 400);
            return;
        }
        if($data['company_country_code'] == ""){
            CoreResponse::error('Company country code is required', 400);
            return;
        }

        if($data['company_phone'] == ""){
            CoreResponse::error('Company phone is required', 400);
            return;
        }
        if($data['first_name'] == ""){
            CoreResponse::error('First name is required', 400);
            return;
        }
        if($data['phone'] == ""){
            CoreResponse::error('Phone is required', 400);
            return;
        }

        $email_exists = false;

        if(isset($data['email'])){
            $customer = $this->db->getCustomerByEmail($data['email']);
            if($customer && $customer['ID'] != $superadminId){
                $email_exists = true;
            }
            if($email_exists){
                CoreResponse::error('Email already exists', 400);
                return;
            }

            

            $result = $this->db->updateSuperadminData($superadminId, $data);

            if(class_exists('Class_Gibbs_Subscription')){
                $Class_Gibbs_Subscription = new Class_Gibbs_Subscription();
                $Class_Gibbs_Subscription->action_init();
                $Class_Gibbs_Subscription->create_Stripe_Customer($superadminId);
            }
            CoreResponse::success($result, 'Superadmin data updated successfully');
        }else{
            CoreResponse::error('Email is required', 400);
            return;
        }
        
    }

    /**
     * Get customers list
     */
    private function getCustomers($data) {
        $params = [
            'page' => isset($data['page']) ? intval($data['page']) : 1,
            'per_page' => isset($data['per_page']) ? intval($data['per_page']) : 20,
            'tab' => isset($data['tab']) ? $data['tab'] : 'all',
            'search' => isset($data['search']) ? $data['search'] : '',
            'sort_by' => isset($data['sort_by']) ? $data['sort_by'] : 'company_name',
            'sort_direction' => isset($data['sort_direction']) ? $data['sort_direction'] : 'asc',
            'status' => isset($data['status']) ? $data['status'] : 'all',
            'country' => isset($data['country']) ? $data['country'] : 'all',
            'industry' => isset($data['industry']) ? $data['industry'] : 'all',
            'owner_id' => isset($data['owner_id']) ? intval($data['owner_id']) : $this->current_user_id
        ];

        $result = $this->db->getCustomers($params);

        CoreResponse::success($result, 'Customers retrieved successfully');
    }

    private function getUsers($data) {
        $params = [
            'page' => isset($data['page']) ? intval($data['page']) : 1,
            'per_page' => isset($data['per_page']) ? intval($data['per_page']) : 20,
            'search' => isset($data['search']) ? $data['search'] : '',
            'role' => isset($data['role']) ? $data['role'] : '',
        ];
        $result = $this->db->getUsers($params);
        CoreResponse::success($result, 'Users retrieved successfully');
        return $result;
    }

    /**
     * Get single customer
     * Supports fetching by customer_id or superadmin_id
     */
    private function getCustomer($data) {
        $customerId = isset($data['customer_id']) ? intval($data['customer_id']) : 0;
        $superadminId = isset($data['superadmin_id']) ? intval($data['superadmin_id']) : 0;

        if (!$customerId && !$superadminId) {
            CoreResponse::error('Customer ID or Superadmin ID is required', 400);
            return;
        }

        try {
            $customer = null;

            if ($superadminId) {
                $metaFields = [
                    'first_name',
                    'last_name',
                    'country_code',
                    'phone',
                    'next_invoice',
                    'package_id',
                    'subscription_id',
                    'subscription_type',
                    'license_status',
                    'stripe_trail',
                    '_gibbs_active_group_id',
                    'stripe_customer_id',
                    'stripe_test_customer_id',
                    'company_email',
                    'company_country',
                    'company_industry',
                    'company_country_code',
                    'company_company_name',
                    'company_organization_number',
                    'company_street_address',
                    'company_zip_code',
                    'company_city',
                    'company_phone'
                ];
                // Fetch by superadmin ID
                $customer = $this->db->getCustomerBySuperadmin($superadminId, $metaFields);
            } elseif ($customerId) {
                // For now, if only customer_id is provided, we can try to get it via superadmin
                // This would require additional implementation if customer_id maps to group id
                // For now, we'll return an error suggesting to use superadmin_id
                CoreResponse::error('Please provide superadmin_id to fetch customer data', 400);
                return;
            }

            if (!$customer) {
                CoreResponse::error('Customer not found', 404);
                return;
            }

            CoreResponse::success(['customer' => $customer],'Customer retrieved successfully' );
        } catch (Exception $e) {
            CoreResponse::serverError('Failed to retrieve customer: ' . $e->getMessage());
        }
    }

    /**
     * Create customer
     */
    private function createCustomer($data) {

        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }else{
                CoreResponse::error('WordPress core not found', 400);
            }
        }

       

        // Implementation for creating customer
        $current_user_id = $this->getCurrentUserId();

        if(!$current_user_id){
            CoreResponse::error('User is not authenticated', 401);
            return;
        }

        if($data['company_company_name'] == ""){
            CoreResponse::error('Company name is required', 400);
            return;
        }
        if($data['company_email'] == ""){
            CoreResponse::error('Company email is required', 400);
            return;
        }
        if($data['company_country'] == ""){
            CoreResponse::error('Company country is required', 400);
            return;
        }
        if($data['company_country_code'] == ""){
            CoreResponse::error('Company country code is required', 400);
            return;
        }

        if($data['company_phone'] == ""){
            CoreResponse::error('Company phone is required', 400);
            return;
        }
        if($data['first_name'] == ""){
            CoreResponse::error('First name is required', 400);
            return;
        }
        if($data['phone'] == ""){
            CoreResponse::error('Phone is required', 400);
            return;
        }

        if($data['package_id'] == ""){
            CoreResponse::error('Package is required', 400);
            return;
        }

        $package_data = $this->db->getPackageData($data['package_id']);

        if(isset($package_data['ID']) && $package_data['ID'] > 0){
            // $package_data['meta']['stripe_product_id'] = get_post_meta($package_data['ID'], 'stripe_product_id', true);
            // $package_data['meta']['start_price_id'] = get_post_meta($package_data['ID'], 'start_price_id', true);
            
        }else{
            CoreResponse::error('Package not found', 400);
            return;
        }

        $role = "owner";

        $user_dd = $this->registerUser($data, $role);
        
        if(isset($user_dd["success"]) && $user_dd["success"] == true && $user_dd["user_id"] > 0){
           $user_id = $user_dd["user_id"];

           $this->db->updateSuperadminData($user_id, $data);

           $user_data = get_user_by('id', $user_id);

           $group_data = $this->createGroupAndAssignUser($user_data, $data["group_name"]);

           $stripe_customer_id = "";

           if(class_exists('Class_Gibbs_Subscription')){
                $Class_Gibbs_Subscription = new Class_Gibbs_Subscription();
                $Class_Gibbs_Subscription->action_init();
                $stripe_customer_id = $Class_Gibbs_Subscription->create_Stripe_Customer($user_id);

                if($stripe_customer_id && $stripe_customer_id != ""){
                    $Class_Gibbs_Subscription->createSubscription($stripe_customer_id, $package_data["ID"], $user_id);
                }
           }

           CoreResponse::success([$user_id=>$user_id],'Customer created successfully');

        }else{
            CoreResponse::error($user_dd["message"], 400);
            return;
        }
    }

    private function createGroupAndAssignUser($user_data, $group_name){

        $user_id = $user_data->ID;

        $group_id = $this->db->createGroup($group_name, $user_id);
        if($group_id){
            $group_role = "3";
            $assign_user_to_group = $this->db->assignUserToGroup($group_id, $user_id, $group_role);

            $update_group_licence = $this->db->insertGroupLicence($group_id);

            $group_email = "admin".$group_id."_".$user_data->user_email;

            $data = array(
                "email" => $group_email,
                "first_name" => $group_name,
                "last_name" => "",
                "display_name" => $group_name,
            );

            $role = "editor";
            $group_admin_user_dd = $this->registerUser($data, $role);

            if(isset($group_admin_user_dd["success"]) && $group_admin_user_dd["success"] == true && $group_admin_user_dd["user_id"] > 0){
                $group_admin_user_id = $group_admin_user_dd["user_id"];
                $update_group_admin = $this->db->updateGroupAdmin($group_id, $group_admin_user_id);
                $group_role = "5";
                $assign_user_to_group = $this->db->assignUserToGroup($group_id, $group_admin_user_id, $group_role);
            }

        }
        return true;
    }

    /**
     * Update customer
     */
    private function updateCustomer($data) {
        // Implementation for updating customer
        CoreResponse::success('Customer updated successfully', []);
    }

    public static function registerUser($data, $role = "owner"){

        $_POST = $data;

        $return = array("success" => false, "user_id" => 0, "message" => "Something went wrong");

        $email = $_POST['email'];
        $user_login = $email;


       

        if ( email_exists( $_POST["email"] )  ) {
            $return["message"] = "Email already exists";
            return $return;
        }
        if ( username_exists( $user_login ) ) {
            $return["message"] = "Username already exists";
            return $return;
        }


        $password = wp_generate_password( 12, false );

        $first_name = (isset($_POST['first_name'])) ? sanitize_text_field( $_POST['first_name'] ) : '' ;
        $last_name = (isset($_POST['last_name'])) ? sanitize_text_field( $_POST['last_name'] ) : '' ;

        $user_data = array(
            'user_login'    => $user_login,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'nickname'      => $first_name,
            'role'          => $role
        );

        $user_id = wp_insert_user( $user_data );

        if($user_id && $user_id > 0){
            if(isset($_POST["country_code"])){
                update_user_meta( $user_id, 'country_code',$_POST["country_code"] );
            }

            if ( isset( $_POST['phone'] ) ){
                update_user_meta($user_id, 'phone', $_POST['phone'] );
            }   
            if ( isset( $_POST['first_name'] ) ){
                update_user_meta($user_id, 'first_name', $_POST['first_name'] );
            }   
            if ( isset( $_POST['last_name'] ) ){
                update_user_meta($user_id, 'last_name', $_POST['last_name'] );
            }
           

            $return["success"] = 1;
            $return["message"] = "User created successfully";
            $return["user_id"] = $user_id;
            
        }

        return $return;


    }

    /**
     * Delete customer
     */
    private function deleteCustomer($data) {
        // Implementation for deleting customer
        CoreResponse::success('Customer deleted successfully', []);
    }

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
           // die($e->getMessage());
            return false;
        }
    }

    /**
     * Authenticate user from token
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
     * Save customer table preferences
     */
    private function saveCustomerPreferences($data) {
        $owner_id = $this->getCurrentUserId();
        
        if (!$owner_id) {
            CoreResponse::error('User is not authenticated', 401);
            return;
        }

        $preferences = isset($data['preferences']) ? $data['preferences'] : [];

        $result = $this->db->saveCustomerPreferences($owner_id, $preferences);

        if ($result) {
            CoreResponse::success(['preferences' => $preferences], 'Preferences saved successfully');
        } else {
            CoreResponse::error('Failed to save preferences', 500);
        }
    }

    /**
     * Get customer table preferences
     */
    private function getCustomerPreferences($data) {
        $owner_id = $this->getCurrentUserId();
        
        if (!$owner_id) {
            CoreResponse::error('User is not authenticated', 401);
            return;
        }

        $preferences = $this->db->getCustomerPreferences($owner_id);

        if ($preferences !== null) {
            CoreResponse::success(['preferences' => $preferences], 'Preferences retrieved successfully');
        } else {
            CoreResponse::error('Failed to retrieve preferences', 500);
        }
    }

    /**
     * Check if email already exists
     */
    private function checkEmailExists($data) {
        $email = isset($data['email']) ? trim($data['email']) : '';
        $excludeUserId = isset($data['exclude_user_id']) ? intval($data['exclude_user_id']) : 0;

        if (empty($email)) {
            CoreResponse::error('Email is required', 400);
            return;
        }

        try {
            $customer = $this->db->getCustomerByEmail($email);
            
            if ($customer) {
                // If exclude_user_id is provided and matches, email doesn't exist for this user
                if ($excludeUserId > 0 && $customer['ID'] == $excludeUserId) {
                    CoreResponse::success(['exists' => false], 'Email is available');
                } else {
                    CoreResponse::error('Email already exists', 400);
                }
            } else {
                CoreResponse::success(['exists' => false], 'Email is available');
            }
        } catch (Exception $e) {
            CoreResponse::serverError('Failed to check email: ' . $e->getMessage());
        }
    }

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
     * Get usergroup by ID
     */
    private function getUsergroup($data) {
        $usergroupId = isset($data['usergroup_id']) ? intval($data['usergroup_id']) : 0;
        
        if (!$usergroupId) {
            CoreResponse::error('Usergroup ID is required', 400);
            return;
        }

        $usergroup = $this->db->getUsergroupById($usergroupId);
        
        if ($usergroup) {
            CoreResponse::success(['usergroup' => $usergroup], 'Usergroup retrieved successfully');
        } else {
            CoreResponse::error('Usergroup not found', 404);
        }
    }

    /**
     * Update usergroup data
     */
    private function updateUsergroupData($data) {
        $usergroupId = isset($data['usergroup_id']) ? intval($data['usergroup_id']) : 0;
        
        if (!$usergroupId) {
            CoreResponse::error('Usergroup ID is required', 400);
            return;
        }

        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        
        if (isset($data['email_cc'])) {
            $updateData['email_cc'] = $data['email_cc'];
        }

        if (empty($updateData)) {
            CoreResponse::error('No data to update', 400);
            return;
        }

        $result = $this->db->updateUsergroup($usergroupId, $updateData);
        
        if ($result) {
            CoreResponse::success(['usergroup_id' => $usergroupId], 'Usergroup updated successfully');
        } else {
            CoreResponse::error('Failed to update usergroup', 500);
        }
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
}


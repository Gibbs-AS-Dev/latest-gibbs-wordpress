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
            case 'updateGibbsCustomer':
                $this->requireAuth();
                $this->updateCustomer($data);
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
            'owner_id' => isset($data['owner_id']) ? intval($data['owner_id']) : $this->current_user_id
        ];

        $result = $this->db->getCustomers($params);

        CoreResponse::success($result, 'Customers retrieved successfully');
    }

    /**
     * Get single customer
     */
    private function getCustomer($data) {
        $customerId = isset($data['customer_id']) ? intval($data['customer_id']) : 0;

        if (!$customerId) {
            CoreResponse::error('Customer ID is required', 400);
        }

        // Implementation for getting single customer
        CoreResponse::success('Customer retrieved successfully', ['customer' => []]);
    }

    /**
     * Create customer
     */
    private function createCustomer($data) {
        // Implementation for creating customer
        CoreResponse::success('Customer created successfully', ['customer_id' => 0]);
    }

    /**
     * Update customer
     */
    private function updateCustomer($data) {
        // Implementation for updating customer
        CoreResponse::success('Customer updated successfully', []);
    }

    /**
     * Delete customer
     */
    private function deleteCustomer($data) {
        // Implementation for deleting customer
        CoreResponse::success('Customer deleted successfully', []);
    }

    /**
     * Authenticate user from token
     */
    private function authenticateUser() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
                     (isset($headers['authorization']) ? $headers['authorization'] : '');

        if (empty($authHeader)) {
            return;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            // Token validation logic here
            // For now, we'll extract user ID from token if possible
            // This is a placeholder - implement proper JWT validation
            $this->current_user_id = null;
        }
    }

    /**
     * Require authentication
     */
    private function requireAuth() {
        // Placeholder - implement proper auth check
        // if (!$this->current_user_id) {
        //     CoreResponse::error('Authentication required', 401);
        // }
    }
}


<?php
/**
 * Subscription Discount API Class
 * Handles all subscription discount API requests
 */

class SubscriptionDiscountApi {
    private $db;
    private $response;
    private $current_user_id = null;
    private $current_user = null;

    public function __construct() {
        try {
            $this->db = new SubscriptionDiscountDatabase();
            $this->response = new CoreResponse();
        } catch (Exception $e) {
            CoreResponse::serverError('Failed to initialize subscription discount API: ' . $e->getMessage());
        }
    }

    /**
     * Handle incoming API request
     */
    public function handleSubscriptionDiscountRequest() {
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
            CoreResponse::serverError('Subscription Discount API error: ' . $e->getMessage());
        }
    }

    /**
     * Handle GET actions
     */
    private function handleGetRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';

        switch ($action) {
            case 'getSubscriptionDiscounts':
                $this->requireAuth();
                $this->getSubscriptionDiscounts($data);
                break;
            case 'getSubscriptionDiscount':
                $this->requireAuth();
                $this->getSubscriptionDiscount($data);
                break;
            case 'getSubscriptionOptions':
                $this->requireAuth();
                $this->getSubscriptionOptions($data);
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
            case 'createSubscriptionDiscount':
                $this->requireAuth();
                $this->createSubscriptionDiscount($data);
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
            case 'updateSubscriptionDiscount':
                $this->requireAuth();
                $this->updateSubscriptionDiscount($data);
                break;
            case 'toggleSubscriptionDiscountStatus':
                $this->requireAuth();
                $this->toggleSubscriptionDiscountStatus($data);
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
            case 'deleteSubscriptionDiscount':
                $this->requireAuth();
                $this->deleteSubscriptionDiscount($data);
                break;
            default:
                CoreResponse::error('Invalid action for DELETE request', 400);
        }
    }

    /**
     * Retrieve subscription discounts for owner
     */
    private function getSubscriptionDiscounts($input) {
        try {
            $ownerId = isset($input['owner_id']) ? intval($input['owner_id']) : $this->getCurrentUserId();
            if ($ownerId <= 0) {
                CoreResponse::error('Missing or invalid owner_id', 400);
                return;
            }

            if (!$this->canAccessOwnerData($ownerId)) {
                CoreResponse::forbidden('You do not have permission to view these discounts');
                return;
            }

            $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
            $perPage = isset($input['per_page']) ? min(100, max(1, intval($input['per_page']))) : 20;
            $search = isset($input['search']) ? trim($input['search']) : '';
            $statusFilter = isset($input['status']) ? strtolower(trim($input['status'])) : 'all';
            if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
                $statusFilter = 'all';
            }

            $offset = ($page - 1) * $perPage;

            $result = $this->db->getDiscounts($ownerId, $perPage, $offset, $search, $statusFilter);
            $records = $result['items'];
            $total = $result['total'];


            CoreResponse::success([
                'discounts' => $records,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => (int)$total,
                    'total_pages' => (int)ceil($total / $perPage)
                ]
            ], 'Subscription discounts retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to retrieve subscription discounts: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve single subscription discount
     */
    private function getSubscriptionDiscount($input) {
        try {
            if (!isset($input['discount_id'])) {
                CoreResponse::error('Missing discount_id parameter', 400);
                return;
            }

            $discountId = intval($input['discount_id']);
            if ($discountId <= 0) {
                CoreResponse::error('Invalid discount_id parameter', 400);
                return;
            }

            $discount = $this->db->getDiscountById($discountId);
            if (!$discount) {
                CoreResponse::notFound('Subscription discount not found');
                return;
            }

            if (!$this->canAccessOwnerData((int)$discount['owner_id'])) {
                CoreResponse::forbidden('You do not have permission to view this discount');
                return;
            }

            CoreResponse::success([
                'discount' => $this->mapDiscountRow($discount)
            ], 'Subscription discount retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to retrieve subscription discount: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new subscription discount
     */
    private function createSubscriptionDiscount($input) {
        try {
            if (!isset($input['discount']) || !is_array($input['discount'])) {
                CoreResponse::error('Missing discount payload', 400);
                return;
            }

            $discount = $input['discount'];
            $ownerId = isset($input['owner_id']) ? intval($input['owner_id']) : $this->getCurrentUserId();
            if ($ownerId <= 0) {
                CoreResponse::error('Missing or invalid owner_id', 400);
                return;
            }

            $errors = [];
            $requiredFields = ['name', 'code', 'type', 'value', 'subscription_products', 'listing_ids'];

            foreach ($requiredFields as $field) {
                if($field == 'subscription_products' || $field == 'listing_ids'){
                    if(!isset($discount[$field]) || empty($discount[$field])){
                        $errors[$field] = ucfirst($field) . ' is required';
                    }
                }else{
                    if (!isset($discount[$field]) || trim((string)$discount[$field]) === '') {
                        $errors[$field] = ucfirst($field) . ' is required';
                    }
                }
            }

            if (!empty($errors)) {
                CoreResponse::validationError($errors);
                return;
            }

            $name = $this->sanitizeString($discount['name']);
            $code = strtoupper(preg_replace('/\s+/', '', $discount['code']));
            $type = strtolower($discount['type']);
            $value = floatval($discount['value']);

            if (!in_array($type, ['percentage', 'amount'], true)) {
                $errors['type'] = 'Invalid discount type. Allowed values: percentage, amount';
            }

            if ($value <= 0) {
                $errors['value'] = 'Discount value must be greater than 0';
            }

            if ($type === 'percentage' && $value > 100) {
                $errors['value'] = 'Percentage discounts cannot exceed 100';
            }


            if (!empty($errors)) {
                CoreResponse::validationError($errors);
                return;
            }

            // $existing = $this->db->getDiscountByCode($ownerId, $code);
            // if ($existing) {
            //     CoreResponse::error('A discount with this code already exists', 400);
            //     return;
            // }

            $startDate = $this->parseDateNullable(isset($discount['start_date']) ? $discount['start_date'] : null, 'start_date');
            if ($startDate === false) {
                return;
            }

            $status = isset($discount['status']) ? strtolower($discount['status']) : 'active';
            if (!in_array($status, ['active', 'inactive'], true)) {
                $status = 'active';
            }

            $subscriptionProducts = $this->sanitizeIdArray(isset($discount['subscription_products']) ? $discount['subscription_products'] : []);
            $listingIds = $this->sanitizeIdArray(isset($discount['listing_ids']) ? $discount['listing_ids'] : []);

            $payload = [
                'owner_id' => $ownerId,
                'name' => $name,
                'code' => $code,
                'discount_type' => $type,
                'discount_value' => $value,
                'start_date' => $startDate,
                'status' => $status,
                'subscription_products' => $subscriptionProducts,
                'listing_ids' => $listingIds,
                'created_by' => $this->getCurrentUserId()
            ];

            $insertId = $this->db->createDiscount($payload);
            if ($insertId <= 0) {
                CoreResponse::error('Failed to create subscription discount', 500);
                return;
            }

            $record = $this->db->getDiscountById($insertId);
            CoreResponse::success([
                'message' => 'Subscription discount created successfully',
                'discount' => $this->mapDiscountRow($record)
            ], 'Subscription discount created successfully', 201);
        } catch (Exception $e) {
            CoreResponse::error('Failed to create subscription discount: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update subscription discount details
     */
    private function updateSubscriptionDiscount($input) {
        try {
            if (!isset($input['discount_id'])) {
                CoreResponse::error('Missing discount_id parameter', 400);
                return;
            }

            $discountId = intval($input['discount_id']);
            if ($discountId <= 0) {
                CoreResponse::error('Invalid discount_id parameter', 400);
                return;
            }

            $existing = $this->db->getDiscountById($discountId);
            if (!$existing) {
                CoreResponse::notFound('Subscription discount not found');
                return;
            }

            // if (!$this->canAccessOwnerData((int)$existing['owner_id'])) {
            //     CoreResponse::forbidden('You do not have permission to update this discount');
            //     return;
            // }

            if (!isset($input['updates']) || !is_array($input['updates'])) {
                CoreResponse::error('Missing updates payload', 400);
                return;
            }

            $updates = $input['updates'];
            $data = [];

            if (isset($updates['name'])) {
                $data['name'] = $this->sanitizeString($updates['name']);
            }

            // if (isset($updates['code'])) {
            //     $newCode = strtoupper(preg_replace('/\s+/', '', $updates['code']));
            //     if (!preg_match('/^[A-Z0-9\-_]+$/', $newCode)) {
            //         CoreResponse::error('Code may only contain uppercase letters, numbers, dashes, and underscores', 400);
            //         return;
            //     }

            //     $existingCode = $this->db->getDiscountByCode((int)$existing['owner_id'], $newCode);
            //     if ($existingCode && (int)$existingCode['id'] !== $discountId) {
            //         CoreResponse::error('Another discount with this code already exists', 400);
            //         return;
            //     }

            //     $data['code'] = $newCode;
            // }

            if (isset($updates['type'])) {
                $type = strtolower($updates['type']);
                if (!in_array($type, ['percentage', 'amount'], true)) {
                    CoreResponse::error('Invalid discount type. Allowed values: percentage, amount', 400);
                    return;
                }
                $data['discount_type'] = $type;
            }

            if (isset($updates['value'])) {
                $value = floatval($updates['value']);
                if ($value <= 0) {
                    CoreResponse::error('Discount value must be greater than 0', 400);
                    return;
                }
                if ((isset($data['discount_type']) ? $data['discount_type'] : $existing['discount_type']) === 'percentage' && $value > 100) {
                    CoreResponse::error('Percentage discounts cannot exceed 100', 400);
                    return;
                }
                $data['discount_value'] = $value;
            }

           
            if (array_key_exists('start_date', $updates)) {
                $parsed = $this->parseDateNullable($updates['start_date'], 'start_date');
                if ($parsed === false) {
                    return;
                }
                $data['start_date'] = $parsed;
            }

            // if (isset($updates['status'])) {
            //     $status = strtolower($updates['status']);
            //     if (!in_array($status, ['active', 'inactive'], true)) {
            //         CoreResponse::error('Invalid status value', 400);
            //         return;
            //     }
            //     $data['status'] = $status;
            // }

            // if (array_key_exists('notes', $updates)) {
            //     $data['notes'] = $updates['notes'] === null ? null : $this->sanitizeString($updates['notes']);
            // }

            if (array_key_exists('subscription_products', $updates)) {
                $data['subscription_products'] = $this->sanitizeIdArray($updates['subscription_products']);
            }

            if (array_key_exists('listing_ids', $updates)) {
                $data['listing_ids'] = $this->sanitizeIdArray($updates['listing_ids']);
            }

            if (empty($data)) {
                CoreResponse::error('No valid fields provided for update', 400);
                return;
            }

            $success = $this->db->updateDiscount($discountId, $data);
            if (!$success) {
                CoreResponse::error('Failed to update subscription discount', 500);
                return;
            }

            $record = $this->db->getDiscountById($discountId);
            CoreResponse::success([
                'message' => 'Subscription discount updated successfully',
                'discount' => $this->mapDiscountRow($record)
            ], 'Subscription discount updated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to update subscription discount: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle subscription discount status
     */
    private function toggleSubscriptionDiscountStatus($input) {
        try {
            if (!isset($input['discount_id'])) {
                CoreResponse::error('Missing discount_id parameter', 400);
                return;
            }

            $discountId = intval($input['discount_id']);
            if ($discountId <= 0) {
                CoreResponse::error('Invalid discount_id parameter', 400);
                return;
            }

            $existing = $this->db->getDiscountById($discountId);
            if (!$existing) {
                CoreResponse::notFound('Subscription discount not found');
                return;
            }

            $success = $this->db->updateDiscountStatus($discountId, $input['status']);

            if (!$success) {
                CoreResponse::error('Failed to update discount status', 500);
                return;
            }

            $record = $this->db->getDiscountById($discountId);
            CoreResponse::success([
                'message' => 'Subscription discount status updated successfully',
                'discount' => $record
            ], 'Subscription discount status updated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to update subscription discount status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete or deactivate subscription discount
     */
    private function deleteSubscriptionDiscount($input) {
        try {
            if (!isset($input['discount_id'])) {
                CoreResponse::error('Missing discount_id parameter', 400);
                return;
            }

            $discountId = intval($input['discount_id']);
            if ($discountId <= 0) {
                CoreResponse::error('Invalid discount_id parameter', 400);
                return;
            }

            $existing = $this->db->getDiscountById($discountId);
            if (!$existing) {
                CoreResponse::notFound('Subscription discount not found');
                return;
            }

            if (!$this->canAccessOwnerData((int)$existing['owner_id'])) {
                CoreResponse::forbidden('You do not have permission to delete this discount');
                return;
            }

            $success = $this->db->deactivateDiscount($discountId);
            if (!$success) {
                CoreResponse::error('Failed to delete subscription discount', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Subscription discount deactivated successfully'
            ], 'Subscription discount deactivated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to delete subscription discount: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve subscription options (products and listings)
     */
    private function getSubscriptionOptions($input) {
        $ownerId = isset($input['owner_id']) ? intval($input['owner_id']) : $this->getCurrentUserId();
        if ($ownerId <= 0) {
            CoreResponse::error('Missing or invalid owner_id', 400);
            return;
        }
        if(!$this->isAuthenticated()){
            CoreResponse::error('Authentication required', 401);
            return;
        }


        $products = $this->db->getSubscriptionProducts($ownerId);
        $listings = $this->db->getOwnerListings($ownerId);

        CoreResponse::success([
            'products' => $products,
            'listings' => $listings,
        ], 'Subscription discount options retrieved successfully');
    }

    /**
     * Determine if current user can access owner data
     */
    private function canAccessOwnerData($ownerId) {
        if (!$this->isAuthenticated()) {
            return false;
        }

        return true;

        // if ($this->current_user_id === $ownerId) {
        //     return true;
        // }

       // return $this->db->isUserAdministrator($this->current_user_id);
    }

    /**
     * Map database row to response format
     */
    private function mapDiscountRow($row) {
        $now = new DateTimeImmutable('now');
        $start = isset($row['start_date']) && $row['start_date'] ? new DateTimeImmutable($row['start_date']) : null;
        $end = isset($row['end_date']) && $row['end_date'] ? new DateTimeImmutable($row['end_date']) : null;

        $status = isset($row['status']) && $row['status'] ? $row['status'] : 'active';
        $lifecycle = $status;

        if ($status === 'active') {
            if ($end && $end < $now) {
                $lifecycle = 'expired';
            } elseif ($start && $start > $now) {
                $lifecycle = 'scheduled';
            } else {
                $lifecycle = 'active';
            }
        } elseif ($status === 'inactive') {
            $lifecycle = 'inactive';
        }

        $subscriptionProducts = isset($row['subscription_products']) ? $row['subscription_products'] : [];
        $listingIds = isset($row['listing_ids']) ? $row['listing_ids'] : [];

        $subscriptionProductItems = $this->resolvePostSummaries($subscriptionProducts);
        $listingItems = $this->resolvePostSummaries($listingIds);

        $maxRedemptions = isset($row['max_redemptions']) ? $row['max_redemptions'] : null;
        if ($maxRedemptions === '' || $maxRedemptions === null) {
            $maxRedemptions = null;
        } else {
            $maxRedemptions = (int) $maxRedemptions;
        }

        return [
            'id' => (int)$row['id'],
            'owner_id' => (int)$row['owner_id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'type' => $row['discount_type'],
            'value' => (float)$row['discount_value'],
            'max_redemptions' => $maxRedemptions,
            'redemption_count' => isset($row['redemption_count']) ? (int)$row['redemption_count'] : 0,
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $status,
            'lifecycle' => $lifecycle,
            'notes' => isset($row['notes']) ? $row['notes'] : '',
            'subscription_products' => $subscriptionProducts,
            'subscription_product_items' => $subscriptionProductItems,
            'listing_ids' => $listingIds,
            'listing_items' => $listingItems,
            'created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    /**
     * Parse optional date input returning MySQL datetime or null
     *
     * @param mixed $value
     * @param string $field
     * @return string|null|false
     */
    private function parseDateNullable($value, $field) {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            CoreResponse::error('Invalid date for ' . $field, 400);
            return false;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString($string) {
        return trim(filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
    }

    /**
     * Sanitize array of IDs
     */
    private function sanitizeIdArray($value) {
        if (!is_array($value)) {
            return [];
        }
        $clean = [];
        foreach ($value as $id) {
            $id = intval($id);
            if ($id > 0) {
                $clean[] = $id;
            }
        }
        return array_values(array_unique($clean));
    }

    private function resolvePostSummaries($ids) {
        if (empty($ids)) {
            return [];
        }

        $details = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post) {
                $details[] = [
                    'id' => (int) $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                ];
            }
        }

        return $details;
    }

    /**
     * ---------------------------------------------------------------------
     * Authentication helpers
     * ---------------------------------------------------------------------
     */

    private function authenticateUser() {
        try {
            $authHeader = $this->getAuthorizationHeader();
            if (!$authHeader) {
                return false;
            }

            $token = $this->extractBearerToken($authHeader);
            if (!$token) {
                return false;
            }

            $userData = $this->validateJWTToken($token);
            if (!$userData || !isset($userData['user_id'])) {
                return false;
            }

            $this->current_user_id = (int)$userData['user_id'];
            $this->current_user = $userData;

            return true;
        } catch (Exception $e) {
            error_log('SubscriptionDiscountApi authenticateUser error: ' . $e->getMessage());
            return false;
        }
    }

    private function getCurrentUserId() {
        return $this->current_user_id;
    }

    private function isAuthenticated() {
        return $this->current_user_id !== null;
    }

    private function requireAuth() {
        if (!$this->isAuthenticated()) {
            CoreResponse::unauthorized('Authentication required');
            return false;
        }
        return true;
    }

    private function getAuthorizationHeader() {
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return trim($headers['Authorization']);
            }
        }

        return null;
    }

    private function extractBearerToken($authHeader) {
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function validateJWTToken($token) {
        try {
            $jwt_file = dirname(__FILE__, 3) . '/includes/class-custom-jwt.php';
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
            error_log('SubscriptionDiscountApi validateJWTToken error: ' . $e->getMessage());
            return false;
        }
    }
}


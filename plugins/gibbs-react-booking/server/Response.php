<?php
/**
 * Core PHP Response Handler
 * Handles standardized API responses without WordPress dependencies
 */

class CoreResponse {
    
    /**
     * Send success response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        $response = array(
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $statusCode
        );
        
        self::sendResponse($response, $statusCode);
    }
    
    /**
     * Send error response
     */
    public static function error($message = 'Error occurred', $statusCode = 400, $errors = null) {
        $response = array(
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $statusCode
        );
        
        self::sendResponse($response, $statusCode);
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 422, $errors);
    }
    
    /**
     * Send server error response
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
    
    /**
     * Send the actual response
     */
    private static function sendResponse($data, $statusCode) {
        // Set HTTP status code
        http_response_code($statusCode);
        
        // Set headers
        header('Content-Type: application/json');
       // header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
        
        // Output JSON response
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequiredFields($data, $requiredFields) {
        $errors = array();
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = self::sanitizeString($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize string input
     */
    private static function sanitizeString($string) {
        // Remove HTML tags
        $string = strip_tags($string);
        
        // Convert special characters to HTML entities
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        
        // Remove null bytes
        $string = str_replace(chr(0), '', $string);
        
        return trim($string);
    }
    
    /**
     * Get request method
     */
    public static function getRequestMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }
    
    /**
     * Get request data
     */
    public static function getRequestData() {
        $method = self::getRequestMethod();
        
        switch ($method) {
            case 'GET':
                return $_GET;
            case 'POST':
                $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
                
                if (strpos($contentType, 'application/json') !== false) {
                    $jsonData = file_get_contents('php://input');
                    return json_decode($jsonData, true) ?: array();
                } else {
                    return $_POST;
                }
            case 'PUT':
            case 'DELETE':
                $jsonData = file_get_contents('php://input');
                return json_decode($jsonData, true) ?: array();
            default:
                return array();
        }
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get pagination parameters
     */
    public static function getPaginationParams($defaultLimit = 10, $maxLimit = 100) {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min($maxLimit, max(1, intval($_GET['limit']))) : $defaultLimit;
        $offset = ($page - 1) * $limit;
        
        return array(
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        );
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate integer
     */
    public static function validateInteger($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * Validate URL
     */
    public static function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIp() {
        $ipKeys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get user agent
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Check if request is from mobile
     */
    public static function isMobile() {
        $userAgent = self::getUserAgent();
        return preg_match('/(android|iphone|ipad|mobile)/i', $userAgent);
    }
} 
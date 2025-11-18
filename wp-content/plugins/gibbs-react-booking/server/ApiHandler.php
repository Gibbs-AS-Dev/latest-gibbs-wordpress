<?php
/**
 * Core PHP API Handler
 * Main class that handles all API requests without WordPress dependencies
 */

class CoreApiHandler {
    
    protected $database;
    
    public function __construct() {
        $this->database = new CoreDatabase();
    }
    
    /**
     * Get database instance
     */
    protected function getDatabase() {
        return $this->database;
    }
    
    /**
     * Handle incoming requests
     */
    public function handleRequest() {
        try {
            $method = CoreResponse::getRequestMethod();
            $data = CoreResponse::getRequestData();
            
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
            CoreResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGetRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'get_data';
        
        switch ($action) {
            case 'get_data':
                $this->getData($data);
                break;
            case 'get_data_by_id':
                $this->getDataById($data);
                break;
            case 'search_data':
                $this->searchData($data);
                break;
            case 'get_users':
                $this->getUsers($data);
                break;
            case 'get_posts':
                $this->getPosts($data);
                break;
            case 'get_data_count':
                $this->getDataCount();
                break;
            case 'test_connection':
                $this->testConnection();
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    
    /**
     * Handle POST requests
     */
    private function handlePostRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'create_data';
        
        switch ($action) {
            case 'create_data':
                $this->createData($data);
                break;
            case 'create_table':
                $this->createTable();
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    
    /**
     * Handle PUT requests
     */
    private function handlePutRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'update_data';
        
        switch ($action) {
            case 'update_data':
                $this->updateData($data);
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    
    /**
     * Handle DELETE requests
     */
    private function handleDeleteRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'delete_data';
        
        switch ($action) {
            case 'delete_data':
                $this->deleteData($data);
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $count = $this->database->getDataCount();
            CoreResponse::success(array(
                'connection' => 'success',
                'total_records' => $count,
                'timestamp' => date('Y-m-d H:i:s')
            ), 'Database connection successful');
        } catch (Exception $e) {
            CoreResponse::error('Database connection failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create database table
     */
    public function createTable() {
        try {
            $this->database->createTables();
            CoreResponse::success(null, 'Database table created successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to create table: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get data with pagination
     */
    public function getData($request = null) {
        $pagination = CoreResponse::getPaginationParams();
        $data = $this->database->getAllData($pagination['limit'], $pagination['offset']);
        $totalCount = $this->database->getDataCount();
        
        $responseData = array(
            'data' => $data,
            'pagination' => array(
                'page' => $pagination['page'],
                'limit' => $pagination['limit'],
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $pagination['limit'])
            )
        );
        
        CoreResponse::success($responseData, 'Data retrieved successfully');
    }
    
    /**
     * Get data by ID
     */
    public function getDataById($data) {
        if (!isset($data['id'])) {
            CoreResponse::validationError(array('id' => 'ID is required'));
        }
        
        $id = intval($data['id']);
        $item = $this->database->getDataById($id);
        
        if (!$item) {
            CoreResponse::notFound('Data not found');
        }
        
        CoreResponse::success($item, 'Data retrieved successfully');
    }
    
    /**
     * Create new data
     */
    public function createData($data) {
        $requiredFields = array('title', 'content');
        $errors = CoreResponse::validateRequiredFields($data, $requiredFields);
        
        if (!empty($errors)) {
            CoreResponse::validationError($errors);
        }
        
        $sanitizedData = CoreResponse::sanitizeInput($data);
        $sanitizedData['status'] = isset($data['status']) ? $data['status'] : 'active';
        
        $insertId = $this->database->insertData($sanitizedData);
        
        if ($insertId) {
            $newData = $this->database->getDataById($insertId);
            CoreResponse::success($newData, 'Data created successfully', 201);
        } else {
            CoreResponse::serverError('Failed to create data');
        }
    }
    
    /**
     * Update existing data
     */
    public function updateData($data) {
        if (!isset($data['id'])) {
            CoreResponse::validationError(array('id' => 'ID is required'));
        }
        
        $id = intval($data['id']);
        $existingData = $this->database->getDataById($id);
        
        if (!$existingData) {
            CoreResponse::notFound('Data not found');
        }
        
        $requiredFields = array('title', 'content');
        $errors = CoreResponse::validateRequiredFields($data, $requiredFields);
        
        if (!empty($errors)) {
            CoreResponse::validationError($errors);
        }
        
        $sanitizedData = CoreResponse::sanitizeInput($data);
        $sanitizedData['status'] = isset($data['status']) ? $data['status'] : 'active';
        
        $success = $this->database->updateData($id, $sanitizedData);
        
        if ($success) {
            $updatedData = $this->database->getDataById($id);
            CoreResponse::success($updatedData, 'Data updated successfully');
        } else {
            CoreResponse::serverError('Failed to update data');
        }
    }
    
    /**
     * Delete data
     */
    public function deleteData($data) {
        if (!isset($data['id'])) {
            CoreResponse::validationError(array('id' => 'ID is required'));
        }
        
        $id = intval($data['id']);
        $existingData = $this->database->getDataById($id);
        
        if (!$existingData) {
            CoreResponse::notFound('Data not found');
        }
        
        $success = $this->database->deleteData($id);
        
        if ($success) {
            CoreResponse::success(null, 'Data deleted successfully');
        } else {
            CoreResponse::serverError('Failed to delete data');
        }
    }
    
    /**
     * Search data
     */
    public function searchData($data) {
        if (!isset($data['search']) || empty(trim($data['search']))) {
            CoreResponse::validationError(array('search' => 'Search term is required'));
        }
        
        $searchTerm = CoreResponse::sanitizeString($data['search']);
        $limit = isset($data['limit']) ? min(100, max(1, intval($data['limit']))) : 10;
        
        $results = $this->database->searchData($searchTerm, $limit);
        
        CoreResponse::success($results, 'Search completed successfully');
    }
    
    /**
     * Get WordPress users
     */
    public function getUsers($data) {
        $limit = isset($data['limit']) ? min(100, max(1, intval($data['limit']))) : 10;
        $users = $this->database->getUsers($limit);
        
        CoreResponse::success($users, 'Users retrieved successfully');
    }
    
    /**
     * Get WordPress posts
     */
    public function getPosts($data) {
        $limit = isset($data['limit']) ? min(100, max(1, intval($data['limit']))) : 10;
        $posts = $this->database->getPosts($limit);
        
        CoreResponse::success($posts, 'Posts retrieved successfully');
    }
    
    /**
     * Get data count
     */
    public function getDataCount() {
        $count = $this->database->getDataCount();
        
        CoreResponse::success(array('count' => $count), 'Count retrieved successfully');
    }
    
    /**
     * Get server information
     */
    public function getServerInfo() {
        $info = array(
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'request_method' => CoreResponse::getRequestMethod(),
            'client_ip' => CoreResponse::getClientIp(),
            'user_agent' => CoreResponse::getUserAgent(),
            'is_mobile' => CoreResponse::isMobile(),
            'timestamp' => date('Y-m-d H:i:s')
        );
        
        CoreResponse::success($info, 'Server information retrieved');
    }
} 
<?php
/**
 * Test file for Core PHP API Server
 * This file demonstrates how to use the API
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load required classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/ApiHandler.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Core PHP API Server Test</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo "h1, h2, h3 { color: #333; }";
echo "pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }";
echo ".success { color: green; }";
echo ".error { color: red; }";
echo ".info { color: blue; }";
echo "</style>";
echo "</head><body>";

echo "<h1>Core PHP API Server Test</h1>";

// Test database connection
echo "<h2>Testing Database Connection</h2>";
try {
    $database = new CoreDatabase();
    $count = $database->getDataCount();
    echo "<p class='success'>✅ Database connection successful. Total records: " . $count . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test creating sample data
echo "<h2>Testing Data Creation</h2>";
try {
    $apiHandler = new CoreApiHandler();
    
    // Create sample data
    $sampleData = array(
        'action' => 'create_data',
        'title' => 'Test Entry ' . date('Y-m-d H:i:s'),
        'content' => 'This is a test entry created at ' . date('Y-m-d H:i:s'),
        'status' => 'active'
    );
    
    echo "<p class='info'>Creating sample data...</p>";
    // Note: In a real scenario, you would make an HTTP request
    // For testing, we'll call the method directly
    $apiHandler->createData($sampleData);
    echo "<p class='success'>✅ Sample data created successfully</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Data creation failed: " . $e->getMessage() . "</p>";
}

echo "<h2>API Usage Examples</h2>";

echo "<h3>Direct API Endpoint</h3>";
echo "<p class='info'>URL: " . $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/api-endpoint.php</p>";

echo "<h2>CURL Examples</h2>";

$baseUrl = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/api-endpoint.php";

echo "<h3>Test Connection</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=test_connection'";
echo "</pre>";

echo "<h3>Create Table</h3>";
echo "<pre>";
echo "curl -X POST '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"create_table\"\n";
echo "  }'";
echo "</pre>";

echo "<h3>Get All Data</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_data&page=1&limit=10'";
echo "</pre>";

echo "<h3>Create New Data</h3>";
echo "<pre>";
echo "curl -X POST '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"create_data\",\n";
echo "    \"title\": \"Test Title\",\n";
echo "    \"content\": \"Test Content\"\n";
echo "  }'";
echo "</pre>";

echo "<h3>Update Data</h3>";
echo "<pre>";
echo "curl -X PUT '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"update_data\",\n";
echo "    \"id\": 1,\n";
echo "    \"title\": \"Updated Title\",\n";
echo "    \"content\": \"Updated Content\"\n";
echo "  }'";
echo "</pre>";

echo "<h3>Delete Data</h3>";
echo "<pre>";
echo "curl -X DELETE '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"delete_data\",\n";
echo "    \"id\": 1\n";
echo "  }'";
echo "</pre>";

echo "<h3>Search Data</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=search_data&search=test&limit=5'";
echo "</pre>";

echo "<h3>Get WordPress Users</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_users&limit=10'";
echo "</pre>";

echo "<h3>Get WordPress Posts</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_posts&limit=10'";
echo "</pre>";

echo "<h3>Get Data Count</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_data_count'";
echo "</pre>";

echo "<h2>JavaScript Examples</h2>";

echo "<h3>Fetch API Example</h3>";
echo "<pre>";
echo "// Test connection\n";
echo "fetch('{$baseUrl}?action=test_connection')\n";
echo "  .then(response => response.json())\n";
echo "  .then(data => console.log(data));\n\n";

echo "// Get all data\n";
echo "fetch('{$baseUrl}?action=get_data')\n";
echo "  .then(response => response.json())\n";
echo "  .then(data => console.log(data));\n\n";

echo "// Create new data\n";
echo "fetch('{$baseUrl}', {\n";
echo "  method: 'POST',\n";
echo "  headers: {\n";
echo "    'Content-Type': 'application/json',\n";
echo "  },\n";
echo "  body: JSON.stringify({\n";
echo "    action: 'create_data',\n";
echo "    title: 'New Title',\n";
echo "    content: 'New Content'\n";
echo "  })\n";
echo "})\n";
echo "  .then(response => response.json())\n";
echo "  .then(data => console.log(data));";
echo "</pre>";

echo "<h2>Available Actions</h2>";
echo "<ul>";
echo "<li><strong>GET actions:</strong> get_data, get_data_by_id, search_data, get_users, get_posts, get_data_count, test_connection</li>";
echo "<li><strong>POST actions:</strong> create_data, create_table</li>";
echo "<li><strong>PUT actions:</strong> update_data</li>";
echo "<li><strong>DELETE actions:</strong> delete_data</li>";
echo "</ul>";

echo "<h2>Response Format</h2>";
echo "<p>All API responses follow this format:</p>";
echo "<pre>";
echo "{\n";
echo "  \"success\": true/false,\n";
echo "  \"message\": \"Response message\",\n";
echo "  \"data\": {...},\n";
echo "  \"timestamp\": \"2024-01-01 12:00:00\",\n";
echo "  \"status_code\": 200\n";
echo "}";
echo "</pre>";

echo "<h2>Database Schema</h2>";
echo "<p>The API creates a custom table <code>wp_gibbs_api_data</code> with the following structure:</p>";
echo "<pre>";
echo "CREATE TABLE wp_gibbs_api_data (\n";
echo "    id mediumint(9) NOT NULL AUTO_INCREMENT,\n";
echo "    title varchar(255) NOT NULL,\n";
echo "    content text NOT NULL,\n";
echo "    status varchar(50) DEFAULT 'active',\n";
echo "    created_at datetime DEFAULT CURRENT_TIMESTAMP,\n";
echo "    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
echo "    PRIMARY KEY (id)\n";
echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
echo "</pre>";

echo "<h2>Features</h2>";
echo "<ul>";
echo "<li><strong>Core PHP:</strong> No WordPress dependencies, pure PHP implementation</li>";
echo "<li><strong>Database Integration:</strong> Connects to WordPress database using PDO</li>";
echo "<li><strong>CRUD Operations:</strong> Create, Read, Update, Delete functionality</li>";
echo "<li><strong>Search:</strong> Full-text search across data</li>";
echo "<li><strong>Pagination:</strong> Built-in pagination support</li>";
echo "<li><strong>Input Validation:</strong> Comprehensive input sanitization</li>";
echo "<li><strong>Error Handling:</strong> Standardized error responses</li>";
echo "<li><strong>CORS Support:</strong> Cross-origin resource sharing enabled</li>";
echo "<li><strong>WordPress Integration:</strong> Access to WordPress users and posts tables</li>";
echo "</ul>";

echo "<h2>Security Features</h2>";
echo "<ul>";
echo "<li><strong>Input Sanitization:</strong> All inputs are sanitized</li>";
echo "<li><strong>SQL Injection Prevention:</strong> Uses prepared statements with PDO</li>";
echo "<li><strong>XSS Protection:</strong> Output is properly escaped</li>";
echo "<li><strong>Error Handling:</strong> Comprehensive exception handling</li>";
echo "</ul>";

echo "</body></html>"; 
<?php
/**
 * Customer API Endpoint
 * Access via: /wp-content/plugins/gibbs-react-booking/server/customer/customer-endpoint.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set('UTC');

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__, 6) . '/');
}

require_once __DIR__ . '/CustomerDatabase.php';
require_once __DIR__ . '/CustomerApi.php';
require_once dirname(__DIR__) . '/Response.php';

try {
    $api = new CustomerApi();
    $api->handleCustomerRequest();
} catch (Exception $e) {
    CoreResponse::serverError($e->getMessage());
}


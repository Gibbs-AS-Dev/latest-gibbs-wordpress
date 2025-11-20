<?php
/**
 * Wallet API Endpoint
 * Dedicated endpoint for wallet functionality
 * Access via: /wp-content/plugins/gibbs-react-booking/server/wallet-endpoint.php
 */

// Set error reporting
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

header("Access-Control-Allow-Origin: *"); // or use your frontend domain instead of *
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight (OPTIONS) request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set timezone
date_default_timezone_set('UTC');

if(!defined('ABSPATH')){
    define('ABSPATH', dirname( __FILE__, 5 ).'/');
}

// Load required classes
require_once __DIR__ . '/WalletDatabase.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/WalletApi.php';

// Handle the wallet API request
try {
    $walletApi = new WalletApi();
    $walletApi->handleWalletRequest();
} catch (Exception $e) {
    CoreResponse::serverError($e->getMessage());
} 
<?php
/**
 * Core PHP API Endpoint
 * Main entry point for the API server
 * Access via: /wp-content/plugins/react-modules-plugin/server/api-endpoint.php
 */

// Set error reporting
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Set timezone
date_default_timezone_set('UTC');

// Load required classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/ApiHandler.php';

// Handle the API request
try {
    $apiHandler = new CoreApiHandler();
    $apiHandler->handleRequest();
} catch (Exception $e) {
    CoreResponse::serverError($e->getMessage());
} 
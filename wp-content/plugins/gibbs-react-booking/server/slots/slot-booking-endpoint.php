<?php
/**
 * Slot Booking API Endpoint
 * Dedicated endpoint for slot booking functionality
 * Access via: /wp-content/plugins/react-modules-plugin/server/slots/slot-booking-endpoint.php
 */

//Set error reporting
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

//header("Access-Control-Allow-Origin: *"); // or use your frontend domain instead of *
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight (OPTIONS) request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set timezone
date_default_timezone_set('UTC');

// Load required classes
require_once __DIR__ . '/Database.php';
require_once  '../Response.php';
require_once __DIR__ . '/SlotBookingApi.php';

// Handle the slot booking API request
try {
    $slotBookingApi = new SlotBookingApi();
    $slotBookingApi->handleSlotBookingRequest();
} catch (Exception $e) {
    CoreResponse::serverError($e->getMessage());
} 
<?php
/**
 * Test file for Slot Booking API
 * This file demonstrates how to use the slot booking API
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load required classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/SlotBookingApi.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Slot Booking API Test</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo "h1, h2, h3 { color: #333; }";
echo "pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }";
echo ".success { color: green; }";
echo ".error { color: red; }";
echo ".info { color: blue; }";
echo ".warning { color: orange; }";
echo "</style>";
echo "</head><body>";

echo "<h1>Slot Booking API Test</h1>";

// Test database connection and create tables
echo "<h2>Testing Database Connection and Tables</h2>";
try {
    $database = new CoreDatabase();
    $database->createTables(); // Create main table
    $database->createSlotsTable(); // Create slots table
    echo "<p class='success'>✅ Database connection successful and tables created</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test slot booking API
echo "<h2>Testing Slot Booking API</h2>";
try {
    $slotApi = new SlotBookingApi();
    
    // Test getting available dates
    echo "<p class='info'>Testing available dates...</p>";
    $availableDates = $database->getAvailableDates();
    echo "<p class='success'>✅ Available dates: " . count($availableDates) . " dates generated</p>";
    
    // Test getting available times for today
    $today = date('Y-m-d');
    echo "<p class='info'>Testing available times for today ({$today})...</p>";
    $availableTimes = $database->getAvailableTimes($today);
    echo "<p class='success'>✅ Available times for today: " . implode(', ', $availableTimes) . "</p>";
    
    // Test booking a slot
    echo "<p class='info'>Testing slot booking...</p>";
    $bookingData = array(
        'date' => $today,
        'time' => '09:00:00',
        'customerName' => 'Test User',
        'customerEmail' => 'test@example.com',
        'customerPhone' => '+1234567890',
        'notes' => 'Test booking from PHP'
    );
    
    $bookingId = $database->bookSlot($bookingData);
    echo "<p class='success'>✅ Slot booked successfully with ID: {$bookingId}</p>";
    
    // Test getting the booking
    $booking = $database->getBookingById($bookingId);
    echo "<p class='success'>✅ Retrieved booking: " . $booking['customer_name'] . " at " . $booking['date'] . " " . $booking['time'] . "</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Slot booking test failed: " . $e->getMessage() . "</p>";
}

echo "<h2>API Usage Examples</h2>";

echo "<h3>Slot Booking API Endpoint</h3>";
echo "<p class='info'>URL: " . $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/slot-booking-endpoint.php</p>";

echo "<h2>CURL Examples</h2>";

$baseUrl = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/slot-booking-endpoint.php";

echo "<h3>Create Slots Table</h3>";
echo "<pre>";
echo "curl -X POST '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"create_slots_table\"\n";
echo "  }'";
echo "</pre>";

echo "<h3>Get Available Dates</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_available_dates'";
echo "</pre>";

echo "<h3>Get Available Times for a Date</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_available_times&date=" . date('Y-m-d') . "'";
echo "</pre>";

echo "<h3>Book a Slot</h3>";
echo "<pre>";
echo "curl -X POST '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"book_slot\",\n";
echo "    \"date\": \"" . date('Y-m-d') . "\",\n";
echo "    \"time\": \"10:00:00\",\n";
echo "    \"customerName\": \"John Doe\",\n";
echo "    \"customerEmail\": \"john@example.com\",\n";
echo "    \"customerPhone\": \"+1234567890\",\n";
echo "    \"notes\": \"Test booking\"\n";
echo "  }'";
echo "</pre>";

echo "<h3>Get All Bookings</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_bookings&page=1&limit=10'";
echo "</pre>";

echo "<h3>Get Booking by ID</h3>";
echo "<pre>";
echo "curl -X GET '{$baseUrl}?action=get_booking_by_id&id=1'";
echo "</pre>";

echo "<h3>Update Booking</h3>";
echo "<pre>";
echo "curl -X PUT '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"update_booking\",\n";
echo "    \"id\": 1,\n";
echo "    \"customerName\": \"Jane Doe\",\n";
echo "    \"customerEmail\": \"jane@example.com\",\n";
echo "    \"notes\": \"Updated booking\"\n";
echo "  }'";
echo "</pre>";

echo "<h3>Cancel Booking</h3>";
echo "<pre>";
echo "curl -X DELETE '{$baseUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"action\": \"cancel_booking\",\n";
echo "    \"id\": 1\n";
echo "  }'";
echo "</pre>";

echo "<h2>JavaScript Examples</h2>";

echo "<h3>Fetch API Example</h3>";
echo "<pre>";
echo "// Get available dates\n";
echo "fetch('{$baseUrl}?action=get_available_dates')\n";
echo "  .then(response => response.json())\n";
echo "  .then(data => console.log(data));\n\n";

echo "// Get available times\n";
echo "fetch('{$baseUrl}?action=get_available_times&date=" . date('Y-m-d') . "')\n";
echo "  .then(response => response.json())\n";
echo "  .then(data => console.log(data));\n\n";

echo "// Book a slot\n";
echo "fetch('{$baseUrl}', {\n";
echo "  method: 'POST',\n";
echo "  headers: {\n";
echo "    'Content-Type': 'application/json',\n";
echo "  },\n";
echo "  body: JSON.stringify({\n";
echo "    action: 'book_slot',\n";
echo "    date: '" . date('Y-m-d') . "',\n";
echo "    time: '11:00:00',\n";
echo "    customerName: 'John Doe',\n";
echo "    customerEmail: 'john@example.com',\n";
echo "    customerPhone: '+1234567890',\n";
echo "    notes: 'Test booking'\n";
echo "  })\n";
echo "})\n";
echo "  .then(response => response.json())\n";
echo "  .then(data => console.log(data));";
echo "</pre>";

echo "<h2>Available Actions</h2>";
echo "<ul>";
echo "<li><strong>GET actions:</strong> get_available_dates, get_available_times, get_bookings, get_booking_by_id</li>";
echo "<li><strong>POST actions:</strong> book_slot, create_slots_table</li>";
echo "<li><strong>PUT actions:</strong> update_booking</li>";
echo "<li><strong>DELETE actions:</strong> cancel_booking</li>";
echo "</ul>";

echo "<h2>Database Schema</h2>";
echo "<p>The API creates a custom table <code>wp_gibbs_slots</code> with the following structure:</p>";
echo "<pre>";
echo "CREATE TABLE wp_gibbs_slots (\n";
echo "    id mediumint(9) NOT NULL AUTO_INCREMENT,\n";
echo "    date date NOT NULL,\n";
echo "    time time NOT NULL,\n";
echo "    customer_name varchar(255) NOT NULL,\n";
echo "    customer_email varchar(255) NOT NULL,\n";
echo "    customer_phone varchar(50) DEFAULT NULL,\n";
echo "    notes text DEFAULT NULL,\n";
echo "    status varchar(50) DEFAULT 'active',\n";
echo "    created_at datetime DEFAULT CURRENT_TIMESTAMP,\n";
echo "    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
echo "    PRIMARY KEY (id),\n";
echo "    UNIQUE KEY unique_slot (date, time),\n";
echo "    KEY idx_date (date),\n";
echo "    KEY idx_status (status)\n";
echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
echo "</pre>";

echo "<h2>Features</h2>";
echo "<ul>";
echo "<li><strong>Available Dates:</strong> Automatically generates available dates for the next 6 months (weekdays only)</li>";
echo "<li><strong>Available Times:</strong> 9 AM to 5 PM, hourly slots</li>";
echo "<li><strong>Booking Management:</strong> Create, read, update, cancel bookings</li>";
echo "<li><strong>Duplicate Prevention:</strong> Unique constraint on date and time</li>";
echo "<li><strong>Status Tracking:</strong> Active and cancelled booking status</li>";
echo "<li><strong>Customer Information:</strong> Name, email, phone, and notes</li>";
echo "<li><strong>Validation:</strong> Email validation and required field checking</li>";
echo "</ul>";

echo "<h2>Integration with React</h2>";
echo "<p>The SlotBooking.js component has been updated to use this PHP API instead of the Node.js API.</p>";
echo "<p>Key changes:</p>";
echo "<ul>";
echo "<li>Updated API endpoint URLs to use the PHP API</li>";
echo "<li>Added proper error handling and success messages</li>";
echo "<li>Improved date range (current date to 6 months ahead)</li>";
echo "<li>Added booking confirmation feedback</li>";
echo "</ul>";

echo "</body></html>"; 
<?php
/*
Test File for Slot Booking Functionality
This file can be used to test if the slot booking is working correctly
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test if the function exists
function test_slot_booking_function() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Slot Booking Test</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .test-container { max-width: 800px; margin: 0 auto; }
            .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
            .success { background-color: #d4edda; border-color: #c3e6cb; }
            .error { background-color: #f8d7da; border-color: #f5c6cb; }
            .info { background-color: #d1ecf1; border-color: #bee5eb; }
        </style>
    </head>
    <body>
        <div class="test-container">
            <h1>Slot Booking Test Page</h1>
            
            <div class="test-section info">
                <h2>Test Information</h2>
                <p>This page tests the slot booking functionality to ensure everything is working correctly.</p>
                <p><strong>Current Page ID:</strong> <?php echo get_the_ID(); ?></p>
                <p><strong>Plugin URL:</strong> <?php echo RMP_PLUGIN_URL; ?></p>
            </div>
            
            <div class="test-section">
                <h2>Test 1: Basic Slot Booking</h2>
                <p>Testing the basic slot booking shortcode:</p>
                <?php echo do_shortcode('[slot_booking]'); ?>
            </div>
            
            <div class="test-section">
                <h2>Test 2: Slot Booking with Custom ID</h2>
                <p>Testing slot booking with a custom container ID:</p>
                <?php echo do_shortcode('[slot_booking id="test-slot-booking-2"]'); ?>
            </div>
            
            <div class="test-section">
                <h2>Test 3: JavaScript Function Test</h2>
                <p>Testing if the rmpSlotBookingInit function is available:</p>
                <div id="js-test-result">Checking...</div>
                <script>
                    function testRmpSlotBookingInit() {
                        const resultDiv = document.getElementById('js-test-result');
                        if (typeof window.rmpSlotBookingInit === 'function') {
                            resultDiv.innerHTML = '<span style="color: green;">✅ SUCCESS: rmpSlotBookingInit function is available</span>';
                            resultDiv.className = 'success';
                        } else {
                            resultDiv.innerHTML = '<span style="color: red;">❌ ERROR: rmpSlotBookingInit function is NOT available</span>';
                            resultDiv.className = 'error';
                        }
                    }
                    
                    // Test immediately
                    testRmpSlotBookingInit();
                    
                    // Test again after a delay to ensure scripts are loaded
                    setTimeout(testRmpSlotBookingInit, 1000);
                    setTimeout(testRmpSlotBookingInit, 2000);
                </script>
            </div>
            
            <div class="test-section">
                <h2>Test 4: Manual Function Call</h2>
                <p>Testing manual function call:</p>
                <div id="manual-test-container" data-page-id="<?php echo get_the_ID(); ?>"></div>
                <script>
                    setTimeout(function() {
                        if (typeof window.rmpSlotBookingInit === 'function') {
                            try {
                                window.rmpSlotBookingInit('manual-test-container', <?php echo get_the_ID(); ?>);
                                document.getElementById('manual-test-container').innerHTML += '<p style="color: green;">✅ Manual function call successful</p>';
                            } catch (error) {
                                document.getElementById('manual-test-container').innerHTML += '<p style="color: red;">❌ Manual function call failed: ' + error.message + '</p>';
                            }
                        } else {
                            document.getElementById('manual-test-container').innerHTML = '<p style="color: red;">❌ Function not available for manual test</p>';
                        }
                    }, 1000);
                </script>
            </div>
            
            <div class="test-section">
                <h2>Debug Information</h2>
                <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                <p><strong>Plugin Version:</strong> <?php echo RMP_PLUGIN_VERSION; ?></p>
                <p><strong>Current Theme:</strong> <?php echo get_template(); ?></p>
                <p><strong>Scripts Enqueued:</strong></p>
                <ul>
                    <?php
                    global $wp_scripts;
                    if (isset($wp_scripts->queue)) {
                        foreach ($wp_scripts->queue as $handle) {
                            if (strpos($handle, 'rmp') !== false || strpos($handle, 'react') !== false) {
                                echo '<li>' . esc_html($handle) . '</li>';
                            }
                        }
                    }
                    ?>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Only run the test if this file is accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === 'test-slot-booking.php') {
    test_slot_booking_function();
}
?> 
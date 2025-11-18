<?php
/*
Example Usage for React Modules Plugin

This file demonstrates how to use the React Modules Plugin shortcodes
in your WordPress posts, pages, or templates.
*/

// Example 1: Basic Dashboard
// Add this to a post or page:
echo do_shortcode('[react_dashboard]');

// Example 2: Chart with custom ID
// Add this to a post or page:
echo do_shortcode('[react_chart id="my-custom-chart"]');

// Example 3: Contact Form
// Add this to a post or page:
echo do_shortcode('[react_form]');

// Example 4: Generic module usage
// Add this to a post or page:
echo do_shortcode('[react_module module="dashboard"]');
echo do_shortcode('[react_module module="chart"]');
echo do_shortcode('[react_module module="form"]');

// Example 4.1: Slot Booking
// Add this to a post or page:
echo do_shortcode('[slot_booking]');

// Example 4.2: Slot Booking with specific listing ID
// Add this to a post or page:
echo do_shortcode('[slot_booking listing_id="123"]');

// Example 5: Multiple modules on one page
// Add this to a post or page:
echo do_shortcode('[react_dashboard]');
echo do_shortcode('[react_chart]');
echo do_shortcode('[react_form]');
echo do_shortcode('[slot_booking]');

// Example 6: Using in PHP templates
// Add this to your theme's template files:
?>
<div class="my-page-content">
    <h1>Welcome to our Dashboard</h1>
    <?php echo do_shortcode('[react_dashboard]'); ?>
    
    <h2>Our Statistics</h2>
    <?php echo do_shortcode('[react_chart]'); ?>
    
    <h2>Contact Us</h2>
    <?php echo do_shortcode('[react_form]'); ?>
</div>

<?php
// Example 7: Using in WordPress widgets
// You can also use these shortcodes in text widgets or custom HTML widgets

// Example 8: Conditional loading
// Only show dashboard for logged-in users
if (is_user_logged_in()) {
    echo do_shortcode('[react_dashboard]');
}

// Example 9: Custom styling wrapper
?>
<div class="my-custom-wrapper">
    <div class="dashboard-section">
        <?php echo do_shortcode('[react_dashboard]'); ?>
    </div>
    
    <div class="chart-section">
        <?php echo do_shortcode('[react_chart]'); ?>
    </div>
    
    <div class="form-section">
        <?php echo do_shortcode('[react_form]'); ?>
    </div>
</div>

<?php
// Example 10: Using with page builders
// These shortcodes work with most page builders like:
// - Elementor
// - WPBakery Page Builder
// - Beaver Builder
// - Divi Builder
// - Gutenberg blocks

// Simply add the shortcode text in the appropriate shortcode block or widget

// Example 11: API Testing
// Test the REST API endpoints directly:
// GET /wp-json/rmp/v1/data/dashboard
// GET /wp-json/rmp/v1/data/chart
// GET /wp-json/rmp/v1/data/form

// Example 12: Custom CSS for specific instances
?>
<style>
/* Custom styling for specific dashboard instance */
#my-custom-dashboard .rmp-dashboard {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

/* Custom styling for specific chart instance */
#my-custom-chart .rmp-chart {
    border: 3px solid #007cba;
    border-radius: 15px;
}

/* Custom styling for specific form instance */
#my-custom-form .rmp-form {
    background: #f8f9fa;
    border: 2px solid #dee2e6;
}
</style>

<?php
// Then use with custom IDs:
echo do_shortcode('[react_dashboard id="my-custom-dashboard"]');
echo do_shortcode('[react_chart id="my-custom-chart"]');
echo do_shortcode('[react_form id="my-custom-form"]');
?> 
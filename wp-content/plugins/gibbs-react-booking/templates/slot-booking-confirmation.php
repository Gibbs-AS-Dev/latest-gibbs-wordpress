<?php
/**
 * Booking Confirmation Template
 * 
 * This template is used for the booking confirmation page
 * URL: /slot-booking-confirmation/{booking_id}/
 */


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?></title>
    <?php
        $css_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/components.min.css') ? 'components.min.css' : 'components.css';
        $js_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/components.min.js') ? 'components.min.js' : 'components.js';
    ?>
    <link rel="stylesheet" href="<?php echo RMP_PLUGIN_URL . 'assets/css/' . $css_file; ?>?v=<?php echo time(); ?>">
    <script src="<?php echo RMP_PLUGIN_URL . 'react/react.production.min.js'; ?>?v=18.2.0"></script>
    <script src="<?php echo RMP_PLUGIN_URL . 'react/react-dom.production.min.js'; ?>?v=18.2.0"></script>
    <script src="<?php echo RMP_PLUGIN_URL . 'assets/js/' . $js_file; ?>?v=<?php echo time(); ?>"></script>
    <style>
        #wpadminbar{
            display: none;
        }
    </style>
</head>
<body class="react-booking-body">
    <div id="page" class="site">
        <div id="content" class="site-content">
            <main id="main" class="site-main">
            <?php
                $container_id = 'rmp-booking-confirmation-root-'.uniqid();
                $api_url = RMP_PLUGIN_URL . 'server/slots/slot-booking-endpoint.php';
                $plugin_url = RMP_PLUGIN_URL;

                $cr_user_id = "";

                if(is_user_logged_in()){
                    $cr_user_id = get_current_user_id();
                }
                $home_url = home_url();

            ?>    
            <div id="<?php echo $container_id; ?>"></div>
            <script>
                (function() {
                    function initBookingConfirmation() {
                        if (typeof window.rmpBookingConfirmationInit === "function") {
                            // Try to extract booking_id from URL (e.g., /slot-booking-confirmation/{booking_id}/)
                            var match = window.location.pathname.match(/slot-booking-confirmation\/([^\/]+)/);
                            var bookingId = match ? match[1] : null;
                            window.rmpBookingConfirmationInit("<?php echo $container_id; ?>", "<?php echo $plugin_url; ?>", "<?php echo $slot_booking_id; ?>", "<?php echo $api_url; ?>", "<?php echo $cr_user_id; ?>", "<?php echo $home_url; ?>");
                        } else {
                            setTimeout(initBookingConfirmation, 100);
                        }
                    }
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", initBookingConfirmation);
                    } else {
                        initBookingConfirmation();
                    }
                })();
            </script>
            </main>
        </div>
    </div>
</body>
</html>

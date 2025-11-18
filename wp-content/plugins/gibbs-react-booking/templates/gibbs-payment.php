<?php
/**
 * Gibbs Payment Template - Dintero Integration
 * 
 * This template is used for the Dintero payment page
 * URL: /gibbs-payment/{order_id}/
 */


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$version = time();


if(defined('GIBBS_VERSION')){
    $version = GIBBS_VERSION;
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
    <link rel="stylesheet" href="<?php echo RMP_PLUGIN_URL . 'assets/css/' . $css_file; ?>?v=<?php echo $version; ?>">
    <script src="<?php echo RMP_PLUGIN_URL . 'react/react.production.min.js'; ?>?v=18.2.0"></script>
    <script src="<?php echo RMP_PLUGIN_URL . 'react/react-dom.production.min.js'; ?>?v=18.2.0"></script>
    <script src="<?php echo RMP_PLUGIN_URL . 'assets/js/' . $js_file; ?>?v=<?php echo $version; ?>"></script>
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
                $container_id = 'gibbs-dintero-payment-root-'.uniqid();
                $api_url = RMP_PLUGIN_URL . 'server/slots/slot-booking-endpoint.php';
                $plugin_url = RMP_PLUGIN_URL;

                $cr_user_id = "";

                if(is_user_logged_in()){
                    $cr_user_id = get_current_user_id();
                }
                $home_url = home_url();

                $gibbs_payment_id = $_GET['payment_id'];
                $checkout_key = $_GET['checkout_key'];
                $mode = $_GET['mode'];


            ?>    
            <div id="<?php echo $container_id; ?>"></div>
            <script>
                (function() {
                    function initNetsEasyPayment() {
                        if (typeof window.rmpNetsEasyPaymentInit === "function") {
                            var paymentId = "<?php echo $gibbs_payment_id; ?>";
                            var checkoutKey = "<?php echo $checkout_key; ?>";
                            var mode = "<?php echo $mode; ?>";
                            window.rmpNetsEasyPaymentInit("<?php echo $container_id; ?>", "<?php echo $plugin_url; ?>", paymentId, checkoutKey, mode, "<?php echo $api_url; ?>", "<?php echo $cr_user_id; ?>", "<?php echo $home_url; ?>");
                        } else {
                            setTimeout(initNetsEasyPayment, 100);
                        }
                    }
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", initNetsEasyPayment);
                    } else {
                        initNetsEasyPayment();
                    }
                })();
            </script>
            </main>
        </div>
    </div>
</body>
</html>

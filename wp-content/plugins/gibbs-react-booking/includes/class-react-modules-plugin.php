<?php
/**
 * React Modules Plugin Class
 * 
 * @package ReactModulesPlugin
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../includes/class-custom-jwt.php';

class ReactModulesPlugin {

    private $custom_jwt;

    private $version;
    
    public function __construct() {
       
        add_action('init', array($this, 'action_init'));

        // Admin settings hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_oauth_settings'));

        $this->custom_jwt = new Custom_JWT();

        
        
    }

    public function action_init() {

        $this->version = defined('GIBBS_VERSION') ? GIBBS_VERSION : time();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_rmp_get_data', array($this, 'ajax_get_data'));
        add_action('wp_ajax_nopriv_rmp_get_data', array($this, 'ajax_get_data'));
        
        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('theme_page_templates', array($this, 'register_plugin_templates'));
        add_filter('template_include', array($this, 'load_plugin_template'));
        // Register shortcodes
        add_shortcode('react_module', array($this, 'render_react_module'));
        add_shortcode('react_dashboard', array($this, 'render_dashboard'));
        add_shortcode('react_chart', array($this, 'render_chart'));
        add_shortcode('react_form', array($this, 'render_form'));
        add_shortcode('slot_booking', array($this, 'render_slot_booking'));

        add_shortcode('gibbs_wallet', array($this, 'render_wallet'));
        add_shortcode('gibbs_sms_log', array($this, 'render_sms_log'));
        add_shortcode('gibbs_email_log', array($this, 'render_email_log'));
        add_shortcode('gibbs_email_template', array($this, 'render_email_template'));

        add_shortcode('gibbs_modules', array($this, 'render_react_modules'));

        $this->add_custom_rewrite_rules();
        add_filter('query_vars', array($this, 'add_custom_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_urls'));

        add_action( 'woocommerce_admin_order_items_after_line_items', array($this, 'add_custom_admin_order_item'), 99, 1 );

        add_action( 'woocommerce_order_details_after_order_table_items', array($this, 'add_custom_order_details_after_order_table_items'), 99, 1 );
        
        add_action('order_completed_event', array($this, 'order_completed_event'), 10, 1);
        
        $this->register_subscription_discount_cpt();

    }

    public function order_completed_event($order_id) {
        $order = wc_get_order($order_id);
        $order->update_status('completed');
    }

    public function orderItems( $order, $colspan = 4 ) {
        global $wpdb;
    
        $sql = "SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` WHERE `order_id` = " . $order->get_id();
    
        $booking_data = $wpdb->get_row($sql, ARRAY_A);
    
        if(isset($booking_data['id']) && !empty($booking_data['id'])){


           //echo $order_received_url =  $order->get_checkout_order_received_url(); die;
    
            $currency = $order->get_currency();
            $currency_symbol = get_woocommerce_currency_symbol($currency);
    
    
            $sql = "SELECT * FROM `bookings_calendar_meta` WHERE `booking_id` = $booking_data[id] AND `meta_key` = 'booking_confirmation_data'";
    
            $booking_confirmation_data = $wpdb->get_row($sql, ARRAY_A);
    
    
            if(isset($booking_confirmation_data['meta_value']) && !empty($booking_confirmation_data['meta_value'])){
    
                $booking_confirmation_data = json_decode($booking_confirmation_data['meta_value'], true);

                

                $slot_name = esc_html__("Slot price", "gibbs-react-booking");

                if(isset($booking_confirmation_data['price_data']['listing_id']) && !empty($booking_confirmation_data['price_data']['listing_id'])){
                    $listing_id = $booking_confirmation_data['price_data']['listing_id'];
                    $listing_name = get_the_title($listing_id);
                    $slot_name = $listing_name;
                }

                if(isset($booking_confirmation_data['price_data']['slot_label']) && !empty($booking_confirmation_data['price_data']['slot_label'])){
                    $slot_name = $booking_confirmation_data['price_data']['slot_label'];
                }

                if(isset($booking_confirmation_data['price_data']['totalPrice']) && !empty($booking_confirmation_data['price_data']['totalPrice'])){
                    ?>
                    <tr>
                        <td colspan="<?php echo $colspan; ?>" style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                           <?php echo $slot_name;?> (<?php echo $booking_confirmation_data['price_data']['adults'] ?>x)
                        </td>
                        <td style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                            <?php echo $currency_symbol; ?> <?php echo number_format($booking_confirmation_data['price_data']['totalPrice'], 2); ?>
                        </td>
                    </tr>
                    <?php
                }
                
                if(isset($booking_confirmation_data['price_data']) && !empty($booking_confirmation_data['price_data']) && isset($booking_confirmation_data['price_data']['services']) && !empty($booking_confirmation_data['price_data']['services'])){
    
                    foreach($booking_confirmation_data['price_data']['services'] as $service){
                        $service_name = $service['name']." (". $service['countable'] ."x)";
                        $service_price = round($service['price']);
                    ?>
                    <tr>
                        <td colspan="<?php echo $colspan; ?>" style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                            <?php echo esc_html($service_name, "gibbs"); ?>
                        </td>
                        <td style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                            <?php echo $currency_symbol; ?> <?php echo number_format($service_price, 2); ?>
                        </td>
                    </tr>
                    <?php    
                    }
    
                }

                if(isset($booking_confirmation_data['price_data']['tax']) && !empty($booking_confirmation_data['price_data']['tax']) && $booking_confirmation_data['price_data']['tax'] > 0){
                    ?>
                    <tr>
                        <td colspan="<?php echo $colspan; ?>" style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                            <?php echo esc_html__("Total mva", "gibbs"); ?>
                        </td>
                        <td style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                            <?php echo $currency_symbol; ?> <?php echo number_format($booking_confirmation_data['price_data']['tax'], 2); ?>
                        </td>
                    </tr>
                    <?php
                }

                if(isset($booking_confirmation_data['price_data']['coupon']) && !empty($booking_confirmation_data['price_data']['coupon_discount']) && $booking_confirmation_data['price_data']['coupon_discount'] > 0){
                    ?>
                    <tr>
                        <td colspan="<?php echo $colspan; ?>" style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                            <?php echo esc_html__("Coupon Code", "gibbs"); ?>
                        </td>
                        <td style="border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
                        <?php echo $booking_confirmation_data['price_data']['coupon']; ?>
                       <?php //echo $currency_symbol; ?> <?php //echo round(floatval($booking_confirmation_data['price_data']['coupon_discount']), 2); ?>
                        </td>
                    </tr>
                    <?php
                }
    
            }
    
        }
        
    }

    public function add_custom_order_details_after_order_table_items( $order ) {

        $this->orderItems($order, 1);

    }

    public function add_custom_admin_order_item( $order_id ) {

        $order = wc_get_order( $order_id );

        $this->orderItems($order);
    
    }
    public function register_plugin_templates($templates) {
        $templates['page-react-template.php'] = 'React Booking Template';
        $templates['page-react-view.php'] = 'React View Template';
        return $templates;
    }
    
    public function load_plugin_template($template) {
        if (is_page()) {
            $template_slug = get_page_template_slug(get_queried_object_id());
            if ($template_slug === 'page-react-template.php') {
                $plugin_template = RMP_PLUGIN_PATH . 'templates/slot-booking-template.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
            if ($template_slug === 'page-react-view.php') {

                $plugin_template = RMP_PLUGIN_PATH . 'templates/react-view-template.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }
        return $template;
    }

    public function react_header() {

        ob_start();
        require_once RMP_PLUGIN_PATH . 'templates/react-header.php';
        $content = ob_get_clean();
        return $content;
    }
    public function react_footer() {
        ob_start();
        require_once RMP_PLUGIN_PATH . 'templates/react-footer.php';
        $content = ob_get_clean();
        return $content;
    }

    public function enqueue_scripts() {
        // Enqueue React and ReactDOM from node_modules
        wp_enqueue_script('react2', RMP_PLUGIN_URL . 'react/react.production.min.js', array(), '18.2.0', true);
        wp_enqueue_script('react-dom2', RMP_PLUGIN_URL . 'react/react-dom.production.min.js', array('react2'), '18.2.0', true);
        
        // Check if minified files exist, otherwise use regular files
        

      
        
        // Localize script for AJAX
        wp_localize_script('rmp-components', 'rmp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rmp_nonce'),
            'rest_url' => rest_url('rmp/v1/')
        ));
        
        // Add a small inline script to ensure proper initialization
        wp_add_inline_script('rmp-components', '
            // Ensure rmpSlotBookingInit is available globally
            if (typeof window.rmpSlotBookingInit === "undefined") {
                window.rmpSlotBookingInit = function(containerId, pageId) {
                    console.warn("rmpSlotBookingInit called but React components not loaded yet");
                };
            }
        ', 'before');
    }

    public function ajax_get_data() {
        check_ajax_referer('rmp_nonce', 'nonce');
        
        $module = sanitize_text_field($_POST['module']);
        $data = $this->get_module_data_by_type($module);
        
        wp_send_json_success($data);
    }

    public function get_module_data($request) {
        $module = $request->get_param('module');
        $data = $this->get_module_data_by_type($module);
        
        return new WP_REST_Response($data, 200);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Register namespace
        register_rest_route('react-modules/v1', '/getSlotPrice', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_slot_price'),
            'permission_callback' => '__return_true'
        ));

        // Register booking endpoint
        register_rest_route('react-modules/v1', '/book-slot', array(
            'methods' => 'POST',
            'callback' => array($this, 'book_slot'),
            'permission_callback' => '__return_true',
            'args' => array(
                'date' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'time' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'customer_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'customer_email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'customer_phone' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'page_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'notes' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'slot_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Register get available dates endpoint
        register_rest_route('react-modules/v1', '/get-available-dates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_available_dates'),
            'permission_callback' => '__return_true',
            'args' => array(
                'pageId' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Register get customer columns endpoint
        register_rest_route('react-modules/v1', '/customer-columns', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_columns'),
            'permission_callback' => '__return_true',
        ));

        // Register get customer actions endpoint
        register_rest_route('react-modules/v1', '/customer-actions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_actions'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Get slot price REST API endpoint
     */
    public function get_slot_price($request) {
        $listing_id = $request->get_param('pageId');
        $adults = $request->get_param('adults');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $slot_price = $request->get_param('slot_price');
        $slotPriceType = $request->get_param('slotPriceType');

        $action = $request->get_param('action');
    
        // Validate action
        if ($action !== 'get_slot_price') {
            return new WP_Error('invalid_action', 'Invalid action specified', array('status' => 400));
        }

        $multiply = 1;

        if($slotPriceType !== "all_slot_price"){

            $count_per_guest = get_post_meta($listing_id, "_count_per_guest" , true );
            if($count_per_guest){
            $multiply = $adults;
            }

        }

        $totalPrice = $slot_price * $multiply;


        // Here you would implement your slot price logic
        // For now, returning a sample response
        $response_data = array(
            'success' => true,
            'data' => array(
                'totalPrice' => $totalPrice,
            )
        );

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Book slot REST API endpoint
     */
    public function book_slot($request) {
        $date = $request->get_param('date');
        $time = $request->get_param('time');
        $customer_name = $request->get_param('customer_name');
        $customer_email = $request->get_param('customer_email');
        $customer_phone = $request->get_param('customer_phone');
        $page_id = $request->get_param('page_id');
        $notes = $request->get_param('notes');
        $slot_id = $request->get_param('slot_id');

        // Validate required fields
        if (empty($date) || empty($time) || empty($customer_name) || empty($customer_email)) {
            return new WP_Error('missing_fields', 'Required fields are missing', array('status' => 400));
        }

        // Validate email
        if (!is_email($customer_email)) {
            return new WP_Error('invalid_email', 'Invalid email address', array('status' => 400));
        }

        // Here you would implement your booking logic
        // For now, returning a sample response
        $booking_data = array(
            'date' => $date,
            'time' => $time,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'page_id' => $page_id,
            'notes' => $notes,
            'slot_id' => $slot_id,
            'booking_id' => uniqid('booking_'),
            'status' => 'confirmed'
        );

        // You could save this to a custom post type or database table here
        // $this->save_booking($booking_data);

        $response_data = array(
            'success' => true,
            'message' => 'Booking successful',
            'data' => $booking_data
        );

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Get available dates REST API endpoint
     */
    public function get_available_dates($request) {
        $page_id = $request->get_param('pageId');

        // Here you would implement your logic to get available dates
        // For now, returning a sample response
        $response_data = array(
            'success' => true,
            'data' => array(
                'booking_slots' => array(
                    '1|09:00|1|10:00|150|5|slot_1|0|300',
                    '1|10:00|1|11:00|150|5|slot_2|0|300',
                    '2|09:00|2|10:00|150|5|slot_3|0|300',
                ),
                'booking_data' => array(),
                'enable_slot_duration' => 'on',
                'enable_slot_price' => 'on',
                'slot_price_label' => 'Drop in',
                'all_slot_price_label' => 'Privat'
            )
        );

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Get customer columns REST API endpoint
     */
    public function get_customer_columns($request) {
        $columns = Customer_Columns::get_columns_array();
        
        $response_data = array(
            'success' => true,
            'data' => $columns
        );

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Get customer actions REST API endpoint
     */
    public function get_customer_actions($request) {
        $actions = Customer_Actions::get_actions_array();
        
        $response_data = array(
            'success' => true,
            'data' => $actions
        );

        return new WP_REST_Response($response_data, 200);
    }

    private function get_module_data_by_type($module) {
        switch ($module) {
            case 'dashboard':
                return array(
                    'stats' => array(
                        'users' => get_user_count(),
                        'posts' => wp_count_posts()->publish,
                        'comments' => get_comments_number()
                    ),
                    'recent_posts' => get_posts(array('numberposts' => 5))
                );
            
            case 'chart':
                return array(
                    'labels' => array('Jan', 'Feb', 'Mar', 'Apr', 'May'),
                    'data' => array(12, 19, 3, 5, 2)
                );
            
            case 'form':
                return array(
                    'fields' => array(
                        array('name' => 'name', 'label' => 'Name', 'type' => 'text'),
                        array('name' => 'email', 'label' => 'Email', 'type' => 'email'),
                        array('name' => 'message', 'label' => 'Message', 'type' => 'textarea')
                    )
                );
            
            default:
                return array('error' => 'Module not found');
        }
    }

    // Shortcode renderers
    public function render_react_module($atts) {
        $atts = shortcode_atts(array(
            'module' => 'dashboard',
            'id' => 'react-module-' . uniqid()
        ), $atts);
        
        return '<div id="' . esc_attr($atts['id']) . '" data-module="' . esc_attr($atts['module']) . '"></div>';
    }

    public function render_dashboard($atts) {
        return $this->render_react_module(array_merge($atts, array('module' => 'dashboard')));
    }

    public function render_chart($atts) {
        return $this->render_react_module(array_merge($atts, array('module' => 'chart')));
    }

    public function render_form($atts) {
        return $this->render_react_module(array_merge($atts, array('module' => 'form')));
    }

    public function render_slot_booking($atts) {

        $js_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/components.min.js') ? 'components.min.js' : 'components.js';
        $css_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/components.min.css') ? 'components.min.css' : 'components.css';
        
        // Enqueue our built React components
        wp_enqueue_script('rmp-components', RMP_PLUGIN_URL . 'assets/js/' . $js_file, array('react2', 'react-dom2'), $this->version, true);
        // Enqueue our built CSS styles
        wp_enqueue_style('rmp-components', RMP_PLUGIN_URL . 'assets/css/' . $css_file, array(), $this->version);

        if(isset($atts['listing_id'])){
            $page_id = $atts['listing_id'];
        }else{
            $page_id = get_the_ID();
        }

        if(isset($_GET['listing_id'])){
            $page_id = $_GET['listing_id'];
        }

        $atts = shortcode_atts(array(
            'id' => 'rmp-slot-booking-' . uniqid()
        ), $atts);


        $api_url = RMP_PLUGIN_URL . 'server/slots/slot-booking-endpoint.php';

        $plugin_url = RMP_PLUGIN_URL;

        $cr_user_id = "";

        if(is_user_logged_in()){
            $cr_user_id = get_current_user_id();
        }

        $home_url = home_url();

        // Get current page/post ID
        
        
        $html = '<div id="' . esc_attr($atts['id']) . '" data-page-id="' . esc_attr($page_id) . '"></div>';
        $html .= '<script>
            (function() {
                function initSlotBooking() {
                    if (typeof window.rmpSlotBookingInit === "function") {
                        window.rmpSlotBookingInit("' . esc_js($atts['id']) . '", ' . esc_js($page_id) . ', "' . esc_js($api_url) . '", "' . esc_js($home_url) . '", "' . esc_js($plugin_url) . '", "' . esc_js($cr_user_id) . '");
                    } else {
                        // If function is not available yet, wait a bit and try again
                        setTimeout(initSlotBooking, 100);
                    }
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initSlotBooking);
                } else {
                    initSlotBooking();
                }
            })();
        </script>';
        return $html;
    }
    public function render_wallet($atts) {

        $js_wallet_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/wallet.min.js') ? 'wallet.min.js' : 'wallet.js';
        $css_wallet_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/wallet.min.css') ? 'wallet.min.css' : 'wallet.css';
        wp_enqueue_script('rmp-wallet', RMP_PLUGIN_URL . 'assets/js/' . $js_wallet_file, array('react2', 'react-dom2'), $this->version, true);
        wp_enqueue_style('rmp-wallet', RMP_PLUGIN_URL . 'assets/css/' . $css_wallet_file, array(), $this->version);

       


        $page_id = get_the_ID();
        $atts = shortcode_atts(array(
            'id' => 'rmp-wallet-' . uniqid()
        ), $atts);


        $api_url = RMP_PLUGIN_URL . 'server/wallet/wallet-endpoint.php';

        $home_url = home_url();

        $user_token = "";

        if(is_user_logged_in()){    
            $cr_user_id = get_current_user_id();
            $user_token = $this->custom_jwt->generate_token($cr_user_id);

            if(!$user_token){
                echo "<p>User token error</p>";
            }
        }



        // Get current page/post ID
        
        
        $html = '<div id="' . esc_attr($atts['id']) . '" data-page-id="' . esc_attr($page_id) . '"></div>';
        $html .= '<script>
            (function() {
                function initWallet() {
                    if (typeof window.rmpWalletInit === "function") {
                        window.rmpWalletInit("' . esc_js($atts['id']) . '", ' . esc_js($page_id) . ', "' . esc_js($api_url) . '", "' . esc_js($home_url) . '", "' . esc_js($user_token) . '");
                    } else {
                        // If function is not available yet, wait a bit and try again
                        setTimeout(initWallet, 100);
                    }
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initWallet);
                } else {
                    initWallet();
                }
            })();
        </script>';
        return $html;
    }
    
    public function render_sms_log($atts) {

        $js_wallet_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/wallet.min.js') ? 'wallet.min.js' : 'wallet.js';
        $css_wallet_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/wallet.min.css') ? 'wallet.min.css' : 'wallet.css';
        wp_enqueue_script('rmp-wallet', RMP_PLUGIN_URL . 'assets/js/' . $js_wallet_file, array('react2', 'react-dom2'), $this->version, true);
        wp_enqueue_style('rmp-wallet', RMP_PLUGIN_URL . 'assets/css/' . $css_wallet_file, array(), $this->version);

       

        $page_id = get_the_ID();
        $atts = shortcode_atts(array(
            'id' => 'rmp-sms-log-' . uniqid()
        ), $atts);


        $api_url = RMP_PLUGIN_URL . 'server/wallet/wallet-endpoint.php';

        $home_url = home_url();

        $user_token = "";

        if(is_user_logged_in()){    
            $cr_user_id = get_current_user_id();
            $user_token = $this->custom_jwt->generate_token($cr_user_id);

            if(!$user_token){
                echo "<p>User token error</p>";
            }
        }

        $group_admin = get_group_admin();

        if($group_admin != ""){
            $owner_id = $group_admin;
        }else{
            $owner_id = get_current_user_id();
        }

        // Get current page/post ID
        
        
        $html = '<div id="' . esc_attr($atts['id']) . '" data-page-id="' . esc_attr($page_id) . '"></div>';
        $html .= '<script>
            (function() {
                function initSmsLog() {
                    if (typeof window.rmpSmsLogInit === "function") {
                        window.rmpSmsLogInit("' . esc_js($atts['id']) . '", ' . esc_js($page_id) . ', "' . esc_js($api_url) . '", "' . esc_js($home_url) . '", "' . esc_js($user_token) . '", ' . esc_js($owner_id) . ');
                    } else {
                        // If function is not available yet, wait a bit and try again
                        setTimeout(initSmsLog, 100);
                    }
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initSmsLog);
                } else {
                    initSmsLog();
                }
            })();
        </script>';
        return $html;
    }
    public function render_email_log($atts) {

        $js_email_template_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/email_template.min.js') ? 'email_template.min.js' : 'email_template.js';
        $css_email_template_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/email_template.min.css') ? 'email_template.min.css' : 'email_template.css';
        wp_enqueue_script('rmp-email-template', RMP_PLUGIN_URL . 'assets/js/' . $js_email_template_file, array('react2', 'react-dom2'), $this->version, true);
        wp_enqueue_style('rmp-email-template', RMP_PLUGIN_URL . 'assets/css/' . $css_email_template_file, array(), $this->version);

       

        $page_id = get_the_ID();
        $atts = shortcode_atts(array(
            'id' => 'rmp-email-log-' . uniqid()
        ), $atts);


        $api_url = RMP_PLUGIN_URL . 'server/emailTemplate/email-template-endpoint.php';

        $home_url = home_url();

        $user_token = "";

        if(is_user_logged_in()){    
            $cr_user_id = get_current_user_id();
            $user_token = $this->custom_jwt->generate_token($cr_user_id);

            if(!$user_token){
                echo "<p>User token error</p>";
            }
        }

        $group_admin = get_group_admin();

        if($group_admin != ""){
            $owner_id = $group_admin;
        }else{
            $owner_id = get_current_user_id();
        }



        // Get current page/post ID
        
        
        $html = '<div id="' . esc_attr($atts['id']) . '" data-page-id="' . esc_attr($page_id) . '"></div>';
        $html .= '<script>
            (function() {
                function initEmailLog() {
                    if (typeof window.rmpEmailLogInit === "function") {
                        window.rmpEmailLogInit("' . esc_js($atts['id']) . '", ' . esc_js($page_id) . ', "' . esc_js($api_url) . '", "' . esc_js($home_url) . '", "' . esc_js($user_token) . '", ' . esc_js($owner_id) . ');
                    } else {
                        // If function is not available yet, wait a bit and try again
                        setTimeout(initEmailLog, 100);
                    }
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initEmailLog);
                } else {
                    initEmailLog();
                }
            })();
        </script>';
        return $html;
    }
    
    public function render_email_template($atts) {

        $js_email_template_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/email_template.min.js') ? 'email_template.min.js' : 'email_template.js';
        $css_email_template_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/email_template.min.css') ? 'email_template.min.css' : 'email_template.css';
        wp_enqueue_script('rmp-email-template', RMP_PLUGIN_URL . 'assets/js/' . $js_email_template_file, array('react2', 'react-dom2'), $this->version, true);
        wp_enqueue_style('rmp-email-template', RMP_PLUGIN_URL . 'assets/css/' . $css_email_template_file, array(), $this->version);

        $page_id = get_the_ID();
        $atts = shortcode_atts(array(
            'id' => 'rmp-email-template-' . uniqid()
        ), $atts);


        $api_url = RMP_PLUGIN_URL . 'server/emailTemplate/email-template-endpoint.php';

        $home_url = home_url();

        $user_token = "";

        if(is_user_logged_in()){    
            $cr_user_id = get_current_user_id();
            $user_token = $this->custom_jwt->generate_token($cr_user_id);

            if(!$user_token){
                echo "<p>User token error</p>";
            }
        }

        $group_admin = get_group_admin();

        if($group_admin != ""){
            $owner_id = $group_admin;
        }else{
            $owner_id = get_current_user_id();
        }



        // Get current page/post ID
        
        
        $html = '<div id="' . esc_attr($atts['id']) . '" data-page-id="' . esc_attr($page_id) . '"></div>';
        $html .= '<script>
            (function() {
                function initEmailTemplate() {
                    if (typeof window.rmpEmailTemplateInit === "function") {
                        window.rmpEmailTemplateInit("' . esc_js($atts['id']) . '", ' . esc_js($page_id) . ', "' . esc_js($api_url) . '", "' . esc_js($home_url) . '", "' . esc_js($user_token) . '", ' . esc_js($owner_id) . ');
                    } else {
                        // If function is not available yet, wait a bit and try again
                        setTimeout(initEmailTemplate, 100);
                    }
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initEmailTemplate);
                } else {
                    initEmailTemplate();
                }
            })();
        </script>';
        return $html;
    }
    
    public function render_react_modules($atts) {

        $js_react_modules_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/react_modules.min.js') ? 'react_modules.min.js' : 'react_modules.js';
        $css_react_modules_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/react_modules.min.css') ? 'react_modules.min.css' : 'react_modules.css';
        wp_enqueue_script('rmp-react-modules', RMP_PLUGIN_URL . 'assets/js/' . $js_react_modules_file, array('react2', 'react-dom2'), $this->version, true);
        wp_enqueue_style('rmp-react-modules', RMP_PLUGIN_URL . 'assets/css/' . $css_react_modules_file, array(), $this->version);
       

        $page_id = get_the_ID();
        $atts = shortcode_atts(array(
            'id' => 'react-modules-' . uniqid(),
            'component' => ''
        ), $atts);


        if(isset($atts['component'])){
            $component = $atts['component'];
        }else{
            return "<p>Component not found</p>";
        }



        $api_url = '';

        $require_login = false;
        $require_admin = false;

        switch ($component) {
            case 'subscription_discount':
                $api_url = RMP_PLUGIN_URL . 'server/subscriptionDiscount/subscription-discount-endpoint.php';
                $require_login = true;
                break;
            case 'gibbs_customer':
                $api_url = RMP_PLUGIN_URL . 'server/customer/customer-endpoint.php';
                $require_login = true;
                $require_admin = true;
                break;    
            case 'component_gallery':
            case 'components':
                // Component gallery doesn't need an API URL
                $api_url = '';
                break;
            default:
                $api_url = '';
                break;
        }

        if($require_login && !is_user_logged_in()){
            return '<div style="display: flex; justify-content: center; align-items: center; min-height: 200px;">
                        <div style="text-align: center;">
                            <svg xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 12px;" width="48" height="48" fill="none" viewBox="0 0 48 48"><circle cx="24" cy="24" r="22" fill="#f5f5f5" stroke="#ccc" stroke-width="2"/><path d="M24 19a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 5.33V29a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.67C32 23.67 29.33 21 24 21z" fill="#b1b1b1"/></svg>
                            <p style="font-size: 18px; color: #555; margin-bottom: 4px;">You must be logged in</p>
                            <p style="font-size: 14px; color: #888;">Please log in to view this component.</p>
                        </div>
                    </div>';
        }
        $scripts = '';

        if ($component == 'gibbs_customer') {
            // Check if user has permission to view customer list
            if (!$this->user_can_view_customer_list() ) {
                return '<div style="display: flex; justify-content: center; align-items: center; min-height: 200px;">
                    <div style="text-align: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 12px;" width="48" height="48" fill="none" viewBox="0 0 48 48"><circle cx="24" cy="24" r="22" fill="#f5f5f5" stroke="#ccc" stroke-width="2"/><path d="M24 19a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 5.33V29a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.67C32 23.67 29.33 21 24 21z" fill="#b1b1b1"/></svg>
                        <p style="font-size: 18px; color: #555; margin-bottom: 4px;">Access denied</p>
                        <p style="font-size: 14px; color: #888;">You do not have permission to view the customer list.</p>
                    </div>
                </div>';
            }

            $cr_role = false;
            $selected_countries = array();

            $current_user_roles = wp_get_current_user()->roles;

            if(in_array('sales_rep', $current_user_roles)){
                $cr_role = true;
                $current_user = wp_get_current_user();
                $selected_countries = Customer_Role_Admin::get_user_country_permissions($current_user->ID);
            }

            $customer_list_data = array(
                'user_can_view_customer_list' => $this->user_can_view_customer_list(),
                'columns' => $this::get_available_columns(),
                'actions' => $this::get_available_actions(),
                'sales_rep_role' => $cr_role,
                'selected_countries' => $selected_countries,
            );
            // print_r($customer_list_data);
            // echo "</pre>";
            // die();

            $scripts = '<script>
                window.customerListData = ' . json_encode($customer_list_data) . ';
            </script>';

        }

        // if($require_admin && !current_user_can('administrator')){
        //     return '<div style="display: flex; justify-content: center; align-items: center; min-height: 200px;">
        //                 <div style="text-align: center;">
        //                     <svg xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 12px;" width="48" height="48" fill="none" viewBox="0 0 48 48"><circle cx="24" cy="24" r="22" fill="#f5f5f5" stroke="#ccc" stroke-width="2"/><path d="M24 19a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 5.33V29a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.67C32 23.67 29.33 21 24 21z" fill="#b1b1b1"/></svg>
        //                     <p style="font-size: 18px; color: #555; margin-bottom: 4px;">You must be an administrator</p>
        //                     <p style="font-size: 14px; color: #888;">Please log in as an administrator to view this component.</p>
        //                 </div>
        //             </div>';
        // }

        // Only require API URL for components that need it
        $components_requiring_api = array('subscription_discount', 'gibbs_customer');
        if(in_array($component, $components_requiring_api) && empty($api_url)){
            return "<p>Component endpoint not configured</p>";
        }

        $home_url = home_url();

        $user_token = "";

        if(is_user_logged_in()){    
            $cr_user_id = get_current_user_id();
            $user_token = $this->custom_jwt->generate_token($cr_user_id);

            if(!$user_token){
                echo "<p>User token error</p>";
            }
        }

        $group_admin = get_group_admin();

        if($group_admin != ""){
            $owner_id = $group_admin;
        }else{
            $owner_id = get_current_user_id();
        }



        // Get current page/post ID
        
        
        $html = '<div id="' . esc_attr($atts['id']) . '" data-page-id="' . esc_attr($page_id) . '"></div>';
        $html .= $scripts;
        $html .= '<script>
            (function() {
                function initReactModules() {
                    if (typeof window.rmpReactModulesInit === "function") {
                        window.rmpReactModulesInit("' . esc_js($component) . '", "' . esc_js($atts['id']) . '", ' . esc_js($page_id) . ', "' . esc_js($api_url) . '", "' . esc_js($home_url) . '", "' . esc_js($user_token) . '", ' . esc_js($owner_id) . ');
                    } else {
                        // If function is not available yet, wait a bit and try again
                        setTimeout(initReactModules, 100);
                    }
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initReactModules);
                } else {
                    initReactModules();
                }
            })();
        </script>';
        return $html;
    }

    /**
     * Add custom rewrite rules for custom URLs
     */
    public function add_custom_rewrite_rules() {
 
    
        add_rewrite_rule(
            'slot-booking-confirmation/([^/]+)/?$',
            'index.php?slot_booking_confirmation=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            'gibbs-pay?$',
            'index.php?gibbs_payment=true',
            'top'
        );
        
    }

    /**
     * Add custom query vars
     */
    public function add_custom_query_vars($vars) {
        $vars[] = 'slot_booking_confirmation';
        $vars[] = 'gibbs_payment';
        return $vars;
    }

    /**
     * Handle custom URLs
     */
    public function handle_custom_urls() {
        $slot_booking_id = get_query_var('slot_booking_confirmation');
        $gibbs_payment_id = get_query_var('gibbs_payment');

        if ($slot_booking_id) {
            $plugin_template = RMP_PLUGIN_PATH . 'templates/slot-booking-confirmation.php';
            if (file_exists($plugin_template)) {
                ob_start();
                require_once $plugin_template;
                echo ob_get_clean();
            }
            exit;
        }

        if ($gibbs_payment_id == "true") {
            
            $plugin_template = RMP_PLUGIN_PATH . 'templates/gibbs-payment.php';
            if (file_exists($plugin_template)) {
                ob_start();
                require_once $plugin_template;
                echo ob_get_clean();
            }
            exit;
        }

        if ($slot_date && $slot_time) {
            // Handle slot URLs like /slot/2024-01-15/09:00/
            $this->render_slot_page($slot_date, $slot_time);
            exit;
        }
    }

    /**
     * Add an admin settings page for OAuth configurations
     */
    public function add_admin_menu() {
        add_options_page(
            __('Gibbs OAuth Settings', 'gibbs-react-booking'),
            __('Gibbs OAuth', 'gibbs-react-booking'),
            'manage_options',
            'gibbs-oauth-settings',
            array($this, 'render_oauth_settings_page')
        );
    }

    /**
     * Register settings to store Google, Microsoft, Vipps credentials
     */
    public function register_oauth_settings() {
        register_setting('rmp_oauth_settings_group', 'rmp_oauth_settings');

        // Sections (for organization only; fields rendered manually)
        add_settings_section('rmp_oauth_settings_section', __('OAuth Providers', 'gibbs-react-booking'), function () {
            echo '<p>' . esc_html__('Configure OAuth credentials for Google, Microsoft, and Vipps.', 'gibbs-react-booking') . '</p>';
        }, 'gibbs-oauth-settings');
    }

    /**
     * Render the OAuth settings page
     */
    public function render_oauth_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = get_option('rmp_oauth_settings', array());

        $providers = array(
            'google' => __('Google', 'gibbs-react-booking'),
            'microsoft' => __('Microsoft', 'gibbs-react-booking'),
            'vipps' => __('Vipps', 'gibbs-react-booking'),
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Gibbs OAuth Settings', 'gibbs-react-booking')); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('rmp_oauth_settings_group');
                do_settings_sections('gibbs-oauth-settings');
                ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ($providers as $key => $label) :
                            $provider = isset($options[$key]) && is_array($options[$key]) ? $options[$key] : array();
                            $client_id = isset($provider['client_id']) ? $provider['client_id'] : '';
                            $client_secret = isset($provider['client_secret']) ? $provider['client_secret'] : '';
                            $scope = isset($provider['scope']) ? $provider['scope'] : '';
                            $redirect_uri = isset($provider['redirect_uri']) ? $provider['redirect_uri'] : '';
                        ?>
                        <tr>
                            <th colspan="2"><h2><?php echo esc_html($label); ?></h2></th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rmp_<?php echo esc_attr($key); ?>_client_id"><?php echo esc_html(__('Client ID', 'gibbs-react-booking')); ?></label></th>
                            <td>
                                <input type="text" id="rmp_<?php echo esc_attr($key); ?>_client_id" name="rmp_oauth_settings[<?php echo esc_attr($key); ?>][client_id]" value="<?php echo esc_attr($client_id); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rmp_<?php echo esc_attr($key); ?>_client_secret"><?php echo esc_html(__('Client Secret', 'gibbs-react-booking')); ?></label></th>
                            <td>
                                <input type="text" id="rmp_<?php echo esc_attr($key); ?>_client_secret" name="rmp_oauth_settings[<?php echo esc_attr($key); ?>][client_secret]" value="<?php echo esc_attr($client_secret); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rmp_<?php echo esc_attr($key); ?>_scope"><?php echo esc_html(__('Scope', 'gibbs-react-booking')); ?></label></th>
                            <td>
                                <input type="text" id="rmp_<?php echo esc_attr($key); ?>_scope" name="rmp_oauth_settings[<?php echo esc_attr($key); ?>][scope]" value="<?php echo esc_attr($scope); ?>" class="regular-text" />
                                <p class="description"><?php echo esc_html(__('Space-separated scopes if multiple.', 'gibbs-react-booking')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rmp_<?php echo esc_attr($key); ?>_redirect_uri"><?php echo esc_html(__('Redirect URI', 'gibbs-react-booking')); ?></label></th>
                            <td>
                                <input type="url" id="rmp_<?php echo esc_attr($key); ?>_redirect_uri" name="rmp_oauth_settings[<?php echo esc_attr($key); ?>][redirect_uri]" value="<?php echo esc_attr($redirect_uri); ?>" class="regular-text code" />
                            </td>
                        </tr>
                        <tr><td colspan="2"><hr /></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'gibbs-react-booking')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render custom booking page
     */
    private function render_custom_booking_page($page) {
        echo '<!DOCTYPE html>';
        echo '<html><head><title>Booking - ' . esc_html($page) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        wp_head();
        echo '</head><body>';
        
        echo '<div id="custom-booking-container" data-page="' . esc_attr($page) . '"></div>';
        
        wp_footer();
        echo '</body></html>';
    }

    /**
     * Render slot page
     */
    private function render_slot_page($date, $time) {
        echo '<!DOCTYPE html>';
        echo '<html><head><title>Slot Booking - ' . esc_html($date) . ' ' . esc_html($time) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        wp_head();
        echo '</head><body>';
        
        echo '<div id="slot-booking-container" data-date="' . esc_attr($date) . '" data-time="' . esc_attr($time) . '"></div>';
        
        wp_footer();
        echo '</body></html>';
    }

    /**
     * Flush rewrite rules (call this once after adding new rules)
     */
    public function flush_rewrite_rules() {
        $this->add_custom_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Register subscription discount custom post type
     */
    private function register_subscription_discount_cpt() {
        if (post_type_exists('subscriptiondiscount')) {
            return;
        }

        register_post_type('subscriptiondiscount', array(
            'labels' => array(
                'name' => __('Subscription Discounts', 'gibbs-react-booking'),
                'singular_name' => __('Subscription Discount', 'gibbs-react-booking'),
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => array('title'),
            'rewrite' => false,
        ));
    }

    /**
     * Check if current user can view customer list
     * 
     * @return bool True if user has permission to view customer list
     */
    private function user_can_view_customer_list() {
        // Administrators always have access
        if (current_user_can('manage_options')) {
            return true;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }

        // Get current user's roles
        $user = wp_get_current_user();
        if (empty($user->roles)) {
            return false;
        }

        // Check if any of the user's roles have view_customer permission
        foreach ($user->roles as $role_name) {
            // Check if role has view_customer action permission
            if (Customer_Role_Admin::can_perform_action($role_name, 'view_customer_list')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available columns that current user has permission to access
     * 
     * @return array Array of columns with 'key' and 'label' that user can access
     */
    public function get_available_columns() {
        // Administrators get all columns
        if (current_user_can('manage_options')) {
            return Customer_Columns::get_columns_array();
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return array();
        }

        // Get current user's roles
        $user = wp_get_current_user();
        if (empty($user->roles)) {
            return array();
        }

        // Collect all allowed columns from all user roles
        $allowed_column_keys = array();
        
        foreach ($user->roles as $role_name) {
            $role_column_permissions = Customer_Role_Admin::get_role_column_permissions($role_name);
            $allowed_column_keys = array_merge($allowed_column_keys, $role_column_permissions);
        }

        // Remove duplicates
        $allowed_column_keys = array_unique($allowed_column_keys);

        // If no columns are allowed, return empty array
        if (empty($allowed_column_keys)) {
            return array();
        }

        // Filter columns to only return allowed ones
        $all_columns = Customer_Columns::get_columns_array();
        $filtered_columns = array();

        foreach ($all_columns as $column) {
            if (in_array($column['key'], $allowed_column_keys)) {
                $filtered_columns[] = $column;
            }
        }

        return $filtered_columns;
    }

    /**
     * Get available actions that current user has permission to perform
     * 
     * @return array Array of actions with 'key' and 'label' that user can perform
     */
    public function get_available_actions() {
        // Administrators get all actions
        if (current_user_can('manage_options')) {
            return Customer_Actions::get_action_keys();
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return array();
        }

        // Get current user's roles
        $user = wp_get_current_user();
        if (empty($user->roles)) {
            return array();
        }

        // Collect all allowed actions from all user roles
        $allowed_action_keys = array();
        
        foreach ($user->roles as $role_name) {
            $role_action_permissions = Customer_Role_Admin::get_role_action_permissions($role_name);
            $allowed_action_keys = array_merge($allowed_action_keys, $role_action_permissions);
        }

        // Remove duplicates
        $allowed_action_keys = array_unique($allowed_action_keys);

        // If no actions are allowed, return empty array
        if (empty($allowed_action_keys)) {
            return array();
        }

        // Filter actions to only return allowed ones
        $all_actions = Customer_Actions::get_actions_array();
        $filtered_actions = array();

        foreach ($all_actions as $action) {
            if (in_array($action['key'], $allowed_action_keys)) {
                $filtered_actions[] = $action['key'];
            }
        }

        return $filtered_actions;
    }

} 
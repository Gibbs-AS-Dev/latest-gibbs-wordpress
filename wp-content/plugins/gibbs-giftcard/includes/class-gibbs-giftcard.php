<?php

class Class_Gibbs_Giftcard 
{
    public function action_init() {
        // Register custom post type
        $this->register_giftcard_post_type();
        $this->add_rewrite_rules();
        add_action('template_redirect', [$this, 'redirect_to_giftcard_creation_page']);
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('giftcard_create', [$this, 'giftcard_create']);
        add_shortcode('giftcards', [$this, 'giftcards']);
        add_shortcode('giftcard_bookings', [$this, 'giftcard_bookings']);
        add_shortcode('check_giftcard', [$this, 'check_giftcard']);
        add_shortcode('customer_refund', [$this, 'customer_refund']);
        add_shortcode('refund_vouchers', [$this, 'refund_vouchers']);
        add_action('wp_ajax_save_giftcard', [$this, 'save_giftcard']);
        add_action('wp_ajax_fetch_giftcard_data', [$this, 'fetch_giftcard_data']);
        add_action('wp_ajax_nopriv_fetch_giftcard_data', [$this, 'fetch_giftcard_data']);
        add_action('wp_ajax_process_refund', [$this, 'process_refund']);

        add_action('wp_ajax_activate_giftcard', [$this, 'activate_giftcard']);
        add_action('wp_ajax_deactivate_giftcard', [$this, 'deactivate_giftcard']);
        
        add_action('wp_ajax_downloadGiftPDF', [$this, 'downloadGiftPDF']);
        add_action('wp_ajax_nopriv_downloadGiftPDF', [$this, 'downloadGiftPDF']);

        add_action('wp_ajax_downloadVoucherPDF', [$this, 'downloadVoucherPDF']);
        add_action('wp_ajax_nopriv_downloadVoucherPDF', [$this, 'downloadVoucherPDF']);

        // Override template for single gift card view
        add_filter('template_include', [$this, 'override_single_giftcard_template']);

        add_action('woocommerce_order_status_changed', array($this, 'send_giftcard_email'), 10, 4);
        
        // Flush rewrite rules on activation
        register_activation_hook(__FILE__, [$this, 'flush_rewrite_rules']);

        add_action('wp_ajax_fetch_voucher_data', array($this, 'fetch_voucher_data'));
        add_action('wp_ajax_nopriv_fetch_voucher_data', array($this, 'fetch_voucher_data'));

        //$this->sendEmail("46174", "46175");
        
    }

    public function getGroupName($user_id){
        global $wpdb;
        $users_groups = $wpdb->prefix . 'users_groups';  // table name
        $users_and_users_groups = $wpdb->prefix . 'users_and_users_groups';  // table name
        $sql_user_group = "select b.id,b.name,a.role  from `$users_and_users_groups` as a left join `$users_groups` as b ON a.users_groups_id = b.id where a.users_id = $user_id";
        $user_group_data_all = $wpdb->get_row($sql_user_group);

        if(isset($user_group_data_all->name)){
            return $user_group_data_all->name;
        }else{
            return "";
        }
    }

    public function downloadGiftPDF(){


        $giftcode = $_POST["giftcode"];
        // Retrieve gift card data
        $gift_data = $this->getGiftDataByGiftCode($giftcode); // Custom method to get gift card data by booking ID
    
        if (!$gift_data) {
           wp_redirect(home_url());
           exit;
        }

        $group_name = $this->getGroupName($gift_data["gift_post_author"]);
        
    
        require GIBBS_GIFT_PATH.'dompdf/vendor/dompdf/dompdf/src/Dompdf.php';
        require GIBBS_GIFT_PATH.'dompdf/vendor/dompdf/dompdf/src/Options.php';
    
        // Email body - HTML template
        ob_start();
        require GIBBS_GIFT_PATH . 'views/giftcard_email_template.php'; 
        $body = ob_get_clean(); 
        // Generate PDF using DOMPDF
        $pdf = new \Dompdf\Dompdf();
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Enable to fetch remote images if needed
        $pdf->setOptions($options);

        $pdf->loadHtml("<style>.download_pdf_div{display:none}</style>".$body);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream("Gift_Card.pdf", array("Attachment" => true));
        exit;

    }

    public function downloadVoucherPDF(){


        $giftcode = $_POST["refund_code"];
        // Retrieve gift card data
        $refund_data = $this->getGiftDataByGiftCode($giftcode); 

    
        if (!$refund_data) {
           wp_redirect(home_url());
           exit;
        }

        $group_name = $this->getGroupName($gift_data["gift_post_author"]);
        
    
        require GIBBS_GIFT_PATH.'dompdf/vendor/dompdf/dompdf/src/Dompdf.php';
        require GIBBS_GIFT_PATH.'dompdf/vendor/dompdf/dompdf/src/Options.php';
    
        // Email body - HTML template
        ob_start();
        require GIBBS_GIFT_PATH . 'views/customer_refund_email_template.php';
        $body = ob_get_clean(); 
        // Generate PDF using DOMPDF
        $pdf = new \Dompdf\Dompdf();
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Enable to fetch remote images if needed
        $pdf->setOptions($options);

        $pdf->loadHtml("<style>.download_pdf_div{display:none}</style>".$body);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream("Voucher_Card.pdf", array("Attachment" => true));
        exit;

    }


    public function updateGiftLogAndprice( $gift_booking_id, $remaining_price, $amount_used, $listing_order_id,$listing_id ){

        if($gift_booking_id && $gift_booking_id != "" && $gift_booking_id > 0){

            $meta_listing_order_id = get_post_meta($gift_booking_id,"listing_order_id_".$listing_order_id,true);

            if($meta_listing_order_id != $listing_order_id){
                $data_log["listing_order_id"] = $listing_order_id;
                $data_log["listing_id"] = $listing_id;
                $data_log["amount_used"] = $amount_used;
                $data_log["date"] = date("Y-m-d H:i:s");
                $data_log = json_encode($data_log);
                update_post_meta($gift_booking_id,"remaining_saldo",$remaining_price);
                update_post_meta($gift_booking_id,"listing_order_id_".$listing_order_id,$listing_order_id);
                add_post_meta($gift_booking_id,"data_log",$data_log);
            }

        }

    }

    public function send_giftcard_email($order_id, $old_status, $new_status, $order) {

        

            $gift_booking_id = get_post_meta($order_id,"gift_booking_id",true);
            if($gift_booking_id && $gift_booking_id != "" && $gift_booking_id > 0){
                if ($new_status == 'completed') {
                   update_post_meta($gift_booking_id,"order_status", $new_status);
                   $this->sendEmail($gift_booking_id, $order_id);
                }else{
                    update_post_meta($gift_booking_id,"order_status", $new_status);
                }
            }
        
    }

    public function sendEmail($gift_booking_id, $order_id) {
        // Retrieve gift card data
        $gift_data = $this->getGiftDataByBookingId($gift_booking_id); // Custom method to get gift card data by booking ID

       // echo "<pre>"; print_r($gift_data); die;
    
        if (!$gift_data) {
            return; // Exit if no gift data is found
        }
        $group_name = $this->getGroupName($gift_data["gift_post_author"]);
        

        $giftcode = $gift_data["code"];
    
        // Email subject and recipient
        $subject = "Her er ditt gavekort";
        $to = $gift_data['purchased_by']; 
        //$to = "sk81930@gmail.com";
    
        // Email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8'
        ];

        require GIBBS_GIFT_PATH.'dompdf/vendor/dompdf/dompdf/src/Dompdf.php';
        require GIBBS_GIFT_PATH.'dompdf/vendor/dompdf/dompdf/src/Options.php';
    
        // Email body - HTML template
        ob_start();
        require GIBBS_GIFT_PATH . 'views/giftcard_email_template.php'; 
        $body = ob_get_clean(); 

        // Generate PDF using DOMPDF
        $pdf = new \Dompdf\Dompdf();
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Enable to fetch remote images if needed
        $pdf->setOptions($options);

        $pdf->loadHtml("<style>.download_pdf_div{display:none}</style>".$body);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        // Save the PDF to a temporary file
        $upload_dir = wp_upload_dir();
        $pdfFilePath = $upload_dir['path'] . '/giftcard_' . $gift_booking_id . '.pdf';
        file_put_contents($pdfFilePath, $pdf->output());

        // Send the email
        $attachments = [$pdfFilePath];
        wp_mail($to, $subject, $body, $headers, $attachments);

        // Clean up temporary PDF file
        unlink($pdfFilePath);
       // die;

        return true;
    }

    public function getGiftDataByBookingId($gift_booking_id) {
        $args = [
            'post_type' => 'giftcard_booking',
            'p' => $gift_booking_id,
            'posts_per_page' => 1,
        ];
    
        $query = new WP_Query($args);
    
        if ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $giftcard_id = get_post_meta($post_id, 'giftcard_id', true);

            $listing_ids = get_post_meta($giftcard_id, 'listing_ids', true);
           

            $listing_names = array();

            foreach($listing_ids as $listing_id){
                $listing_id = trim($listing_id);
                $listing_names[] = get_the_title($listing_id);
            }

            $listing_name_data = implode(", ",$listing_names);

            $gift_post_data = get_post($giftcard_id);
    
            // Retrieve gift card meta data
            $gift_data = [
                'id' => $post_id,
                'gift_post_author' => $gift_post_data->post_author,
                'code' => get_post_meta($post_id, 'gift_code', true),
                'purchased_by' => get_post_meta($post_id, 'email', true),
                'purchased_amount' => get_post_meta($post_id, 'giftcard_amount', true),
                'expire_date' => get_post_meta($post_id, 'expire_date', true),
                'listing_name_data' => $listing_name_data,
                'giftcard_description' => get_post_meta($giftcard_id, 'giftcard_description', true),
            ];
    
            wp_reset_postdata();
            return $gift_data;
        } else {
            wp_reset_postdata();
            return null;
        }
    }
    
    public function getGiftDataByGiftCode($code) {
        $args = [
            'post_type' => 'giftcard_booking',
            'meta_query' => [
                [
                    'key' => 'gift_code',
                    'value' => $code,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
        ];
    
        $query = new WP_Query($args);
    
        if ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $giftcard_id = get_post_meta($post_id, 'giftcard_id', true);

            $listing_ids = get_post_meta($giftcard_id, 'listing_ids', true);
           

            $listing_names = array();

            foreach($listing_ids as $listing_id){
                $listing_id = trim($listing_id);
                $listing_names[] = get_the_title($listing_id);
            }

            $listing_name_data = implode(", ",$listing_names);

            $gift_post_data = get_post($giftcard_id);
    
            // Retrieve gift card meta data
            $gift_data = [
                'id' => $post_id,
                'gift_post_author' => $gift_post_data->post_author,
                'code' => get_post_meta($post_id, 'gift_code', true),
                'purchased_by' => get_post_meta($post_id, 'email', true),
                'purchased_amount' => get_post_meta($post_id, 'giftcard_amount', true),
                'remaining_saldo' => get_post_meta($post_id, 'remaining_saldo', true),
                'expire_date' => get_post_meta($post_id, 'expire_date', true),
                'listing_name_data' => $listing_name_data,
                'giftcard_description' => get_post_meta($giftcard_id, 'giftcard_description', true),
            ];
    
            wp_reset_postdata();
            return $gift_data;
        } else {
            wp_reset_postdata();
            return null;
        }
    }
    

    public function override_single_giftcard_template($template) {
        if (is_singular('giftcard')) {
            $plugin_template = GIBBS_GIFT_PATH . 'views/single-giftcard.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function get_page_id_by_shortcode($shortcode) {
        // Query pages that are published
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);
    
        // Loop through pages to find one with the shortcode
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, $shortcode)) {
                return $page->ID; // Return the page ID if found
            }
        }
    
        return null; // Return null if no page found with the shortcode
    }

    public function register_giftcard_post_type() {
        $labels = array(
            'name'               => 'Gift Cards',
            'singular_name'      => 'Gift Card',
            'menu_name'          => 'Gift Cards',
            'name_admin_bar'     => 'Gift Card',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Gift Card',
            'new_item'           => 'New Gift Card',
            'edit_item'          => 'Edit Gift Card',
            'view_item'          => 'View Gift Card',
            'all_items'          => 'All Gift Cards',
            'search_items'       => 'Search Gift Cards',
            'not_found'          => 'No gift cards found.',
            'not_found_in_trash' => 'No gift cards found in Trash.'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'giftcard'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-tickets-alt',
            'supports'           => array('title', 'editor', 'thumbnail')
        );

        register_post_type('giftcard', $args);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('gift-plugin-css', GIBBS_GIFT_URL . '/css/styles.css');
        wp_enqueue_script('gift-select2-js', get_template_directory_uri() . '/js/select2.min.js');
    }

    public function add_rewrite_rules() {
        // add_rewrite_rule('^create-gift/?$', 'index.php?create_gift=1', 'top');
        // add_rewrite_tag('%create_gift%', '([0-9]+)');

        add_rewrite_rule('^gift-booking/?$', 'index.php?booking_gift=1', 'top');
        add_rewrite_tag('%booking_gift%', '([0-9]+)');
    }

    public function redirect_to_giftcard_creation_page() {
       
        if (get_query_var('booking_gift')) {
            if(!isset($_POST["gift_booking"])){
                wp_redirect(home_url());
                exit;
            }
            $woocommerce_product_id = get_post_meta($_POST["giftcard_id"], 'woocommerce_product_id', true);
            if(!$woocommerce_product_id){
                wp_redirect(home_url());
                exit;
            }

            $message_data = array();


            if(isset($_POST["save_gift_booking"])){
                $data_message = $this->save_gift_booking();

                if(isset($data_message["error"]) && $data_message["error"]){
                    $message_data["error"] = $data_message["message"];
                }else{
                    exit;
                }

            }
           
            // Load the gift card creation page
            include GIBBS_GIFT_PATH . 'views/booking_gift.php';
            exit;
        }
    }

    public function flush_rewrite_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function getGiftData($code) {
        // Query to find the giftcard_booking post with the matching gift code
        $args = [
            'post_type' => 'giftcard_booking',
            'meta_query' => [
                [
                    'key' => 'gift_code',
                    'value' => $code,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ];
    
        $query = new WP_Query($args);
    
        if ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            

            $logs = $this->giftLogs($post_id);

            $giftcard_id = get_post_meta($post_id, 'giftcard_id', true);

            $listing_ids = get_post_meta($giftcard_id, 'listing_ids', true);
            
            //$listing_ids = explode(",",$listing_ids);
           

            $listing_names = array();

            foreach($listing_ids as $listing_id){
                $listing_id = trim($listing_id);
                $listing_names[] = get_the_title($listing_id);
            }

            $listing_name_data = implode(", ",$listing_names);

            //echo "<pre>"; print_r($listing_names); die;


    
            // Collect necessary gift card data
            $gift_data = [
                'id' => $post_id,
                'code' => get_post_meta($post_id, 'gift_code', true),
                'purchased_by' => get_post_meta($post_id, 'email', true),
                'purchased_amount' => get_post_meta($post_id, 'giftcard_amount', true),
                'remaining_saldo' => get_post_meta($post_id, 'remaining_saldo', true),
                'purchased_date' => get_the_date('F j, Y', $post_id),
                'expire_date' => get_post_meta($post_id, 'expire_date', true),
                'is_active' => strtotime(get_post_meta($post_id, 'expire_date', true)) > time(),
                'listing_name_data' => $listing_name_data,
                'logs' => $logs,
            ];
    
            wp_reset_postdata();
    
            return $gift_data; // Return the gift card data
        } else {
            wp_reset_postdata();
            return null; // Return null if no gift card found
        }
    }

    public function giftLogs($giftcard_booking_id){

        $data_logs = get_post_meta($giftcard_booking_id,"data_log");

        $logss = array();

        if(!empty($data_logs)){
            foreach ($data_logs as $key_log => $data_log) {

                if($data_log && $data_log != ""){
                    $data_log_decode = json_decode($data_log,true);

                    if(isset($data_log_decode["listing_id"])){
                        $logss[$key_log]["listing_name"] = get_the_title($data_log_decode["listing_id"]);
                        $logss[$key_log]["amount_used"] = $data_log_decode["amount_used"];
                        $logss[$key_log]["date"] = date('F j, Y',strtotime($data_log_decode["date"]));
                    }
                }
            }
        }
        if(!empty($logss) && count($logss) > 1){
            krsort($logss);
        }

        return $logss;

    }

    public function check_giftcard() {

        $data = array();

        if(isset($_POST["giftcard_code"])  && $_POST["giftcard_code"] != ""){

            $data = $this->getGiftData($_POST["giftcard_code"]);

        }
        ob_start();
        require GIBBS_GIFT_PATH . 'views/check_giftcard.php'; 
        return ob_get_clean();
    }
    public function giftcard_create($atts) {
        $redirect = "";
        if (isset($atts["redirect"])) {
            $redirect = $atts["redirect"];
        }

        $page_id = $this->get_page_id_by_shortcode("giftcards"); 

        if(!$page_id){
            return "Gift card shortcut page not found!";
        }

        ob_start();
        require GIBBS_GIFT_PATH . 'views/giftcard_create.php'; 
        return ob_get_clean();
    }

    public function giftcards($atts) {
        $redirect = "";
        if (isset($atts["redirect"])) {
            $redirect = $atts["redirect"];
        }
        $page_id = $this->get_page_id_by_shortcode("giftcard_create"); 

        if(!$page_id){
            return "Create Gift card shortcut page not found!";
        }

        ob_start();
        require GIBBS_GIFT_PATH . 'views/giftcards.php'; 
        return ob_get_clean();
    }

    public function save_giftcard() {
        // Check for required fields
        $errors = [];
        if (empty($_POST['title'])) {
            $errors[] = 'Title is required.';
        }
    
        // if (empty($_POST['description'])) {
        //     $errors[] = 'Description is required.';
        // }
    
        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
        } else {
            $title = sanitize_text_field($_POST['title']);
            $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : ''; // Allows safe HTML
            $listing_ids = isset($_POST['listing_ids']) ? array_map('intval', $_POST['listing_ids']) : [];
            $gift_status = sanitize_text_field($_POST['gift_status']);
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

            if(empty($listing_ids)){

                $errors[] = 'Minst 1 utleieobjekt er obligatorisk';
                wp_send_json_error(['errors' => $errors]);
                wp_die();

            }

            $group_admin = get_group_admin();
            if($group_admin == ""){
                $group_admin = get_current_user_ID();
            }
                
            // Create or update giftcard post
            $post_data = [
                'post_author' => $group_admin,
                'ID'           => $post_id,
                'post_title'   => $title,
                'post_content' => $description,
                'post_type'    => 'giftcard',
                'post_status'  => $gift_status == 'publish' ? 'publish' : 'draft',
            ];
    
            if ($post_id) {
                $post_id = wp_update_post($post_data);
                $log_args = array(
                    'action' => "giftcard_updated",
                    'related_to_id' => $group_admin,
                    'user_id' => $group_admin,
                    'post_id' => $post_id
                );
                listeo_insert_log($log_args);
            } else {
                $post_id = wp_insert_post($post_data);
                $log_args = array(
                    'action' => "giftcard_created",
                    'related_to_id' => $group_admin,
                    'user_id' => $group_admin,
                    'post_id' => $post_id
                );
                listeo_insert_log($log_args);
            }
    
            if (is_wp_error($post_id)) {
                wp_send_json_error(['errors' => ['Failed to save gift card.']]);
            } else {

                

                

                update_post_meta($post_id, 'listing_ids', $listing_ids);
                update_post_meta($post_id, 'giftcard_description', $_POST["giftcard_description"]);
                update_post_meta($post_id, 'min_amount', $_POST["min_amount"]);

                $woocommerce_product_id = get_post_meta($post_id, 'woocommerce_product_id', true);

                $product_id = $this->save_as_product($title,$description,$woocommerce_product_id,$group_admin);

                if($product_id && $product_id != "" && $product_id > 0){
                    update_post_meta($post_id, 'woocommerce_product_id', $product_id);
                }else{
                    $post_id = wp_update_post(["ID"=>$post_id,'post_status'  => 'draft']);
                    wp_send_json_error(['errors' => ['Product id not exist.']]);
                    wp_die();
                }
                

                
                wp_send_json_success(['message' => 'Lagret']);
            }
        }
    
        wp_die();
    }
    function generateRandomStringWithNumber($number) {
        // Generate a random alphanumeric string with lowercase letters
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 4; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
    
        // Choose a random position to insert the number
        $positions = ['start', 'middle', 'end'];
        $position = $positions[array_rand($positions)];
    
        // Insert the specified number at the chosen position
        if ($position === 'start') {
            $finalString = $number . $randomString;
        } elseif ($position === 'middle') {
            $midIndex = intdiv(strlen($randomString), 2);
            $finalString = substr($randomString, 0, $midIndex) . $number . substr($randomString, $midIndex);
        } else {
            $finalString = $randomString . $number;
        }
    
        return $finalString;
    }
    
    
    public function save_gift_booking() {

        $data_return = array("error"=>0,"message"=>"");

       
        if (isset($_POST['giftcard_id'])) {

           // echo "<pre>"; print_r($_POST); die;

            
            
            if(!is_user_logged_in()){

                $user_dd = self::registerUser($_POST);

                if(isset($user_dd["success"]) && $user_dd["user_id"] > 0){
                    wp_set_current_user($user_dd["user_id"]);
                }else{
                    $data_return["error"] = 1;
                    $data_return["message"] = 'User issue!';
            
                    return $data_return;
                }

            }

            if(!is_user_logged_in()){
                $data_return["error"] = 1;
                $data_return["message"] = 'User not logged in';
            
                return $data_return;
            }

            $user_id = get_current_user_id();
            

            $product_id = get_post_meta( $_POST['giftcard_id'], 'woocommerce_product_id', true);

            if($product_id && $product_id != "" && $product_id > 0){

                $giftcard_data = get_post($_POST["giftcard_id"]);
               
                $post_data = [
                    'post_author' => $user_id,
                    'post_title'   => $giftcard_data->post_title,
                    'post_content' => $giftcard_data->post_content,
                    'post_type'    => 'giftcard_booking',
                    'post_status'  => 'publish',
                ];
        
                $post_id = wp_insert_post($post_data);
                if (is_wp_error($post_id)) {
                    $data_return["error"] = 1;
                    $data_return["message"] = 'Failed to save gift card booking';
                
                    return $data_return;
                } else {

                    $log_args = array(
                        'action' => "giftcard_booking_created",
                        'related_to_id' => $giftcard_data->post_author,
                        'user_id' => $user_id,
                        'post_id' => $post_id
                    );
                    listeo_insert_log($log_args);

                    $expire_date = date('Y-m-d', strtotime('+2 years'));

                    $gift_code = $this->generateRandomStringWithNumber($post_id);
                    update_post_meta($post_id,"gift_code",$gift_code);
                    update_post_meta($post_id,"first_name",$_POST["gift_first_name"]);
                    update_post_meta($post_id,"last_name",$_POST["gift_last_name"]);
                    update_post_meta($post_id,"email",$_POST["gift_email"]);
                    update_post_meta($post_id,"phone",$_POST["gift_phone"]);
                    update_post_meta($post_id,"country_code",$_POST["country_code"]);
                    update_post_meta($post_id,"giftcard_amount",$_POST["giftcard_amount"]);
                    update_post_meta($post_id,"remaining_saldo",$_POST["giftcard_amount"]);
                    update_post_meta($post_id,"gift_customer_type",$_POST["gift_customer_type"]);
                    update_post_meta($post_id,"giftcard_id",$_POST["giftcard_id"]);
                    update_post_meta($post_id,"product_id",$product_id);
                    update_post_meta($post_id,"gift_owner_id",$giftcard_data->post_author);
                    update_post_meta($post_id,"expire_date",$expire_date);

                    $this->createOrder($post_id,$_POST,$product_id,$user_id,$giftcard_data->post_author);

                }


            }else{
                $data_return["error"] = 1;
                $data_return["message"] = 'Product not found!';
            
                return $data_return;
            }


            
        } else {
            $data_return["error"] = 1;
            $data_return["message"] = "giftcard_id not exist";
    
            return $data_return;
        }
        
        // wp_die();
    }

    

    private function createOrder($gift_booking_id,$post,$product_id,$user_id,$owner_id){
        $user_data = get_userdata($user_id);
        $billing_address_1 = get_user_meta($user_id,"billing_address_1",true);

        if($billing_address_1 == ""){
            $billing_address_1 = "..";
        }
        $city = get_user_meta($user_id,"billing_city",true);

        if($city == ""){
            $city = "..";
        }
        $billing_postcode = get_user_meta($user_id,"billing_postcode",true);

        if($billing_postcode == ""){
            $billing_postcode = "0000";
        }

        $address = array(
            'first_name' => $user_data->first_name,
            'last_name'  => $user_data->last_name,
            'address_1' => $billing_address_1,
            //billing_address_2
            'city' => $city,
            //'billing_state'
            'postcode'  => $billing_postcode,
            'country'   => "NO",
            
        );

        global $woocommerce;


                
        // creating woocommerce order
            $order = wc_create_order();


            $args['totals']['subtotal'] = $post["giftcard_amount"];
            $args['totals']['total'] = $post["giftcard_amount"];
            
            
            $order->add_product( wc_get_product( $product_id ), 1, $args );
        
            
            $order->set_address( $address, 'billing' );
            $order->set_address( $address, 'shipping' );
            $order->set_billing_phone( $post["gift_phone"] );
            $order->set_customer_id($user_id);
            $order->set_billing_email( $post["gift_email"] );



            $payment_url = $order->get_checkout_payment_url();
            
            $order->calculate_totals();
            $order->save();
            
            $order->update_meta_data('gift_booking_id', $gift_booking_id);
            $order->update_meta_data('owner_id', $owner_id);
            //get_post_meta($order_id,'owner_id',true);

            $order->save_meta_data();

            update_post_meta($gift_booking_id,"order_id",$order->get_id());
            unset($_POST);

            wp_redirect($payment_url);
            exit;
       
    }

    private static function registerUser($data){

        $_POST = $data;

        $return = array("success" => 0, "user_id" => 0);

        if ( email_exists( $_POST["gift_email"] ) ) {
            $user = get_user_by( 'email', $_POST["gift_email"] );
            $return["success"] = 1;
            $return["user_id"] = $user->ID;
            return $return;
        }

        $password = wp_generate_password( 12, false );

        $first_name = (isset($_POST['gift_first_name'])) ? sanitize_text_field( $_POST['gift_first_name'] ) : '' ;
        $last_name = (isset($_POST['gift_last_name'])) ? sanitize_text_field( $_POST['gift_last_name'] ) : '' ;
        $email = $_POST['gift_email'];
        $email_arr = explode('@', $email);
        $user_login = $email;

        $role =  "owner";

        //echo "<pre>"; print_r($_POST); die;

        $user_data = array(
            'user_login'    => $user_login,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'nickname'      => $first_name,
            'role'          => $role
        );

        $user_id = wp_insert_user( $user_data );

        if($user_id && $user_id > 0){
            if(isset($_POST["country_code"])){
                update_user_meta( $user_id, 'country_code',$_POST["country_code"] );
            }

            if ( isset( $_POST['gift_phone'] ) ){
                update_user_meta($user_id, 'phone', $_POST['gift_phone'] );
            }   
            if ( isset( $_POST['firstname'] ) ){
                update_user_meta($user_id, 'first_name', $_POST['firstname'] );
                update_user_meta($user_id, 'billing_first_name', $_POST['firstname'] );
            }   
            if ( isset( $_POST['lastname'] ) ){
                update_user_meta($user_id, 'last_name', $_POST['lastname'] );
                update_user_meta($user_id, 'billing_last_name', esc_attr( $_POST['lastname'] ) );
            }
            if ( isset( $_POST['email']  )){
                update_user_meta($user_id, 'billing_email', esc_attr( $_POST['email'] ) );
            } 

            if ( isset( $_POST['billing_address_1'] ) ){
                update_user_meta($user_id, 'billing_address_1', esc_attr( $_POST['billing_address_1'] ) );
            }
            if ( isset( $_POST['billing_address_2'] ) ){
                update_user_meta($user_id, 'billing_address_2', esc_attr( $_POST['billing_address_2'] ) );
            }
            if ( isset( $_POST['billing_city'] ) ){
                update_user_meta($user_id, 'billing_city', esc_attr( $_POST['billing_city'] ) );
            }
            if ( isset( $_POST['billing_postcode'] ) ){
                update_user_meta($user_id, 'billing_postcode', esc_attr( $_POST['billing_postcode'] ) );
            }
             if ( isset( $_POST['billing_country'] ) ){
                update_user_meta($user_id, 'billing_country', esc_attr( $_POST['billing_country'] ) );
            }
             if ( isset( $_POST['personalorcompany'] ) ){

                update_user_meta($user_id, 'profile_type', $_POST['personalorcompany']  );
            }
            if ( isset( $_POST['organization_number'] ) ){

                update_user_meta($user_id, 'company_number', $_POST['organization_number']);
            }

            $return["success"] = 1;
            $return["user_id"] = $user_id;
            
        }

        return $return;


    }

    private function save_as_product($post_title,$post_content,$product_id, $author){

        if($post_content == ""){
            $post_content = ".";
        }
        $product = array (
            'post_author' => $author,
            'post_content' => $post_content,
            'post_status' => 'publish',
            'post_title' => $post_title,
            'post_parent' => '',
            'post_type' => 'product',
        );




        // add product if not exist
        if ( ! $product_id ||  get_post_type( $product_id ) != 'product') {
            
            // insert listing as WooCommerce product
            $product_id = wp_insert_post( $product );
            wp_set_object_terms( $product_id, 'listing_booking', 'product_type' );

        } else {

            // update existing product
            $product['ID'] = $product_id;
            wp_update_post ( $product );

        }



        if($product_id == "" || $product_id == "0"){
            return;
        }
        
        // set product category
        $term = get_term_by( 'name', apply_filters( 'listeo_default_product_category', 'Listeo booking'), 'product_cat', ARRAY_A );

        try{
            if ( ! $term ) { 
                $term = wp_insert_term(
                        apply_filters( 'listeo_default_product_category', 'Listeo booking'),
                        'product_cat',
                        array(
                            'description'=> __( 'Listings category', 'listeo-core' ),
                            'slug' => str_replace( ' ', '-', apply_filters( 'listeo_default_product_category', 'Listeo booking') )
                        )
                        ); 
                if(!isset($term->errors)){
                    wp_set_object_terms( $product_id, $term['term_id'], 'product_cat');
                }
                
            }
        } catch (\Exception $ex) {
            //echo $ex->getMessage();
        }

        

        return $product_id;
    }	

    public function giftcard_bookings() {
        // Enqueue DataTables CSS and JavaScript
        wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css');
        wp_enqueue_style('datatables-responsive-css', 'https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js', ['jquery'], null, true);
        wp_enqueue_script('datatables-responsive-js', 'https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js', [], null, true);
    
        // Output HTML and JavaScript for the DataTable
        ob_start();
        require GIBBS_GIFT_PATH . 'views/giftcard_bookings.php'; 
        return ob_get_clean();
    }

    // Fetch gift card data for DataTable
    function fetch_giftcard_data() {
        $draw = $_POST['draw'];
        $start = $_POST['start'];
        $length = $_POST['length'];
        $searchValue = $_POST['searchData'];
    
        $columns = ['gift_code', 'purchased_by', 'giftcard_amount', 'remaining_saldo', 'purchased_date', 'expire_date'];
        $orderColumnIndex = $_POST['order'][0]['column'];
        $orderColumn = $columns[$orderColumnIndex];
        $orderDir = $_POST['order'][0]['dir'];

        $group_admin = get_group_admin();
        if($group_admin == ""){
            $group_admin = get_current_user_ID();
        }
    
        $args = [
            'post_type'      => 'giftcard_booking',
            'posts_per_page' => $length,
            'offset'         => $start,
            'orderby'        => $orderColumn == 'purchased_date' ? 'date' : 'meta_value',
            'order'          => $orderDir,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'gift_owner_id',
                    'value'   => $group_admin,
                    'compare' => '='
                ],
                [
                    'key'     => 'order_id',
                    'value'   => '',
                    'compare' => '!=' // Ensures order_id is not blank
                ],
                [
                    'key'     => 'order_status',
                    'value'   => 'completed',
                    'compare' => '=' // Ensures order_id is not blank
                ]
            ]
        ];
    
        if (!empty($searchValue)) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'     => 'email',
                    'value'   => $searchValue,
                    'compare' => 'LIKE'
                ],
                [
                    'key'     => 'gift_code',
                    'value'   => $searchValue,
                    'compare' => 'LIKE'
                ]
            ];
        }
    
        $query = new WP_Query($args);
        $totalRecords = (new WP_Query(['post_type' => 'giftcard_booking', 'posts_per_page' => -1]))->found_posts;
    
        $data = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $expire_date = get_post_meta($post_id, 'expire_date', true);
                $is_active = strtotime($expire_date) > time();

                $purchased_date = get_the_date('Y-m-d', $post_id); 
                $expiration_threshold = date('Y-m-d', strtotime($purchased_date . ' +2 years'));
                $show_actions = (strtotime($expiration_threshold) > time());
    
                $data[] = [
                    'id' => $post_id,
                    'code' => get_post_meta($post_id, 'gift_code', true),
                    'purchased_by' => get_post_meta($post_id, 'email', true),
                    'purchased_amount' => get_post_meta($post_id, 'giftcard_amount', true),
                    'remaining_saldo' => get_post_meta($post_id, 'remaining_saldo', true),
                    'purchased_date' => get_the_date('F j, Y', $post_id),
                    'expire_date' => get_post_meta($post_id, 'expire_date', true),
                    'cancellation_reason' => $this->get_formatted_cancellation_reason($post_id),
                    'is_active' => $is_active,
                    'show_actions' => $show_actions
                ];
            }
        }
        wp_reset_postdata();
    
        $response = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $query->found_posts,
            "data" => $data
        ];
    
        echo json_encode($response);
        wp_die();
    }


    // Deactivate gift card by setting expire date to 1 day before today
    public function deactivate_giftcard() {
        $post_id = intval($_POST['post_id']);
        $expire_date = date('Y-m-d', strtotime('-1 day'));
        update_post_meta($post_id, 'expire_date', $expire_date);

        wp_send_json_success(['message' => 'Gift card deactivated successfully.']);
    }

    public function activate_giftcard() {
        $post_id = intval($_POST['post_id']);
        $purchased_date = get_the_date('Y-m-d', $post_id);

        // Calculate the expiration date as 2 years from the purchased date
        $expire_date = date('Y-m-d', strtotime($purchased_date . ' +2 years'));
        update_post_meta($post_id, 'expire_date', $expire_date);

        wp_send_json_success(['message' => 'Gift card activated successfully.']);
    }
    

    public function customer_refund() {
        ob_start();
        require GIBBS_GIFT_PATH . 'views/customer_refund.php';
        return ob_get_clean();
    }

    private function calculate_refund_amount($booking) {
        $amount = 0;
        
        if (!$booking) {
            return array(
                'amount' => 0,
                'policy' => array(
                    'hours' => 0,
                    'amount' => 0,
                    'name' => 'No Refund'
                ),
                'total_hours' => 0,
                'original_amount' => 0,
                'policy_type' => 'no_refund'
            );
        }

        // Get refund policy from listing post meta
        $listing_id = $booking->listing_id;
        $refund_policy_type = get_post_meta($listing_id, 'refund_policy', true);
        
        // Define refund policies
        $refund_policies = array(
            'flexible_refund' => array(
                'hours' => 12,
                'amount' => 1.0, // 100% as voucher
                'name' => 'Flexible'
            ),
            'standard_refund' => array(
                'hours' => 72,
                'amount' => 0.5, // 50% as voucher
                'name' => 'Standard'
            ),
            'no_refund' => array(
                'hours' => 0,
                'amount' => 0,
                'name' => 'No Refund'
            )
        );
        
        $current_policy = isset($refund_policies[$refund_policy_type]) 
        ? $refund_policies[$refund_policy_type] 
        : $refund_policies['no_refund'];


    
        

        $booking_start = $booking->date_start;
        $start_timestamp = strtotime($booking_start);
        $current_timestamp = current_time('timestamp');
        
        // Calculate hours until policy deadline
        $policy_deadline = $start_timestamp - ($current_policy['hours'] * 3600);
        $hours_until_deadline = round(($policy_deadline - $current_timestamp) / 3600);
        
        // Calculate days, hours, and minutes until deadline
        $days = floor($hours_until_deadline / 24);
        $remaining_hours = $hours_until_deadline % 24;
        $minutes = round((($policy_deadline - $current_timestamp) % 3600) / 60);
        
        // For total hours calculation (used in refund eligibility check)
        $total_hours = round(($start_timestamp - $current_timestamp) / 3600);
        
        // Get amount from booking price field (stored as JSON)
        if (!empty($booking->price)) {
            $price_data = json_decode($booking->price, true);
            if ($price_data !== null) {
                $amount = floatval($price_data);
                
                // Calculate refund amount based on policy
                if ($current_policy['hours'] > 0 && $total_hours >= $current_policy['hours']) {
                    $amount = $amount * $current_policy['amount'];
                } else {
                    $total_hours = 0;
                    $amount = 0; // No refund
                }
            }
        }

        return array(
            'amount' => $amount,
            'policy' => $current_policy,
            'days' => $days,
            'remaining_hours' => $remaining_hours,
            'minutes' => $minutes,
            'total_hours' => $total_hours,
            'original_amount' => isset($price_data) ? floatval($price_data) : 0,
            'policy_type' => $refund_policy_type // Add the policy type to the return array
        );
    }

    public function process_refund() {
        // Verify nonce and user permissions
        // if (!current_user_can('edit_posts')) {
        //     wp_send_json_error(['message' => 'Permission denied']);
        //     return;
        // }

        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : 0;
        $booking_id = isset($_POST['booking_id']) ? $_POST['booking_id'] : 0;
        $reason_option = isset($_POST['reason_option']) ? sanitize_text_field($_POST['reason_option']) : '';
        $reason_text = isset($_POST['reason_text']) ? sanitize_textarea_field($_POST['reason_text']) : '';

        if (!$order_id || !$reason_option) {
            wp_send_json_error(['message' => 'Missing required fields']);
            return;
        }

        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order']);
            return;
        }

        try {
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'bookings_calendar';

            // Get the booking details
            $sql = $wpdb->prepare("SELECT * FROM $bookings_table WHERE id = %d", $booking_id);
            $booking = $wpdb->get_row($sql);

            if (!$booking) {
                wp_send_json_error(['message' => 'Booking not found']);
                return;
            }

            // Calculate refund amount
            $refund_data = $this->calculate_refund_amount($booking);

            $amount = $refund_data['amount'];

            $formatted_reason = '';
                if ($reason_option === 'other') {
                    $formatted_reason = 'Kanselleringsårsak: ' . $reason_text;
                } else {
                    $reason_map = array(
                        'change_of_plans' => 'Endring i planer',
                        'found_better_option' => 'Funnet bedre alternativ',
                        'personal_emergency' => 'Personlig nødstilfelle',
                        'weather_concerns' => 'Værforhold',
                        'booking_mistake' => 'Bestillingsfeil'
                    );
                    $formatted_reason = 'Kanselleringsårsak fra kunde: ' . (isset($reason_map[$reason_option]) ? $reason_map[$reason_option] : $reason_text);
                }

            if ($amount <= 0) {

                $existing_description = !empty($booking->description) ? $booking->description . ' | ' : '';
                $new_description = $existing_description . $formatted_reason;

                $update_result = $wpdb->update(
                    $bookings_table,
                    array(
                        'status' => 'cancelled',
                        'description' => $new_description
                    ),
                    array('id' => $booking_id),
                    array('%s', '%s'),
                    array('%d')
                );

                update_post_meta($order_id, 'canceled_booking_zero', "true");

                $html = '<div class="refund-status notification-box success">
                        <div class="notification-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="notification-content">
                            <h3>You have successfully cancelled your reservation.</h3>
                        </div>
                    </div>';

                wp_send_json_success(['message' => 'Refund processed successfully', 'html' => $html]);
                return;
            }

            $listing_data = get_post($booking->listing_id);
            $listing_name = $listing_data->post_title;

            // Create voucher post
            $voucher_data = [
                'post_title' => 'Refund Voucher - ' . $listing_name,
                'post_content' => 'Refund voucher for order #' . $order_id,
                'post_status' => 'publish',
                'post_type' => 'giftcard_booking',
                'post_author' => $booking->booking_author
            ];

            $voucher_id = wp_insert_post($voucher_data);

            if (!is_wp_error($voucher_id)) {
                // Generate unique voucher code
                $voucher_code = $this->generateRandomStringWithNumber($voucher_id);

                // Save cancellation reason to order metadata
                update_post_meta($order_id, 'cancellation_reason_option', $reason_option);
                update_post_meta($order_id, 'cancellation_reason_text', $reason_text);
                
                update_post_meta($order_id, 'voucher_refund', "true");
                update_post_meta($order_id, 'voucher_code', $voucher_code);
                
                // Set voucher metadata
                update_post_meta($voucher_id, 'gift_code', $voucher_code);
                update_post_meta($voucher_id, 'email', $order->get_billing_email());
                update_post_meta($voucher_id, 'giftcard_amount', $amount);
                update_post_meta($voucher_id, 'remaining_saldo', $amount);
                update_post_meta($voucher_id, 'expire_date', date('Y-m-d', strtotime('+1 year')));
                update_post_meta($voucher_id, 'order_status', 'completed');
                update_post_meta($voucher_id, 'voucher_type', 'refund');
                update_post_meta($voucher_id, 'original_order_id', $order_id);
                update_post_meta($voucher_id, 'gift_owner_id', $booking->owner_id);
                
                // Also save cancellation reason to voucher metadata for displaying in the table
                update_post_meta($voucher_id, 'cancellation_reason_option', $reason_option);
                update_post_meta($voucher_id, 'cancellation_reason_text', $reason_text);

                $html = $this->send_refund_email($voucher_id); 

                // Format the cancellation reason for the description column
                

                // Check if there's an existing description and preserve it
                $existing_description = !empty($booking->description) ? $booking->description . ' | ' : '';
                $new_description = $existing_description . $formatted_reason;

                $update_result = $wpdb->update(
                    $bookings_table,
                    array(
                        'status' => 'cancelled',
                        'description' => $new_description
                    ),
                    array('id' => $booking_id),
                    array('%s', '%s'),
                    array('%d')
                );

                wp_send_json_success(['message' => 'Refund processed successfully', 'html' => $html]);
            } else {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function send_refund_email($voucher_id) {
        

        $gift_code = get_post_meta($voucher_id, 'gift_code', true);
        $refund_data = $this->getGiftDataByGiftCode($gift_code); 
        $to = $refund_data['purchased_by'];
        

        // Email subject
        $subject = "Here is your voucher";

        // Email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8'
        ];

        // Generate PDF
        require_once GIBBS_GIFT_PATH . 'dompdf/vendor/dompdf/dompdf/src/Dompdf.php';
        require_once GIBBS_GIFT_PATH . 'dompdf/vendor/dompdf/dompdf/src/Options.php';

        // Get email body from template
        ob_start();
        require GIBBS_GIFT_PATH . 'views/customer_refund_email_template.php';
        $body = ob_get_clean();
        // Generate PDF
        $pdf = new \Dompdf\Dompdf();
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $pdf->setOptions($options);

        $pdf->loadHtml("<style>.download_pdf_div{display:none}</style>" . $body);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        // Save PDF temporarily
        $upload_dir = wp_upload_dir();
        $pdfFilePath = $upload_dir['path'] . '/refund_voucher_' . $voucher_id . '.pdf';
        file_put_contents($pdfFilePath, $pdf->output());

        // Send email with PDF attachment
        $attachments = [$pdfFilePath];
        wp_mail($to, $subject, $body, $headers, $attachments);

        // Clean up temporary PDF file
        unlink($pdfFilePath);

        return $body;
    }

    public function downloadRefundPDF() {
        if (!isset($_POST['refund_id'])) {
            wp_redirect(home_url());
            exit;
        }

        $refund_id = intval($_POST['refund_id']);
        $refund = wc_get_order($refund_id);
        
        if (!$refund) {
            wp_redirect(home_url());
            exit;
        }

        // Get order data
        $order_items = $refund->get_items();
        $first_item = reset($order_items);
        $listing_name = $first_item ? $first_item->get_name() : '';

        // Set up refund data
        $refund_data = [
            'refund_id' => $refund_id,
            'amount' => $refund->get_total(),
            'listing_name' => $listing_name,
            'valid_until' => date('Y-m-d', strtotime('+1 year')),
        ];

        require GIBBS_GIFT_PATH . 'dompdf/vendor/dompdf/dompdf/src/Dompdf.php';
        require GIBBS_GIFT_PATH . 'dompdf/vendor/dompdf/dompdf/src/Options.php';

        // Get template content
        ob_start();
        require GIBBS_GIFT_PATH . 'views/customer_refund_email_template.php';
        $body = ob_get_clean();

        // Generate PDF
        $pdf = new \Dompdf\Dompdf();
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $pdf->setOptions($options);

        $pdf->loadHtml("<style>.download_pdf_div{display:none}</style>" . $body);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream("Refund_Voucher.pdf", array("Attachment" => true));
        exit;
    }

    public function refund_vouchers() {
        // Enqueue DataTables CSS and JavaScript
        wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css');
        wp_enqueue_style('datatables-responsive-css', 'https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js', ['jquery'], null, true);
        wp_enqueue_script('datatables-responsive-js', 'https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js', [], null, true);
    
        // Output HTML and JavaScript for the DataTable
        ob_start();
        require GIBBS_GIFT_PATH . 'views/refund_vouchers.php'; 
        return ob_get_clean();
    }

    public function fetch_voucher_data() {
        // Check nonce and permissions if needed
        
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search = isset($_POST['searchData']) ? sanitize_text_field($_POST['searchData']) : '';

        error_log('Fetching voucher data with params: ' . json_encode([
            'draw' => $draw,
            'start' => $start,
            'length' => $length,
            'search' => $search
        ]));

        $group_admin = get_group_admin();
        if($group_admin == ""){
            $group_admin = get_current_user_ID();
        }

        error_log('Group admin ID: ' . $group_admin);

        // Query arguments
        $args = array(
            'post_type' => 'giftcard_booking',
            'post_status' => 'publish',
            'posts_per_page' => $length,
            'offset' => $start,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'voucher_type',
                    'value' => 'refund',
                    'compare' => '='
                ),
                array(
                    'key' => 'gift_owner_id',
                    'value' => $group_admin,
                    'compare' => '='
                )
            )
        );

        error_log('WP_Query args: ' . json_encode($args));

        // Add search condition if search term exists
        if (!empty($search)) {
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key' => 'email',
                    'value' => $search,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'gift_code',
                    'value' => $search,
                    'compare' => 'LIKE'
                )
            );
        }

        // Get total count without pagination
        $total_query = new WP_Query(array_merge(
            $args,
            array('posts_per_page' => -1, 'offset' => 0)
        ));
        $total_records = $total_query->found_posts;

        error_log('Total records found: ' . $total_records);

        // Get paginated results
        $query = new WP_Query($args);
        $filtered_records = $query->found_posts;

        $data = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $expire_date = get_post_meta($post_id, 'expire_date', true);
                $is_active = strtotime($expire_date) > time();

                $purchased_date = get_the_date('Y-m-d', $post_id); 
                $expiration_threshold = date('Y-m-d', strtotime($purchased_date . ' +2 years'));
                $show_actions = (strtotime($expiration_threshold) > time());
                
                $data[] = array(
                    'id' => $post_id,
                    'code' => get_post_meta($post_id, 'gift_code', true),
                    'purchased_by' => get_post_meta($post_id, 'email', true),
                    'purchased_amount' => get_post_meta($post_id, 'giftcard_amount', true),
                    'remaining_saldo' => get_post_meta($post_id, 'remaining_saldo', true),
                    'purchased_date' => get_the_date('F j, Y', $post_id),
                    'expire_date' => get_post_meta($post_id, 'expire_date', true),
                    'cancellation_reason' => $this->get_formatted_cancellation_reason($post_id),
                    'is_active' => $is_active,
                    'show_actions' => $show_actions
                );
            }
        }
        wp_reset_postdata();

        $response = array(
            'draw' => $draw,
            'recordsTotal' => $total_records,
            'recordsFiltered' => $filtered_records,
            'data' => $data
        );

        error_log('Sending response: ' . json_encode($response));

        wp_send_json($response);
    }

    private function get_formatted_cancellation_reason($post_id) {
        // Get the original order ID from the voucher metadata
        $original_order_id = get_post_meta($post_id, 'original_order_id', true);
        
        if (!$original_order_id) {
            return 'Ikke tilgjengelig';
        }
        
        // Get the cancellation reason from order metadata
        $reason_option = get_post_meta($post_id, 'cancellation_reason_option', true);
        $reason_text = get_post_meta($post_id, 'cancellation_reason_text', true);
        
        if (empty($reason_option)) {
            // Try getting from original order if not present on voucher
            $reason_option = get_post_meta($original_order_id, 'cancellation_reason_option', true);
            $reason_text = get_post_meta($original_order_id, 'cancellation_reason_text', true);
        }
        
        if (empty($reason_option)) {
            return 'Ikke spesifisert';
        }
        
        // Map reason option to readable text
        $reason_map = array(
            'change_of_plans' => 'Endring i planer',
            'found_better_option' => 'Funnet bedre alternativ',
            'personal_emergency' => 'Personlig nødstilfelle',
            'weather_concerns' => 'Værforhold',
            'booking_mistake' => 'Bestillingsfeil',
            'other' => $reason_text
        );
        
        return isset($reason_map[$reason_option]) ? $reason_map[$reason_option] : 'Ikke spesifisert';
    }
}

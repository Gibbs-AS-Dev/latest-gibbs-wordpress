<?php
/**
 * Email Template API Class
 * Handles all email template-related API requests
 * Follows the same error handling pattern as WalletApi
 */

class EmailTemplateApi {
    private $db;
    private $response;
    private $current_user_id = null;
    private $current_user = null;

    public function __construct() {
        try {
            $this->db = new EmailTemplateDatabase();
            $this->response = new CoreResponse();
        } catch (Exception $e) {
            CoreResponse::serverError('Failed to initialize email template API: ' . $e->getMessage());
        }
    }

    /**
     * Main method to handle email template API requests
     */
    public function handleEmailTemplateRequest() {
        try {
            $method = CoreResponse::getRequestMethod();
            $data = CoreResponse::getRequestData();

            $this->authenticateUser();
            
            switch ($method) {
                case 'GET':
                    $this->handleEmailTemplateGetRequest($data);
                    break;
                case 'POST':
                    $this->handleEmailTemplatePostRequest($data);
                    break;
                case 'PUT':
                    $this->handleEmailTemplatePutRequest($data);
                    break;
                case 'DELETE':
                    $this->handleEmailTemplateDeleteRequest($data);
                    break;
                default:
                    CoreResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            CoreResponse::serverError('Email Template API error: ' . $e->getMessage());
        }
    }

    /**
     * Handle GET requests for email template operations
     */
    private function handleEmailTemplateGetRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'getEmailTemplates':
                $this->requireAuth();
                $this->getEmailTemplatesForOwner($data);
                break;
            case 'get_template':
                $this->requireAuth();
                $this->getTemplate($data);
                break;
            case 'get_all_templates':
                $this->requireAuth();
                $this->getAllTemplates($data);
                break;
            case 'get_template_by_name':
                $this->requireAuth();
                $this->getTemplateByName($data);
                break;
            case 'get_email_logs':
                $this->requireAuth();
                $this->getEmailLogs($data);
                break;
            case 'get_template_stats':
                $this->requireAuth();
                $this->getTemplateStats($data);
                break;
            case 'trigger_email':
                $this->trigger_email($data);
                break;
            case 'triggerSendgridWebhook':
                $this->triggerSendgridWebhook($data);
                break;    
            case 'getEmailLogsData':
                $this->requireAuth();
                $this->getEmailLogsData($data);
                break;
            case 'getEmailSettings':
                $this->requireAuth();
                $this->getEmailSettings($data);
                break;
            default:
                CoreResponse::error('Invalid action for GET request', 400);
                break;
        }
    }

    /**
     * Get templates for owner (mapped for frontend)
     */
    private function getEmailTemplatesForOwner($input) {
        try {
            $ownerId = isset($input['owner_id']) ? intval($input['owner_id']) : 0;
            $limit = isset($input['limit']) ? intval($input['limit']) : 100;
            if ($ownerId <= 0) {
                CoreResponse::error('Missing or invalid owner_id', 400);
                return;
            }

            $rows = $this->db->getTemplatesByOwner($ownerId, $limit);

            // Map DB columns to frontend expected fields
            $templates = array_map(function($r) {
                return [
                    'id' => intval($r['id']),
                    'name' => $r['template_name'] ?? '',
                    'subject' => $r['template_header'] ?? '',
                    'content' => $r['template_content'] ?? '',
                    'copyTo' => $r['email_cc'] ?? '',
                    'event' => $r['trigger_type'] ?? '',
                    'delay' => is_numeric($r['delay']) ? intval($r['delay']) : 0,
                    'active' => isset($r['active']) ? ($r['active'] === '1' || $r['active'] === 1) : false,
                    'before_booking_unique_minute' => isset($r['before_booking_unique_minute']) && is_numeric($r['before_booking_unique_minute']) ? intval($r['before_booking_unique_minute']) : 0,
                    'send_once' => isset($r['send_once']) ? ($r['send_once'] === '1' || $r['send_once'] === 1) : 0,
                    'editorType' => isset($r['editorType']) ? $r['editorType'] : 'rich',
                    'type' => $r['template_type'] ?? 'email',
                    'owner_id' => intval($r['owner_id'])
                ];
            }, $rows ?: []);

            CoreResponse::success([
                'templates' => $templates
            ], 'Email templates retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get email templates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle POST requests for email template operations
     */
    private function handleEmailTemplatePostRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'createEmailTemplate':
                $this->requireAuth();
                $this->createTemplate($data);
                break;
            case 'deleteEmailTemplate':
                $this->requireAuth();
                $this->deleteTemplate($data);
                break;
            case 'hardDeleteEmailTemplate':
                $this->requireAuth();
                $this->hardDeleteTemplate($data);
                break;
            case 'updateEmailTemplate':
                $this->requireAuth();
                $this->updateTemplateMapped($data);
                break;
            case 'send_email':
                $this->requireAuth();
                $this->sendEmail($data);
                break;
            case 'test_email':
                $this->requireAuth();
                $this->testEmail($data);
                break;
            case 'saveEmailSettings':
                $this->requireAuth();
                $this->saveEmailSettings($data);
                break;
            case 'saveSendgridData':
                $this->saveSendgridData($data);
                break;
            default:
                CoreResponse::error('Invalid action for POST request', 400);
                break;
        }
    }

    public function saveSendgridData($input) {

        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }
        }
        if(isset($input["log_id"]) && $input["log_id"] != ""){

            global $wpdb;

            if(isset($input["message_id"]) && $input["message_id"] != ""){

               $wpdb->update($wpdb->prefix . 'gibbs_email_log', array('sendgrid_message_id' => $input["message_id"]), array('trigger_log_id' => $input["log_id"]));
               

            }else{

                $wpdb->update($wpdb->prefix . 'gibbs_email_log', array('sendgrid_message_id' => $input["message_id"], "status"=> "failed"), array('trigger_log_id' => $input["log_id"]));

            }

        }
        // $data = $input["log_id"];

        // $log_dir = WP_CONTENT_DIR . '/sendgrid-logs';
        //             if (!file_exists($log_dir)) {
        //                 mkdir($log_dir, 0755, true);
        //             }
                    
        //             $log_file = $log_dir . '/webhook2.log';
        //            $log_data = date('Y-m-d H:i:s') . " | " . json_encode($data) . "\n";
                    
        //             file_put_contents($log_file, $log_data, FILE_APPEND);


        // echo "<pre>"; print_r($data); die;
    }

    public function triggerSendgridWebhook($input) {
        $webhook_data = file_get_contents('php://input');
        // $webhook_data = '[{"email":"sk81930@gmail.com","event":"delivered","ip":"159.183.231.27","response":"250 2.0.0 OK 1760877474 e9e14a558f8ab-430d07aeb2dsi37225145ab.137 - gsmtp","sg_event_id":"ZGVsaXZlcmVkLTAtMzM0MDY3MTktTUMzVFktYmhSdGVOMm9XZEgzT05RUS0w","sg_message_id":"MC3TY-bhRteN2oWdH3ONQQ.recvd-7d86b8fd66-cq829-1-68F4DBA0-5.0","smtp-id":"<MC3TY-bhRteN2oWdH3ONQQ@geopod-ismtpd-2>","timestamp":1760877474,"tls":1}]';

        $data = json_decode($webhook_data, true);

        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }
        }
        

        try {

            global $wpdb;

            if (is_array($data) && !empty($data)) {
                foreach ($data as $event) {

                    $fullMessageId = $event['sg_message_id'];

                    // Extract the short ID (the part before the first ".")
                    $shortMessageId = explode('.', $fullMessageId)[0];

                    // Extract status from "event"
                    $status = $event['event'];

                    // Optional: extract timestamp
                    $eventTime = date('Y-m-d H:i:s', $event['timestamp']);


                    $email = $event['email'];
                    // Extract relevant data

                    if(strtolower($status) === "delivered"  || strtolower($status) === "processed"){
                        $status = "Sent";
                    }

                    $wpdb->update($wpdb->prefix . 'gibbs_email_log', array("status"=> $status), array('sendgrid_message_id' => $shortMessageId));
                   
                    // Save webhook data to log file
                    //     $log_dir = WP_CONTENT_DIR . '/sendgrid-logs';
                    //     if (!file_exists($log_dir)) {
                    //         mkdir($log_dir, 0755, true);
                    //     }
                        
                    //     $log_file = $log_dir . '/webhook.log';
                    //    echo  $log_data = date('Y-m-d H:i:s') . " | log ID: $shortMessageId | Event: $status | Email: $email | Timestamp: $eventTime\n";
                        
                    //     file_put_contents($log_file, $log_data, FILE_APPEND);
                }
            }

            CoreResponse::success(null, 'Webhook processed successfully');
        } catch (Exception $e) {
            return false;
           // CoreResponse::error('Failed to get Email logs: ' . $e->getMessage(), 500);
        }
    }

    private function getEmailLogsData($input) {
        try {
            $userId = $this->getCurrentUserId();
            $emailLogs = $this->db->getEmailLogsWithPagination($input['page'], $input['per_page'], $input['search'], $input['owner_id']);
            CoreResponse::success($emailLogs, 'Email logs retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get Email logs: ' . $e->getMessage(), 500);
        }
    }  

    public function trigger_email($input) {
        try {

            $all_bookings = $this->db->getAllBookingsPaid();

            // $email_data = array();
            // $email_data[] = array("recipient_email" => "dlkdhfduhfuhuhrufhr@gmail.com", "recipient_name" => "sk81932", "subject" => "test", "body" => "test", "copy_to" => "sk8193@gmail.com", "template_type" => "email", "phone" => "1234567890", "country_code" => "1","email_logs_id" => "log_id_7_416013");

            // $email_data[] = array("recipient_email" => "sk81930@gmail.com", "recipient_name" => "sk8193", "subject" => "test", "body" => "test", "copy_to" => "sk8193@gmail.com", "template_type" => "email", "phone" => "1234567890", "country_code" => "1","email_logs_id" => "log_id_7_416012");

           

            // CoreResponse::success($email_data, 'Email data fetched successfully');

            // die;
           



            $email_data = array();

            if(!empty($all_bookings)){
                foreach($all_bookings as $booking){

                    
                    $paid_activated_templates = $this->db->getPaidActivatedTemplates($booking['owner_id']);

                    
                    if(!empty($paid_activated_templates)){
                        foreach($paid_activated_templates as $template){


                            $templateCreatedAt = strtotime($template['created_at']);
                            $bookingCreatedAt = strtotime($booking['created_at']);

                            if ($templateCreatedAt >= $bookingCreatedAt) {
                                continue;
                            }


                            $email_sent = $this->db->getBookingMeta($booking['id'], $template["trigger_type"]."_".$template["id"]);
                            if($email_sent && $email_sent['meta_value'] == "sent"){
                                continue;
                            }
                            $data = $this->sendPaidActivatedEmail($template, $booking);
                            if($data && isset($data['recipient_email'])){
                                $email_data[] = $data;
                            }
                        }
                    }
                }
            }

            $start_dates_bookings = $this->db->getBookingsForStartDates();

           // die;


           


            if(!empty($start_dates_bookings)){
                foreach($start_dates_bookings as $booking){

                    
                    $start_date_activated_templates = $this->db->getStartDateActivatedTemplates($booking['owner_id']);
                    
                    if(!empty($start_date_activated_templates)){

                       


                        foreach($start_date_activated_templates as $template){

                            $templateCreatedAt = strtotime($template['created_at']);
                            $bookingCreatedAt = strtotime($booking['created_at']);

                            if ($templateCreatedAt >= $bookingCreatedAt) {
                                continue;
                            }

                          
                           $email_sent = $this->db->getBookingMeta($booking['id'], $template["trigger_type"]."_".$template["id"]);
                            if($email_sent && $email_sent['meta_value'] == "sent"){
                                continue;
                            }
                            if($template["trigger_type"] == "before_booking_start"){
                                $data = $this->sendBeforeBookingStartEmail($template, $booking);
                                if($data && isset($data['recipient_email'])){
                                    $email_data[] = $data;
                                }
                            }else if($template["trigger_type"] == "before_booking_start_unique"){
                                $data = $this->sendBeforeBookingStartUniqueEmail($template, $booking);

                               
                                if($data && isset($data['recipient_email'])){
                                    $email_data[] = $data;
                                }
                            }
                            // $data = $this->sendStartAndEndDatesActivatedEmail($template, $booking);
                            // if($data && isset($data['recipient_email'])){
                            //     $email_data[] = $data;
                            // }
                        }
                    }
                }
            }

            $end_dates_bookings = $this->db->getBookingsForEndDates();

            
            if(!empty($end_dates_bookings)){
                foreach($end_dates_bookings as $booking){

                    
                    $end_date_activated_templates = $this->db->getEndDateActivatedTemplates($booking['owner_id']);
                    
                    if(!empty($end_date_activated_templates)){



                        foreach($end_date_activated_templates as $template){

                            $templateCreatedAt = strtotime($template['created_at']);
                            $bookingCreatedAt = strtotime($booking['created_at']);

                            if ($templateCreatedAt >= $bookingCreatedAt) {
                                continue;
                            }

                          
                           $email_sent = $this->db->getBookingMeta($booking['id'], $template["trigger_type"]."_".$template["id"]);
                            if($email_sent && $email_sent['meta_value'] == "sent"){
                                continue;
                            }
                            if($template["trigger_type"] == "after_booking_end"){
                                $data = $this->sendAfterBookingEndEmail($template, $booking);
                                if($data && isset($data['recipient_email'])){
                                    $email_data[] = $data;
                                }
                            }
                            // $data = $this->sendStartAndEndDatesActivatedEmail($template, $booking);
                            // if($data && isset($data['recipient_email'])){
                            //     $email_data[] = $data;
                            // }
                        }
                    }
                }
            }

            // echo '<pre>';
            // print_r($email_data);
            // echo '</pre>';
            // die;
            CoreResponse::success($email_data, 'Email data fetched successfully');


            // echo '<pre>';
            // print_r($all_bookings);
            // echo '</pre>';
        } catch (Exception $e) {
            CoreResponse::error('Failed to trigger email: ' . $e->getMessage(), 500);
        }
    }

    public function sendBeforeBookingStartEmail($template, $booking) {
        try {

            $currentdateTime = $this->db->getCurrentDate();

            $currentDate = $currentdateTime['current_datetime']; 

            $email_logs_id = "log_id_".$template["id"]."_".$booking["id"];
            $email_data = $this->getEmailData($template, $booking);

            if($template["active"] == 0){

                
            
                if($email_data && isset($email_data['recipient_email'])){
                   // $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "stopped");
                }

                return false;

            }else{

                if($email_data && isset($email_data['recipient_email'])){
                    $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "created");
                }

            }

           

            if($template["trigger_type"] == "before_booking_start"){
                
                // Get delay from template (in minutes)
                $delay = isset($template['delay']) ? intval($template['delay']) : 0;

                $send_once = isset($template['send_once']) ? intval($template['send_once']) : 0;

                $user_meta_key = "sent_once_".$booking["bookings_author"]."_".$booking["listing_id"]."_".$template["id"];

            

                if($send_once == 1){
                    $email_sent_once = $this->db->getUserMeta($booking['bookings_author'], $user_meta_key);
    
                    if(!empty($email_sent_once)){
    
                        if($email_data && isset($email_data['recipient_email'])){
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "sent_once");
                        }
    
                        return false;
                    }
                }

            
                $bookingStartTime = strtotime($booking['date_start']);
                $triggerTime = $bookingStartTime - ($delay * 60); // Convert minutes to seconds
                $currentTime = strtotime($currentDate);


                
                // Check if current time has reached or passed the trigger time
                if ($currentTime >= $triggerTime) {

                    $data = $this->sendDelayedEmail($template, $booking, $email_logs_id);
                    if($data && isset($data['recipient_email'])){
                        if($email_data && isset($email_data['recipient_email'])){
                            if($send_once == 1){
                                $this->db->updateUserMeta($booking['bookings_author'], $user_meta_key, "sent");
                            }
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "processing");
                        }
                        return $data;
                    }else{
                        if($email_data && isset($email_data['recipient_email'])){
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "failed");
                        }
                        return false;
                    }
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    public function sendBeforeBookingStartUniqueEmail($template, $booking) {
        try {

            $currentdateTime = $this->db->getCurrentDate();

            $currentDate = $currentdateTime['current_datetime']; 

            $email_logs_id = "log_id_".$template["id"]."_".$booking["id"];
            $email_data = $this->getEmailData($template, $booking);

            if($template["active"] == 0){

                
            
                if($email_data && isset($email_data['recipient_email'])){
                    //$this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "stopped");
                }

                return false;

            }else{

                if($email_data && isset($email_data['recipient_email'])){
                    $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "created");
                }

            }


           


            // echo '<pre>';
            // print_r($before_booking_unique_minute_date);
            // echo '<br>';
            // print_r($booking['date_start']);
            // echo '</pre>';
           

            if($template["trigger_type"] == "before_booking_start_unique"){

                $send_once = isset($template['send_once']) ? intval($template['send_once']) : 0;

                $user_meta_key = "sent_once_".$booking["bookings_author"]."_".$booking["listing_id"]."_".$template["id"];

            

                if($send_once == 1){
                    $email_sent_once = $this->db->getUserMeta($booking['bookings_author'], $user_meta_key);
    
                    if(!empty($email_sent_once)){
    
                        if($email_data && isset($email_data['recipient_email'])){
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "sent_once");
                        }
    
                        return false;
                    }
                }

                $before_booking_unique_minute = isset($template['before_booking_unique_minute']) ? intval($template['before_booking_unique_minute']) : 0;

                


               
                // echo '<pre>';
                // print_r($booking);
                // print_r($currentDate);
                // print_r($before_booking_unique_minute_date);
                // echo '</pre>';


                // die;
                

                //if(empty($check_overlapping_bookings)){
                    $delay = isset($template['delay']) ? intval($template['delay']) : 0;
                    $bookingStartTime = strtotime($booking['date_start']);
                    $triggerTime = $bookingStartTime - ($delay * 60); // Convert minutes to seconds
                    $currentTime = strtotime($currentDate);

                    // Check if current time has reached or passed the trigger time
                    if ($currentTime >= $triggerTime) {

                        $before_booking_unique_minute_date = date('Y-m-d H:i:s', strtotime($booking['date_start'] . ' - ' . $before_booking_unique_minute . ' minutes'));

                        $check_overlapping_bookings = $this->db->checkIfBookingHasPreviousBooking($before_booking_unique_minute_date, $booking['date_start'], $booking['listing_id']);

                        if(empty($check_overlapping_bookings)){
                            $data = $this->sendDelayedEmail($template, $booking, $email_logs_id);
                            if($data && isset($data['recipient_email'])){
                                if($email_data && isset($email_data['recipient_email'])){
                                    if($send_once == 1){
                                        $this->db->updateUserMeta($booking['bookings_author'], $user_meta_key, "sent");
                                    }
                                    $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "processing");
                                }
                                return $data;
                            }else{
                                if($email_data && isset($email_data['recipient_email'])){
                                    $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "failed");
                                }
                                return false;
                            }
                        }    
                    }
                //}
                
                // Get delay from template (in minutes)
                // $delay = isset($template['delay']) ? intval($template['delay']) : 0;

            
                // $bookingStartTime = strtotime($booking['date_start']);
                // $triggerTime = $bookingStartTime - ($delay * 60); // Convert minutes to seconds
                // $currentTime = strtotime($currentDate);
                
                // // Check if current time has reached or passed the trigger time
                // if ($currentTime >= $triggerTime) {
                //     return $this->sendDelayedEmail($template, $booking);
                // }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    public function sendAfterBookingEndEmail($template, $booking) {
        try {
            $currentdateTime = $this->db->getCurrentDate();
            $currentDate = $currentdateTime['current_datetime']; 

            $email_logs_id = "log_id_".$template["id"]."_".$booking["id"];
            $email_data = $this->getEmailData($template, $booking);

            if($template["active"] == 0){

                
            
                if($email_data && isset($email_data['recipient_email'])){
                    //$this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "stopped");
                }

                return false;

            }else{

                if($email_data && isset($email_data['recipient_email'])){
                    $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "created");
                }

            }

            if($template["trigger_type"] == "after_booking_end"){
                
                // Get delay from template (in minutes)
                $delay = isset($template['delay']) ? intval($template['delay']) : 0;

                $send_once = isset($template['send_once']) ? intval($template['send_once']) : 0;

                $user_meta_key = "sent_once_".$booking["bookings_author"]."_".$booking["listing_id"]."_".$template["id"];

            

                if($send_once == 1){
                    $email_sent_once = $this->db->getUserMeta($booking['bookings_author'], $user_meta_key);
    
                    if(!empty($email_sent_once)){
    
                        if($email_data && isset($email_data['recipient_email'])){
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "sent_once");
                        }
    
                        return false;
                    }
                }

                // Calculate trigger time: booking end time + delay
                $bookingEndTime = strtotime($booking['date_end']);
                $triggerTime = $bookingEndTime + ($delay * 60); // Convert minutes to seconds
                $currentTime = strtotime($currentDate);
                
                // Check if current time has reached or passed the trigger time (after booking end + delay)

                if($currentTime >= $bookingEndTime && $currentTime <= $triggerTime){
                    if($email_data && isset($email_data['recipient_email'])){
                        $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "waiting_for_sending");
                    }
                }
                if ($currentTime >= $triggerTime) {
                    $data = $this->sendDelayedEmail($template, $booking, $email_logs_id);
                    if($data && isset($data['recipient_email'])){
                        if($email_data && isset($email_data['recipient_email'])){
                            if($send_once == 1){
                                $this->db->updateUserMeta($booking['bookings_author'], $user_meta_key, "sent");
                            }
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "processing");
                        }
                        return $data;
                    }else{
                        if($email_data && isset($email_data['recipient_email'])){
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "failed");
                        }
                        return false;
                    }
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function sendPaidActivatedEmail($template, $booking) {
        try {

            $email_logs_id = "log_id_".$template["id"]."_".$booking["id"];
            $email_data = $this->getEmailData($template, $booking);

            

            if($template["active"] == 0){

                
            
                if($email_data && isset($email_data['recipient_email'])){
                   // $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "stopped");
                }

                return false;

            }else{

                if($email_data && isset($email_data['recipient_email'])){
                    $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "created");
                }

            }




            $currentdateTime = $this->db->getCurrentDate();

            $currentDate = $currentdateTime['current_datetime']; 
            // Check if template has a delay
            $delay = isset($template['delay']) ? intval($template['delay']) : 0;
            $send_once = isset($template['send_once']) ? intval($template['send_once']) : 0;

            $user_meta_key = "sent_once_".$booking["bookings_author"]."_".$booking["listing_id"]."_".$template["id"];

           

            if($send_once == 1){
                $email_sent_once = $this->db->getUserMeta($booking['bookings_author'], $user_meta_key);
                


                if(!empty($email_sent_once)){

                    if($email_data && isset($email_data['recipient_email'])){
                        $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "sent_once");
                    }

                    return false;
                }
            }
            


            
            if ($delay > 0) {
                

                // Calculate the scheduled time (delay in minutes)
                $bookingCreatedAt = $booking['created_at'];
                $scheduledTime = strtotime($bookingCreatedAt . ' +' . $delay . ' minutes');
                $currentTime = strtotime($currentDate);

                $bookingCreatedAttime = strtotime($bookingCreatedAt);

                if($currentTime <= $scheduledTime){
                    if($email_data && isset($email_data['recipient_email'])){
                        $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "waiting_for_sending");
                    }
                }
                
                // Check if the delay period has already passed
                if ($currentTime >= $scheduledTime) {
                    

                    

                    $data = $this->sendDelayedEmail($template, $booking, $email_logs_id);
                    if($data && isset($data['recipient_email'])){
                        if($email_data && isset($email_data['recipient_email'])){
                            if($send_once == 1){
                                $this->db->updateUserMeta($booking['bookings_author'], $user_meta_key, "sent");
                            }
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "processing");
                        }
                        return $data;
                    }else{
                        if($email_data && isset($email_data['recipient_email'])){
                            $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "failed");
                        }
                        return false;
                    }


                } 
            } else {
                $data = $this->sendDelayedEmail($template, $booking, $email_logs_id);
                if($data && isset($data['recipient_email'])){
                    if($email_data && isset($email_data['recipient_email'])){
                        $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "processing");
                    }
                    return $data;
                }else{
                    if($email_data && isset($email_data['recipient_email'])){
                        $this->db->updateEmailSmsLogs($template["template_type"],$email_data, $booking, $email_logs_id, "failed");
                    }
                    return false;
                }
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }


    /**
     * Send delayed email using template and booking data
     */
    private function sendDelayedEmail($template, $booking, $email_logs_id) {
        try {

            // Get booking details for email variables

            $email_data = $this->getEmailData($template, $booking);
            
            if($email_data && isset($email_data['recipient_email'])){
                $this->db->insertBookingMeta($booking['id'],$template["trigger_type"]."_".$template["id"], "sent");

                $email_data['email_logs_id'] = $email_logs_id;

                return $email_data;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getEmailData($template, $booking) {
        try {

            $group_data = $this->db->getGroupId($booking['owner_id']);

            $settings = array();

            if(isset($group_data['users_groups_id'])){
                $group_id = $group_data['users_groups_id'];

                $settings_data = $this->db->getEmailSettings($group_id);

                
                if ($settings_data) {
                    foreach($settings_data as $setting){
                        $settings[$setting['setting_key']] = $setting['setting_id'];
                    }
                }
            }


            $bookingData = $this->getBookingDetailsForEmail($booking);
            
            // Prepare email variables from booking data
            $variables = $this->prepareEmailVariables($bookingData);


            $recipientEmail = '';
            $recipientName = '';
            if (isset($bookingData['user_info']['user_email'])) {
                $recipientEmail = $bookingData['user_info']['user_email'];

                if(isset($bookingData['user_info']['first_name']) && isset($bookingData['user_info']['last_name'])){
                    $recipientName = $bookingData['user_info']['first_name'] . ' ' . $bookingData['user_info']['last_name'];
                }else{
                    $recipientName = $bookingData['user_info']['display_name']??'';
                }
            }

            $phone = $bookingData['user_info']['phone']??'';
            $country_code = $bookingData['user_info']['country_code']??'';

            if (strpos($phone, "+") !== false) {

                $country_code = str_replace("+", "", $country_code);

                $country_code = "+" . $country_code;

                $phone = str_replace($country_code, "", $phone);
              
            }else{
                $phone = $phone;
            }

            $phone = str_replace(" ", "", $phone);

            $phone = trim($phone);

            // echo '<pre>';
            // print_r($phone);
            // print_r($country_code);
            // echo '</pre>';
            // die;


            

           
            
            if (!$recipientEmail) {
                //CoreResponse::error('Recipient email not found', 500);
                return false;
            }


            if(isset($settings['from_email_name']) && !empty($settings['from_email_name'])){
                $from_email_name = $settings['from_email_name'];
            }else{
                $from_email_name = "Gibbs";
            }
            if(isset($settings['from_email']) && !empty($settings['from_email'])){
                $from_email = $settings['from_email'];
            }else{
                $from_email = 'no_reply@gibbs.no';
            }

            
            // Process template variables and ensure proper UTF-8 encoding for emoji support
            $subject = $this->processTemplateVariables($template['template_header'], $variables);
            $body = $this->processTemplateVariables($template['template_content'], $variables);
            
            // Ensure proper UTF-8 encoding for emoji support
            $subject = mb_convert_encoding($subject, 'UTF-8', 'UTF-8');
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
            
            $cc = $template['email_cc']??'';
            $cc = trim($cc);
            $cc = str_replace(' ', '', $cc);

            $data = array("recipient_email" => $recipientEmail, "recipient_name" => $recipientName, "subject" => $subject, "body" => $body, "copy_to" => $cc, "template_type" => $template["template_type"], "phone" => $phone, "country_code" => $country_code, "from_email_name" => $from_email_name, "from_email" => $from_email);

            // echo "<pre>";
            // print_r($data);
            // echo "</pre>";
            // die;
            

            return $data;
        } catch (Exception $e) {
            return false;
        }
    }
    private function processTemplateVariables($content, $variables) {
        // Ensure input content is properly UTF-8 encoded
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        foreach ($variables as $key => $value) {
            // Ensure variable values are properly UTF-8 encoded
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }

    private function getBookingDetailsForEmail($booking) {
        try {
            // Get additional booking data from meta
            // Get user information
            if (isset($booking['bookings_author'])) {
                $userInfo = $this->db->getUserById($booking['bookings_author']);
                if ($userInfo) {
                    $booking['user_info'] = $userInfo;
                }
            }
            
            return $booking;
        } catch (Exception $e) {
            error_log('Error getting booking details: ' . $e->getMessage());
            return $booking;
        }
    }

    private function prepareEmailVariables($bookingData) {
        $variables = [];
        
        // Basic booking variables
        $variables['booking_id'] = $bookingData['id'] ?? '';
        $variables['booking_start_date'] = $bookingData['date_start'] ?? '';
        $variables['booking_end_date'] = $bookingData['date_end'] ?? '';
        $variables['amount'] = $bookingData['price'] ?? '';
        
        // User variables
        if (isset($bookingData['user_info'])) {
            $userInfo = $bookingData['user_info'];
            $variables['first_name'] = $userInfo['first_name'] ?? $userInfo['display_name'] ?? '';
            $variables['last_name'] = $userInfo['last_name'] ?? '';
            $variables['customer_email'] = $userInfo['user_email'] ?? '';
            $variables['customer_phone'] = $userInfo['phone'] ?? '';
            $variables['full_name'] = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
        }
        
        
        return $variables;
    }


   
    /**
     * Update template using frontend payload mapping
     */
    private function updateTemplateMapped($input) {
        try {
            if (!isset($input['template']) || !isset($input['template']['template_id'])) {
                CoreResponse::error('Missing template payload or template_id', 400);
                return;
            }

            $payload = $input['template'];
            $templateId = intval($payload['template_id']);

            // Ensure template exists and belongs to the same owner if provided
            $existing = $this->db->getTemplateById($templateId);
            if (!$existing) {
                CoreResponse::error('Email template not found', 404);
                return;
            }

            $ok = $this->db->updateTemplateMapped($templateId, $payload);
            if (!$ok) {
                CoreResponse::error('Failed to update email template', 500);
                return;
            }

            // Fetch updated and map to frontend shape
            $updated = $this->db->getTemplateById($templateId);
            $mapped = [
                'id' => intval($updated['id']),
                'name' => $updated['template_name'] ?? '',
                'subject' => $updated['template_header'] ?? '',
                'content' => $updated['template_content'] ?? '',
                'copyTo' => $updated['email_cc'] ?? '',
                'event' => $updated['trigger_type'] ?? '',
                'delay' => is_numeric($updated['delay']) ? intval($updated['delay']) : 0,
                'active' => isset($updated['active']) ? ($updated['active'] === '1' || $updated['active'] === 1) : false,
                'before_booking_unique_minute' => is_numeric($updated['before_booking_unique_minute']) ? intval($updated['before_booking_unique_minute']) : 0,
                'send_once' => isset($updated['send_once']) ? ($updated['send_once'] === '1' || $updated['send_once'] === 1) : 0,
                'editorType' => $updated['editorType'] ?? 'rich',
                'type' => $updated['template_type'] ?? 'email',
                'owner_id' => intval($updated['owner_id'])
            ];

            CoreResponse::success([
                'message' => 'Email template updated successfully',
                'template' => $mapped
            ], 'Email template updated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to update email template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle PUT requests for email template operations
     */
    private function handleEmailTemplatePutRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'update_template':
                $this->requireAuth();
                $this->updateTemplate($data);
                break;
            case 'activate_template':
                $this->requireAuth();
                $this->activateTemplate($data);
                break;
            default:
                CoreResponse::error('Invalid action for PUT request', 400);
                break;
        }
    }

    /**
     * Handle DELETE requests for email template operations
     */
    private function handleEmailTemplateDeleteRequest($data) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        switch ($action) {
            case 'delete_template':
                $this->requireAuth();
                $this->deleteTemplate($data);
                break;
            default:
                CoreResponse::error('Invalid action for DELETE request', 400);
                break;
        }
    }

    /**
     * Get email template by ID
     */
    private function getTemplate($input) {
        try {
            $this->validateRequiredFields($input, ['template_id']);
            
            $templateId = intval($input['template_id']);
            $template = $this->db->getTemplateById($templateId);
            
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }

            CoreResponse::success([
                'template' => $template
            ], 'Email template retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get email template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get email template by name
     */
    private function getTemplateByName($input) {
        try {
            $this->validateRequiredFields($input, ['name']);
            
            $name = $input['name'];
            $template = $this->db->getTemplateByName($name);
            
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }

            CoreResponse::success([
                'template' => $template
            ], 'Email template retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get email template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all email templates
     */
    private function getAllTemplates($input) {
        try {
            $templateType = isset($input['template_type']) ? $input['template_type'] : null;
            $limit = isset($input['limit']) ? intval($input['limit']) : 100;
            
            // Ensure limit is reasonable
            if ($limit > 200) {
                $limit = 200;
            }
            
            $templates = $this->db->getAllTemplates($templateType, $limit);
            
            CoreResponse::success([
                'templates' => $templates,
                'count' => count($templates)
            ], 'Email templates retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get email templates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new email template
     */
    private function createTemplate($input) {
        try {
            // Handle both old format and new template structure
            if (isset($input['template'])) {
                // New format with template object
                $template = $input['template'];

                $requiredFields = ['name'];

                if(isset($template['type']) && $template['type'] == 'email'){
                    $requiredFields[] = 'subject';
                }

                $this->validateRequiredFields($template, $requiredFields);
                
                $name = $template['name'];
                $subject = $template['subject'];
                $body = isset($template['content']) ? $template['content'] : (isset($template['body']) ? $template['body'] : '');
                $templateType = isset($template['type']) ? $template['type'] : 'email';
                $createdBy = isset($input['owner_id']) ? (int)$input['owner_id'] : $this->getCurrentUserId();
                
                // Prepare additional data for new fields
                $additionalData = [
                    'active' => isset($template['active']) ? $template['active'] : true,
                    'type' => isset($template['type']) ? $template['type'] : 'email',
                    'delay' => isset($template['delay']) ? (int)$template['delay'] : 0,
                    'event' => isset($template['event']) ? $template['event'] : null,
                    'content' => isset($template['content']) ? $template['content'] : $body,
                    'copyTo' => isset($template['copyTo']) ? $template['copyTo'] : null,
                    'before_booking_unique_minute' => isset($template['before_booking_unique_minute']) ? (int)$template['before_booking_unique_minute'] : 0,
                    'send_once' => isset($template['send_once']) ? $template['send_once'] : 0,
                    'editorType' => isset($template['editorType']) ? $template['editorType'] : 'rich',
                    'owner_id' => $createdBy
                ];
            } else {
                CoreResponse::error('Data not found!', 400);
                return;
            }
            
            // Check if template with same name already exists
            $existingTemplate = $this->db->getTemplateByName($name);
            if ($existingTemplate) {
                CoreResponse::error('Email template with this name already exists', 400);
                return;
            }
            
            $templateId = $this->db->createTemplate($name, $subject, $body, $templateType, $createdBy, $additionalData);
            
            if (!$templateId) {
                CoreResponse::error('Failed to create email template', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Email template created successfully',
                'template_id' => $templateId,
                'name' => $name,
                'active' => isset($additionalData['active']) ? $additionalData['active'] : true,
                'type' => isset($additionalData['type']) ? $additionalData['type'] : 'email',
                'event' => isset($additionalData['event']) ? $additionalData['event'] : null
            ], 'Email template created successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to create email template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update email template
     */
    private function updateTemplate($input) {
        try {
            $this->validateRequiredFields($input, ['template_id']);
            
            $templateId = intval($input['template_id']);
            $updateData = [];
            
            // Build update data from input
            $allowedFields = ['name', 'subject', 'body', 'template_type', 'status'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            if (empty($updateData)) {
                CoreResponse::error('No valid fields provided for update', 400);
                return;
            }
            
            // Check if template exists
            $template = $this->db->getTemplateById($templateId);
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }
            
            $success = $this->db->updateTemplate($templateId, $updateData);
            
            if (!$success) {
                CoreResponse::error('Failed to update email template', 500);
                return;
            }

            // Get updated template
            $updatedTemplate = $this->db->getTemplateById($templateId);
            
            CoreResponse::success([
                'message' => 'Email template updated successfully',
                'template' => $updatedTemplate
            ], 'Email template updated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to update email template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete email template
     */
    private function deleteTemplate($input) {
        try {
            $this->validateRequiredFields($input, ['template_id']);
            
            $templateId = intval($input['template_id']);
            
            // Check if template exists
            $template = $this->db->getTemplateById($templateId);
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }
            
            $success = $this->db->deleteTemplate($templateId);
            
            if (!$success) {
                CoreResponse::error('Failed to delete email template', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Email template deleted successfully',
                'template_id' => $templateId
            ], 'Email template deleted successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to delete email template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Hard delete email template
     */
    private function hardDeleteTemplate($input) {
        try {
            $this->validateRequiredFields($input, ['template_id']);
            
            $templateId = intval($input['template_id']);
            
            // Check if template exists
            $template = $this->db->getTemplateById($templateId);
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }
            
            $success = $this->db->hardDeleteTemplate($templateId);
            
            if (!$success) {
                CoreResponse::error('Failed to hard delete email template', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Email template permanently deleted',
                'template_id' => $templateId
            ], 'Email template permanently deleted');
        } catch (Exception $e) {
            CoreResponse::error('Failed to hard delete email template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send email using template
     */
    private function sendEmail($input) {
        try {
            $this->validateRequiredFields($input, ['template_id', 'recipient_email']);
            
            $templateId = intval($input['template_id']);
            $recipientEmail = $input['recipient_email'];
            $recipientName = isset($input['recipient_name']) ? $input['recipient_name'] : '';
            $variables = isset($input['variables']) ? $input['variables'] : [];
            
            // Get template
            $template = $this->db->getTemplateById($templateId);
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }
            
            // Process template variables
            $subject = $this->processTemplateVariables($template['subject'], $variables);
            $body = $this->processTemplateVariables($template['body'], $variables);
            
            // Log email attempt
            $logId = $this->db->logEmail($templateId, $recipientEmail, $recipientName, $subject, $body, 'pending');
            
            // Send email (implement your email sending logic here)
            $emailSent = $this->sendEmailViaProvider($recipientEmail, $recipientName, $subject, $body);
            
            if ($emailSent) {
                $this->db->updateEmailLogStatus($logId, 'sent');
                CoreResponse::success([
                    'message' => 'Email sent successfully',
                    'log_id' => $logId,
                    'recipient_email' => $recipientEmail
                ], 'Email sent successfully');
            } else {
                $this->db->updateEmailLogStatus($logId, 'failed', 'Failed to send email');
                CoreResponse::error('Failed to send email', 500);
            }
        } catch (Exception $e) {
            CoreResponse::error('Failed to send email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test email template
     */
    private function testEmail($input) {
        try {
            $this->validateRequiredFields($input, ['template_id', 'test_email']);
            
            $templateId = intval($input['template_id']);
            $testEmail = $input['test_email'];
            $variables = isset($input['variables']) ? $input['variables'] : [];
            
            // Get template
            $template = $this->db->getTemplateById($templateId);
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }
            
            // Process template variables
            $subject = $this->processTemplateVariables($template['subject'], $variables);
            $body = $this->processTemplateVariables($template['body'], $variables);
            
            // Log test email
            $logId = $this->db->logEmail($templateId, $testEmail, 'Test User', $subject, $body, 'pending');
            
            // Send test email
            $emailSent = $this->sendEmailViaProvider($testEmail, 'Test User', $subject, $body);
            
            if ($emailSent) {
                $this->db->updateEmailLogStatus($logId, 'sent');
                CoreResponse::success([
                    'message' => 'Test email sent successfully',
                    'log_id' => $logId,
                    'test_email' => $testEmail,
                    'subject' => $subject
                ], 'Test email sent successfully');
            } else {
                $this->db->updateEmailLogStatus($logId, 'failed', 'Failed to send test email');
                CoreResponse::error('Failed to send test email', 500);
            }
        } catch (Exception $e) {
            CoreResponse::error('Failed to send test email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get email logs
     */
    private function getEmailLogs($input) {
        try {
            $page = isset($input['page']) ? intval($input['page']) : 1;
            $perPage = isset($input['per_page']) ? intval($input['per_page']) : 20;
            $search = isset($input['search']) ? $input['search'] : '';
            $templateId = isset($input['template_id']) ? intval($input['template_id']) : null;
            
            // Ensure reasonable limits
            if ($perPage > 100) {
                $perPage = 100;
            }
            
            $emailLogs = $this->db->getEmailLogsWithPagination($page, $perPage, $search, $templateId);
            
            CoreResponse::success($emailLogs, 'Email logs retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get email logs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get template statistics
     */
    private function getTemplateStats($input) {
        try {
            $stats = $this->db->getEmailTemplateStats();
            
            CoreResponse::success($stats, 'Template statistics retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get template statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get email statistics
     */
    private function getEmailStats($input) {
        try {
            $stats = $this->db->getEmailStats();
            
            CoreResponse::success($stats, 'Email statistics retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get email statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activate template
     */
    private function activateTemplate($input) {
        try {
            $this->validateRequiredFields($input, ['template_id']);
            
            $templateId = intval($input['template_id']);
            
            // Check if template exists
            $template = $this->db->getTemplateById($templateId);
            if (!$template) {
                CoreResponse::error('Email template not found', 404);
                return;
            }
            
            $success = $this->db->updateTemplate($templateId, ['status' => 'active']);
            
            if (!$success) {
                CoreResponse::error('Failed to activate email template', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Email template activated successfully',
                'template_id' => $templateId
            ], 'Email template activated successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to activate email template: ' . $e->getMessage(), 500);
        }
    }



    /**
     * Send email via email provider (implement your email sending logic)
     */
    private function sendEmailViaProvider($recipientEmail, $recipientName, $subject, $body) {
        try {
            // This is a placeholder - implement your actual email sending logic here
            // You might use WordPress wp_mail(), PHPMailer, or a third-party service
            
            // Example using WordPress wp_mail (if WordPress is loaded)
            if (function_exists('wp_mail')) {
                $headers = array('Content-Type: text/html; charset=UTF-8');
                return wp_mail($recipientEmail, $subject, $body, $headers);
            }
            
            // Fallback to basic mail() function
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: noreply@gibbs.no\r\n";
            
            return mail($recipientEmail, $subject, $body, $headers);
        } catch (Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate required fields in input
     */
    private function validateRequiredFields($input, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                CoreResponse::error("Missing required field: {$field}", 400);
                return false;
            }
        }
        return true;
    }

    /**
     * Centralized authentication method
     * Sets current user if authenticated, returns false if not
     */
    private function authenticateUser() {
        try {
            // Extract JWT token from Authorization header
            $authHeader = $this->getAuthorizationHeader();
            if (!$authHeader) {
                return false;
            }

            $token = $this->extractBearerToken($authHeader);
            if (!$token) {
                return false;
            }

            // Validate JWT token and get user ID
            $userData = $this->validateJWTToken($token);
            
            if(!isset($userData['user_id'])){
                return false;
            }

            // Set current user
            $this->current_user_id = $userData['user_id'];
            $this->current_user = $userData;
            
            return true;
        } catch (Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId() {
        return $this->current_user_id;
    }

    /**
     * Get current authenticated user object
     */
    private function getCurrentUser() {
        return $this->current_user;
    }

    /**
     * Check if user is authenticated
     */
    private function isAuthenticated() {
        return $this->current_user_id !== null;
    }

    /**
     * Require authentication for protected operations
     */
    private function requireAuth() {
        if (!$this->isAuthenticated()) {
            CoreResponse::error('Authentication required', 401);
            return false;
        }
        return true;
    }

    /**
     * Get Authorization header
     */
    private function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }

    /**
     * Extract Bearer token from Authorization header
     */
    private function extractBearerToken($authHeader) {
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Validate JWT token and return user ID
     */
    private function validateJWTToken($token) {
        try {
            // Include the custom JWT class
            $jwt_file = dirname( __FILE__, 3 ) . '/includes/class-custom-jwt.php';
            if (file_exists($jwt_file)) {
                require_once $jwt_file;
                
                $custom_jwt = new Custom_JWT();
                $decoded = $custom_jwt->validate_token($token);
                
                if ($decoded && isset($decoded['user_id'])) {
                    return $decoded;
                }
            }
            
            return false;
        } catch (Exception $e) {
            die($e->getMessage());
            return false;
        }
    }

    /**
     * Get email settings for owner
     */
    private function getEmailSettings($input) {
        try {

            if ( ! function_exists( 'get_current_user_id' ) ) {
                // Try to include WordPress core if not already loaded
                $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
                if ( file_exists( $wp_load_path ) ) {
                    require_once( $wp_load_path );
                }
            }

            if(!function_exists('get_current_user_id')){
                CoreResponse::error('WordPress is not loaded', 400);
                return;
            }

            $active_group_id = get_user_meta( get_current_user_ID(), '_gibbs_active_group_id',true );
            //$active_group_id = 577;

            if(!$active_group_id){
                CoreResponse::error('No active group found for user', 404);
                return;
            }

             $settings_data = $this->db->getEmailSettings($active_group_id);

             $settings = array();
             if ($settings_data) {
                 foreach($settings_data as $setting){
                     $settings[$setting['setting_key']] = $setting['setting_id'];
                 }
             }

            CoreResponse::success([
                'settings' => $settings
            ], 'Email settings retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get email settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Save email settings for owner
     */
    private function saveEmailSettings($input) {
        try {
            if ( ! function_exists( 'get_current_user_id' ) ) {
                // Try to include WordPress core if not already loaded
                $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
                if ( file_exists( $wp_load_path ) ) {
                    require_once( $wp_load_path );
                }
            }

            if(!function_exists('get_current_user_id')){
                CoreResponse::error('WordPress is not loaded', 400);
                return;
            }

            $active_group_id = get_user_meta( get_current_user_ID(), '_gibbs_active_group_id',true );
            //$active_group_id = 577;

            if(!$active_group_id){
                CoreResponse::error('No active group found for user', 404);
                return;
            }


            if (!isset($input['settings'])) {
                CoreResponse::error('Missing settings data', 400);
                return;
            }

            $settings = $input['settings'];
            
            // Validate required fields
            $requiredFields = ['from_email_name', 'from_email', 'reply_to_email', 'company_name', 'company_address', 'company_postcode', 'company_area', 'company_country'];
            foreach ($requiredFields as $field) {
                if (!isset($settings[$field])) {
                    $settings[$field] = '';
                }
            }


            // Save settings to database
            $success = $this->db->saveEmailSettings($active_group_id, $settings);

            if (!$success) {
                CoreResponse::error('Failed to save email settings', 500);
                return;
            }

            CoreResponse::success([
                'message' => 'Email settings saved successfully',
                'settings' => $settings
            ], 'Email settings saved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to save email settings: ' . $e->getMessage(), 500);
        }
    }
}

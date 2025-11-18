<?php
/**
 * Email Template Database Class
 * Handles all database operations for email template functionality
 * Uses direct PDO connection like CoreDatabase
 */

class EmailTemplateDatabase {
    private $connection;
    private $wp_prefix;
    private $email_templates_table;
    private $email_logs_table;
    private $sms_logs_table;
    
    public function __construct() {
        // Load WordPress config to get database credentials
        $this->loadWordPressConfig();
        $this->connect();
        $this->email_templates_table = $this->wp_prefix . 'email_template';
        $this->email_logs_table = $this->wp_prefix . 'gibbs_email_log';
        $this->sms_logs_table = $this->wp_prefix . 'gibbs_sms_log';
        // Table already exists, no need to create
    }
    
    /**
     * Load WordPress configuration
     */
    private function loadWordPressConfig() {
        // Path to wp-config.php from react-modules-plugin/server/
        $wp_config_path = dirname( __FILE__, 6 ) . '/wp-config.php';
        
        if (!file_exists($wp_config_path)) {
            throw new Exception('WordPress configuration file not found');
        }
        
        // Extract database constants from wp-config.php
        $config_content = file_get_contents($wp_config_path);
        
        // Extract database constants using regex
        preg_match("/define\(\s*'DB_NAME',\s*'([^']+)'\s*\)/", $config_content, $db_name_match);
        preg_match("/define\(\s*'DB_USER',\s*'([^']+)'\s*\)/", $config_content, $db_user_match);
        preg_match("/define\(\s*'DB_PASSWORD',\s*'([^']+)'\s*\)/", $config_content, $db_password_match);
        preg_match("/define\(\s*'DB_HOST',\s*'([^']+)'\s*\)/", $config_content, $db_host_match);
        preg_match("/\\\$table_prefix\s*=\s*'([^']+)'/", $config_content, $table_prefix_match);
        
        if (!$db_name_match || !$db_user_match || !$db_password_match || !$db_host_match) {
            throw new Exception('Database configuration not found in wp-config.php');
        }
        
        $this->db_name = $db_name_match[1];
        $this->db_user = $db_user_match[1];
        $this->db_password = $db_password_match[1];
        $this->db_host = $db_host_match[1];
        $this->wp_prefix = $table_prefix_match ? $table_prefix_match[1] : 'wp_';
    }
    
    /**
     * Connect to database
     */
    private function connect() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->db_host};dbname={$this->db_name};charset=utf8mb4",
                $this->db_user,
                $this->db_password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
            
            // Ensure UTF-8 support for emojis
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Create the email templates and logs tables if they don't exist
     */
    private function createTablesIfNotExist() {
        
    }

    /**
     * Get email template by ID
     */
    public function getTemplateById($templateId) {
        try {
            $sql = "SELECT * FROM {$this->email_templates_table} WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':id', $templateId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get email template by ID: ' . $e->getMessage());
        }
    }

    /**
     * Get email template by name
     */
    public function getTemplateByName($name) {
        try {
            $sql = "SELECT * FROM {$this->email_templates_table} WHERE template_name = :name";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get email template by name: ' . $e->getMessage());
        }
    }

    /**
     * Get all email templates
     */
    public function getAllTemplates($templateType = null, $limit = 100) {
        try {
            $sql = "SELECT * FROM {$this->email_templates_table}";
            $params = [];
            
            if ($templateType) {
                $templateTypeEnum = ($templateType === 'email') ? 'email' : 'email';
                $sql .= " AND template_type = :template_type";
                $params[':template_type'] = $templateTypeEnum;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT :limit";
            $params[':limit'] = $limit;
            
            $stmt = $this->connection->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindParam($key, $value, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get all email templates: ' . $e->getMessage());
        }
    }

    /**
     * Get templates by owner
     */
    public function getTemplatesByOwner($ownerId, $limit = 100) {
        try {
            $sql = "SELECT * FROM {$this->email_templates_table} WHERE owner_id = :owner_id ORDER BY created_at DESC LIMIT :limit";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get email templates by owner: ' . $e->getMessage());
        }
    }

    /**
     * Create a new email template
     */
    public function createTemplate($name, $subject, $body, $templateType = 'general', $createdBy, $additionalData = []) {
        try {
            // Map to existing table structure
            $active = isset($additionalData['active']) ? ($additionalData['active'] ? '1' : '0') : '1';
            $type = isset($additionalData['type']) ? $additionalData['type'] : 'email';
            $delay = isset($additionalData['delay']) ? (string)$additionalData['delay'] : '0';
            $event = isset($additionalData['event']) ? $additionalData['event'] : '';
            $content = isset($additionalData['content']) ? $additionalData['content'] : $body;
            $copyTo = isset($additionalData['copyTo']) ? $additionalData['copyTo'] : '';
            $ownerId = isset($additionalData['owner_id']) ? (int)$additionalData['owner_id'] : $createdBy;
            $beforeBookingUniqueMinute = isset($additionalData['before_booking_unique_minute']) ? (int)$additionalData['before_booking_unique_minute'] : 0;
            $sendOnce = isset($additionalData['send_once']) ? ($additionalData['send_once'] ? '1' : '0') : '0';
            $editorType = isset($additionalData['editorType']) ? $additionalData['editorType'] : 'rich';
            // Map template_type to enum values (1 or 2)
            $templateTypeEnum = ($templateType !== '') ? $templateType : 'email';
            
            $sql = "INSERT INTO {$this->email_templates_table} (owner_id, template_name, template_header, template_content, email_cc, delay, active, template_type, trigger_type, before_booking_unique_minute, send_once, editorType) VALUES (:owner_id, :template_name, :template_header, :template_content, :email_cc, :delay, :active, :template_type, :trigger_type, :before_booking_unique_minute, :send_once, :editorType)";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            
            // Ensure proper UTF-8 encoding for emoji support
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
            $subject = mb_convert_encoding($subject, 'UTF-8', 'UTF-8');
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
            $copyTo = mb_convert_encoding($copyTo, 'UTF-8', 'UTF-8');
            
            $stmt->bindParam(':template_name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':template_header', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':template_content', $content, PDO::PARAM_STR);
            $stmt->bindParam(':email_cc', $copyTo, PDO::PARAM_STR);
            $stmt->bindParam(':delay', $delay, PDO::PARAM_STR);
            $stmt->bindParam(':active', $active, PDO::PARAM_STR);
            $stmt->bindParam(':template_type', $templateTypeEnum, PDO::PARAM_STR);
            $stmt->bindParam(':trigger_type', $event, PDO::PARAM_STR);
            $stmt->bindParam(':before_booking_unique_minute', $beforeBookingUniqueMinute, PDO::PARAM_STR);
            $stmt->bindParam(':send_once', $sendOnce, PDO::PARAM_STR);
            $stmt->bindParam(':editorType', $editorType, PDO::PARAM_STR);
            $stmt->execute();
            
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Failed to create email template: ' . $e->getMessage());
        }
    }

    /**
     * Update email template
     */
    public function updateTemplate($templateId, $data) {
        try {
            // Build dynamic SET clause based on provided data
            $setParts = [];
            $allowedFields = ['name', 'subject', 'body', 'content', 'template_type', 'type', 'status', 'active', 'delay', 'event', 'copy_to', 'owner_id'];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $setParts[] = "$field = :$field";
                }
            }
            
            if (empty($setParts)) {
                throw new Exception('No valid fields provided for update');
            }
            
            $setClause = implode(', ', $setParts);
            $sql = "UPDATE {$this->email_templates_table} SET $setClause WHERE id = :id";
            
            $stmt = $this->connection->prepare($sql);
            
            // Bind dynamic parameters
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $stmt->bindParam(":$field", $data[$field], PDO::PARAM_STR);
                }
            }
            
            $stmt->bindParam(':id', $templateId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to update email template: ' . $e->getMessage());
        }
    }

    /**
     * Delete email template (soft delete by setting status to inactive)
     */
    public function deleteTemplate($templateId) {
        try {
            $sql = "UPDATE {$this->email_templates_table} SET active = '0' WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':id', $templateId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to delete email template: ' . $e->getMessage());
        }
    }
    public function getGroupId($ownerId) {
        try {
            $sql = "SELECT users_groups_id FROM {$this->wp_prefix}users_and_users_groups WHERE users_id = :owner_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getAllBookingsPaid() {
        try {
            $currentDate = $this->getCurrentDate()['current_datetime'];
            $currentDate = date('Y-m-d 23:59:59', strtotime($currentDate));
            $oneMonthAgo = date('Y-m-d 00:00:00', strtotime('-2 days'));
            
            $sql = "SELECT * FROM {$this->wp_prefix}bookings_calendar 
                    WHERE (created_at BETWEEN :one_month_ago AND :current_date)
                    AND status = 'paid'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':one_month_ago', $oneMonthAgo, PDO::PARAM_STR);
            $stmt->bindParam(':current_date', $currentDate, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get all bookings: ' . $e->getMessage());
        }
    }

    public function getBookingsForStartDates() {
        try {
            $currentDate = date('Y-m-d 23:59:59', strtotime('+2 days'));
            $oneMonthAgo = date('Y-m-d 00:00:00', strtotime('-2 days'));

            $sql = "SELECT * FROM {$this->wp_prefix}bookings_calendar WHERE date_start BETWEEN :one_month_ago AND :current_date AND status = 'paid'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':one_month_ago', $oneMonthAgo, PDO::PARAM_STR);
            $stmt->bindParam(':current_date', $currentDate, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get bookings for start  dates: ' . $e->getMessage());
        }
    }

    public function getBookingsForEndDates() {
        try {
            $currentDate = date('Y-m-d 23:59:59', strtotime('+2 days'));
            $oneMonthAgo = date('Y-m-d 00:00:00', strtotime('-1 days'));
            
            $sql = "SELECT * FROM {$this->wp_prefix}bookings_calendar WHERE date_end BETWEEN :one_month_ago AND :current_date AND status = 'paid'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':one_month_ago', $oneMonthAgo, PDO::PARAM_STR);
            $stmt->bindParam(':current_date', $currentDate, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get bookings for  end dates: ' . $e->getMessage());
        }
    }

    public function getCurrentDate() {
        try {
            $sql = "SELECT NOW() as current_datetime";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get current date: ' . $e->getMessage());
        }
    }
    
    public function updateEmailSmsLogs($trigger_type, $email_data, $booking, $trigger_log_id, $status) {

        if($email_data["template_type"] == "sms"){
            
            return $this->insertSmsLogs($trigger_type, $email_data, $booking, $trigger_log_id, $status);

        }

        try {

            // echo '<pre>';
            // print_r($email_data);
            // print_r($trigger_type);
            // print_r($status);
            // echo '</pre>';

            $owner_id = $booking["owner_id"];
            $user_id = $booking["bookings_author"];
            $order_id = $booking["order_id"];
            $listing_id = $booking["listing_id"];
            $subject = $email_data["subject"];
            $message = $email_data["body"];
            $sent_to_email = $email_data["recipient_email"];

            if(trim($email_data["copy_to"]) != ""){
                $sent_to_email = $sent_to_email.",".trim($email_data["copy_to"]);
            }

            $status = $status;
            $sent_date = $this->getCurrentDate()['current_datetime'];
            


           
            
            // First check if record exists with this trigger_log_id
            $check_sql = "SELECT id, status FROM {$this->email_logs_table} WHERE trigger_log_id = :trigger_log_id";
            $check_stmt = $this->connection->prepare($check_sql);
            $check_stmt->bindParam(':trigger_log_id', $trigger_log_id, PDO::PARAM_STR);
            $check_stmt->execute();
            $existing_record = $check_stmt->fetch();

           
            
            if ($existing_record) {

                if($existing_record['status'] === $status || $existing_record['status'] === "sent_once" || $status === "created" || $status === "stopped"){
                    return true;
                }
                // echo '<pre>';
                // print_r($existing_record);
                // echo '</pre>';
                // echo $status;
                // die;
                // Record exists, update it with all fields
               $sql = "UPDATE {$this->email_logs_table} SET 
                        status = :status, 
                        sent_date = :sent_date
                        WHERE trigger_log_id = :trigger_log_id";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':trigger_log_id', $trigger_log_id, PDO::PARAM_STR);
                $stmt->bindParam(':sent_date', $sent_date, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            } else {
                // Record doesn't exist, insert new one with all fields
                $sql = "INSERT INTO {$this->email_logs_table} 
                        (trigger_log_id, owner_id, user_id, order_id,  sent_to_email, subject, message, status, sent_date) 
                        VALUES (:trigger_log_id, :owner_id, :user_id, :order_id,  :sent_to_email, :subject, :message, :status, :sent_date)";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':trigger_log_id', $trigger_log_id, PDO::PARAM_STR);
                $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                $stmt->bindParam(':sent_to_email', $sent_to_email, PDO::PARAM_STR);
                $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
                $stmt->bindParam(':message', $message, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':sent_date', $sent_date, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            }
        } catch (PDOException $e) {
            //echo $e->getMessage(); die;
            //throw new Exception('Failed to update/insert email logs: ' . $e->getMessage());
        }
    }

    public function insertSmsLogs($trigger_type, $email_data, $booking, $trigger_log_id, $status) {


        try {

            // echo '<pre>';
            // print_r($email_data);
            // print_r($trigger_type);
            // print_r($status);
            // echo '</pre>';

            $owner_id = $booking["owner_id"];
            $user_id = $booking["bookings_author"];
            $order_id = $booking["order_id"];
            $listing_id = $booking["listing_id"];
            $subject = $email_data["subject"];
            $message = $email_data["body"];
            $sent_to_email = $email_data["recipient_email"];

            $phone = $email_data["phone"];
            $country_code = $email_data["country_code"];

        
            $status = $status;
            
            $send_date = $this->getCurrentDate()['current_datetime'];


           
            
            // First check if record exists with this trigger_log_id
            $check_sql = "SELECT id FROM {$this->sms_logs_table} WHERE trigger_log_id = :trigger_log_id";
            $check_stmt = $this->connection->prepare($check_sql);
            $check_stmt->bindParam(':trigger_log_id', $trigger_log_id, PDO::PARAM_STR);
            $check_stmt->execute();
            $existing_record = $check_stmt->fetch();
            
            if ($existing_record) {
                // Record exists, update it with all fields
               $sql = "UPDATE {$this->sms_logs_table} SET 
                        status = :status,
                        send_date = :send_date
                        WHERE trigger_log_id = :trigger_log_id";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':trigger_log_id', $trigger_log_id, PDO::PARAM_STR);
                $stmt->bindParam(':send_date', $send_date, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            } else {
                // Record doesn't exist, insert new one with all fields
                $sql = "INSERT INTO {$this->sms_logs_table} 
                        (trigger_log_id, owner_id, user_id, order_id,  country_code, phone,  message, status, send_date) 
                        VALUES (:trigger_log_id, :owner_id, :user_id, :order_id,  :country_code, :phone, :message, :status, :send_date)";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':trigger_log_id', $trigger_log_id, PDO::PARAM_STR);
                $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                $stmt->bindParam(':country_code', $country_code, PDO::PARAM_STR);
                $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindParam(':message', $message, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':send_date', $send_date, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            }
        } catch (PDOException $e) {
            //echo $e->getMessage(); die;
           // throw new Exception('Failed to update/insert sms logs: ' . $e->getMessage());
        }
    }

    public function getPaidActivatedTemplates($ownerId) {
        try {
            $sql = "SELECT * FROM {$this->email_templates_table} WHERE trigger_type = 'order_created_paid' AND owner_id = :owner_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get activated templates: ' . $e->getMessage());
        }
    }

    public function getStartDateActivatedTemplates($ownerId) {
        try {
            $sql = "SELECT * FROM {$this->email_templates_table} WHERE  (trigger_type = 'before_booking_start' OR trigger_type = 'before_booking_start_unique') AND owner_id = :owner_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get activated templates: ' . $e->getMessage());
        }
    }
    public function checkIfBookingHasPreviousBooking($date_start, $date_end, $listing_id) {
        try {
            $sql = "SELECT * FROM {$this->wp_prefix}bookings_calendar WHERE date_end BETWEEN :date_start AND :date_end AND listing_id = :listing_id AND status = 'paid'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':date_start', $date_start, PDO::PARAM_STR);
            $stmt->bindParam(':date_end', $date_end, PDO::PARAM_STR);
            $stmt->bindParam(':listing_id', $listing_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to check if booking has previous booking: ' . $e->getMessage());
        }
    }

    public function getEndDateActivatedTemplates($ownerId) {
        try {
            $sql = "SELECT * FROM {$this->email_templates_table} WHERE trigger_type = 'after_booking_end' AND owner_id = :owner_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get activated templates: ' . $e->getMessage());
        }
    }
    public function getEmailLogsWithPagination($page_id, $per_page, $search, $owner_id) {
        try {
            // Calculate offset
            $offset = ($page_id - 1) * $per_page;

            

            // Base query
            $baseQuery = "FROM {$this->email_logs_table} WHERE owner_id = $owner_id";
            
            // Add search condition if search term provided
            if (!empty($search)) {
                $baseQuery .= " AND (sent_to_email LIKE :sent_to_email OR subject LIKE :subject OR message LIKE :message OR status LIKE :status OR sent_date LIKE :sent_date)";
            }

            // Get total count
            $countSql = "SELECT COUNT(*) " . $baseQuery;
            $countStmt = $this->connection->prepare($countSql);
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $countStmt->bindParam(':sent_to_email', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':subject', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':message', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':status', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':sent_date', $searchTerm, PDO::PARAM_STR); 
            }
            $countStmt->execute();
            $total = $countStmt->fetchColumn();

            // echo "<pre>";
            // print_r($total);
            // echo "</pre>";
            // die;

            // Get paginated results
            $sql = "SELECT * " . $baseQuery . " ORDER BY sent_date DESC LIMIT :per_page OFFSET :offset";
            $stmt = $this->connection->prepare($sql);
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $stmt->bindParam(':sent_to_email', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':subject', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':message', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':status', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':sent_date', $searchTerm, PDO::PARAM_STR);
            }
            $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'email_logs' => $stmt->fetchAll(),
                'total' => $total,
                'total_pages' => ceil($total / $per_page),
                'current_page' => $page_id
            ];
        } catch (PDOException $e) {
            throw new Exception('Failed to get SMS logs: ' . $e->getMessage());
        }
    }

    public function getUserMeta($userId, $metaKey) {
        try {
            $sql = "SELECT meta_key, meta_value FROM {$this->wp_prefix}usermeta WHERE user_id = :user_id AND meta_key = :meta_key";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $metaKey, PDO::PARAM_STR);
            $stmt->execute();

            $metaData = array();
            while ($row = $stmt->fetch()) {
                $metaData[$row['meta_key']] = $row['meta_value'];
            }
            return $metaData;
        } catch (PDOException $e) {
            throw new Exception('Failed to get user meta: ' . $e->getMessage());
        }
    }
    public function updateUserMeta($userId, $metaKey, $metaValue) {
        try {
            // First check if the meta key exists
            $sql = "SELECT * FROM {$this->wp_prefix}usermeta WHERE user_id = :user_id AND meta_key = :meta_key";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $metaKey, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $sql = "UPDATE {$this->wp_prefix}usermeta SET meta_value = :meta_value WHERE user_id = :user_id AND meta_key = :meta_key";
            } else {
                // Insert new record
                $sql = "INSERT INTO {$this->wp_prefix}usermeta (user_id, meta_key, meta_value) VALUES (:user_id, :meta_key, :meta_value)";
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $metaKey, PDO::PARAM_STR);
            $stmt->bindParam(':meta_value', $metaValue, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            die($e->getMessage());    
            throw new Exception('Failed to update/insert user meta: ' . $e->getMessage());
        }
    }


    public function getUserById($userId) {
        try {
            // First get user data
            $sql = "SELECT * FROM {$this->wp_prefix}users WHERE ID = :user_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!$user) {
                return null;
            }
            
            // Then get user meta data
            $sql = "SELECT meta_key, meta_value FROM {$this->wp_prefix}usermeta 
                    WHERE user_id = :user_id AND meta_key IN ('first_name', 'last_name', 'phone', 'country_code')";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $meta = $stmt->fetchAll();
            
            // Process meta data
            $firstName = '';
            $lastName = '';
            $phone = '';
            $countryCode = '';

            foreach ($meta as $metaRow) {
                if ($metaRow['meta_key'] === 'first_name') {
                    $firstName = $metaRow['meta_value'];
                } elseif ($metaRow['meta_key'] === 'last_name') {
                    $lastName = $metaRow['meta_value'];
                }elseif ($metaRow['meta_key'] === 'phone') {
                    $phone = $metaRow['meta_value'];
                }elseif ($metaRow['meta_key'] === 'country_code') {
                    $countryCode = $metaRow['meta_value'];
                }
            }
            
            // Add full_name to user data
            $fullName = trim($firstName . ' ' . $lastName);
            $user['full_name'] = !empty($fullName) ? $fullName : $user['display_name'];
            $user['first_name'] = $firstName;
            $user['last_name'] = $lastName;
            $user['phone'] = $countryCode . ' ' . $phone;
            $user['country_code'] = $countryCode;
            return $user;
        } catch (PDOException $e) {
            throw new Exception('Failed to get user by ID: ' . $e->getMessage());
        }
    }

    public function insertBookingMeta($booking_id, $meta_key, $meta_value) {
        // First check if the booking_id and meta_key combination already exists
        $check_sql = "SELECT COUNT(*) as count FROM bookings_calendar_meta WHERE booking_id = :booking_id AND meta_key = :meta_key";
        
        try {
            $check_stmt = $this->connection->prepare($check_sql);
            $check_stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $check_stmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
            $check_stmt->execute();
            $result = $check_stmt->fetch();
            
            if ($result['count'] > 0) {
                // Record exists, update it
                $sql = "UPDATE bookings_calendar_meta SET meta_value = :meta_value WHERE booking_id = :booking_id AND meta_key = :meta_key";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
                $stmt->bindParam(':meta_value', $meta_value, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            } else {
                // Record doesn't exist, insert new one
                $sql = "INSERT INTO bookings_calendar_meta (booking_id, meta_key, meta_value) VALUES (:booking_id, :meta_key, :meta_value)";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
                $stmt->bindParam(':meta_value', $meta_value, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            }
        } catch (PDOException $e) {
            throw new Exception('Failed to insert/update booking meta: ' . $e->getMessage());
        }
    }
    public function getBookingMeta($bookingId, $meta_key) {
        $metaSql = "SELECT meta_value 
                    FROM bookings_calendar_meta 
                    WHERE booking_id = :bookingId 
                    AND meta_key = :meta_key";

        try {
            $metaStmt = $this->connection->prepare($metaSql);
            $metaStmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
            $metaStmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
            $metaStmt->execute();
            return $metaStmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get booking meta: ' . $e->getMessage());
        }
    }


    /**
     * Permanently delete template by ID (hard delete)
     */
    public function hardDeleteTemplate($templateId) {
        try {
            $sql = "DELETE FROM {$this->email_templates_table} WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':id', $templateId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to hard delete email template: ' . $e->getMessage());
        }
    }

    /**
     * Update template with frontend payload mapping to existing DB columns
     */
    public function updateTemplateMapped($templateId, $payload) {
        try {
            $fieldsMap = [
                'name' => 'template_name',
                'subject' => 'template_header',
                'content' => 'template_content',
                'copyTo' => 'email_cc',
                'delay' => 'delay',
                'event' => 'trigger_type',
                'before_booking_unique_minute' => 'before_booking_unique_minute',
                'send_once' => 'send_once',
                'editorType' => 'editorType',
                // 'type' maps to template_type (kept as is for now)
                'type' => 'template_type',
                'active' => 'active'
            ];

            $setParts = [];
            $params = [':id' => $templateId];

            foreach ($fieldsMap as $inputKey => $column) {
                if (array_key_exists($inputKey, $payload)) {
                    $value = $payload[$inputKey];
                    if ($inputKey === 'active') {
                        $value = $value ? '1' : '0';
                    }
                    if ($inputKey === 'delay') {
                        $value = (string) (is_numeric($value) ? $value : 0);
                    }
                    if ($inputKey === 'type') {
                        // normalize to 'email' for now
                        $value = $value ?? 'email';
                    }
                    $setParts[] = "$column = :$column";
                    $params[":$column"] = $value;
                }
            }

            if (empty($setParts)) {
                throw new Exception('No valid fields provided for update');
            }

            $sql = "UPDATE {$this->email_templates_table} SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $this->connection->prepare($sql);

            foreach ($params as $key => $val) {
                $type = PDO::PARAM_STR;
                if ($key === ':id' || $key === ':owner_id') {
                    $type = PDO::PARAM_INT;
                }
                
                // Ensure proper UTF-8 encoding for emoji support
                if (is_string($val)) {
                    $val = mb_convert_encoding($val, 'UTF-8', 'UTF-8');
                }
                
                $stmt->bindValue($key, $val, $type);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to update mapped email template: ' . $e->getMessage());
        }
    }

    /**
     * Get email template statistics
     */
    public function getEmailTemplateStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_templates,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_templates,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_templates
                    FROM {$this->email_templates_table}";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get email template statistics: ' . $e->getMessage());
        }
    }

  

    /**
     * Get email settings for owner
     */
    public function getEmailSettings($group_id) {
        try {

            // Correct variable name and use proper placeholders for meta_key IN clause
            $setting_keys = array("from_email_name", "from_email", "reply_to_email", "company_name", "company_address", "company_postcode", "company_area", "company_country");
            $placeholders = implode(',', array_fill(0, count($setting_keys), '?'));
            $sql = "
                SELECT *
                FROM {$this->wp_prefix}users_groups_settings 
                WHERE group_id = ? AND setting_key IN ($placeholders)
            ";
            $stmt = $this->connection->prepare($sql);
            // group_id is the first parameter, then setting_keys
            $params = array_merge([$group_id], $setting_keys);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            throw new Exception('Failed to get email settings: ' . $e->getMessage());
        }
    }

    /**
     * Save email settings for group
     */
    public function saveEmailSettings($group_id, $settings) {
        try {
            $setting_keys = array("from_email_name", "from_email", "reply_to_email", "company_name", "company_address", "company_postcode", "company_area", "company_country");


            
            foreach ($setting_keys as $key) {
                $value = isset($settings[$key]) ? $settings[$key] : '';

                // Check if the setting exists
                $checkStmt = $this->connection->prepare("
                    SELECT COUNT(*) as count 
                    FROM {$this->wp_prefix}users_groups_settings 
                    WHERE group_id = ? AND setting_key = ?
                ");
                $checkStmt->execute([$group_id, $key]);
                $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($exists && $exists['count'] > 0) {
                    // Update existing setting
                    $updateStmt = $this->connection->prepare("
                        UPDATE {$this->wp_prefix}users_groups_settings 
                        SET setting_id = ?
                        WHERE group_id = ? AND setting_key = ?
                    ");
                    $updateStmt->execute([$value, $group_id, $key]);
                } else {
                    // Insert new setting
                    $insertStmt = $this->connection->prepare("
                        INSERT INTO {$this->wp_prefix}users_groups_settings 
                        (group_id, setting_key, setting_id)
                        VALUES (?, ?, ?)
                    ");
                    $insertStmt->execute([$group_id, $key, $value]);
                }
            }
            
            return true;
        } catch (PDOException $e) {
            throw new Exception('Failed to save email settings: ' . $e->getMessage());
        }
    }

    /**
     * Get table prefix
     */
    public function getTablePrefix() {
        return $this->wp_prefix;
    }
    
    /**
     * Close database connection
     */
    public function close() {
        $this->connection = null;
    }
    
    /**
     * Destructor to close connection
     */
    public function __destruct() {
        $this->close();
    }
}

<?php
/**
 * Core PHP Database Handler
 * Connects to WordPress database without using WordPress functions
 */

class CoreDatabase {
    
    private $connection;
    private $table_name;
    private $wp_prefix;
    
    public function __construct() {
        // Load WordPress config to get database credentials
        $this->loadWordPressConfig();
        $this->connect();
        $this->table_name = $this->wp_prefix . 'gibbs_api_data';
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
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create custom tables
     */
    public function createTables() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content text NOT NULL,
            status varchar(50) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        try {
            $this->connection->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new Exception('Failed to create table: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all data with pagination
     */
    public function getAllData($limit = 10, $offset = 0) {
        $sql = "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get data: ' . $e->getMessage());
        }
    }
    
    /**
     * Get data by ID
     */
    public function getDataById($id) {
        $sql = "SELECT * FROM {$this->table_name} WHERE id = :id";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get data by ID: ' . $e->getMessage());
        }
    }
    
    /**
     * Insert new data
     */
    public function insertData($data) {
        $sql = "INSERT INTO {$this->table_name} (title, content, status) VALUES (:title, :content, :status)";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            $stmt->execute();
            
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Failed to insert data: ' . $e->getMessage());
        }
    }
    
    /**
     * Update existing data
     */
    public function updateData($id, $data) {
        $sql = "UPDATE {$this->table_name} SET title = :title, content = :content, status = :status WHERE id = :id";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to update data: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete data by ID
     */
    public function deleteData($id) {
        $sql = "DELETE FROM {$this->table_name} WHERE id = :id";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to delete data: ' . $e->getMessage());
        }
    }
    
    /**
     * Search data by title or content
     */
    public function searchData($searchTerm, $limit = 10) {
        $sql = "SELECT * FROM {$this->table_name} 
                WHERE title LIKE :search OR content LIKE :search 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $searchPattern = '%' . $searchTerm . '%';
            $stmt->bindParam(':search', $searchPattern, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to search data: ' . $e->getMessage());
        }
    }
    public function insertBookingData($booking_data) {

        $data = array(
            'owner_id' => $booking_data['owner_id'],
            'listing_id' => $booking_data['listing_id'],
            'date_start' => $booking_data['date_start'],
            'date_end' => $booking_data['date_end'],
            'bookings_author' => $booking_data['bookings_author'],
            'type' => $booking_data['type'],
            'price' => $booking_data['price']
        );

        if(isset($booking_data['booking_extra_data']) && !empty($booking_data['booking_extra_data'])){
            $data['booking_extra_data'] = $booking_data['booking_extra_data'];
        }

        if(isset($booking_data['comment']) && !empty($booking_data['comment'])){
            $data['comment'] = $booking_data['comment'];
        }
        
        $columns = array_keys($data);
        $placeholders = array_map(function($column) {
            return ':' . $column;
        }, $columns);
        
        $sql = "INSERT INTO {$this->wp_prefix}bookings_calendar (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            // Build the bind parameters array dynamically
            $bindParams = [];
            foreach ($data as $key => $value) {
                $bindParams[':' . $key] = $value;
            }
            
            $stmt->execute($bindParams);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Failed to insert booking data: ' . $e->getMessage());
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
    public function updateBookingData($booking_id, $booking_update_data) {
        // Dynamically build the UPDATE query based on the data array keys
        $columns = array_keys($booking_update_data);
        $set_clauses = array_map(function($column) {
            return $column . ' = :' . $column;
        }, $columns);
        
        $sql = "UPDATE {$this->wp_prefix}bookings_calendar SET " . implode(', ', $set_clauses) . " WHERE id = :booking_id";
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            // Build the bind parameters array dynamically
            $bindParams = [];
            foreach ($booking_update_data as $key => $value) {
                $bindParams[':' . $key] = $value;
            }
            // Add the booking_id parameter
            $bindParams[':booking_id'] = $booking_id;
            
            $stmt->execute($bindParams);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('Failed to update booking data: ' . $e->getMessage());
        }
    }
    
    /**
     * Get data count
     */
    public function getDataCount() {
        $sql = "SELECT COUNT(*) as count FROM {$this->table_name}";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'];
        } catch (PDOException $e) {
            throw new Exception('Failed to get data count: ' . $e->getMessage());
        }
    }
    public function getUserByEmail($email) {
        try{
            $sql = "SELECT * FROM {$this->wp_prefix}users WHERE user_email = :email";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch();
        }catch(PDOException $e){
            throw new Exception('Failed to get user by email: ' . $e->getMessage());
        }
    }

    public function getUserByEmailOrPhone($input) {
        try {
            // First, try to find by email
            $sql = "SELECT * FROM {$this->wp_prefix}users WHERE user_email = :input";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':input', $input, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user && !empty($user['ID'])) {
                return $user;
            }

            // If not found by email, try to find by phone in usermeta
            // $sql_meta = "SELECT user_id FROM {$this->wp_prefix}usermeta WHERE meta_key = 'phone' AND meta_value = :input";
            // $stmt_meta = $this->connection->prepare($sql_meta);
            // $stmt_meta->bindParam(':input', $input, PDO::PARAM_STR);
            // $stmt_meta->execute();
            // $meta = $stmt_meta->fetch();

            // if ($meta && !empty($meta['user_id'])) {
            //     // Fetch user by ID
            //     $sql_user = "SELECT * FROM {$this->wp_prefix}users WHERE ID = :user_id";
            //     $stmt_user = $this->connection->prepare($sql_user);
            //     $stmt_user->bindParam(':user_id', $meta['user_id'], PDO::PARAM_INT);
            //     $stmt_user->execute();
            //     return $stmt_user->fetch();
            // }

            // Not found
            return false;
        } catch(PDOException $e) {
            throw new Exception('Failed to get user by email or phone: ' . $e->getMessage());
        }
    }
    public function getUserById($user_id) {
        try{
            $sql = "SELECT * FROM {$this->wp_prefix}users WHERE ID = :user_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch();
        }catch(PDOException $e){
            throw new Exception('Failed to get user by id: ' . $e->getMessage());
        }
    }
    
    /**
     * Get WordPress users (accessing WordPress tables)
     */
    public function getUsers($limit = 10) {
        $users_table = $this->wp_prefix . 'users';
        $sql = "SELECT ID, user_login, user_email, user_registered 
                FROM {$users_table} 
                ORDER BY user_registered DESC 
                LIMIT :limit";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get users: ' . $e->getMessage());
        }
    }
    
    /**
     * Get WordPress posts (accessing WordPress tables)
     */
    public function getPosts($limit = 10) {
        $posts_table = $this->wp_prefix . 'posts';
        $sql = "SELECT ID, post_title, post_content, post_date 
                FROM {$posts_table} 
                WHERE post_status = 'publish' 
                ORDER BY post_date DESC 
                LIMIT :limit";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get posts: ' . $e->getMessage());
        }
    }
    
    /**
     * Get table prefix
     */
    public function getTablePrefix() {
        return $this->wp_prefix;
    }
    public function getGroupAdmin($group_id) {
        $sql = "SELECT * FROM {$this->wp_prefix}users_groups WHERE ID = :group_id";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch();
            if(isset($data['group_admin']) && $data['group_admin'] != ""){
                return $data['group_admin'];
            }
            return "";
        }catch (PDOException $e) {
            return "";
            throw new Exception('Failed to get group admin: ' . $e->getMessage());
        }
    }
    public function getSubscriptionDiscount($post_author, $listing_id, $cr_user_id, $subscription_product_ids) {
        $sql = "SELECT * FROM {$this->wp_prefix}posts WHERE post_author = :post_author AND post_type = 'subscriptiondiscount' AND post_status = 'publish'";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':post_author', $post_author, PDO::PARAM_INT);
            $stmt->execute();
            $subscriptions = $stmt->fetchAll();

            if (!$subscriptions || empty($subscriptions)) {
                return array();
            }

            // Get post IDs
            $post_ids = array_column($subscriptions, 'ID');
            if(empty($post_ids)){
                return array();
            }

            // Build IN clause for postmeta query
            $placeholders = implode(',', array_fill(0, count($post_ids), '?'));
            $meta_sql = "SELECT post_id, meta_key, meta_value FROM {$this->wp_prefix}postmeta WHERE post_id IN ($placeholders) AND meta_key = 'discount_start_date'";
            $meta_stmt = $this->connection->prepare($meta_sql);
            $meta_stmt->execute($post_ids);
            
            $discount_dates = array();
            while ($row = $meta_stmt->fetch()) {
                $discount_dates[$row['post_id']] = $row['meta_value'];
            }

            $result = array();
            $now = date('Y-m-d');
            foreach ($subscriptions as $subscription) {
                $post_id = $subscription['ID'];
                if(
                    isset($discount_dates[$post_id]) 
                    && !empty($discount_dates[$post_id]) 
                    && strtotime($now) >= strtotime($discount_dates[$post_id])
                ){
                    // Optionally, you may want to return the discount_start_date with the subscription
                    $subscription['discount_start_date'] = $discount_dates[$post_id];

                    $meta_keys = array(
                        "discount_listing_ids",
                        "discount_subscription_products",
                        "discount_start_date",
                        "discount_value",
                        "discount_type"
                    );
            
                    $getPostMetaMultiple = $this->getPostMetaMultiple($post_id, $meta_keys);

                    if(isset($getPostMetaMultiple['discount_listing_ids']) && !empty($getPostMetaMultiple['discount_listing_ids'])){
                        $discount_listing_ids = unserialize($getPostMetaMultiple['discount_listing_ids']);
                    }
                    if(isset($getPostMetaMultiple['discount_subscription_products']) && !empty($getPostMetaMultiple['discount_subscription_products'])){
                        $discount_subscription_products = unserialize($getPostMetaMultiple['discount_subscription_products']);
                    }

                    if(!empty($discount_listing_ids) && !empty($discount_subscription_products)){
                        $subscription['discount_listing_ids'] = $discount_listing_ids;
                        $subscription['discount_subscription_products'] = $discount_subscription_products;
                        $subscription['discount_type'] = $getPostMetaMultiple['discount_type'];
                        $subscription['discount_value'] = $getPostMetaMultiple['discount_value'];
                        if(in_array($listing_id, $discount_listing_ids)){

                            $has_product = false;

                            foreach($discount_subscription_products as $discount_subscription_product){
                                if(in_array($discount_subscription_product, $subscription_product_ids)){
                                    $has_product = true;
                                    break;
                                }
                            }

                            if(!$has_product){
                                continue;
                            }

                        }else{
                            continue;
                        }
                       
                    }else{
                        continue;
                    }


                    //$subscription['discount_meta'] = $getPostMetaMultiple;
                    $result[] = $subscription;
                }
            }
            return $result;
        }
        catch (PDOException $e) {
            return null;
        }
    }

    public function getUserSubscriptions($cr_user_id, $post_author) {
        // Fetch subscriptions, joining wp_posts to get product_type_id
        // Only include subscriptions where start_date is today or earlier (active or future)
        $sql = "SELECT p.product_type_id 
                FROM subscriptions s
                LEFT JOIN {$this->wp_prefix}posts p 
                    ON p.ID = s.product_id
                WHERE s.buyer_id = :cr_user_id 
                  AND s.owner_id = :post_author 
                  AND s.active = 1
                  AND s.start_date <= CURDATE()
                  AND (s.end_date IS NULL OR s.end_date >= CURDATE())";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':cr_user_id', $cr_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':post_author', $post_author, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    
    /**
     * Get available dates
     */
    public function getPost($pageId) {
        // Get post data
        $postSql = "SELECT * FROM {$this->wp_prefix}posts WHERE ID = :pageId";
        
        try {
            $stmt = $this->connection->prepare($postSql);
            $stmt->bindParam(':pageId', $pageId, PDO::PARAM_INT);
            $stmt->execute();
            return $post = $stmt->fetch();
        
            
        } catch (PDOException $e) {
            return null;
            //throw new Exception('Failed to get post data: ' . $e->getMessage());
        }
    }
    public function getAllBookings($listing_id) {
        // Get all bookings where listing_id matches and date_end is greater than 2 days before current date
        // $sql = "SELECT bc.id, bc.date_start, bc.date_end, 
        //                COALESCE(CAST(bcm.meta_value AS UNSIGNED), 1) AS count_slot
        //         FROM {$this->wp_prefix}bookings_calendar bc
        //         LEFT JOIN bookings_calendar_meta bcm 
        //             ON bcm.booking_id = bc.id 
        //             AND bcm.meta_key = 'number_of_guests'
        //         WHERE bc.listing_id = :pageId 
        //         AND bc.date_end > DATE_SUB(NOW(), INTERVAL 90 DAY)
        //         AND (
        //             bc.status = 'confirmed' 
        //             OR bc.status = 'paid' 
        //             OR bc.status = 'pay_to_confirm' 
        //             OR bc.status = 'waiting'
        //         )
        //         ORDER BY bc.date_start ASC";
        $sql = "SELECT bc.id, bc.date_start, bc.date_end
                   FROM {$this->wp_prefix}bookings_calendar bc
                WHERE bc.listing_id = :listing_id 
                AND bc.date_end > DATE_SUB(NOW(), INTERVAL 2 DAY)
                AND (
                    bc.status = 'confirmed' 
                    OR bc.status = 'paid' 
                    OR bc.status = 'pay_to_confirm' 
                    OR bc.status = 'waiting'
                )
                ORDER BY bc.date_start ASC";
       
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':listing_id', $listing_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            throw new Exception('Failed to get bookings: ' . $e->getMessage());
        }
    }
    public function getBookingMetaByGuest($bookingIds) {
        $inPlaceholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $metaSql = "SELECT booking_id, meta_value 
                    FROM bookings_calendar_meta 
                    WHERE booking_id IN ($inPlaceholders) 
                    AND meta_key = 'number_of_guests'";

  

        try {
            $metaStmt = $this->connection->prepare($metaSql);
            $metaStmt->execute($bookingIds);
            $metaResults = $metaStmt->fetchAll(PDO::FETCH_ASSOC);
            return $metaResults;
        } catch (PDOException $e) {
           return null;
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
    public function getBookingById($booking_id){
        $sql = "SELECT * FROM {$this->wp_prefix}bookings_calendar WHERE id = :booking_id";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get booking by id: ' . $e->getMessage());
        }
    }
    public function updatePostMeta($post_id, $meta_key, $meta_value){
        // First check if the meta key exists
        $checkSql = "SELECT COUNT(*) as count FROM {$this->wp_prefix}postmeta WHERE post_id = :post_id AND meta_key = :meta_key";
        
        try {
            $checkStmt = $this->connection->prepare($checkSql);
            $checkStmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $checkStmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
            $checkStmt->execute();
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                // Update existing meta
                $sql = "UPDATE {$this->wp_prefix}postmeta SET meta_value = :meta_value WHERE post_id = :post_id AND meta_key = :meta_key";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
                $stmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
                $stmt->bindParam(':meta_value', $meta_value, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            } else {
                // Insert new meta
                $sql = "INSERT INTO {$this->wp_prefix}postmeta (post_id, meta_key, meta_value) VALUES (:post_id, :meta_key, :meta_value)";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
                $stmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
                $stmt->bindParam(':meta_value', $meta_value, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount();
            }
        } catch (PDOException $e) {
            throw new Exception('Failed to update post meta: ' . $e->getMessage());
        }
    }
    
    /**
     * Get specific post meta value
     */
    public function getPostMeta($pageId, $metaKey) {
        $sql = "SELECT meta_value FROM {$this->wp_prefix}postmeta WHERE post_id = :pageId AND meta_key = :metaKey";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':pageId', $pageId, PDO::PARAM_INT);
            $stmt->bindParam(':metaKey', $metaKey, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? $result['meta_value'] : null;
            
        } catch (PDOException $e) {
            throw new Exception('Failed to get post meta: ' . $e->getMessage());
        }
    }
    
    /**
     * Get post meta value (WordPress-style function)
     */
    public function get_post_meta($post_id, $meta_key, $single = true) {
        $sql = "SELECT meta_value FROM {$this->wp_prefix}postmeta WHERE post_id = :post_id AND meta_key = :meta_key";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $meta_key, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($single) {
                $result = $stmt->fetch();
                return $result ? $result['meta_value'] : '';
            } else {
                $results = $stmt->fetchAll();
                $values = array();
                foreach ($results as $row) {
                    $values[] = $row['meta_value'];
                }
                return $values;
            }
            
        } catch (PDOException $e) {
            throw new Exception('Failed to get post meta: ' . $e->getMessage());
        }
    }
    public function get_slots_bookings($listing_id, $date_start, $date_end){
        $sql = "SELECT * FROM {$this->wp_prefix}bookings_calendar 
                            WHERE listing_id = :listing_id  
                            AND (
                                status = 'confirmed' 
                                OR status = 'paid' 
                                OR status = 'pay_to_confirm' 
                                OR status = 'waiting'
                            )
                            AND date_start < :date_end 
                            AND date_end > :date_start";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':listing_id', $listing_id, PDO::PARAM_INT);
            $stmt->bindParam(':date_start', $date_start, PDO::PARAM_STR);
            $stmt->bindParam(':date_end', $date_end, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return null;
        }
    }


    
    public function get_All_Postmeta($post_id) {
        $sql = "SELECT meta_key, meta_value FROM {$this->wp_prefix}postmeta WHERE post_id = :post_id";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $meta = array();
            while ($row = $stmt->fetch()) {
                $meta[$row['meta_key']] = $row['meta_value'];
            }
            
            return $meta;
        } catch (PDOException $e) {
            throw new Exception('Failed to get post meta: ' . $e->getMessage());
        }
    }
    
    /**
     * Get multiple post meta values
     */
    public function getPostMetaMultiple($pageId, $metaKeys) {
        if (empty($metaKeys)) {
            return array();
        }
        
        $placeholders = str_repeat('?,', count($metaKeys) - 1) . '?';
        $sql = "SELECT meta_key, meta_value FROM {$this->wp_prefix}postmeta WHERE post_id = ? AND meta_key IN ($placeholders)";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $params = array_merge(array($pageId), $metaKeys);
            $stmt->execute($params);
            
            $metaData = array();
            while ($row = $stmt->fetch()) {
                $metaData[$row['meta_key']] = $row['meta_value'];
            }

            $meta = array();

            foreach($metaKeys as $metaKey){
                if(isset($metaData[$metaKey]) && $metaData[$metaKey] != ""){
                    $meta[$metaKey] = $metaData[$metaKey];
                }else{
                    $meta[$metaKey] = "";
                }
            }
            
            return $meta;
            
        } catch (PDOException $e) {
            throw new Exception('Failed to get post meta: ' . $e->getMessage());
        }
    }
    public function getUserMetaMultiple($userId, $metaKeys) {
        if (empty($metaKeys)) {
            return array();
        }
        
        $placeholders = str_repeat('?,', count($metaKeys) - 1) . '?';
        $sql = "SELECT meta_key, meta_value FROM {$this->wp_prefix}usermeta WHERE user_id = ? AND meta_key IN ($placeholders)";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $params = array_merge(array($userId), $metaKeys);
            $stmt->execute($params);
            
            $metaData = array();
            while ($row = $stmt->fetch()) {
                $metaData[$row['meta_key']] = $row['meta_value'];
            }

            $meta = array();

            foreach($metaKeys as $metaKey){
                if(isset($metaData[$metaKey]) && $metaData[$metaKey] != ""){
                    $meta[$metaKey] = $metaData[$metaKey];
                }else{
                    $meta[$metaKey] = "";
                }
            }
            
            return $meta;
            
        } catch (PDOException $e) {
            throw new Exception('Failed to get post meta: ' . $e->getMessage());
        }
    }
    public function getGiftDataByGiftCode($coupon){
        $sql = "SELECT * FROM {$this->wp_prefix}postmeta WHERE meta_key = 'gift_code' AND meta_value = :coupon";
        
        try {
            
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':coupon', $coupon, PDO::PARAM_STR);
            $stmt->execute();
    
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get bookings by coupon: ' . $e->getMessage());
        }
    }
    public function check_if_coupon_exists($coupon){
        $sql = "SELECT * FROM {$this->wp_prefix}posts WHERE post_type = 'shop_coupon' AND post_status = 'publish' AND post_title = :coupon ORDER BY post_date DESC LIMIT 1";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':coupon', $coupon, PDO::PARAM_STR);
            $stmt->execute();
    
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get bookings by coupon: ' . $e->getMessage());
        }
    }
    
    
    
    
    /**
     * Generate unique booking token
     */
    private function generateBookingToken() {
        // Get the last booking ID
        $sql = "SELECT MAX(id) as last_id FROM {$this->wp_prefix}slot_bookings";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $lastId = $result['last_id'] ?? 0;
            
            // Generate unique token using last ID + timestamp + random string
            $timestamp = date('Ymd').time();
            $randomString = bin2hex(random_bytes(8));
            $tokenData = $lastId . '_' . $timestamp . '_' . $randomString;
            
            return md5($tokenData);
        } catch (PDOException $e) {
            // Fallback if query fails
            $timestamp = time();
            $randomString = bin2hex(random_bytes(16));
            return md5($timestamp . '_' . $randomString);
        }
    }

    public function deleteBookingByToken($bookingToken){
        $sql = "DELETE FROM {$this->wp_prefix}slot_bookings WHERE booking_token = :bookingToken";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':bookingToken', $bookingToken, PDO::PARAM_STR);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            throw new Exception('Failed to delete booking by token: ' . $e->getMessage());
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

    /**
     * Book a slot
     */
    public function bookConfirmation($data) {
        // Generate unique booking token
        $bookingToken = $this->generateBookingToken();

        $additional_info = json_encode($data);
        $listing_id = $data['listing_id'];

        // Prepare services data
        $services = null;
        if(isset($data['services']) && !empty($data['services'])){
            $services = json_encode($data['services']);
        }

        // Prepare other optional fields with defaults
        $price_type = $data['price_type'] ?? null;
        $coupon = $data['coupon'] ?? null;
        
        $sql = "INSERT INTO {$this->wp_prefix}slot_bookings 
                (booking_token, listing_id, start_date, end_date, adults, slot, slot_id, price_type, services, coupon, page_url,additional_info) 
                VALUES (:booking_token, :listing_id, :start_date, :end_date, :adults, :slot, :slot_id, :price_type, :services, :coupon, :page_url, :additional_info)";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':booking_token', $bookingToken, PDO::PARAM_STR);
            $stmt->bindParam(':listing_id', $listing_id, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
            $stmt->bindParam(':adults', $data['adults'], PDO::PARAM_INT);
            $stmt->bindParam(':slot', $data['slot_text'], PDO::PARAM_STR);
            $stmt->bindParam(':slot_id', $data['slot_id'], PDO::PARAM_STR);
            $stmt->bindParam(':price_type', $price_type, PDO::PARAM_STR);
            $stmt->bindParam(':services', $services, PDO::PARAM_STR);
            $stmt->bindParam(':coupon', $coupon, PDO::PARAM_STR);
            $stmt->bindParam(':page_url', $data['current_page_url'], PDO::PARAM_STR);
            $stmt->bindParam(':additional_info', $additional_info, PDO::PARAM_STR);
            $stmt->execute();
            
            $bookingId = $this->connection->lastInsertId();
            
            return [
                'booking_id' => $bookingId,
                'booking_token' => $bookingToken
            ];
        } catch (PDOException $e) {
            //if ($e->getCode() == 23000) { // Duplicate entry
            //    throw new Exception('This time slot is already booked');
            //}
            throw new Exception('Failed to book slot: ' . $e->getMessage());
        }
    }
    public function getBookingByToken($bookingToken){
        $sql = "SELECT * FROM {$this->wp_prefix}slot_bookings WHERE booking_token = :bookingToken";
        
        try {
            
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':bookingToken', $bookingToken, PDO::PARAM_STR);
            $stmt->execute();
    
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get bookings by coupon: ' . $e->getMessage());
        }
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
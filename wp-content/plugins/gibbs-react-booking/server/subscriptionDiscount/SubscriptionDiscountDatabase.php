<?php
/**
 * Subscription Discount data access layer
 * Stores discounts as custom posts with meta instead of bespoke tables
 */

class SubscriptionDiscountDatabase {
    private $connection;
    private $db_name;
    private $db_user;
    private $db_password;
    private $db_host;
    private $wp_prefix;
    private $subscriptionProductTable;

    public function __construct() {
        
        $this->initializeCustomConnection();
    }

    /**
     * Ensure core WordPress functions are available
     */
    private function ensureWordPressLoaded() {
        if (!function_exists('wp_insert_post')) {
            $wp_load = dirname(__FILE__, 6) . '/wp-load.php';
            if (file_exists($wp_load)) {
                require_once $wp_load;
            } else {
                throw new Exception('WordPress bootstrap file not found');
            }
        }
    }

    private function initializeCustomConnection() {
        try {
            $this->loadWordPressConfig();
            $this->connect();
            $this->subscriptionProductTable = $this->resolveSubscriptionProductTableName();

        } catch (Exception $e) {
            $this->connection = null;
            $this->subscriptionProductTable = null;
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to initialize custom DB connection: ' . $e->getMessage());
            }
        }
    }

    private function loadWordPressConfig() {
        $wpConfigPath = dirname(__FILE__, 6) . '/wp-config.php';

        if (!file_exists($wpConfigPath)) {
            throw new Exception('WordPress configuration file not found');
        }

        $configContent = file_get_contents($wpConfigPath);

        preg_match("/define\(\s*'DB_NAME',\s*'([^']+)'\s*\)/", $configContent, $dbNameMatch);
        preg_match("/define\(\s*'DB_USER',\s*'([^']+)'\s*\)/", $configContent, $dbUserMatch);
        preg_match("/define\(\s*'DB_PASSWORD',\s*'([^']+)'\s*\)/", $configContent, $dbPasswordMatch);
        preg_match("/define\(\s*'DB_HOST',\s*'([^']+)'\s*\)/", $configContent, $dbHostMatch);
        preg_match("/\\\$table_prefix\s*=\s*'([^']+)'/", $configContent, $tablePrefixMatch);

        if (!$dbNameMatch || !$dbUserMatch || !$dbPasswordMatch || !$dbHostMatch) {
            throw new Exception('Database configuration not found in wp-config.php');
        }

        $this->db_name     = $dbNameMatch[1];
        $this->db_user     = $dbUserMatch[1];
        $this->db_password = $dbPasswordMatch[1];
        $this->db_host     = $dbHostMatch[1];
        $this->wp_prefix   = $tablePrefixMatch ? $tablePrefixMatch[1] : 'wp_';
    }

    private function connect() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->db_host};dbname={$this->db_name};charset=utf8mb4",
                $this->db_user,
                $this->db_password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    private function closeConnection() {
        $this->connection = null;
    }

    private function resolveSubscriptionProductTableName() {
        if (!$this->connection) {
            return null;
        }

        $candidates = [];
        if (!empty($this->wp_prefix)) {
            $candidates[] = $this->wp_prefix . 'subscription_product_type';
        }
        $candidates[] = 'subscription_product_type';

        foreach ($candidates as $candidate) {
            if ($this->tableExists($candidate)) {
                return $candidate;
            }
        }

        // Default to prefixed variant even if missing so existence checks can fail gracefully later.
        return !empty($this->wp_prefix) ? $this->wp_prefix . 'subscription_product_type' : 'subscription_product_type';
    }

    private function tableExists($tableName) {
        if (!$this->connection) {
            return false;
        }

        try {
            $sql = "SELECT COUNT(*) AS table_count
                    FROM information_schema.tables
                    WHERE table_schema = :schema AND table_name = :table";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':schema', $this->db_name, PDO::PARAM_STR);
            $stmt->bindParam(':table', $tableName, PDO::PARAM_STR);
            $stmt->execute();

            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to verify table existence: ' . $e->getMessage());
            }
            return false;
        }
    }

    private function subscriptionProductTableExists() {
        if (!$this->subscriptionProductTable) {
            return false;
        }
        return $this->tableExists($this->subscriptionProductTable);
    }

    /**
     * Query discount posts for an owner
     */
    public function getDiscounts($ownerId, $limit = 20, $offset = 0, $search = '', $status = null) {

        if (!$this->connection) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        $limit  = max(1, intval($limit));
        $offset = max(0, intval($offset));
        $statusFilter = ($status !== null && $status !== '' && $status !== 'all')
            ? $status
            : null;
        $searchTerm = trim($search);
        if (function_exists('sanitize_text_field')) {
            $searchTerm = sanitize_text_field($searchTerm);
        }

        $postsTable = $this->wp_prefix . 'posts';
        $metaTable  = $this->wp_prefix . 'postmeta';

        $whereClauses = [
            "p.post_type = :post_type",
            "p.post_author = :owner_id",
        ];

        $params = [
            ':post_type' => 'subscriptiondiscount',
            ':owner_id'  => $ownerId,
        ];

        if ($searchTerm !== '') {
            $whereClauses[] = "(p.post_title LIKE :search)";
            $params[':search'] = '%' . $searchTerm . '%';
        }

        if($statusFilter == "active"){
            $statusFilter = "publish";
        }else if($statusFilter == "inactive"){
            $statusFilter = "draft";
        }else if($statusFilter == "expired"){
            $statusFilter = "expired";
        }else if($statusFilter == "all"){
            $statusFilter = "publish";
        }

        if ($statusFilter !== null) {
            $whereClauses[] = "(p.post_status = :status)";
            $params[':status'] = $statusFilter;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*)
                     FROM {$postsTable} p
                     LEFT JOIN {$metaTable} statusMeta
                       ON statusMeta.post_id = p.ID
                       AND statusMeta.meta_key = 'discount_status'
                     WHERE {$whereSql}";

        try {
            $countStmt = $this->connection->prepare($countSql);
            foreach ($params as $key => $value) {
                $paramType = ($key === ':owner_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
                $countStmt->bindValue($key, $value, $paramType);
            }
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to count discounts: ' . $e->getMessage());
            }
            return [
                'items' => [],
                'total' => 0,
            ];
        }

       

        if ($total === 0) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

       $selectSql = "SELECT p.ID, p.post_author, p.post_title, p.post_date, p.post_modified, p.post_status
                      FROM {$postsTable} p
                      LEFT JOIN {$metaTable} statusMeta
                        ON statusMeta.post_id = p.ID
                        AND statusMeta.meta_key = 'discount_status'
                      WHERE {$whereSql}
                      ORDER BY p.post_date DESC
                      LIMIT :limit OFFSET :offset";

        try {
            $selectStmt = $this->connection->prepare($selectSql);
            foreach ($params as $key => $value) {
                $paramType = ($key === ':owner_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
                $selectStmt->bindValue($key, $value, $paramType);
            }
            $selectStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $selectStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $selectStmt->execute();
            $posts = $selectStmt->fetchAll();
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to fetch discounts: ' . $e->getMessage());
            }
            return [
                'items' => [],
                'total' => $total,
            ];
        }
        if (empty($posts)) {
            return [
                'items' => [],
                'total' => $total,
            ];
        }

        $metaKeys = [
            'discount_code',
            'discount_type',
            'discount_value',
            'discount_max_redemptions',
            'discount_start_date',
            'discount_end_date',
            'discount_status',
            'discount_notes',
            'discount_subscription_products',
            'discount_listing_ids',
            'created_by',
        ];

        $items = [];

        foreach ($posts as $post) {

            $post = (array) $post;

            $post["meta"] = $this->getPostMetaForPostId($post['ID'],$metaKeys);

            $items[] = $post;

        }



        // $postIds = array_map(function ($post) {
        //     return isset($post['ID']) ? (int) $post['ID'] : 0;
        // }, $posts);

        

        // $meta = $this->getPostMetaForPosts($postIds, $metaKeys);

        // $items = [];
        // foreach ($posts as $post) {
        //     $discount = $this->mapRowToDiscount($post, $meta);
        //     if ($discount) {
        //         $items[] = $discount;
        //     }
        // }


        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function getDiscountsCount($ownerId, $search = '', $status = null) {
        $result = $this->getDiscounts($ownerId, 1, 0, $search, $status);
        return $result['total'];
    }

    public function getDiscountByCode($ownerId, $code) {
        $query = new WP_Query([
            'post_type'      => 'subscriptiondiscount',
            'post_status'    => ['publish'],
            'author'         => $ownerId,
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'discount_code',
                    'value' => strtoupper($code),
                ],
            ],
        ]);

        if (!$query->have_posts()) {
            return false;
        }

        return $this->mapPostToArray($query->posts[0]);
    }

    public function getDiscountById($discountId) {
        $selectSql = "SELECT p.ID, p.post_author, p.post_title, p.post_date, p.post_modified, p.post_status
                      FROM {$this->wp_prefix}posts p
                      WHERE p.ID = :discountId";

        try {
            $selectStmt = $this->connection->prepare($selectSql);
            $selectStmt->bindValue(':discountId', $discountId, PDO::PARAM_INT);
            $selectStmt->execute();
            $post = $selectStmt->fetch();

            $metaKeys = [
                'discount_code',
                'discount_type',
                'discount_value',
                'discount_max_redemptions',
                'discount_start_date',
                'discount_end_date',
                'discount_status',
                'discount_notes',
                'discount_subscription_products',
                'discount_listing_ids',
                'created_by',
            ];

            if($post){
                $post = (array) $post;
                $post["meta"] = $this->getPostMetaForPostId($post['ID'], $metaKeys);
                return $post;
            }else{
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }   
    }

    public function createDiscount($data) {
        try {
            $this->ensureWordPressLoaded();

            $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : 0;
            if ($ownerId <= 0) {
                throw new Exception('Owner ID is required to create a discount');
            }

            $name = isset($data['name']) ? trim($data['name']) : '';
            if ($name === '') {
                throw new Exception('Discount name is required');
            }

            $code = isset($data['code']) ? strtoupper(trim($data['code'])) : '';
            $type = isset($data['discount_type']) ? $data['discount_type'] : ($data['type'] ?? 'percentage');
            $value = isset($data['discount_value']) ? (float) $data['discount_value'] : (isset($data['value']) ? (float) $data['value'] : 0);
            $status = isset($data['status']) ? $data['status'] : 'active';
            $startDate = array_key_exists('start_date', $data) ? $data['start_date'] : null;
            $createdBy = isset($data['created_by']) ? (int) $data['created_by'] : $ownerId;
            $subscriptionProducts = $this->sanitizeIdArray($data['subscription_products'] ?? ($data['discount_subscription_products'] ?? []));
            $listingIds = $this->sanitizeIdArray($data['listing_ids'] ?? ($data['discount_listing_ids'] ?? []));

            $status = $status !== '' ? $status : 'active';
            $type = $type !== '' ? $type : 'percentage';

            $postArr = [
                'post_type'   => 'subscriptiondiscount',
                'post_status' => 'publish',
                'post_title'  => $name,
                'post_author' => $ownerId,
            ];

            

            if ($code !== '') {
                $postArr['post_title'] = sanitize_title($name . '-' . $code);
            }

            $postId = wp_insert_post($postArr, true);

            // echo '<pre>';
            // print_r($postArr);
            // echo '</pre>';
            // exit;
            if (is_wp_error($postId)) {
                throw new Exception('Failed to create subscription discount: ' . $postId->get_error_message());
            }

            update_post_meta($postId, 'discount_code', $code);
            update_post_meta($postId, 'discount_type', $type);
            update_post_meta($postId, 'discount_value', $value);
            update_post_meta($postId, 'discount_start_date', $startDate);
            update_post_meta($postId, 'discount_status', $status);
            update_post_meta($postId, 'discount_subscription_products', $subscriptionProducts);
            update_post_meta($postId, 'discount_listing_ids', $listingIds);
            update_post_meta($postId, 'created_by', $createdBy);

            return (int) $postId;
        } catch (Exception $e) {

            // echo '<pre>';
            // print_r($e);
            // echo '</pre>';
            // exit;
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to create discount: ' . $e->getMessage());
            }
            return 0;
        }
    }

    public function updateDiscount($discountId, $data) {
        try {
            $this->ensureWordPressLoaded();

            $discountId = intval($discountId);
            if ($discountId <= 0) {
                throw new Exception('Invalid discount ID supplied');
            }

            $postUpdates = ['ID' => $discountId];
            $shouldUpdatePost = false;

            if (isset($data['name']) && trim((string) $data['name']) !== '') {
                $name = trim((string) $data['name']);
                $postUpdates['post_title'] = $name;
                $shouldUpdatePost = true;
            }

            if (isset($data['code']) && trim((string) $data['code']) !== '') {
                $code = strtoupper(trim((string) $data['code']));
                if (!isset($postUpdates['post_title'])) {
                    $currentPost = get_post($discountId);
                    $baseTitle = $currentPost ? $currentPost->post_title : '';
                    $postUpdates['post_title'] = sanitize_title($baseTitle . '-' . $code);
                } else {
                    $postUpdates['post_title'] = sanitize_title($postUpdates['post_title'] . '-' . $code);
                }
                $shouldUpdatePost = true;
            }

            // if (isset($data['status'])) {
            //     $postUpdates['post_status'] = ($data['status'] === 'active') ? 'publish' : 'draft';
            //     $shouldUpdatePost = true;
            // }

            if ($shouldUpdatePost) {
                $result = wp_update_post($postUpdates, true);
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
            }

            $this->persistMeta($discountId, $data, false);

            return true;
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to update discount: ' . $e->getMessage());
            }
            return false;
        }
    }
    public function updateDiscountStatus($discountId, $status) {
        try {
            $this->ensureWordPressLoaded();

            $normalizedStatus = ($status === 'active') ? 'active' : 'inactive';
            $postStatus = ($normalizedStatus === 'active') ? 'publish' : 'draft';

            $postArr = [
                'ID' => $discountId,
                'post_status' => $postStatus,
            ];

            $result = wp_update_post($postArr, true);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            update_post_meta($discountId, 'discount_status', $normalizedStatus);

            return true;
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to update discount status: ' . $e->getMessage());
            }
            return false;
        }
    }

    public function deactivateDiscount($discountId) {
        update_post_meta($discountId, 'discount_status', 'inactive');
        return true;
    }

    public function isUserAdministrator($userId) {
        return user_can($userId, 'administrator');
    }

    public function getCurrentDateTime() {
        return current_time('mysql');
    }

    public function getSubscriptionProducts($ownerId) {
        $products = [];
        if (!$ownerId || !$this->subscriptionProductTableExists()) {
            return $products;
        }

        try {
            $sql = "SELECT id, name, price, size, notes, owner_id, category_id
                    FROM {$this->subscriptionProductTable}
                    WHERE owner_id = :owner_id
                    ORDER BY name ASC";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll();

            foreach ($results as $row) {
                $products[] = [
                    'id'          => isset($row['id']) ? (int) $row['id'] : 0,
                    'title'       => isset($row['name']) ? $row['name'] : '',
                    'type'        => 'subscription_product_type',
                    'price'       => isset($row['price']) ? (float) $row['price'] : 0,
                    'size'        => isset($row['size']) ? (float) $row['size'] : 0,
                    'notes'       => isset($row['notes']) ? $row['notes'] : '',
                    'owner_id'    => isset($row['owner_id']) ? (int) $row['owner_id'] : 0,
                    'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : 0,
                ];
            }
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to fetch subscription products: ' . $e->getMessage());
            }
        }

        return $products;
    }

    public function getOwnerListings($ownerId) {
        $listings = [];

        if (!$ownerId || !$this->connection) {
            return $listings;
        }

        $posts = $this->getPostsByOwner('listing', $ownerId, 'publish');
        if (empty($posts)) {
            return $listings;
        }

        $postIds = array_map(function ($post) {
            return isset($post['ID']) ? (int) $post['ID'] : 0;
        }, $posts);

        $meta = $this->getPostMetaForPosts($postIds, ['listing_address', 'listing_city']);

        foreach ($posts as $post) {
            $postId = isset($post['ID']) ? (int) $post['ID'] : 0;
            if ($postId <= 0) {
                continue;
            }

            $listings[] = [
                'id'      => $postId,
                'title'   => isset($post['post_title']) ? $post['post_title'] : '',
                'status'  => isset($post['post_status']) ? $post['post_status'] : '',
                'date'    => isset($post['post_date']) ? $post['post_date'] : null,
                'meta'    => isset($meta[$postId]) ? $meta[$postId] : [],
            ];
        }

        return $listings;
    }

    private function getPostsByOwner($postType, $ownerId, $postStatus = 'publish', $orderBy = 'post_title', $order = 'ASC') {
        if (!$this->connection) {
            return [];
        }

        try {
            $postsTable = $this->wp_prefix . 'posts';
            $sql = "SELECT ID, post_title, post_status, post_date, post_type, post_author
                    FROM {$postsTable}
                    WHERE post_type = :post_type
                      AND post_author = :owner_id";

            if ($postStatus !== null) {
                $sql .= " AND post_status = :post_status";
            }

            $orderBy = in_array($orderBy, ['post_title', 'post_date', 'ID'], true) ? $orderBy : 'post_title';
            $order   = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
            $sql    .= " ORDER BY {$orderBy} {$order}";

            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':post_type', $postType, PDO::PARAM_STR);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);

            if ($postStatus !== null) {
                $stmt->bindParam(':post_status', $postStatus, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to fetch posts: ' . $e->getMessage());
            }
            return [];
        }
    }

    private function getPostMetaForPostId($postId, array $metaKeys = []) {
        if (empty($metaKeys)) {
            return array();
        }
        
        $placeholders = str_repeat('?,', count($metaKeys) - 1) . '?';
        $sql = "SELECT meta_key, meta_value FROM {$this->wp_prefix}postmeta WHERE post_id = ? AND meta_key IN ($placeholders)";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $params = array_merge(array($postId), $metaKeys);
            $stmt->execute($params);
            
            $metaData = array();
            while ($row = $stmt->fetch()) {
                $metaData[$row['meta_key']] = $row['meta_value'];
            }

            $meta = array();

            foreach($metaKeys as $metaKey){
                if(isset($metaData[$metaKey]) && $metaData[$metaKey] != ""){

                    if(($metaKey == "discount_subscription_products" || $metaKey == "discount_listing_ids") && $metaData[$metaKey] != ""){
                        $meta[$metaKey] = unserialize($metaData[$metaKey]);
                    }else{
                        $meta[$metaKey] = $metaData[$metaKey];
                    }
                }else{
                    $meta[$metaKey] = "";
                }
            }

            
            return $meta;
            
        } catch (PDOException $e) {
            throw new Exception('Failed to get post meta: ' . $e->getMessage());
        }
    }
    
    private function getPostMetaForPosts(array $postIds, array $metaKeys = []) {
        if (!$this->connection || empty($postIds)) {
            return [];
        }

        try {
            $metaTable = $this->wp_prefix . 'postmeta';
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            $sql = "SELECT post_id, meta_key, meta_value
                    FROM {$metaTable}
                    WHERE post_id IN ({$placeholders})";

            $params = $postIds;

            if (!empty($metaKeys)) {
                $metaPlaceholders = implode(',', array_fill(0, count($metaKeys), '?'));
                $sql .= " AND meta_key IN ({$metaPlaceholders})";
                $params = array_merge($params, $metaKeys);
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            $meta = [];
            foreach ($results as $row) {
                $postId = (int) $row['post_id'];
                if (!isset($meta[$postId])) {
                    $meta[$postId] = [];
                }
                $meta[$postId][$row['meta_key']][] = $row['meta_value'];
            }

            return $meta;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[SubscriptionDiscountDatabase] Failed to fetch post meta: ' . $e->getMessage());
            }
            return [];
        }
    }

    private function mapRowToDiscount(array $row, array $meta) {
        if (!isset($row['ID'])) {
            return null;
        }

        $postId = (int) $row['ID'];
        $metaForPost = isset($meta[$postId]) ? $meta[$postId] : [];

        $code           = $this->getMetaValueFromRaw($metaForPost, 'discount_code', '');
        $type           = $this->getMetaValueFromRaw($metaForPost, 'discount_type', 'percentage');
        $value          = (float) $this->getMetaValueFromRaw($metaForPost, 'discount_value', 0);
        $maxRedemptions = $this->getMetaValueFromRaw($metaForPost, 'discount_max_redemptions', '');
        $startDate      = $this->getMetaValueFromRaw($metaForPost, 'discount_start_date', null);
        $endDate        = $this->getMetaValueFromRaw($metaForPost, 'discount_end_date', null);
        $status         = $this->getMetaValueFromRaw($metaForPost, 'discount_status', isset($row['discount_status']) ? $row['discount_status'] : 'active');
        $notes          = $this->getMetaValueFromRaw($metaForPost, 'discount_notes', '');
        $createdBy      = (int) $this->getMetaValueFromRaw($metaForPost, 'created_by', isset($row['post_author']) ? $row['post_author'] : 0);

        $subscriptionProducts = $this->getMetaArrayFromRaw($metaForPost, 'discount_subscription_products');
        $listingIds           = $this->getMetaArrayFromRaw($metaForPost, 'discount_listing_ids');

        $subscriptionProducts = $this->sanitizeIdArray($subscriptionProducts);
        $listingIds           = $this->sanitizeIdArray($listingIds);

        return [
            'id'                    => $postId,
            'owner_id'              => isset($row['post_author']) ? (int) $row['post_author'] : 0,
            'name'                  => isset($row['post_title']) ? $row['post_title'] : '',
            'code'                  => $code,
            'discount_type'         => $type,
            'discount_value'        => $value,
            'max_redemptions'       => $maxRedemptions,
            'start_date'            => $startDate,
            'end_date'              => $endDate,
            'status'                => $status,
            'notes'                 => $notes,
            'subscription_products' => $subscriptionProducts,
            'listing_ids'           => $listingIds,
            'created_by'            => $createdBy > 0 ? $createdBy : (isset($row['post_author']) ? (int) $row['post_author'] : 0),
            'created_at'            => isset($row['post_date']) ? $row['post_date'] : null,
            'updated_at'            => isset($row['post_modified']) ? $row['post_modified'] : null,
            'redemption_count'      => 0,
        ];
    }

    private function getMetaValueFromRaw(array $meta, $key, $default = '') {
        if (!isset($meta[$key]) || !is_array($meta[$key]) || empty($meta[$key])) {
            return $default;
        }

        $value = $meta[$key][0];

        if (function_exists('maybe_unserialize')) {
            $value = maybe_unserialize($value);
        } else {
            $maybe = @unserialize($value);
            if ($maybe !== false || $value === 'b:0;') {
                $value = $maybe;
            }
        }

        if ($value === '' || $value === null) {
            return $default;
        }

        return $value;
    }

    private function getMetaArrayFromRaw(array $meta, $key) {
        $value = $this->getMetaValueFromRaw($meta, $key, []);
        if (!is_array($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        return array_map('intval', $value);
    }

    private function persistMeta($postId, $data, $enforceRequired = true) {
        if (isset($data['code'])) {
            update_post_meta($postId, 'discount_code', strtoupper($data['code']));
        }

        if (isset($data['discount_type']) || isset($data['type'])) {
            $type = isset($data['discount_type']) ? $data['discount_type'] : $data['type'];
            update_post_meta($postId, 'discount_type', sanitize_text_field($type));
        }

        if (isset($data['discount_value']) || isset($data['value'])) {
            $value = isset($data['discount_value']) ? $data['discount_value'] : $data['value'];
            update_post_meta($postId, 'discount_value', floatval($value));
        }

        if (array_key_exists('max_redemptions', $data)) {
            $max = ($data['max_redemptions'] === null || $data['max_redemptions'] === '') ? '' : intval($data['max_redemptions']);
            update_post_meta($postId, 'discount_max_redemptions', $max);
        }

        if (array_key_exists('start_date', $data)) {
            update_post_meta($postId, 'discount_start_date', $data['start_date']);
        }

        if (array_key_exists('end_date', $data)) {
            update_post_meta($postId, 'discount_end_date', $data['end_date']);
        }

        if (isset($data['status'])) {
            update_post_meta($postId, 'discount_status', sanitize_text_field($data['status']));
        }

        if (array_key_exists('notes', $data)) {
            update_post_meta($postId, 'discount_notes', wp_kses_post($data['notes']));
        }

        if (array_key_exists('subscription_products', $data)) {
            $products = $this->sanitizeIdArray($data['subscription_products']);
            update_post_meta($postId, 'discount_subscription_products', $products);
        }

        if (array_key_exists('listing_ids', $data)) {
            $listings = $this->sanitizeIdArray($data['listing_ids']);
            update_post_meta($postId, 'discount_listing_ids', $listings);
        }
    }

    private function sanitizeIdArray($value) {
        if (!is_array($value)) {
            return [];
        }
        $clean = [];
        foreach ($value as $id) {
            $id = intval($id);
            if ($id > 0) {
                $clean[] = $id;
            }
        }
        return array_values(array_unique($clean));
    }

    private function mapPostToArray($post) {
        $meta = get_post_meta($post->ID);

        $status = $this->getMetaValue($meta, 'discount_status', 'active');
        $start  = $this->getMetaValue($meta, 'discount_start_date', null);
        $end    = $this->getMetaValue($meta, 'discount_end_date', null);

        return [
            'id'                       => (int) $post->ID,
            'owner_id'                 => (int) $post->post_author,
            'name'                     => $post->post_title,
            'code'                     => $this->getMetaValue($meta, 'discount_code', ''),
            'discount_type'            => $this->getMetaValue($meta, 'discount_type', 'percentage'),
            'discount_value'           => (float) $this->getMetaValue($meta, 'discount_value', 0),
            'max_redemptions'          => $this->getMetaValue($meta, 'discount_max_redemptions', ''),
            'start_date'               => $start,
            'end_date'                 => $end,
            'status'                   => $status,
            'notes'                    => $this->getMetaValue($meta, 'discount_notes', ''),
            'subscription_products'    => $this->getMetaArray($meta, 'discount_subscription_products'),
            'listing_ids'              => $this->getMetaArray($meta, 'discount_listing_ids'),
            'created_by'               => (int) $this->getMetaValue($meta, 'created_by', $post->post_author),
            'created_at'               => $post->post_date,
            'updated_at'               => $post->post_modified,
            'redemption_count'         => 0,
        ];
    }

    private function getMetaValue($meta, $key, $default = '') {
        if (isset($meta[$key][0])) {
            $value = maybe_unserialize($meta[$key][0]);
            return $value === '' ? $default : $value;
        }
        return $default;
    }

    private function getMetaArray($meta, $key) {
        $value = $this->getMetaValue($meta, $key, []);
        if (!is_array($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        return array_map('intval', $value);
    }

    public function __destruct() {
        $this->closeConnection();
    }
}


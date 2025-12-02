<?php
/**
 * Customer data access layer
 * Handles database operations for customers (companies and usergroups)
 */

class CustomerDatabase {
    private $connection;
    private $db_name;
    private $db_user;
    private $db_password;
    private $db_host;
    private $wp_prefix;

    public function __construct() {
        $this->initializeCustomConnection();
    }

    private function initializeCustomConnection() {
        try {
            $this->loadWordPressConfig();
            $this->connect();
        } catch (Exception $e) {
            $this->connection = null;
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Failed to initialize custom DB connection: ' . $e->getMessage());
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

    /**
     * Get customers (companies) with pagination and filters
     * Gets data from users_groups table with unique superadmin IDs
     */
    public function getCustomers($params = []) {
        if (!$this->connection) {
            return ['customers' => [], 'pagination' => ['total' => 0, 'total_pages' => 1, 'page' => 1]];
        }

        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $perPage = isset($params['per_page']) ? max(1, intval($params['per_page'])) : 20;
        $tab = isset($params['tab']) ? $params['tab'] : 'all';
        $search = isset($params['search']) ? trim($params['search']) : '';
        $sortBy = isset($params['sort_by']) ? $params['sort_by'] : 'name';
        $sortDirection = isset($params['sort_direction']) && strtoupper($params['sort_direction']) === 'DESC' ? 'DESC' : 'ASC';
        $status = isset($params['status']) ? strtolower(trim($params['status'])) : 'all';
        $superadminId = isset($params['superadmin_id']) ? intval($params['superadmin_id']) : null;

        $offset = ($page - 1) * $perPage;

        // Validate sort column
        $allowedSortColumns = ['name', 'created_at', 'superadmin', 'group_admin', 'type_of_form'];
        $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'superadmin';

        // Build where conditions for subquery
        $baseWhereConditions = ["superadmin IS NOT NULL AND superadmin != 0"];
        // If "invoice" tab is active, only include superadmins with active license_status in usermeta
        if ($tab === 'stripe') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1 
                FROM {$this->wp_prefix}usermeta um 
                WHERE um.user_id = superadmin 
                  AND um.meta_key = 'subscription_id' 
                  AND um.meta_value != ''
            )";
        }else if ($tab === 'invoice') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1 
                FROM {$this->wp_prefix}usermeta um 
                WHERE um.user_id = superadmin 
                  AND um.meta_key = 'subscription_id' 
                  AND (um.meta_value = '' OR um.meta_value IS NULL)
            )";
        }
        // Apply license status filter (active/inactive) based on usermeta 'license_status'
        if ($status === 'active') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1
                FROM {$this->wp_prefix}usermeta um_status
                WHERE um_status.user_id = superadmin
                  AND um_status.meta_key = 'license_status'
                  AND um_status.meta_value = 'active'
            )";
        } elseif ($status === 'inactive') {
            // Treat anything that is NOT explicitly active as inactive
            $baseWhereConditions[] = "NOT EXISTS (
                SELECT 1
                FROM {$this->wp_prefix}usermeta um_status
                WHERE um_status.user_id = superadmin
                  AND um_status.meta_key = 'license_status'
                  AND um_status.meta_value = 'active'
            )";
        }
        $subQueryParams = [];
        if ($superadminId) {
            $baseWhereConditions[] = "superadmin = :sub_superadmin_id";
            $subQueryParams[':sub_superadmin_id'] = $superadminId;
        }
        $baseWhereClause = 'WHERE ' . implode(' AND ', $baseWhereConditions);
        
        // Apply search filter in outer query only
        $outerWhereConditions = [];
        if (!empty($search)) {
            $outerWhereConditions[] = "(u.user_email LIKE :search OR u.user_login LIKE :search2 OR u.display_name LIKE :search3 OR u.user_nicename LIKE :search4 OR ug.name LIKE :search5)";
        }
        $outerWhereClause = !empty($outerWhereConditions) ? 'WHERE ' . implode(' AND ', $outerWhereConditions) : '';
        
        // Get unique superadmin IDs count first
        // Build count query with same logic as main query
        $countSql = "SELECT COUNT(*) as total 
            FROM (
                SELECT ug1.superadmin
                FROM {$this->wp_prefix}users_groups ug1
                INNER JOIN (
                    SELECT superadmin, MIN(id) as min_id
                    FROM {$this->wp_prefix}users_groups
                    {$baseWhereClause}
                    GROUP BY superadmin
                ) ug2 ON ug1.superadmin = ug2.superadmin AND ug1.id = ug2.min_id
            ) ug
            LEFT JOIN {$this->wp_prefix}users u ON ug.superadmin = u.ID
            {$outerWhereClause}";

        try {
            $countStmt = $this->connection->prepare($countSql);
            $countParams = [];
            if (isset($subQueryParams[':sub_superadmin_id'])) {
                $countParams[':sub_superadmin_id'] = $subQueryParams[':sub_superadmin_id'];
            }
            if (!empty($search)) {
                $countParams[':search'] = "%{$search}%";
                $countParams[':search2'] = "%{$search}%";
                $countParams[':search3'] = "%{$search}%";
                $countParams[':search4'] = "%{$search}%";
                $countParams[':search5'] = "%{$search}%";
            }
            foreach ($countParams as $key => $value) {
                $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $countStmt->execute();
            $totalResult = $countStmt->fetch();
            $total = intval($totalResult['total'] ?? 0);
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Count query error: ' . $e->getMessage());
            }
            $total = 0;
        }

        // Get customers from users_groups table with unique superadmin IDs
        // Using subquery to get one record per unique superadmin (using MIN id to get first record)
        $sortColumn = $sortBy === 'name' ? 'ug.name' : 
                     ($sortBy === 'created_at' ? 'ug.created_at' : 
                     ($sortBy === 'superadmin' ? 'ug.superadmin' : 
                     ($sortBy === 'group_admin' ? 'ug.group_admin' : 
                     ($sortBy === 'type_of_form' ? 'ug.type_of_form' : 'ug.name'))));
        
        $sql = "SELECT 
            ug.id,
            ug.name,
            ug.superadmin,
            ug.group_admin,
            ug.abdis_notes,
            ug.listing_counter,
            ug.show_in_form,
            ug.type_of_form,
            ug.published_at,
            ug.created_by,
            ug.updated_by,
            ug.created_at,
            ug.updated_at,
            u.ID as user_id,
            u.user_login,
            u.display_name as display_name,
            u.user_email as email,
            u.user_registered as user_created_at
        FROM (
            SELECT ug1.*
            FROM {$this->wp_prefix}users_groups ug1
            INNER JOIN (
                SELECT superadmin, MIN(id) as min_id
                FROM {$this->wp_prefix}users_groups
                {$baseWhereClause}
                GROUP BY superadmin
            ) ug2 ON ug1.superadmin = ug2.superadmin AND ug1.id = ug2.min_id
        ) ug
        LEFT JOIN {$this->wp_prefix}users u ON ug.superadmin = u.ID
        {$outerWhereClause}
        ORDER BY {$sortColumn} {$sortDirection}
        LIMIT :limit OFFSET :offset";
        
        // Build final query parameters
        $finalQueryParams = [];
        if (isset($subQueryParams[':sub_superadmin_id'])) {
            $finalQueryParams[':sub_superadmin_id'] = $subQueryParams[':sub_superadmin_id'];
        }
        if (!empty($search)) {
            $finalQueryParams[':search'] = "%{$search}%";
            $finalQueryParams[':search2'] = "%{$search}%";
            $finalQueryParams[':search3'] = "%{$search}%";
            $finalQueryParams[':search4'] = "%{$search}%";
            $finalQueryParams[':search5'] = "%{$search}%";
        }
        $finalQueryParams[':limit'] = $perPage;
        $finalQueryParams[':offset'] = $offset;

        try {
            $stmt = $this->connection->prepare($sql);
            foreach ($finalQueryParams as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $customers = $stmt->fetchAll();

            // Get usergroups for each company (by superadmin)
            foreach ($customers as &$customer) {
                $customer['usergroups'] = $this->getUsergroupsBySuperadmin($customer['superadmin']);
                // Map fields for compatibility

                $meta_keys = ['first_name', 'last_name', 'phone', 'package_company_name', 'country_code', 'package_id', 'license_status'];

                $getPostMetaMultiple = $this->getUserMetaMultiple($customer['superadmin'], $meta_keys);

               // echo "<pre>"; print_r($getPostMetaMultiple); die;


               // $customer['company_name'] = $customer['name'];
                $customer['first_name'] = $getPostMetaMultiple['first_name'] ?? '';
                $customer['last_name'] = $getPostMetaMultiple['last_name'] ?? '';
                $customer['country_code'] = $getPostMetaMultiple['country_code'] ?? '';
                $customer['phone'] = $getPostMetaMultiple['phone'] ?? '';
                $customer['company_name'] = $getPostMetaMultiple['package_company_name'] && $getPostMetaMultiple['package_company_name'] != "" ? $getPostMetaMultiple['package_company_name'] : $customer['display_name'];
                $customer['created_at'] = $customer['created_at'] ?? $customer['user_created_at'] ?? null;

                $user_package = $this->getUserLicenses($customer['superadmin'], $getPostMetaMultiple['package_id']);
                

                if($getPostMetaMultiple['license_status'] == "active"){
                    $customer['stripe_license'] = $user_package['post_title'] ?? 'Custom Plan';
                }else{
                    $customer['stripe_license'] = 'No License';
                }

            }

            $totalPages = max(1, ceil($total / $perPage));

            $group_licenses = $this->getAllGroupLicenses();

            return [
                'customers' => $customers,
                'group_licenses' => $group_licenses,
                'pagination' => [
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ];
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Query error: ' . $e->getMessage());
            }
            return ['customers' => [], 'pagination' => ['total' => 0, 'total_pages' => 1, 'page' => 1]];
        }
    }

    public function getUserLicenses($userId, $packageId) {
        if (!$this->connection) {
            return [];
        }

        if(!$packageId && $packageId != 0 && $packageId != '0' && $packageId != 'null' && $packageId != 'NULL' && $packageId != '') {
            return null;
        }

        try {

            $sql = "SELECT * FROM {$this->wp_prefix}posts WHERE ID = :package_id AND post_status = 'publish'";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':package_id' => $packageId]);
            $package = $stmt->fetch();
            return $package;
            
        } catch (PDOException $e) {
            
            return null;
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

    /**
     * Get usergroups for a company
     */
    private function getUsergroupsForCompany($companyId) {
        if (!$this->connection) {
            return [];
        }

        $sql = "SELECT 
            u.ID as id,
            u.user_login,
            u.user_email as email,
            u.user_registered as created_at,
            um1.meta_value as name,
            um2.meta_value as phone,
            um3.meta_value as licenses
        FROM {$this->wp_prefix}users u
        LEFT JOIN {$this->wp_prefix}usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = 'usergroup_name'
        LEFT JOIN {$this->wp_prefix}usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'phone'
        LEFT JOIN {$this->wp_prefix}usermeta um3 ON u.ID = um3.user_id AND um3.meta_key = 'gibbs_licenses'
        LEFT JOIN {$this->wp_prefix}usermeta um4 ON u.ID = um4.user_id AND um4.meta_key = 'parent_company_id'
        WHERE um4.meta_value = :company_id";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':company_id' => $companyId]);
            $usergroups = $stmt->fetchAll();

            // Parse licenses if stored as JSON
            foreach ($usergroups as &$usergroup) {
                if ($usergroup['licenses']) {
                    $licenses = json_decode($usergroup['licenses'], true);
                    $usergroup['licenses'] = is_array($licenses) ? $licenses : [$usergroup['licenses']];
                } else {
                    $usergroup['licenses'] = [];
                }
            }

            return $usergroups;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Usergroups query error: ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Get usergroups by superadmin ID
     */
    private function getUsergroupsBySuperadmin($superadminId) {
        if (!$this->connection || !$superadminId) {
            return [];
        }

        $sql = "SELECT 
            ug.id,
            ug.name,
            ug.group_admin,
            ug.superadmin,
            ug.abdis_notes,
            ug.listing_counter,
            ug.show_in_form,
            ug.type_of_form,
            ug.published_at,
            ug.created_at,
            ug.updated_at,
            u.user_email as email,
            u.display_name as display_name
        FROM {$this->wp_prefix}users_groups ug
        left join {$this->wp_prefix}users u on ug.group_admin = u.ID
        WHERE ug.superadmin = :superadmin_id
        ORDER BY ug.created_at DESC";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':superadmin_id' => $superadminId]);
            $usergroups = $stmt->fetchAll();

            foreach($usergroups as &$usergroup){
                $group_license = $this->getUserGroupLicenses($usergroup['group_admin']);
                $usergroup['group_license'] = $group_license;

                //echo "<pre>"; print_r($group_license); 
            }

            return $usergroups;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Usergroups by superadmin query error: ' . $e->getMessage());
            }
            return [];
        }
    }

    public function getUserGroupLicenses($groupId) {
        if (!$this->connection || !$groupId) {
            return [];
        }
        try{
            $sql = "SELECT * FROM {$this->wp_prefix}users_and_users_groups_licence as a left join {$this->wp_prefix}users_groups_licence as b on a.licence_id = b.id WHERE a.users_groups_id = :group_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':group_id' => $groupId]);
            $licenses = $stmt->fetchAll();
            return $licenses;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getAllGroupLicenses() {
        if (!$this->connection) {
            return [];
        }
        try{
            $sql = "SELECT * FROM {$this->wp_prefix}users_groups_licence";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            $licenses = $stmt->fetchAll();

            return $licenses;
        } catch (PDOException $e) {
            return [];
        }
    }
}


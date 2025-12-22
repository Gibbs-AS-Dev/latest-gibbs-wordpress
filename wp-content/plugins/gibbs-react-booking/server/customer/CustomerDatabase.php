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

    public function getPackageData($post_id) {
        if (!$this->connection) {
            return [];
        }

        try {
            $sql = "SELECT ID, post_title, post_content, post_date, post_modified FROM {$this->wp_prefix}posts WHERE ID = :post_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] get_package_data query error: ' . $e->getMessage());
            }
            return null;
        }
        return null;
    }

    public function getPackages() {
        if (!$this->connection) {
            return [];
        }

        try {
            // Get all packages
            $sql = "SELECT ID, post_title, post_content, post_date, post_modified FROM {$this->wp_prefix}posts 
                    WHERE post_type = 'stripe-packages' 
                    AND post_status = 'publish' 
                    ORDER BY post_date DESC";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            $packages = $stmt->fetchAll();
            
            if (empty($packages)) {
                return [];
            }
            
            // Get all package IDs
            $packageIds = array_column($packages, 'ID');
            $placeholders = str_repeat('?,', count($packageIds) - 1) . '?';
            
            // Define meta keys to fetch
            $metaKeys = [
                'stripe_product_id',
                'start_price_id',
                'listing_2_to_5_price_id',
                'listing_6_to_20_price_id',
                'listing_20_plus_price_id',
                'lock_price',
                'shally_price',
                '_package_type',
                '_package_price'
            ];
            
            // Fetch all post meta for these packages
            $metaPlaceholders = str_repeat('?,', count($metaKeys) - 1) . '?';
            $metaSql = "SELECT post_id, meta_key, meta_value 
                        FROM {$this->wp_prefix}postmeta 
                        WHERE post_id IN ($placeholders) 
                        AND meta_key IN ($metaPlaceholders)";
            
            $metaStmt = $this->connection->prepare($metaSql);
            $metaParams = array_merge($packageIds, $metaKeys);
            $metaStmt->execute($metaParams);
            $metaData = $metaStmt->fetchAll();
            
            // Organize meta data by post_id
            $metaByPostId = [];
            foreach ($metaData as $meta) {
                $postId = $meta['post_id'];
                if (!isset($metaByPostId[$postId])) {
                    $metaByPostId[$postId] = [];
                }
                $metaByPostId[$postId][$meta['meta_key']] = $meta['meta_value'];
            }
            
            // Attach meta data to packages
            foreach ($packages as &$package) {
                $package['meta'] = $metaByPostId[$package['ID']] ?? [];
            }
            
            return $packages;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] getPackages query error: ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Get users from users and usermeta table
     * Supports search (by name/email), pagination, and basic sorting.
     */
    public function getUsers($params = []) {
        if (!$this->connection) {
            return ['users' => []];
        }

        $page    = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $perPage = isset($params['per_page']) ? max(1, intval($params['per_page'])) : 20;
        $search  = isset($params['search']) ? trim($params['search']) : '';
        $sortBy  = isset($params['sort_by']) ? $params['sort_by'] : 'display_name';
        $sortDirection = (isset($params['sort_direction']) && strtoupper($params['sort_direction']) === 'DESC') ? 'DESC' : 'ASC';
        $role    = "owner";

        $offset = ($page - 1) * $perPage;

        // Allow only certain columns for sorting
        $allowedSortColumns = ['display_name', 'user_email', 'user_login', 'user_registered', 'ID'];
        $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'display_name';

        $whereParts = [];
        $queryParams = [];

        // Search by display_name, first_name, last_name, user_email, or user_login
        if (!empty($search)) {
            $whereParts[] = "(
                u.display_name LIKE :search 
                OR u.user_email LIKE :search2
                OR u.user_login LIKE :search3
                OR u.ID IN (
                    SELECT DISTINCT user_id FROM {$this->wp_prefix}usermeta 
                    WHERE meta_key IN ('first_name', 'last_name') 
                    AND meta_value LIKE :search4
                )
            )";
            $queryParams[':search'] = '%' . $search . '%';
            $queryParams[':search2'] = '%' . $search . '%';
            $queryParams[':search3'] = '%' . $search . '%';
            $queryParams[':search4'] = '%' . $search . '%';
        }

        // Filter by role if provided
        // if (!empty($role)) {
        //     $whereParts[] = "um_caps.meta_value LIKE :role_filter";
        //     $queryParams[':role_filter'] = '%"' . $role . '"%';
        // }

        // Exclude users who exist as superadmin or group_admin in users_groups table
        // Add to whereParts to be included in WHERE clause
        $whereParts[] = "ug.user_id IS NULL AND ug2.user_id IS NULL";

        // Build where clause
        $where = '';
        if (!empty($whereParts)) {
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }

        // Build ORDER BY clause
        $orderBy = "ORDER BY u.{$sortBy} {$sortDirection}";

        // Optimized query: Use single LEFT JOIN for usermeta and derived table for exclusion
        // Using separate derived tables for superadmin and group_admin for better index usage
        $sql = "SELECT 
                    u.ID as id,
                    u.display_name,
                    u.user_email,
                    u.user_login,
                    u.user_registered,
                    MAX(CASE WHEN um.meta_key = 'first_name' THEN um.meta_value END) as first_name,
                    MAX(CASE WHEN um.meta_key = 'last_name' THEN um.meta_value END) as last_name,
                    MAX(CASE WHEN um.meta_key = 'phone' THEN um.meta_value END) as phone,
                    MAX(CASE WHEN um.meta_key = 'country_code' THEN um.meta_value END) as country_code
                FROM {$this->wp_prefix}users u
                LEFT JOIN {$this->wp_prefix}usermeta um ON u.ID = um.user_id 
                    AND um.meta_key IN ('first_name', 'last_name', 'phone', 'country_code')
                LEFT JOIN (
                    SELECT DISTINCT superadmin as user_id FROM {$this->wp_prefix}users_groups WHERE superadmin IS NOT NULL
                ) ug ON ug.user_id = u.ID
                LEFT JOIN (
                    SELECT DISTINCT group_admin as user_id FROM {$this->wp_prefix}users_groups WHERE group_admin IS NOT NULL
                ) ug2 ON ug2.user_id = u.ID
                $where
                GROUP BY u.ID, u.display_name, u.user_email, u.user_login, u.user_registered
                $orderBy
                LIMIT :limit OFFSET :offset";

        

        try {
            $stmt = $this->connection->prepare($sql);

            // Bind search
            foreach ($queryParams as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $users = $stmt->fetchAll();

            return [
                'users' => $users
            ];
        } catch (\PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] getUsers query error: ' . $e->getMessage());
            }
            return ['users' => []];
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
        $country = isset($params['country']) ? trim($params['country']) : 'all';
        $industry = isset($params['industry']) ? trim($params['industry']) : 'all';
        $superadminId = isset($params['superadmin_id']) ? intval($params['superadmin_id']) : null;

        $salesRepRole = false;
        $selectedCountries = [];
        if(isset($params['sales_rep']) && $params['sales_rep'] != ''){
            $salesRepRole = true;
            $selectedCountries = $params['selected_countries'];
        }

        // echo '<pre>';
        // print_r($selectedCountries);
        // echo '</pre>';
        // die();

        $offset = ($page - 1) * $perPage;

        // Validate sort column
        $allowedSortColumns = ['name', 'created_at', 'superadmin', 'group_admin', 'type_of_form', 'email', 'group_updated_at', 'company_country', 'company_industry', 'next_invoice', 'canceled_at'];
        $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'id';
        

        // Build where conditions for subquery
        $baseWhereConditions = ["superadmin IS NOT NULL AND superadmin != 0"];

        
        // If "invoice" tab is active, only include superadmins with active license_status in usermeta
        if ($tab === 'stripe') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1 
                FROM {$this->wp_prefix}usermeta um_sid
                WHERE um_sid.user_id = superadmin 
                  AND um_sid.meta_key = 'subscription_id' 
                  AND um_sid.meta_value != ''
            ) 
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->wp_prefix}usermeta um_sid
                WHERE um_sid.user_id = superadmin 
                  AND um_sid.meta_key = 'subscription_type' 
                  AND um_sid.meta_value = 'invoice'
            )";
        }else if ($tab === 'invoice') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1 
                FROM {$this->wp_prefix}usermeta um 
                WHERE um.user_id = superadmin 
                  AND um.meta_key = 'subscription_type' 
                  AND um.meta_value = 'invoice'
            )";
        }elseif ($tab === 'active') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1
                FROM {$this->wp_prefix}usermeta um_status
                WHERE um_status.user_id = superadmin
                  AND um_status.meta_key = 'license_status'
                  AND um_status.meta_value = 'active'
            )";
        } elseif ($tab === 'inactive') {
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
        if($salesRepRole){

            if(empty($selectedCountries)){
                $selectedCountries = ["empty_country"];
            }
            // Create individual placeholders for each country to properly use IN clause
            $countryPlaceholders = [];
            foreach ($selectedCountries as $index => $countryCode) {
                $placeholder = ':selected_country_' . $index;
                $countryPlaceholders[] = $placeholder;
                $subQueryParams[$placeholder] = $countryCode;
            }
            
            if (!empty($countryPlaceholders)) {
                $placeholdersStr = implode(',', $countryPlaceholders);
                $baseWhereConditions[] = "EXISTS (
                    SELECT 1
                    FROM {$this->wp_prefix}usermeta um_sales_rep
                    WHERE um_sales_rep.user_id = superadmin
                      AND um_sales_rep.meta_key = 'company_country'
                      AND um_sales_rep.meta_value IN ({$placeholdersStr})
                )";
            }
        }
        if (!empty($country) && strtolower($country) !== 'all') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1
                FROM {$this->wp_prefix}usermeta um_country_filter
                WHERE um_country_filter.user_id = superadmin
                  AND um_country_filter.meta_key = 'company_country'
                  AND um_country_filter.meta_value = :country_filter
            )";
            $subQueryParams[':country_filter'] = $country;
        }
        if (!empty($industry) && strtolower($industry) !== 'all') {
            $baseWhereConditions[] = "EXISTS (
                SELECT 1
                FROM {$this->wp_prefix}usermeta um_industry_filter
                WHERE um_industry_filter.user_id = superadmin
                  AND um_industry_filter.meta_key = 'company_industry'
                  AND um_industry_filter.meta_value = :industry_filter
            )";
            $subQueryParams[':industry_filter'] = $industry;
        }
        // Build base where clause
        $baseWhereClause = 'WHERE ' . implode(' AND ', $baseWhereConditions);
        
        // Apply search filter in outer query - check user fields OR any group name
        $outerWhereConditions = [];
        if (!empty($search)) {
            // Check user fields OR displayed group name OR any group name OR group admin email OR company name for this superadmin
            $outerWhereConditions[] = "(
                u.user_email LIKE :search 
                OR u.user_login LIKE :search2 
                OR u.display_name LIKE :search3 
                OR u.user_nicename LIKE :search4 
                OR ug.name LIKE :search5
                OR EXISTS (
                    SELECT 1
                    FROM {$this->wp_prefix}users_groups ug_search
                    WHERE ug_search.superadmin = ug.superadmin
                      AND ug_search.name LIKE :group_search
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$this->wp_prefix}users_groups ug_email_search
                    INNER JOIN {$this->wp_prefix}users u_email ON ug_email_search.group_admin = u_email.ID
                    WHERE ug_email_search.superadmin = ug.superadmin
                      AND u_email.user_email LIKE :group_admin_email_search
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$this->wp_prefix}usermeta um_company
                    WHERE um_company.user_id = ug.superadmin
                      AND um_company.meta_key = 'company_company_name'
                      AND um_company.meta_value LIKE :company_name_search
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$this->wp_prefix}usermeta um_company_email
                    WHERE um_company_email.user_id = ug.superadmin
                      AND um_company_email.meta_key = 'company_email'
                      AND um_company_email.meta_value LIKE :company_email_search
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$this->wp_prefix}usermeta um_company_organization_number
                    WHERE um_company_organization_number.user_id = ug.superadmin
                      AND um_company_organization_number.meta_key = 'company_organization_number'
                      AND um_company_organization_number.meta_value LIKE :company_organization_number_search
                )
            )";
        }
        $outerWhereClause = !empty($outerWhereConditions) ? 'WHERE ' . implode(' AND ', $outerWhereConditions) : '';
        
        // Get unique superadmin IDs count first
        // Build count query with same logic as main query
        $countSql = "SELECT COUNT(*) as total 
            FROM (
                SELECT ug1.superadmin, ug1.name
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
            if (isset($subQueryParams[':country_filter'])) {
                $countParams[':country_filter'] = $subQueryParams[':country_filter'];
            }
            // Copy all selected_country_X parameters
            foreach ($subQueryParams as $key => $value) {
                if (strpos($key, ':selected_country_') === 0) {
                    $countParams[$key] = $value;
                }
            }
            if (isset($subQueryParams[':industry_filter'])) {
                $countParams[':industry_filter'] = $subQueryParams[':industry_filter'];
            }
            if (!empty($search)) {
                $countParams[':group_search'] = "%{$search}%";
                $countParams[':group_admin_email_search'] = "%{$search}%";
                $countParams[':company_name_search'] = "%{$search}%";
                $countParams[':company_email_search'] = "%{$search}%";
                $countParams[':company_organization_number_search'] = "%{$search}%";
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
                     ($sortBy === 'superadmin' ? 'u.display_name' : 
                     ($sortBy === 'id' ? 'ug.superadmin' : 
                     ($sortBy === 'group_admin' ? 'ug.group_admin' : 
                     ($sortBy === 'email' ? 'u.user_email' : 
                     ($sortBy === 'group_updated_at' ? 'ug.updated_at' : 
                     ($sortBy === 'type_of_form' ? 'ug.type_of_form' : 
                     ($sortBy === 'company_country' ? 'um_country.meta_value' : 
                     ($sortBy === 'canceled_at' ? 'um_canceled_at.meta_value' : 
                     ($sortBy === 'next_invoice' ? "COALESCE(NULLIF(um_next_invoice.meta_value, ''), '9999-12-31')" : 
                     ($sortBy === 'company_industry' ? 'um_industry.meta_value' : 'ug.name')))))))))));

        // Build meta joins when needed (supports multiple meta-based sorts)
        $metaJoinParts = [];
        if ($sortBy === 'company_country') {
            $metaJoinParts[] = "LEFT JOIN {$this->wp_prefix}usermeta um_country ON ug.superadmin = um_country.user_id AND um_country.meta_key = 'company_country'";
        }
        if ($sortBy === 'company_industry') {
            $metaJoinParts[] = "LEFT JOIN {$this->wp_prefix}usermeta um_industry ON ug.superadmin = um_industry.user_id AND um_industry.meta_key = 'company_industry'";
        }
        if ($sortBy === 'next_invoice') {
            $metaJoinParts[] = "LEFT JOIN {$this->wp_prefix}usermeta um_next_invoice ON ug.superadmin = um_next_invoice.user_id AND um_next_invoice.meta_key = 'next_invoice'";
        }
        if ($sortBy === 'canceled_at') {
            $metaJoinParts[] = "LEFT JOIN {$this->wp_prefix}usermeta um_canceled_at ON ug.superadmin = um_canceled_at.user_id AND um_canceled_at.meta_key = 'canceled_at'";
        }   
        $metaJoinClause = implode(' ', $metaJoinParts);
        
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
        {$metaJoinClause}
        {$outerWhereClause}
        ORDER BY {$sortColumn} {$sortDirection}
        LIMIT :limit OFFSET :offset";
        
        // Build final query parameters
        $finalQueryParams = [];
        if (isset($subQueryParams[':sub_superadmin_id'])) {
            $finalQueryParams[':sub_superadmin_id'] = $subQueryParams[':sub_superadmin_id'];
        }
        if (isset($subQueryParams[':country_filter'])) {
            $finalQueryParams[':country_filter'] = $subQueryParams[':country_filter'];
        }
        // Copy all selected_country_X parameters
        foreach ($subQueryParams as $key => $value) {
            if (strpos($key, ':selected_country_') === 0) {
                $finalQueryParams[$key] = $value;
            }
        }
        if (isset($subQueryParams[':industry_filter'])) {
            $finalQueryParams[':industry_filter'] = $subQueryParams[':industry_filter'];
        }
        if (!empty($search)) {
            $finalQueryParams[':group_search'] = "%{$search}%";
            $finalQueryParams[':group_admin_email_search'] = "%{$search}%";
            $finalQueryParams[':company_name_search'] = "%{$search}%";
            $finalQueryParams[':company_email_search'] = "%{$search}%";
            $finalQueryParams[':company_organization_number_search'] = "%{$search}%";
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

                $meta_keys = ['first_name', 'last_name', 'phone', 'company_company_name', 'country_code', 'package_id', 'license_status', 'subscription_type', 'subscription_id', "next_invoice", "company_country", "company_industry", 'mrr', 'arr', 'subscription_interval', 'subscription_amount', 'subscription_currency', 'subscription_status', 'canceled_at'];

                $getPostMetaMultiple = $this->getUserMetaMultiple($customer['superadmin'], $meta_keys);

               // echo "<pre>"; print_r($getPostMetaMultiple); die;


               // $customer['company_name'] = $customer['name'];
                $customer['first_name'] = $getPostMetaMultiple['first_name'] ?? '';
                $customer['last_name'] = $getPostMetaMultiple['last_name'] ?? '';
                $customer['country_code'] = $getPostMetaMultiple['country_code'] ?? '';
                $customer['phone'] = $getPostMetaMultiple['phone'] ?? '';
                $customer['company_name'] = $getPostMetaMultiple['company_company_name'] && $getPostMetaMultiple['company_company_name'] != "" ? $getPostMetaMultiple['company_company_name'] : $customer['display_name'];
                $customer['created_at'] = $customer['created_at'] ?? $customer['user_created_at'] ?? null;
                $customer['next_invoice'] = $getPostMetaMultiple['next_invoice'] ?? "";
                $customer['company_country'] = $getPostMetaMultiple['company_country'] ?? "";
                $customer['company_industry'] = $getPostMetaMultiple['company_industry'] ?? "";
                $customer['mrr'] = $getPostMetaMultiple['mrr'] ?? "";
                $customer['arr'] = $getPostMetaMultiple['arr'] ?? "";
                $customer['canceled_at'] = $getPostMetaMultiple['canceled_at'] ?? "";

                if($getPostMetaMultiple['subscription_type'] == "invoice"){
                    $customer['payment'] = 'Invoice';
                }else if($getPostMetaMultiple['subscription_id'] != ""){
                    $customer['payment'] = 'Card';

                    if(isset($getPostMetaMultiple['subscription_interval']) && isset($getPostMetaMultiple['subscription_amount'])){
                        if($getPostMetaMultiple['subscription_interval'] == "month"){
                            $customer['mrr'] = $getPostMetaMultiple['subscription_amount'];
                            $customer['arr'] = $getPostMetaMultiple['subscription_amount'] * 12;
                        }else{
                            $customer['mrr'] = $getPostMetaMultiple['subscription_amount'] ? $getPostMetaMultiple['subscription_amount'] / 12 : "";
                            $customer['arr'] = $getPostMetaMultiple['subscription_amount'];
                        }
                    }
                }else{
                    $customer['payment'] = '';
                }
                



                $user_package = $this->getUserLicenses($customer['superadmin'], $getPostMetaMultiple['package_id']);
                

                if($getPostMetaMultiple['license_status'] == "active"){

                    if($getPostMetaMultiple['subscription_status'] == "trialing"){
                        $customer['stripe_license'] = 'Trial';
                    }else{
                        $customer['stripe_license'] = $user_package['post_title'] ?? 'Custom Plan';
                    }
                   
                }else{
                    if($getPostMetaMultiple['subscription_status'] != ""){
                        $customer['stripe_license'] = $getPostMetaMultiple['subscription_status'];
                    }else{
                        $customer['stripe_license'] = 'No License';
                    }
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

    /**
     * Get customer by superadmin ID
     * Returns the first customer (group) associated with the given superadmin
     */
    public function getCustomerBySuperadmin($superadminId, $meta_keys = []) {
        if (!$this->connection || !$superadminId) {
            return null;
        }

        try {
            // Get the first group for this superadmin (using MIN id to get first record)
            $sql = "SELECT * FROM  {$this->wp_prefix}users WHERE ID = :superadmin_id";

            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':superadmin_id' => $superadminId]);
            $user = $stmt->fetch();

            if(!$user){
                return null;
            }


            // Get user meta data
            // $meta_keys = ['first_name', 'last_name', 'country_code', 'phone', 'package_company_name', 'package_organization_number','package_street_address', 'package_zip_code', 'package_city',  'package_contact_name', 'package_contact_email', 'package_contact_phone', 'next_invoice', 'package_id', 'subscription_id', 'subscription_type', 'license_status', 'stripe_trail', '_gibbs_active_group_id', 'stripe_customer_id', "stripe_test_customer_id"];
            $getPostMetaMultiple = $this->getUserMetaMultiple($user['ID'], $meta_keys);
            
            $user["meta_data"] = $getPostMetaMultiple;

            return $user;
        } catch (PDOException $e) {
            // if (function_exists('error_log')) {
            //     error_log('[CustomerDatabase] getCustomerBySuperadmin query error: ' . $e->getMessage());
            // }
            return null;
        }
    }

    public function checkSuperadminExistsInUsersGroups($superadminId) {
        if (!$this->connection || !$superadminId) {
            return false;
        }

        try {
            $sql = "SELECT COUNT(*) FROM {$this->wp_prefix}users_groups WHERE superadmin = :superadmin_id OR group_admin = :superadmin_id2";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':superadmin_id', $superadminId, PDO::PARAM_INT);
            $stmt->bindValue(':superadmin_id2', $superadminId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['COUNT(*)'] > 0;
        }catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] checkSuperadminExistsInUsersGroups query error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Update or insert user meta
     */
    private function updateUserMeta($userId, $metaKey, $metaValue) {
        if (!$this->connection || !$userId || !$metaKey) {
            return false;
        }

        try {
            // Check if meta key exists
            $checkSql = "SELECT umeta_id FROM {$this->wp_prefix}usermeta WHERE user_id = :user_id AND meta_key = :meta_key";
            $checkStmt = $this->connection->prepare($checkSql);
            $checkStmt->execute([':user_id' => $userId, ':meta_key' => $metaKey]);
            $metaExists = $checkStmt->fetch();

            if ($metaExists) {
                // Update existing meta
                $sql = "UPDATE {$this->wp_prefix}usermeta SET meta_value = :meta_value WHERE user_id = :user_id AND meta_key = :meta_key";
            } else {
                // Insert new meta
                $sql = "INSERT INTO {$this->wp_prefix}usermeta (user_id, meta_key, meta_value) VALUES (:user_id, :meta_key, :meta_value)";
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':meta_key' => $metaKey,
                ':meta_value' => $metaValue ?? ''
            ]);
            return true;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] updateUserMeta error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Update superadmin data in users table and usermeta table
     */
    public function updateSuperadminData($superadminId, $data) {
        if (!$this->connection || !$superadminId) {
            return false;
        }

        //echo "<pre>"; print_r($data); die;

        try {
            // Start transaction
            // $this->connection->beginTransaction();

            // Update users table - only user_email can be updated directly
            if (isset($data['first_name']) && !empty($data['first_name'])) {
                $display_name = $data['first_name'] . ($data['last_name'] ? ' ' . $data['last_name'] : '');
                $sql = "UPDATE {$this->wp_prefix}users SET display_name = :display_name WHERE ID = :superadmin_id";
                $stmt = $this->connection->prepare($sql);
                $stmt->execute([
                    ':superadmin_id' => $superadminId,
                    ':display_name' => $display_name
                ]);

                // Also update updated_at for all the user groups (users_groups) where this superadmin is the owner,
                // set updated_at according to the current database server time
                $sql2 = "UPDATE {$this->wp_prefix}users_groups SET updated_at = NOW() WHERE superadmin = :superadmin_id";
                $stmt2 = $this->connection->prepare($sql2);
                $stmt2->execute([
                    ':superadmin_id' => $superadminId
                ]);
            }

            // Update/Insert usermeta fields
            $metaFields = [
                'first_name',
                'last_name',
                'phone',
                'country_code',
                'company_email',
                'company_country',
                'company_industry',
                'company_country_code',
                'company_company_name',
                'company_organization_number',
                'company_street_address',
                'company_zip_code',
                'company_city',
                'company_phone'
            ];
            

            foreach ($metaFields as $metaKey) {
                if (isset($data[$metaKey])) {
                    $this->updateUserMeta($superadminId, $metaKey, $data[$metaKey]);
                }
            }

            return true;

        } catch (PDOException $e) {
            // Rollback on error
            // if ($this->connection->inTransaction()) {
            //     $this->connection->rollBack();
            // }
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] updateSuperadminData error: ' . $e->getMessage());
            }
            return false;
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
    public function getCustomerByEmail($email) {
        if (!$this->connection || !$email) {
            return null;
        }
        try{
            $sql = "SELECT * FROM {$this->wp_prefix}users WHERE user_email = :email";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            return $user;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getUserMeta($userId, $metaKey) {
        if (!$userId || !$metaKey) {
            return null;
        }
        
        $sql = "SELECT meta_value FROM {$this->wp_prefix}usermeta WHERE user_id = ? AND meta_key = ?";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$userId, $metaKey]);
            $result = $stmt->fetch();
            
            return $result ? $result['meta_value'] : null;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] getUserMeta error: ' . $e->getMessage());
            }
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
                $group_license = $this->getUserGroupLicenses($usergroup['id']);
                $usergroup['group_license'] = $group_license;
                
                // Fetch admin_emails (CC emails) from group_admin user meta
                if (!empty($usergroup['group_admin'])) {
                    $admin_emails = $this->getUserMeta($usergroup['group_admin'], 'admin_emails');
                    $usergroup['email_cc'] = $admin_emails ? $admin_emails : '';
                } else {
                    $usergroup['email_cc'] = '';
                }
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

    /**
     * Update group licenses for a usergroup
     * Deletes existing licenses and inserts new ones
     */
    public function updateGroupLicenses($groupId, $superadmin_id, $licenseIds) {
        if (!$this->connection || !$groupId || !$superadmin_id) {
            return false;
        }

        $license_status = $this->getUserMeta($superadmin_id, 'license_status');

        if($license_status == "active"){
            $license_is_active = 1;
        }else{
            $license_is_active = 0;
        }
        try {
            // Delete existing licenses for this group
            $deleteSql = "DELETE FROM {$this->wp_prefix}users_and_users_groups_licence WHERE users_groups_id = :group_id";
            $deleteStmt = $this->connection->prepare($deleteSql);
            $deleteStmt->execute([':group_id' => $groupId]);

            // Insert new licenses
            if (!empty($licenseIds) && is_array($licenseIds)) {
                $insertSql = "INSERT INTO {$this->wp_prefix}users_and_users_groups_licence (users_groups_id, licence_id, licence_is_active) VALUES (:group_id, :licence_id, :licence_is_active)";
                $insertStmt = $this->connection->prepare($insertSql);
                
                foreach ($licenseIds as $licenseId) {
                    $licenseId = intval($licenseId);
                    if ($licenseId > 0) {
                        $insertStmt->execute([
                            ':group_id' => $groupId,
                            ':licence_id' => $licenseId,
                            ':licence_is_active' => $license_is_active
                        ]);
                    }
                }
            }

            return true;
        } catch (PDOException $e) {
            return false;
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

    public function createGroup($group_name, $user_id) {
        if (!$this->connection || !$group_name || !$user_id) {
            return false;
        }
        try{
            $sql = "INSERT INTO {$this->wp_prefix}users_groups (name, created_by, superadmin, published_at, updated_by) VALUES (:group_name, :created_by, :superadmin, :published_at, :updated_by)";
            $stmt = $this->connection->prepare($sql);
            $published_at = date('Y-m-d H:i:s');
            $stmt->bindParam(':group_name', $group_name, PDO::PARAM_STR);
            $stmt->bindParam(':superadmin', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':created_by', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':published_at', $published_at, PDO::PARAM_STR);
            $stmt->bindParam(':updated_by', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            //echo $e->getMessage(); die;
            return false;
        }
    }

    public function assignUserToGroup($group_id, $user_id, $group_role) {
        if (!$this->connection || !$group_id || !$user_id) {
            return false;
        }
        try{
            $sql = "INSERT INTO {$this->wp_prefix}users_and_users_groups (users_groups_id, users_id, role) VALUES (:group_id, :user_id, :role)";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':group_id' => $group_id, ':user_id' => $user_id, ':role' => $group_role]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
           // echo $e->getMessage(); die;
            return false;
        }
    }
    public function insertGroupLicence($group_id) {
        if (!$this->connection || !$group_id) {
            return false;
        }
        try{
            $sql = "INSERT INTO {$this->wp_prefix}users_and_users_groups_licence (users_groups_id, licence_id, licence_is_active) VALUES (:group_id, :licence_id, :licence_is_active)";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':group_id' => $group_id, ':licence_id' => 10, ':licence_is_active' => 0]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
           // echo $e->getMessage(); die;
            return false;
        }
    }
    public function updateGroupAdmin($group_id, $user_id) {
        if (!$this->connection || !$group_id || !$user_id) {
            return false;
        }
        try{
            $sql = "UPDATE {$this->wp_prefix}users_groups SET group_admin = :user_id WHERE id = :group_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':user_id' => $user_id, ':group_id' => $group_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
           // echo $e->getMessage(); die;
            return false;
        }
    }

    /**
     * Save user preferences for customer table
     */
    public function saveCustomerPreferences($user_id, $preferences) {
      

        $preferencesData = [
            'selectedHideColumns' => isset($preferences['selectedHideColumns']) ? $preferences['selectedHideColumns'] : [],
            'rowsPerPage' => isset($preferences['rowsPerPage']) ? intval($preferences['rowsPerPage']) : 10
        ];

        if (!$this->connection || !$user_id) {
            return [];
        }
        try{
            // Check if meta key exists for this user
            $checkSql = "SELECT umeta_id FROM {$this->wp_prefix}usermeta WHERE user_id = :user_id AND meta_key = '_gibbs_customer_table_preferences'";
            $checkStmt = $this->connection->prepare($checkSql);
            $checkStmt->execute([':user_id' => $user_id]);
            $metaExists = $checkStmt->fetch();

            if ($metaExists) {
                // Update
                $sql = "UPDATE {$this->wp_prefix}usermeta SET meta_value = :meta_value WHERE user_id = :user_id AND meta_key = '_gibbs_customer_table_preferences'";
                $stmt = $this->connection->prepare($sql);
                $stmt->execute([':meta_value' => json_encode($preferencesData), ':user_id' => $user_id]);
                return $stmt->rowCount() > 0;
            } else {
                // Insert
                $sql = "INSERT INTO {$this->wp_prefix}usermeta (user_id, meta_key, meta_value) VALUES (:user_id, '_gibbs_customer_table_preferences', :meta_value)";
                $stmt = $this->connection->prepare($sql);
                $stmt->execute([':user_id' => $user_id, ':meta_value' => json_encode($preferencesData)]);
                return $stmt->rowCount() > 0;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get user preferences for customer table
     */
    public function getCustomerPreferences($user_id) {
        if (!$this->connection || !$user_id) {
            return [];
        }
        try{
            $sql = "SELECT meta_value FROM {$this->wp_prefix}usermeta WHERE user_id = :user_id AND meta_key = '_gibbs_customer_table_preferences'";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $preferences = $stmt->fetch();

            if($preferences){
                $preferences = json_decode($preferences['meta_value'], true);
            }

            if(empty($preferences) || !is_array($preferences)){
                return [];
            }

            return $preferences;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getUserGroupBySuperadmin($superadminId) {
        if (!$this->connection || !$superadminId) {
            return [];
        }
        try{
            $sql = "SELECT id FROM {$this->wp_prefix}users_groups WHERE superadmin = :superadmin_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':superadmin_id' => $superadminId]);
            $groupIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $groupIds;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function changeSuperAdmin($oldSuperadminId, $newSuperadminId) {
        if (!$this->connection || !$oldSuperadminId || !$newSuperadminId) {
            return false;
        }
        $metaFields = [
            'next_invoice',
            'package_id',
            'subscription_id',
            'subscription_type',
            'license_status',
            'stripe_trail',
            '_gibbs_active_group_id',
            'stripe_customer_id',
            'stripe_test_customer_id',
            'company_email',
            'company_country',
            'company_industry',
            'company_country_code',
            'company_company_name',
            'company_organization_number',
            'company_street_address',
            'company_zip_code',
            'company_city',
            'company_phone'
        ];

        $oldSuperadmin = $this->getCustomerBySuperadmin($oldSuperadminId, $metaFields);

        $meta_data = [];

        if(isset($oldSuperadmin['ID']) && $oldSuperadmin['ID'] != 0 && isset($oldSuperadmin['meta_data']) && !empty($oldSuperadmin['meta_data'])){

            $meta_data = $oldSuperadmin['meta_data'];

        }else{
            return false;
        }

        $superAdminGroup = $this->getUserGroupBySuperadmin($oldSuperadminId);

        $group_ids = "";

        if($superAdminGroup && !empty($superAdminGroup)){
            $group_ids = implode(',', $superAdminGroup);
        }else{
            return false;
        }

        if($group_ids == ""){
            return false;
        }

       

        try{

            $sql = "UPDATE {$this->wp_prefix}users_groups SET superadmin = :newSuperadminId, updated_at = NOW() WHERE id IN ($group_ids)";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':newSuperadminId', $newSuperadminId, is_int($newSuperadminId) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->execute();
            $count = $stmt->rowCount() > 0;


            



            if($count){

                $sql = "UPDATE {$this->wp_prefix}users_and_users_groups SET users_id = :newSuperadminId WHERE users_groups_id IN ($group_ids) AND users_id = :oldSuperadminId";
                $stmt = $this->connection->prepare($sql);
                $stmt->bindValue(':newSuperadminId', $newSuperadminId, is_int($newSuperadminId) ? PDO::PARAM_INT : PDO::PARAM_STR);
                $stmt->bindValue(':oldSuperadminId', $oldSuperadminId, is_int($oldSuperadminId) ? PDO::PARAM_INT : PDO::PARAM_STR);
                $stmt->execute();
                $count2 = $stmt->rowCount() > 0;

                
    
                foreach ($metaFields as $metaKey) {
                    if (isset($meta_data[$metaKey])) {
                        $this->updateUserMeta($newSuperadminId, $metaKey, $meta_data[$metaKey]);
                        $this->updateUserMeta($oldSuperadminId, $metaKey, "");
                    }
                }

                $oldSuperadminIdMeta = $this->getUserMeta($oldSuperadminId, 'oldSuperadminId');
                if($oldSuperadminIdMeta){
                    $oldSuperadminIdMeta = json_decode($oldSuperadminIdMeta, true);
                    $oldSuperadminIdMeta[] = $oldSuperadminId;
                    $this->updateUserMeta($newSuperadminId, 'oldSuperadminId', json_encode($oldSuperadminIdMeta));
                }else{
                    $this->updateUserMeta($newSuperadminId, 'oldSuperadminId', json_encode([$oldSuperadminId]));
                }

            }else{
                return false;
            }
            return true;
        } catch (PDOException $e) {
           // echo $e->getMessage(); die;
            return false;
        }
    }

    /**
     * Get usergroup by ID
     */
    public function getUsergroupById($usergroupId) {
        if (!$this->connection || !$usergroupId) {
            return null;
        }

        $sql = "SELECT 
            ug.id,
            ug.name,
            ug.group_admin,
            ug.superadmin,
            u.user_email as email,
            u.display_name as display_name
        FROM {$this->wp_prefix}users_groups ug
        LEFT JOIN {$this->wp_prefix}users u ON ug.group_admin = u.ID
        WHERE ug.id = :usergroup_id";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':usergroup_id' => $usergroupId]);
            $usergroup = $stmt->fetch();

            if ($usergroup) {
                // Fetch admin_emails (CC emails) from group_admin user meta
                if (!empty($usergroup['group_admin'])) {
                    $admin_emails = $this->getUserMeta($usergroup['group_admin'], 'admin_emails');
                    $usergroup['email_cc'] = $admin_emails ? $admin_emails : '';
                } else {
                    $usergroup['email_cc'] = '';
                }
            }

            return $usergroup ? $usergroup : null;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Get usergroup error: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Update usergroup data
     */
    public function updateUsergroup($usergroupId, $data) {
        if (!$this->connection || !$usergroupId) {
            return false;
        }

        try {
            // Get group_admin user ID first (before updating name)
            $sql = "SELECT group_admin FROM {$this->wp_prefix}users_groups WHERE id = :usergroup_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':usergroup_id' => $usergroupId]);
            $usergroup = $stmt->fetch();
            
            if (!$usergroup || empty($usergroup['group_admin'])) {
                return false;
            }

           

            $groupAdminId = $usergroup['group_admin'];

            // Update usergroup name
            if (isset($data['name'])) {
                $sql = "UPDATE {$this->wp_prefix}users_groups SET name = :name WHERE id = :usergroup_id";
                $stmt = $this->connection->prepare($sql);
                $stmt->execute([
                    ':name' => $data['name'],
                    ':usergroup_id' => $usergroupId
                ]);
            }

            // Update email (group_admin user email)
            if (isset($data['email'])) {
                $sql = "UPDATE {$this->wp_prefix}users SET user_email = :email WHERE ID = :user_id";
                $stmt = $this->connection->prepare($sql);
                $stmt->execute([
                    ':email' => $data['email'],
                    ':user_id' => $groupAdminId
                ]);
            }

            // Update admin_emails (CC emails) in user meta
            if (isset($data['email_cc'])) {
                $this->updateUserMeta($groupAdminId, 'admin_emails', $data['email_cc']);
            }

            return true;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Update usergroup error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Update next invoice date for a customer (superadmin)
     */
    public function updateNextInvoice($superadminId, $nextInvoiceDate) {
        if (!$this->connection || !$superadminId) {
            return false;
        }

        try {
            // Update or insert next_invoice in usermeta
            $this->updateUserMeta($superadminId, 'next_invoice', $nextInvoiceDate);
            return true;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Update next invoice error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Update MRR and ARR values for a customer (superadmin)
     */
    public function updateMrrArr($superadminId, $mrr, $arr) {
        if (!$this->connection || !$superadminId) {
            return false;
        }

        try {
            $this->updateUserMeta($superadminId, 'mrr', $mrr);
            $this->updateUserMeta($superadminId, 'arr', $arr);
            return true;
        } catch (PDOException $e) {
            if (function_exists('error_log')) {
                error_log('[CustomerDatabase] Update MRR/ARR error: ' . $e->getMessage());
            }
            return false;
        }
    }
}


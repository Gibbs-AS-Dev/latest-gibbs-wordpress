<?php
/**
 * Wallet Database Class
 * Handles all database operations for wallet functionality
 * Uses direct PDO connection like CoreDatabase
 */

class WalletDatabase {
    private $connection;
    private $wp_prefix;
    private $wallets_table;
    private $transactions_table;
    
    public function __construct() {
        // Load WordPress config to get database credentials
        $this->loadWordPressConfig();
        $this->connect();
        $this->wallets_table = $this->wp_prefix . 'gibbs_wallets';
        $this->transactions_table = $this->wp_prefix . 'gibbs_wallet_transactions';
        $this->sms_logs_table = $this->wp_prefix . 'access_management';
        $this->posts_table = $this->wp_prefix . 'posts';
        $this->createTablesIfNotExist();
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
     * Create the wallets and transactions tables if they don't exist
     */
    private function createTablesIfNotExist() {
        try {
            // Create wallets table
            $wallets_sql = "CREATE TABLE IF NOT EXISTS {$this->wallets_table} (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                balance decimal(10,2) DEFAULT 0.00,
                currency varchar(3) DEFAULT 'NOK',
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id),
                KEY status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $this->connection->exec($wallets_sql);
            
            // Create transactions table
            $transactions_sql = "CREATE TABLE IF NOT EXISTS {$this->transactions_table} (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                payment_id text,
                type varchar(20) NOT NULL,
                amount decimal(10,2) NOT NULL,
                description text,
                reference varchar(255),
                status varchar(20) DEFAULT 'processing',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY type (type),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $this->connection->exec($transactions_sql);
            
            return true;
        } catch (PDOException $e) {
            throw new Exception('Failed to create wallet tables: ' . $e->getMessage());
        }
    }

    /**
     * Get wallet by user ID
     */
    public function getWalletByUserId($userId) {
        try {
            $sql = "SELECT * FROM {$this->wallets_table} WHERE user_id = :user_id AND status = 'active'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get wallet by user ID: ' . $e->getMessage());
        }
    }

    /**
     * Create a new wallet for a user
     */
    public function createWallet($userId, $initialBalance = 0.00, $currency = 'NOK') {
        try {
            $status = 'active';
            $sql = "INSERT INTO {$this->wallets_table} (user_id, balance, currency, status) VALUES (:user_id, :balance, :currency, :status)";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':balance', $initialBalance, PDO::PARAM_STR);
            $stmt->bindParam(':currency', $currency, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->execute();
            
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Failed to create wallet: ' . $e->getMessage());
        }
    }

    /**
     * Update wallet balance
     */
    public function updateBalance($userId, $newBalance) {
        try {
            $sql = "UPDATE {$this->wallets_table} SET balance = :balance WHERE user_id = :user_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':balance', $newBalance, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to update wallet balance: ' . $e->getMessage());
        }
    }

    /**
     * Add funds to wallet
     */
    public function addFunds($userId, $amount) {
        try {
            $wallet = $this->getWalletByUserId($userId);
            if (!$wallet) {
                throw new Exception('Wallet not found for user');
            }

            $newBalance = $wallet['balance'] + $amount;
            return $this->updateBalance($userId, $newBalance);
        } catch (Exception $e) {
            throw new Exception('Failed to add funds: ' . $e->getMessage());
        }
    }

    /**
     * Deduct funds from wallet
     */
    public function deductFunds($userId, $amount) {
        try {
            $wallet = $this->getWalletByUserId($userId);
            if (!$wallet) {
                throw new Exception('Wallet not found for user');
            }
            
            // if ($wallet['balance'] < $amount) {
            //     throw new Exception('Insufficient funds');
            // }

            $newBalance = $wallet['balance'] - $amount;
            return $this->updateBalance($userId, $newBalance);
        } catch (Exception $e) {
            throw new Exception('Failed to deduct funds: ' . $e->getMessage());
        }
    }

    /**
     * Check if user has sufficient funds
     */
    public function hasSufficientFunds($userId, $amount) {
        try {
            $wallet = $this->getWalletByUserId($userId);
            return $wallet && $wallet['balance'] >= $amount;
        } catch (Exception $e) {
            throw new Exception('Failed to check sufficient funds: ' . $e->getMessage());
        }
    }

    public function getSmsLogsWithPagination($page_id, $per_page, $search, $listings) {
        try {
            // Calculate offset
            $offset = ($page_id - 1) * $per_page;

            $listings = implode(',', $listings);

            if(empty($listings)){
                $listings = 2340979777;
            }

            // Base query
            $baseQuery = "FROM {$this->sms_logs_table} WHERE listing_id IN ($listings)";
            
            // Add search condition if search term provided
            if (!empty($search)) {
                $baseQuery .= " AND (phone_number LIKE :search_phone OR sms_content LIKE :search_content OR listing_id LIKE :search_listing OR listing_name LIKE :search_listingname OR name LIKE :search_name OR email LIKE :search_email OR created_at LIKE :search_created_at)";
            }

            // Get total count
            $countSql = "SELECT COUNT(*) " . $baseQuery;
            $countStmt = $this->connection->prepare($countSql);
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $countStmt->bindParam(':search_phone', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':search_content', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':search_listing', $searchTerm, PDO::PARAM_INT);
                $countStmt->bindParam(':search_listingname', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':search_name', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':search_email', $searchTerm, PDO::PARAM_STR);
                $countStmt->bindParam(':search_created_at', $searchTerm, PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total = $countStmt->fetchColumn();

            // Get paginated results
            $sql = "SELECT * " . $baseQuery . " ORDER BY created_at DESC LIMIT :per_page OFFSET :offset";
            $stmt = $this->connection->prepare($sql);
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $stmt->bindParam(':search_phone', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':search_content', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':search_listing', $searchTerm, PDO::PARAM_INT);
                $stmt->bindParam(':search_listingname', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':search_name', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':search_email', $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(':search_created_at', $searchTerm, PDO::PARAM_STR);
            }
            $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'sms_logs' => $stmt->fetchAll(),
                'total' => $total,
                'total_pages' => ceil($total / $per_page),
                'current_page' => $page_id
            ];
        } catch (PDOException $e) {
            throw new Exception('Failed to get SMS logs: ' . $e->getMessage());
        }
    }
    public function getAllListingIds($owner_id) {
        try {
            $sql = "SELECT ID FROM {$this->posts_table} WHERE post_author = :user_id AND post_type = 'listing'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $owner_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw new Exception('Failed to get all listings: ' . $e->getMessage());
        }
    }

    /**
     * Get wallet transaction history
     */
    public function getTransactionHistory($userId, $limit = 50) {
        try {
            $sql = "SELECT * FROM {$this->transactions_table} WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get transaction history: ' . $e->getMessage());
        }
    }

    public function updateTransactionRecord($record_transaction_id, $data) {
        try {
            // Build dynamic SET clause based on provided data
            $setParts = [];
            $allowedFields = ['status', 'payment_id', 'description', 'reference'];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $setParts[] = "$field = :$field";
                }
            }
            
            if (empty($setParts)) {
                throw new Exception('No valid fields provided for update');
            }
            
            $setClause = implode(', ', $setParts);
            $sql = "UPDATE {$this->transactions_table} SET $setClause WHERE id = :id";
            
            $stmt = $this->connection->prepare($sql);
            
            // Bind dynamic parameters
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $stmt->bindParam(":$field", $data[$field], PDO::PARAM_STR);
                }
            }
            
            $stmt->bindParam(':id', $record_transaction_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to update transaction record: ' . $e->getMessage());
        }
    }

    /**
     * Record a transaction
     */
    public function recordTransaction($userId, $type, $amount, $description = '', $reference = '', $status = 'processing') {
        try {
            $sql = "INSERT INTO {$this->transactions_table} (user_id, type, amount, description, reference, status) VALUES (:user_id, :type, :amount, :description, :reference, :status)";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':reference', $reference, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->execute();
            
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Failed to record transaction: ' . $e->getMessage());
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

    public function check_if_user_is_admin($userId) {
        try {
            $users_table = $this->wp_prefix . 'users';
            $usermeta_table = $this->wp_prefix . 'usermeta';
            $sql = "SELECT COUNT(*) as is_admin
                    FROM {$usermeta_table} umeta
                    JOIN {$users_table} users ON umeta.user_id = users.ID
                    WHERE umeta.meta_key = '{$this->wp_prefix}capabilities'
                      AND umeta.meta_value LIKE '%administrator%'
                      AND users.ID = :user_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result && $result['is_admin'] > 0;
        } catch (PDOException $e) {
            throw new Exception('Failed to check if user is admin: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate wallet
     */
    public function deactivateWallet($userId) {
        try {
            $sql = "UPDATE {$this->wallets_table} SET status = 'inactive' WHERE user_id = :user_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Failed to deactivate wallet: ' . $e->getMessage());
        }
    }

    /**
     * Get all wallets (admin function)
     */
    public function getAllWallets($status = 'active', $limit = 100) {
        try {
            $users_table = $this->wp_prefix . 'users';
            $sql = "SELECT w.*, u.display_name, u.user_email 
                    FROM {$this->wallets_table} w 
                    JOIN {$users_table} u ON w.user_id = u.ID 
                    WHERE w.status = :status 
                    ORDER BY w.updated_at DESC 
                    LIMIT :limit";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Failed to get all wallets: ' . $e->getMessage());
        }
    }

    /**
     * Get wallet statistics
     */
    public function getWalletStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_wallets,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_wallets,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_wallets,
                        SUM(balance) as total_balance
                    FROM {$this->wallets_table}";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Failed to get wallet statistics: ' . $e->getMessage());
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
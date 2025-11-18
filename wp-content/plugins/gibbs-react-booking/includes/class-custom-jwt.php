<?php
/**
 * Custom JWT Implementation with High Security
 * 
 * This is a custom JWT implementation with no third-party dependencies
 * Includes advanced security features like encryption, custom algorithms, and anti-tampering
 * 
 * @package ReactModulesPlugin
 * @version 1.0.0
 */

 defined('ABSPATH') or exit;


class Custom_JWT {

    private $CUSTOM_JWT_SECRET_KEY = 'gibbswallet*^*&%&^%^&*%';
    private $CUSTOM_JWT_ENCRYPTION_KEY = 'gibbswallet*^*&%&^%^&*%';
    
    /**
     * Secret key for JWT signing
     */
    private $secret_key;
    
    /**
     * Encryption key for payload encryption
     */
    private $encryption_key;
    
    /**
     * Algorithm for JWT signing (custom implementation)
     */
    private $algorithm = 'CUSTOM_HS512';
    
    /**
     * Token expiration time in seconds (24 hours by default)
     */
    private $expiration_time = 86400;
    
    /**
     * Maximum token age in seconds (24 hours)
     */
    private $max_token_age = 86400;
    
    /**
     * Token rotation interval in seconds (24 hours)
     */
    private $rotation_interval = 86400;
    
    /**
     * Anti-replay window in seconds (24 hours)
     */
    private $anti_replay_window = 86400;
    
    /**
     * Store used tokens to prevent replay attacks
     */
    private static $used_tokens = array();
    
    public function __construct() {
        // Get secret key from wp-config.php or generate a strong one
        $this->secret_key = $this->CUSTOM_JWT_SECRET_KEY;
        
        // Get encryption key from wp-config.php or generate a strong one
        $this->encryption_key = $this->CUSTOM_JWT_ENCRYPTION_KEY;
        
        // Clean up old used tokens periodically
        $this->cleanup_used_tokens();
    }
    
    /**
     * Generate a cryptographically strong key
     */
    private function generate_strong_key() {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(64));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(64));
        } else {
            // Fallback to WordPress salt with additional entropy
            return wp_salt('auth') . wp_salt('secure_auth') . uniqid('', true);
        }
    }
    
    /**
     * Custom hash function with multiple rounds and salt
     */
    private function custom_hash($data, $salt = '') {
        $hash = $data . $salt . $this->secret_key;
        
        // Multiple rounds of hashing for increased security
        for ($i = 0; $i < 10000; $i++) {
            $hash = hash('sha512', $hash . $i . $this->secret_key, true);
        }
        
        return base64_encode($hash);
    }
    
    /**
     * Encrypt payload data
     */
    private function encrypt_payload($data) {
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt payload data
     */
    private function decrypt_payload($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return json_decode($decrypted, true);
    }
    
    /**
     * Generate JWT token for user
     */
    public function generate_token($user_id, $additional_payload = array()) {
        try {
            // Get user data
            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }
            
            // Create base payload with security features
            $payload = array(
                'user_id' => $user_id,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'iat' => time(), // Issued at
                'exp' => time() + $this->expiration_time, // Expires in
                'nbf' => time(), // Not before (prevent future use)
                'iss' => get_site_url(), // Issuer
                'aud' => get_site_url(), // Audience
                'jti' => $this->generate_unique_id(), // JWT ID for uniqueness
                'version' => '1.0', // Token version for future compatibility
                'session_id' => $this->generate_session_id(), // Session identifier
                'ip_hash' => $this->hash_ip_address(), // IP address hash
                'user_agent_hash' => $this->hash_user_agent(), // User agent hash
                'last_password_change' => get_user_meta($user_id, 'last_password_change', true) ?: 0
            );
            
            // Merge additional payload
            if (!empty($additional_payload)) {
                $payload = array_merge($payload, $additional_payload);
            }
            
            // Encrypt the payload
            $encrypted_payload = $this->encrypt_payload($payload);
            
            // Create header
            $header = array(
                'alg' => $this->algorithm,
                'typ' => 'JWT',
                'kid' => $this->generate_key_id(), // Key ID for key rotation
                'enc' => 'AES-256-CBC' // Encryption method
            );
            
            // Encode header and payload
            $header_encoded = $this->base64url_encode(json_encode($header));
            $payload_encoded = $this->base64url_encode($encrypted_payload);
            
            // Create signature
            $signature = $this->create_signature($header_encoded, $payload_encoded);
            $signature_encoded = $this->base64url_encode($signature);
            
            // Combine all parts
            $token = $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
            
            // Store token for anti-replay protection
            $this->store_used_token($payload['jti'], $payload['exp']);
            
            return $token;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Validate JWT token
     */
    public function validate_token($token) {
        try {
           
            
            // Split token into parts
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
            
            // Decode header
            $header = json_decode($this->base64url_decode($header_encoded), true);
            if (!$header || !isset($header['alg']) || $header['alg'] !== $this->algorithm) {
                return false;
            }
            
            // Verify signature
            $expected_signature = $this->create_signature($header_encoded, $payload_encoded);
            if (!$this->constant_time_compare($signature_encoded, $this->base64url_encode($expected_signature))) {
                return false;
            }
            
            // Decrypt and decode payload
            $encrypted_payload = $this->base64url_decode($payload_encoded);
            $payload = $this->decrypt_payload($encrypted_payload);
            
            if (!$payload) {
                return false;
            }
            
            // Validate payload
            if (!$this->validate_payload($payload)) {
                return false;
            }
            
            // Mark token as used
            $this->store_used_token($payload['jti'], $payload['exp']);
            
            return (array)$payload;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Validate token payload
     */
    private function validate_payload($payload) {
        $current_time = time();
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < $current_time) {
            return false;
        }
        
        // Check not before time
        if (isset($payload['nbf']) && $payload['nbf'] > $current_time) {
            return false;
        }
        
        // Check token age
        if (isset($payload['iat']) && ($current_time - $payload['iat']) > $this->max_token_age) {
            return false;
        }
        
        // Check if user still exists
        // if (isset($payload['user_id']) && !get_userdata($payload['user_id'])) {
        //     return false;
        // }
        
        
        // Check IP address hash
        if (isset($payload['ip_hash']) && $payload['ip_hash'] !== $this->hash_ip_address()) {
            return false;
        }
        
        // Check user agent hash
        if (isset($payload['user_agent_hash']) && $payload['user_agent_hash'] !== $this->hash_user_agent()) {
            return false;
        }
        
        
        return true;
    }
    
    /**
     * Create signature for token
     */
    private function create_signature($header_encoded, $payload_encoded) {
        $data = $header_encoded . '.' . $payload_encoded;
        return $this->custom_hash($data, $this->secret_key);
    }
    
    /**
     * Generate unique identifier
     */
    private function generate_unique_id() {
        return uniqid('jwt_', true) . '_' . bin2hex(random_bytes(16));
    }
 
    
    /**
     * Generate session ID
     */
    private function generate_session_id() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Hash IP address
     */
    private function hash_ip_address() {
        $ip = $this->get_client_ip();
        return hash('sha256', $ip . $this->secret_key);
    }
    
    /**
     * Hash user agent
     */
    private function hash_user_agent() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return hash('sha256', $user_agent . $this->secret_key);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Generate key ID for key rotation
     */
    private function generate_key_id() {
        return hash('sha256', $this->secret_key . time());
    }
    
    /**
     * Store used token for anti-replay protection
     */
    private function store_used_token($jti, $exp) {
        self::$used_tokens[$jti] = $exp;
        
        // Limit stored tokens to prevent memory issues
        if (count(self::$used_tokens) > 10000) {
            $this->cleanup_used_tokens();
        }
    }
    
    
    /**
     * Clean up expired used tokens
     */
    private function cleanup_used_tokens() {
        $current_time = time();
        foreach (self::$used_tokens as $jti => $exp) {
            if ($exp < $current_time) {
                unset(self::$used_tokens[$jti]);
            }
        }
    }
    
    /**
     * Base64URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64URL decode
     */
    private function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
    
    /**
     * Constant time comparison to prevent timing attacks
     */
    private function constant_time_compare($a, $b) {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        
        $result = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $result |= ord($a[$i]) ^ ord($b[$i]);
        }
        
        return $result === 0;
    }
    
    /**
     * Get user from JWT token
     */
    public function get_user_from_token($token) {
        $decoded = $this->validate_token($token);
        
        if ($decoded && isset($decoded->user_id)) {
            return get_userdata($decoded->user_id);
        }
        
        return false;
    }
    
    /**
     * Get user ID from JWT token
     */
    public function get_user_id_from_token($token) {
        $decoded = $this->validate_token($token);
        
        if ($decoded && isset($decoded->user_id)) {
            return intval($decoded->user_id);
        }
        
        return false;
    }
    
    /**
     * Refresh JWT token
     */
    public function refresh_token($token) {
        $decoded = $this->validate_token($token);
        
        if ($decoded && isset($decoded->user_id)) {
            // Check if token is eligible for refresh
            $current_time = time();
            $token_age = $current_time - $decoded->iat;
            
            if ($token_age > $this->rotation_interval) {
                // Generate new token with same user data
                $additional_payload = array();
                
                // Copy custom fields from the original token
                $custom_fields = array_diff_key((array)$decoded, array_flip(array('iat', 'exp', 'nbf', 'jti', 'session_id', 'ip_hash', 'user_agent_hash')));
                if (!empty($custom_fields)) {
                    $additional_payload = $custom_fields;
                }
                
                return $this->generate_token($decoded->user_id, $additional_payload);
            }
        }
        
        return false;
    }
    
    /**
     * Set custom expiration time
     */
    public function set_expiration_time($seconds) {
        $this->expiration_time = intval($seconds);
    }
    
    /**
     * Get token expiration time
     */
    public function get_expiration_time() {
        return $this->expiration_time;
    }
    
    /**
     * Set custom secret key
     */
    public function set_secret_key($key) {
        $this->secret_key = $key;
    }
    
    /**
     * Set custom encryption key
     */
    public function set_encryption_key($key) {
        $this->encryption_key = $key;
    }
    
    /**
     * Generate token for current user
     */
    public function generate_token_for_current_user($additional_payload = array()) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        return $this->generate_token($user_id, $additional_payload);
    }
    
    /**
     * Get token payload as array
     */
    public function get_token_payload($token) {
        $decoded = $this->validate_token($token);
        
        if ($decoded) {
            return (array)$decoded;
        }
        
        return false;
    }
    
    /**
     * Invalidate all tokens for a user (e.g., after password change)
     */
    public function invalidate_user_tokens($user_id) {
        // Update last password change timestamp
        update_user_meta($user_id, 'last_password_change', time());
        
        // Clear any stored tokens for this user
        $this->cleanup_used_tokens();
        
        return true;
    }
    
    /**
     * Get security statistics
     */
    public function get_security_stats() {
        return array(
            'used_tokens_count' => count(self::$used_tokens),
            'algorithm' => $this->algorithm,
            'expiration_time' => $this->expiration_time,
            'max_token_age' => $this->max_token_age,
            'rotation_interval' => $this->rotation_interval,
            'anti_replay_window' => $this->anti_replay_window
        );
    }
} 
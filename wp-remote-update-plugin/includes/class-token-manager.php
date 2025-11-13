<?php
/**
 * Token Manager Class
 * 
 * Handles API token generation, validation, and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class RUM_Token_Manager {
    
    /**
     * Table name for tokens
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rum_api_tokens';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Generate a new API token
     * 
     * @return string|false Token string on success, false on failure
     */
    public function generate_token() {
        global $wpdb;
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Generate a secure random token
        $token = bin2hex(random_bytes(32)); // 64 character hex string
        
        // Insert token into database
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'token' => $token,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $token;
    }
    
    /**
     * Validate an API token
     * 
     * @param string $token Token to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_token($token) {
        global $wpdb;
        
        if (empty($token)) {
            return false;
        }
        
        // Sanitize token
        $token = sanitize_text_field($token);
        
        // Check if token exists
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE token = %s",
            $token
        ));
        
        if (!$result) {
            return false;
        }
        
        // Update last used timestamp
        $wpdb->update(
            $this->table_name,
            array('last_used' => current_time('mysql')),
            array('id' => $result->id),
            array('%s'),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Get all tokens
     * 
     * @return array Array of token objects
     */
    public function get_all_tokens() {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return array();
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC"
        );
    }
    
    /**
     * Delete a token
     * 
     * @param int $token_id Token ID to delete
     * @return bool True on success, false on failure
     */
    public function delete_token($token_id) {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $token_id = intval($token_id);
        
        return $wpdb->delete(
            $this->table_name,
            array('id' => $token_id),
            array('%d')
        ) !== false;
    }
    
    /**
     * Extract token from Authorization header
     * 
     * @return string|false Token string or false if not found
     */
    public function get_token_from_header() {
        $auth_header = null;
        
        // Check Authorization header - try multiple methods for compatibility
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $apache_headers = apache_request_headers();
            if (isset($apache_headers['Authorization'])) {
                $auth_header = $apache_headers['Authorization'];
            } elseif (isset($apache_headers['authorization'])) {
                $auth_header = $apache_headers['authorization'];
            }
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $auth_header = $headers['authorization'];
            }
        }
        
        if (empty($auth_header)) {
            return false;
        }
        
        // Extract Bearer token
        if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
            return trim($matches[1]);
        }
        
        return false;
    }
}


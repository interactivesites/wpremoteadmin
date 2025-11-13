<?php
/**
 * Database Class
 * 
 * Handles database operations for sites and update logs
 */

require_once __DIR__ . '/config.php';

class Database {
    
    /**
     * Database connection
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->connect();
        $this->create_tables();
    }
    
    /**
     * Connect to database
     */
    private function connect() {
        if (DB_TYPE === 'sqlite') {
            // Create data directory if it doesn't exist
            $db_dir = dirname(DB_PATH);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0755, true);
            }
            
            try {
                $this->db = new PDO('sqlite:' . DB_PATH);
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
        } else {
            try {
                $this->db = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    )
                );
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        $sites_table = "
            CREATE TABLE IF NOT EXISTS sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                url VARCHAR(500) NOT NULL,
                api_token VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_checked DATETIME DEFAULT NULL,
                UNIQUE(url)
            )
        ";
        
        $update_logs_table = "
            CREATE TABLE IF NOT EXISTS update_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id INTEGER NOT NULL,
                update_type VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL,
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
            )
        ";
        
        // Adjust for MySQL
        if (DB_TYPE === 'mysql') {
            $sites_table = str_replace('AUTOINCREMENT', 'AUTO_INCREMENT', $sites_table);
            $sites_table = str_replace('INTEGER PRIMARY KEY', 'INT AUTO_INCREMENT PRIMARY KEY', $sites_table);
            $sites_table = str_replace('INTEGER NOT NULL', 'INT NOT NULL', $sites_table);
            
            $update_logs_table = str_replace('AUTOINCREMENT', 'AUTO_INCREMENT', $update_logs_table);
            $update_logs_table = str_replace('INTEGER PRIMARY KEY', 'INT AUTO_INCREMENT PRIMARY KEY', $update_logs_table);
            $update_logs_table = str_replace('INTEGER NOT NULL', 'INT NOT NULL', $update_logs_table);
        }
        
        try {
            $this->db->exec($sites_table);
            $this->db->exec($update_logs_table);
        } catch (PDOException $e) {
            die('Failed to create tables: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all sites
     * 
     * @return array Array of site records
     */
    public function get_all_sites() {
        $stmt = $this->db->query("SELECT * FROM sites ORDER BY name ASC");
        return $stmt->fetchAll();
    }
    
    /**
     * Get site by ID
     * 
     * @param int $id Site ID
     * @return array|false Site record or false if not found
     */
    public function get_site($id) {
        $stmt = $this->db->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch();
    }
    
    /**
     * Add a new site
     * 
     * @param string $name Site name
     * @param string $url Site URL
     * @param string $api_token API token
     * @return int|false Site ID on success, false on failure
     */
    public function add_site($name, $url, $api_token) {
        $stmt = $this->db->prepare(
            "INSERT INTO sites (name, url, api_token) VALUES (?, ?, ?)"
        );
        
        if ($stmt->execute(array($name, $url, $api_token))) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update a site
     * 
     * @param int $id Site ID
     * @param string $name Site name
     * @param string $url Site URL
     * @param string $api_token API token
     * @return bool True on success, false on failure
     */
    public function update_site($id, $name, $url, $api_token) {
        $stmt = $this->db->prepare(
            "UPDATE sites SET name = ?, url = ?, api_token = ? WHERE id = ?"
        );
        
        return $stmt->execute(array($name, $url, $api_token, $id));
    }
    
    /**
     * Delete a site
     * 
     * @param int $id Site ID
     * @return bool True on success, false on failure
     */
    public function delete_site($id) {
        $stmt = $this->db->prepare("DELETE FROM sites WHERE id = ?");
        return $stmt->execute(array($id));
    }
    
    /**
     * Update last checked timestamp
     * 
     * @param int $id Site ID
     * @return bool True on success, false on failure
     */
    public function update_last_checked($id) {
        $stmt = $this->db->prepare(
            "UPDATE sites SET last_checked = CURRENT_TIMESTAMP WHERE id = ?"
        );
        return $stmt->execute(array($id));
    }
    
    /**
     * Add update log entry
     * 
     * @param int $site_id Site ID
     * @param string $update_type Update type (core, plugins, themes)
     * @param string $status Status (success, error)
     * @param string $message Log message
     * @return int|false Log ID on success, false on failure
     */
    public function add_log($site_id, $update_type, $status, $message) {
        $stmt = $this->db->prepare(
            "INSERT INTO update_logs (site_id, update_type, status, message) VALUES (?, ?, ?, ?)"
        );
        
        if ($stmt->execute(array($site_id, $update_type, $status, $message))) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Get update logs for a site
     * 
     * @param int $site_id Site ID
     * @param int $limit Limit number of results
     * @return array Array of log records
     */
    public function get_logs($site_id, $limit = 50) {
        $stmt = $this->db->prepare(
            "SELECT * FROM update_logs WHERE site_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute(array($site_id, $limit));
        return $stmt->fetchAll();
    }
    
    /**
     * Get all update logs
     * 
     * @param int $limit Limit number of results
     * @return array Array of log records
     */
    public function get_all_logs($limit = 100) {
        $stmt = $this->db->query(
            "SELECT l.*, s.name as site_name FROM update_logs l 
             LEFT JOIN sites s ON l.site_id = s.id 
             ORDER BY l.created_at DESC LIMIT " . intval($limit)
        );
        return $stmt->fetchAll();
    }
}


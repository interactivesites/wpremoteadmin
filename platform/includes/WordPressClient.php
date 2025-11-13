<?php
/**
 * WordPress Client Class
 * 
 * Handles HTTP requests to WordPress REST API endpoints
 */

class WordPressClient {
    
    /**
     * Site URL
     */
    private $url;
    
    /**
     * API token
     */
    private $token;
    
    /**
     * Constructor
     * 
     * @param string $url WordPress site URL
     * @param string $token API token
     */
    public function __construct($url, $token) {
        $this->url = rtrim($url, '/');
        $this->token = $token;
    }
    
    /**
     * Make authenticated request to WordPress API
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $data Request data for POST requests
     * @return array|false Response data or false on failure
     */
    private function request($endpoint, $method = 'GET', $data = array()) {
        $url = $this->url . '/wp-json/remote-update/v1/' . ltrim($endpoint, '/');
        
        $ch = curl_init();
        
        $headers = array(
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        );
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 300, // 5 minutes for updates
            CURLOPT_FOLLOWLOCATION => true
        ));
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return array(
                'success' => false,
                'error' => 'cURL Error: ' . $error
            );
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response',
                'http_code' => $http_code,
                'raw_response' => $response
            );
        }
        
        $decoded['http_code'] = $http_code;
        
        return $decoded;
    }
    
    /**
     * Check for available updates
     * 
     * @return array|false Update status or false on failure
     */
    public function check_status() {
        return $this->request('status', 'GET');
    }
    
    /**
     * Update WordPress core
     * 
     * @return array|false Update result or false on failure
     */
    public function update_core() {
        return $this->request('update-core', 'POST');
    }
    
    /**
     * Update plugins
     * 
     * @param array $plugins Optional array of plugin files to update
     * @return array|false Update result or false on failure
     */
    public function update_plugins($plugins = array()) {
        $data = array();
        if (!empty($plugins)) {
            $data['plugins'] = $plugins;
        }
        return $this->request('update-plugins', 'POST', $data);
    }
    
    /**
     * Update themes
     * 
     * @param array $themes Optional array of theme slugs to update
     * @return array|false Update result or false on failure
     */
    public function update_themes($themes = array()) {
        $data = array();
        if (!empty($themes)) {
            $data['themes'] = $themes;
        }
        return $this->request('update-themes', 'POST', $data);
    }
}


<?php
/**
 * API Handler Class
 * 
 * Handles REST API endpoints for remote updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class RUM_API_Handler {
    
    /**
     * Token manager instance
     */
    private $token_manager;
    
    /**
     * Update handler instance
     */
    private $update_handler;
    
    /**
     * Namespace for REST API
     */
    private $namespace = 'remote-update/v1';
    
    /**
     * Constructor
     */
    public function __construct($token_manager) {
        $this->token_manager = $token_manager;
        $this->update_handler = new RUM_Update_Handler();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($this->namespace, '/update-core', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_core'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($this->namespace, '/update-plugins', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_plugins'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($this->namespace, '/update-themes', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_themes'),
            'permission_callback' => array($this, 'check_permission')
        ));
    }
    
    /**
     * Check permission for API requests
     * 
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if authorized, WP_Error otherwise
     */
    public function check_permission($request) {
        // Get token from Authorization header
        $token = $this->token_manager->get_token_from_header();
        
        if (!$token) {
            return new WP_Error(
                'missing_token',
                'API token is required. Include it in the Authorization header as: Authorization: Bearer YOUR_TOKEN',
                array('status' => 401)
            );
        }
        
        // Validate token
        if (!$this->token_manager->validate_token($token)) {
            return new WP_Error(
                'invalid_token',
                'Invalid or expired API token',
                array('status' => 401)
            );
        }
        
        // Additional security: Require HTTPS in production
        if (!is_ssl() && !WP_DEBUG) {
            return new WP_Error(
                'https_required',
                'HTTPS is required for API requests',
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Get update status
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_status($request) {
        $updates = $this->update_handler->check_updates();
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'wordpress_version' => get_bloginfo('version'),
                'updates' => $updates
            )
        ), 200);
    }
    
    /**
     * Update WordPress core
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function update_core($request) {
        $result = $this->update_handler->update_core();
        
        $status_code = $result['success'] ? 200 : 400;
        
        return new WP_REST_Response($result, $status_code);
    }
    
    /**
     * Update plugins
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function update_plugins($request) {
        $plugins = $request->get_param('plugins');
        
        // If plugins parameter is provided, ensure it's an array
        if (!empty($plugins) && !is_array($plugins)) {
            $plugins = array($plugins);
        }
        
        $result = $this->update_handler->update_plugins($plugins);
        
        $status_code = $result['success'] ? 200 : 400;
        
        return new WP_REST_Response($result, $status_code);
    }
    
    /**
     * Update themes
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function update_themes($request) {
        $themes = $request->get_param('themes');
        
        // If themes parameter is provided, ensure it's an array
        if (!empty($themes) && !is_array($themes)) {
            $themes = array($themes);
        }
        
        $result = $this->update_handler->update_themes($themes);
        
        $status_code = $result['success'] ? 200 : 400;
        
        return new WP_REST_Response($result, $status_code);
    }
}


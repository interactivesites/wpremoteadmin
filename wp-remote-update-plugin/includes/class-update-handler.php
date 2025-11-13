<?php
/**
 * Update Handler Class
 * 
 * Handles WordPress core, plugin, and theme updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class RUM_Update_Handler {
    
    /**
     * Check for available updates
     * 
     * @return array Array containing update information
     */
    public function check_updates() {
        // Load WordPress update functions
        require_once(ABSPATH . 'wp-admin/includes/update.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        require_once(ABSPATH . 'wp-admin/includes/theme.php');
        
        // Force update checks
        wp_update_plugins();
        wp_update_themes();
        
        $updates = array(
            'core' => $this->get_core_updates(),
            'plugins' => $this->get_plugin_updates(),
            'themes' => $this->get_theme_updates()
        );
        
        return $updates;
    }
    
    /**
     * Get core updates
     * 
     * @return array|false Array of core updates or false if none
     */
    private function get_core_updates() {
        $core_updates = get_core_updates();
        
        if (empty($core_updates) || !isset($core_updates[0])) {
            return false;
        }
        
        $update = $core_updates[0];
        
        if ($update->response === 'latest') {
            return false;
        }
        
        return array(
            'version' => isset($update->version) ? $update->version : '',
            'current_version' => get_bloginfo('version'),
            'response' => isset($update->response) ? $update->response : ''
        );
    }
    
    /**
     * Get plugin updates
     * 
     * @return array Array of plugin updates
     */
    private function get_plugin_updates() {
        $plugin_updates = get_plugin_updates();
        $updates = array();
        
        foreach ($plugin_updates as $plugin_file => $plugin_data) {
            if (isset($plugin_data->update) && isset($plugin_data->update->new_version)) {
                $updates[] = array(
                    'file' => $plugin_file,
                    'name' => isset($plugin_data->Name) ? $plugin_data->Name : $plugin_file,
                    'version' => isset($plugin_data->Version) ? $plugin_data->Version : '',
                    'new_version' => $plugin_data->update->new_version
                );
            }
        }
        
        return $updates;
    }
    
    /**
     * Get theme updates
     * 
     * @return array Array of theme updates
     */
    private function get_theme_updates() {
        $theme_updates = get_theme_updates();
        $updates = array();
        
        foreach ($theme_updates as $theme_slug => $theme_data) {
            if (isset($theme_data->update) && isset($theme_data->update['new_version'])) {
                $updates[] = array(
                    'slug' => $theme_slug,
                    'name' => isset($theme_data->Name) ? $theme_data->Name : $theme_slug,
                    'version' => isset($theme_data->Version) ? $theme_data->Version : '',
                    'new_version' => $theme_data->update['new_version']
                );
            }
        }
        
        return $updates;
    }
    
    /**
     * Update WordPress core
     * 
     * @return array Result array with status and message
     */
    public function update_core() {
        // Load WordPress update functions
        require_once(ABSPATH . 'wp-admin/includes/update.php');
        
        // Check if update is available
        $core_updates = get_core_updates();
        
        if (empty($core_updates) || !isset($core_updates[0])) {
            return array(
                'success' => false,
                'message' => 'No core updates available'
            );
        }
        
        $core_update = $core_updates[0];
        
        if ($core_update->response === 'latest') {
            return array(
                'success' => false,
                'message' => 'WordPress is already up to date'
            );
        }
        
        // Load update functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/misc.php');
        
        // Disable filesystem credentials form (only if not already defined)
        if (!defined('FS_METHOD')) {
            define('FS_METHOD', 'direct');
        }
        
        // Perform core update - wp_update_core expects the update object
        $result = wp_update_core($core_update);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => 'WordPress core updated successfully to version ' . $core_update->version
        );
    }
    
    /**
     * Update plugins
     * 
     * @param array $plugins Optional array of plugin files to update. If empty, updates all.
     * @return array Result array with status and messages
     */
    public function update_plugins($plugins = array()) {
        // Load update functions
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/misc.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
        
        // Disable filesystem credentials form (only if not already defined)
        if (!defined('FS_METHOD')) {
            define('FS_METHOD', 'direct');
        }
        
        // Get available plugin updates
        $plugin_updates = $this->get_plugin_updates();
        
        if (empty($plugin_updates)) {
            return array(
                'success' => false,
                'message' => 'No plugin updates available'
            );
        }
        
        // Filter plugins if specific ones requested
        if (!empty($plugins)) {
            $plugin_updates = array_filter($plugin_updates, function($update) use ($plugins) {
                return in_array($update['file'], $plugins);
            });
        }
        
        if (empty($plugin_updates)) {
            return array(
                'success' => false,
                'message' => 'No matching plugin updates found'
            );
        }
        
        $results = array();
        $upgrader = new Plugin_Upgrader();
        
        foreach ($plugin_updates as $plugin_update) {
            $result = $upgrader->upgrade($plugin_update['file']);
            
            if (is_wp_error($result)) {
                $results[] = array(
                    'plugin' => $plugin_update['name'],
                    'success' => false,
                    'message' => $result->get_error_message()
                );
            } else {
                $results[] = array(
                    'plugin' => $plugin_update['name'],
                    'success' => true,
                    'message' => 'Updated successfully to version ' . $plugin_update['new_version']
                );
            }
        }
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
    
    /**
     * Update themes
     * 
     * @param array $themes Optional array of theme slugs to update. If empty, updates all.
     * @return array Result array with status and messages
     */
    public function update_themes($themes = array()) {
        // Load update functions
        require_once(ABSPATH . 'wp-admin/includes/theme.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/misc.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        require_once(ABSPATH . 'wp-admin/includes/theme-install.php');
        
        // Disable filesystem credentials form (only if not already defined)
        if (!defined('FS_METHOD')) {
            define('FS_METHOD', 'direct');
        }
        
        // Get available theme updates
        $theme_updates = $this->get_theme_updates();
        
        if (empty($theme_updates)) {
            return array(
                'success' => false,
                'message' => 'No theme updates available'
            );
        }
        
        // Filter themes if specific ones requested
        if (!empty($themes)) {
            $theme_updates = array_filter($theme_updates, function($update) use ($themes) {
                return in_array($update['slug'], $themes);
            });
        }
        
        if (empty($theme_updates)) {
            return array(
                'success' => false,
                'message' => 'No matching theme updates found'
            );
        }
        
        $results = array();
        $upgrader = new Theme_Upgrader();
        
        foreach ($theme_updates as $theme_update) {
            $result = $upgrader->upgrade($theme_update['slug']);
            
            if (is_wp_error($result)) {
                $results[] = array(
                    'theme' => $theme_update['name'],
                    'success' => false,
                    'message' => $result->get_error_message()
                );
            } else {
                $results[] = array(
                    'theme' => $theme_update['name'],
                    'success' => true,
                    'message' => 'Updated successfully to version ' . $theme_update['new_version']
                );
            }
        }
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
}


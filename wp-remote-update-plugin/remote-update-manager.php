<?php
/**
 * Plugin Name: interactivesites Remote Update Manager
 * Plugin URI: https://github.com/interactivesites/remote-update-manager
 * Description: Allows remote management of WordPress updates via authenticated REST API
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://interactivesites.co.nz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: remote-update-manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RUM_VERSION', '1.0.0');
define('RUM_PLUGIN_FILE', __FILE__);
define('RUM_PLUGIN_DIR', dirname(__FILE__) . '/');
define('RUM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files - use direct path resolution
$includes_dir = dirname(__FILE__) . '/includes/';

$required_files = array(
    'class-token-manager.php',
    'class-update-handler.php',
    'class-api-handler.php'
);

foreach ($required_files as $file) {
    $file_path = $includes_dir . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // Show error notice if WordPress is loaded, otherwise trigger error
        if (function_exists('add_action')) {
            add_action('admin_notices', function() use ($file_path) {
                echo '<div class="notice notice-error"><p><strong>Remote Update Manager Error:</strong> Required file not found: ' . esc_html($file_path) . '</p></div>';
            });
        } else {
            trigger_error('Remote Update Manager: Required file not found: ' . $file_path, E_USER_ERROR);
        }
        return;
    }
}

/**
 * Main plugin class
 */
class Remote_Update_Manager {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Token manager instance
     */
    private $token_manager;
    
    /**
     * API handler instance
     */
    private $api_handler;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->token_manager = new RUM_Token_Manager();
        $this->api_handler = new RUM_API_Handler($this->token_manager);
        
        // Activation and deactivation hooks
        register_activation_hook(RUM_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(RUM_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register REST API routes on the correct hook
        add_action('rest_api_init', array($this->api_handler, 'register_routes'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Load text domain for translations (on init hook, not plugins_loaded)
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('remote-update-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->token_manager->create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Remote Update Manager', 'remote-update-manager'),
            __('Remote Updates', 'remote-update-manager'),
            'manage_options',
            'remote-update-manager',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Handle token generation
        if (isset($_POST['rum_generate_token']) && check_admin_referer('rum_generate_token')) {
            $token = $this->token_manager->generate_token();
            if ($token) {
                echo '<div class="notice notice-success"><p>' . __('API token generated successfully!', 'remote-update-manager') . '</p></div>';
            }
        }
        
        // Handle token deletion
        if (isset($_POST['rum_delete_token']) && check_admin_referer('rum_delete_token')) {
            $token_id = intval($_POST['token_id']);
            if ($this->token_manager->delete_token($token_id)) {
                echo '<div class="notice notice-success"><p>' . __('Token deleted successfully!', 'remote-update-manager') . '</p></div>';
            }
        }
        
        // Get all tokens
        $tokens = $this->token_manager->get_all_tokens();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2><?php _e('API Tokens', 'remote-update-manager'); ?></h2>
                <p><?php _e('Generate API tokens to authenticate remote update requests. Keep these tokens secure.', 'remote-update-manager'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('rum_generate_token'); ?>
                    <p>
                        <input type="submit" name="rum_generate_token" class="button button-primary" value="<?php esc_attr_e('Generate New Token', 'remote-update-manager'); ?>" />
                    </p>
                </form>
                
                <?php if (!empty($tokens)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Token', 'remote-update-manager'); ?></th>
                                <th><?php _e('Created', 'remote-update-manager'); ?></th>
                                <th><?php _e('Last Used', 'remote-update-manager'); ?></th>
                                <th><?php _e('Actions', 'remote-update-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tokens as $token): ?>
                                <tr>
                                    <td><code><?php echo esc_html($token->token); ?></code></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->created_at))); ?></td>
                                    <td><?php echo $token->last_used ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->last_used))) : __('Never', 'remote-update-manager'); ?></td>
                                    <td>
                                        <form method="post" action="" style="display: inline;">
                                            <?php wp_nonce_field('rum_delete_token'); ?>
                                            <input type="hidden" name="token_id" value="<?php echo esc_attr($token->id); ?>" />
                                            <input type="submit" name="rum_delete_token" class="button button-small" value="<?php esc_attr_e('Delete', 'remote-update-manager'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this token?', 'remote-update-manager'); ?>');" />
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No tokens generated yet.', 'remote-update-manager'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2><?php _e('API Endpoints', 'remote-update-manager'); ?></h2>
                <p><?php _e('Use these endpoints with your API token in the Authorization header:', 'remote-update-manager'); ?></p>
                <ul>
                    <li><code>GET <?php echo esc_url(rest_url('remote-update/v1/status')); ?></code> - <?php _e('Check for available updates', 'remote-update-manager'); ?></li>
                    <li><code>POST <?php echo esc_url(rest_url('remote-update/v1/update-core')); ?></code> - <?php _e('Update WordPress core', 'remote-update-manager'); ?></li>
                    <li><code>POST <?php echo esc_url(rest_url('remote-update/v1/update-plugins')); ?></code> - <?php _e('Update plugins', 'remote-update-manager'); ?></li>
                    <li><code>POST <?php echo esc_url(rest_url('remote-update/v1/update-themes')); ?></code> - <?php _e('Update themes', 'remote-update-manager'); ?></li>
                </ul>
                <p><strong><?php _e('Authorization Header:', 'remote-update-manager'); ?></strong> <code>Authorization: Bearer YOUR_TOKEN_HERE</code></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_remote-update-manager' !== $hook) {
            return;
        }
        
        // Add any admin CSS/JS here if needed
    }
}

// Initialize plugin
Remote_Update_Manager::get_instance();


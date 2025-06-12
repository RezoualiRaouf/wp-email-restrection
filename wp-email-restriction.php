<?php
/**
 * Plugin Name: WP Email Restriction
 * Description: Restrict website access to specific domain email addresses with custom login page.
 * Version: 2.0
 * Author: Raouf Rezouali
 * Text Domain: wp-email-restriction
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class WP_Email_Restriction {

    /**
     * Plugin version.
     */
    const VERSION = '2.0';

    /**
     * The single instance of the class.
     *
     * @var WP_Email_Restriction|null
     */
    private static $instance = null;

    /**
     * Frontend authentication instance.
     *
     * @var WP_Email_Restriction_Frontend_Auth|null
     */
    private $frontend_auth = null;

    /**
     * Returns the main instance.
     *
     * @return WP_Email_Restriction
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants.
     */
    private function define_constants() {
        define('WP_EMAIL_RESTRICTION_VERSION', self::VERSION);
        define('WP_EMAIL_RESTRICTION_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WP_EMAIL_RESTRICTION_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('WP_EMAIL_RESTRICTION_PLUGIN_FILE', __FILE__);
    }

    /**
     * Include required files.
     */
    private function includes() {
        // Core classes
        require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/class-database.php';
        require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/class-email-validator.php';
        require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/class-email-manager.php';
        
        // Frontend authentication
        require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/class-frontend-auth.php';
        
        // Admin files
        if (is_admin()) {
            require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/admin/class-admin.php';
            require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/admin/class-email-list-table.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
        
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_init', [$this, 'check_version']);
    }

    /**
     * Initialize the plugin after all plugins are loaded.
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('wp-email-restriction', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Initialize frontend authentication
        if (!is_admin() || wp_doing_ajax()) {
            $this->frontend_auth = new WP_Email_Restriction_Frontend_Auth();
        }
        
        // Initialize admin
        if (is_admin()) {
            new WP_Email_Restriction_Admin();
        }
        
        // Add admin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
    }

    /**
     * Check if plugin version has changed and run updates if necessary.
     */
    public function check_version() {
        $current_version = get_option('wp_email_restriction_version');
        
        if ($current_version !== self::VERSION) {
            $this->run_updates($current_version, self::VERSION);
            update_option('wp_email_restriction_version', self::VERSION);
        }
    }

    /**
     * Run updates when plugin version changes.
     */
    private function run_updates($old_version, $new_version) {
        // Database migration
        $database = new WP_Email_Restriction_Database();
        $database->migrate_data();
        
        // Clear any caches
        wp_cache_flush();
        
        // Set default login settings if they don't exist
        if (!get_option('wp_email_restriction_login_settings')) {
            $default_settings = [
                'title' => 'Access Restricted',
                'message' => 'Please login with your authorized email address to access this website.',
                'logo_url' => '',
                'background_color' => '#f1f1f1',
                'form_background' => '#ffffff',
                'primary_color' => '#0073aa',
                'text_color' => '#23282d'
            ];
            update_option('wp_email_restriction_login_settings', $default_settings);
        }
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Create database tables
        $database = new WP_Email_Restriction_Database();
        $database->create_tables();
        
        // Set plugin version
        update_option('wp_email_restriction_version', self::VERSION);
        
        // Set default login settings
        if (!get_option('wp_email_restriction_login_settings')) {
            $default_settings = [
                'title' => 'Access Restricted',
                'message' => 'Please login with your authorized email address to access this website.',
                'logo_url' => '',
                'background_color' => '#f1f1f1',
                'form_background' => '#ffffff',
                'primary_color' => '#0073aa',
                'text_color' => '#23282d'
            ];
            update_option('wp_email_restriction_login_settings', $default_settings);
        }
        
        // Flush rewrite rules (in case we add custom endpoints later)
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clear any transients
        delete_transient('mcp_email_notice_delete');
        delete_transient('mcp_email_notice_bulk_delete');
        
        // Clear object cache
        wp_cache_flush();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall.
     */
    public static function uninstall() {
        global $wpdb;
        
        // Remove database table
        $table_name = $wpdb->prefix . 'email_restriction';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Remove options
        delete_option('wp_email_restriction_version');
        delete_option('wp_email_restriction_login_settings');
        
        // Remove any backup tables
        $backup_tables = $wpdb->get_results(
            "SHOW TABLES LIKE '{$table_name}_backup_%'"
        );
        
        foreach ($backup_tables as $table) {
            $table_name = array_values((array) $table)[0];
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        // Clear any remaining transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_mcp_email_%' 
             OR option_name LIKE '_transient_timeout_mcp_email_%'"
        );
        
        // Clear object cache
        wp_cache_flush();
    }

    /**
     * Add action links to plugin page.
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wp-email-restriction') . '">' . 
                        __('Settings', 'wp-email-restriction') . '</a>';
        array_unshift($links, $settings_link);
        
        $docs_link = '<a href="#" target="_blank">' . 
                    __('Documentation', 'wp-email-restriction') . '</a>';
        array_push($links, $docs_link);
        
        return $links;
    }

    /**
     * Get frontend authentication instance.
     */
    public function get_frontend_auth() {
        return $this->frontend_auth;
    }

    /**
     * Check if current user is authenticated through our system.
     */
    public function is_user_authenticated() {
        return $this->frontend_auth ? $this->frontend_auth->is_user_authenticated() : false;
    }

    /**
     * Get current authenticated user.
     */
    public function get_current_user() {
        return $this->frontend_auth ? $this->frontend_auth->get_current_user() : null;
    }
}

/**
 * Helper function to get the main plugin instance.
 */
function wp_email_restriction() {
    return WP_Email_Restriction::instance();
}

/**
 * Helper functions for theme developers.
 */

/**
 * Check if user is authenticated.
 */
function wp_email_restriction_is_authenticated() {
    return wp_email_restriction()->is_user_authenticated();
}

/**
 * Get current authenticated user.
 */
function wp_email_restriction_current_user() {
    return wp_email_restriction()->get_current_user();
}

/**
 * Display login form shortcode.
 */
function wp_email_restriction_login_form_shortcode($atts) {
    $atts = shortcode_atts([
        'redirect' => home_url()
    ], $atts);
    
    if (wp_email_restriction_is_authenticated()) {
        $user = wp_email_restriction_current_user();
        return '<p>Welcome, ' . esc_html($user['name']) . '! <a href="#" class="restricted-logout-link">Logout</a></p>';
    }
    
    ob_start();
    ?>
    <div class="wp-email-restriction-shortcode-form">
        <form id="restricted-login-form-shortcode" class="login-form">
            <p>
                <label for="email-shortcode">Email:</label>
                <input type="email" id="email-shortcode" name="email" required>
            </p>
            <p>
                <label for="password-shortcode">Password:</label>
                <input type="password" id="password-shortcode" name="password" required>
            </p>
            <p>
                <button type="submit">Login</button>
            </p>
            <input type="hidden" name="action" value="restricted_login">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('restricted_login_nonce'); ?>">
            <input type="hidden" name="redirect_url" value="<?php echo esc_attr($atts['redirect']); ?>">
        </form>
        <div id="login-message-shortcode" style="display: none;"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('wp_email_restriction_login', 'wp_email_restriction_login_form_shortcode');

/**
 * Database migration on plugin load.
 */
add_action('plugins_loaded', 'wp_email_restriction_migrate_db', 5);
function wp_email_restriction_migrate_db() {
    if (class_exists('WP_Email_Restriction_Database')) {
        $db = new WP_Email_Restriction_Database();
        $db->migrate_data();
    }
}

// Start the plugin
wp_email_restriction();
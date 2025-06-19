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
     * Email validator instance.
     *
     * @var WP_Email_Restriction_Validator|null
     */
    private $validator = null;

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
        add_action('admin_notices', [$this, 'show_setup_notice']);
    }

    /**
     * Show setup notice in admin if domain not configured
     */
    public function show_setup_notice() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wp-email-restriction') !== false) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->validator) {
            $this->validator = new WP_Email_Restriction_Validator();
        }

        if (!$this->validator->is_domain_configured()) {
            $setup_url = admin_url('admin.php?page=wp-email-restriction&tab=settings');
            ?>
            <div class="notice notice-warning is-dismissible wp-email-restriction-setup-notice">
                <h3>
                    <span class="dashicons dashicons-email"></span>
                    WP Email Restriction - Setup Required
                </h3>
                <p>
                    <strong>Your website is currently unprotected.</strong> 
                    Configure your allowed email domain to activate access restrictions.
                </p>
                <p>
                    <a href="<?php echo esc_url($setup_url); ?>" class="button button-primary">
                        Configure Now
                    </a>
                    <button type="button" class="notice-dismiss" onclick="wpEmailRestrictionDismissNotice()">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </p>
            </div>
            
            <script>
            function wpEmailRestrictionDismissNotice() {
                document.querySelector('.wp-email-restriction-setup-notice').style.display = 'none';
                localStorage.setItem('wp_email_restriction_notice_dismissed', Date.now() + (24 * 60 * 60 * 1000));
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                const dismissed = localStorage.getItem('wp_email_restriction_notice_dismissed');
                if (dismissed && Date.now() < parseInt(dismissed)) {
                    const notice = document.querySelector('.wp-email-restriction-setup-notice');
                    if (notice) notice.style.display = 'none';
                }
            });
            </script>
            <?php
        }
    }

    /**
     * Initialize the plugin after all plugins are loaded.
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('wp-email-restriction', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Initialize validator
        $this->validator = new WP_Email_Restriction_Validator();
        
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

        // Migrate old domain setting if exists
        $this->migrate_old_domain_setting();
    }

    /**
     * Migrate old hardcoded domain to new configurable system
     */
    private function migrate_old_domain_setting() {
        $settings = get_option('wp_email_restriction_settings', []);
        
        // If domain is not set but we have users, try to detect domain from existing users
        if (empty($settings['allowed_domain'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'email_restriction';
            
            // Check if table exists and has users
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $sample_email = $wpdb->get_var("SELECT email FROM $table_name LIMIT 1");
                
                if ($sample_email && strpos($sample_email, '@') !== false) {
                    $domain = substr(strrchr($sample_email, '@'), 1);
                    
                    if ($domain) {
                        $settings['allowed_domain'] = $domain;
                        update_option('wp_email_restriction_settings', $settings);
                        
                        // Show notice about auto-migration
                        add_action('admin_notices', function() use ($domain) {
                            echo '<div class="notice notice-success is-dismissible">';
                            echo '<h3>WP Email Restriction - Domain Auto-Configured</h3>';
                            echo '<p>We detected your existing users and automatically configured the domain as: <strong>@' . esc_html($domain) . '</strong></p>';
                            echo '<p>You can change this in the plugin settings if needed.</p>';
                            echo '</div>';
                        });
                    }
                }
            }
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

        // Initialize settings array if not exists (but don't set domain)
        if (!get_option('wp_email_restriction_settings')) {
            update_option('wp_email_restriction_settings', []);
        }

        // Try to migrate old domain if exists
        $this->migrate_old_domain_setting();
        
        // Flush rewrite rules (in case we add custom endpoints later)
        flush_rewrite_rules();

        // Set activation redirect flag
        set_transient('wp_email_restriction_activation_redirect', true, 30);
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clear any transients
        delete_transient('mcp_email_notice_delete');
        delete_transient('mcp_email_notice_bulk_delete');
        delete_transient('wp_email_restriction_activation_redirect');
        
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
        delete_option('wp_email_restriction_settings');
        
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
             OR option_name LIKE '_transient_timeout_mcp_email_%'
             OR option_name LIKE '_transient_wp_email_restriction_%'"
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
        
        // Add setup link if not configured
        if ($this->validator && !$this->validator->is_domain_configured()) {
            $setup_link = '<a href="' . admin_url('admin.php?page=wp-email-restriction&tab=settings') . '" style="color: #d63638; font-weight: bold;">' . 
                         __('Setup Required', 'wp-email-restriction') . '</a>';
            array_unshift($links, $setup_link);
        }
        
        return $links;
    }

    /**
     * Get frontend authentication instance.
     */
    public function get_frontend_auth() {
        return $this->frontend_auth;
    }

    /**
     * Get validator instance.
     */
    public function get_validator() {
        return $this->validator;
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

    /**
     * Check if plugin is properly configured
     */
    public function is_configured() {
        return $this->validator ? $this->validator->is_domain_configured() : false;
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
 * Check if plugin is configured.
 */
function wp_email_restriction_is_configured() {
    return wp_email_restriction()->is_configured();
}

/**
 * Get allowed domain.
 */
function wp_email_restriction_get_domain() {
    $validator = wp_email_restriction()->get_validator();
    return $validator ? $validator->get_allowed_domain() : null;
}

/**
 * Display login form shortcode.
 */
function wp_email_restriction_login_form_shortcode($atts) {
    $atts = shortcode_atts([
        'redirect' => home_url()
    ], $atts);
    
    // Check if plugin is configured
    if (!wp_email_restriction_is_configured()) {
        return '<div class="wp-email-restriction-notice domain-notice"><strong>Setup Required:</strong> Email restriction domain not configured.</div>';
    }
    
    if (wp_email_restriction_is_authenticated()) {
        $user = wp_email_restriction_current_user();
        return '<p>Welcome, ' . esc_html($user['name']) . '! <a href="#" class="restricted-logout-link">Logout</a></p>';
    }
    
    $allowed_domain = wp_email_restriction_get_domain();
    
    ob_start();
    ?>
    <div class="wp-email-restriction-shortcode-form">
        <form id="restricted-login-form-shortcode" class="login-form">
            <p>
                <label for="email-shortcode">Email (<?php echo esc_html($allowed_domain); ?>):</label>
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

/**
 * Redirect to setup page after activation if needed
 */
add_action('admin_init', 'wp_email_restriction_activation_redirect');
function wp_email_restriction_activation_redirect() {
    if (get_transient('wp_email_restriction_activation_redirect')) {
        delete_transient('wp_email_restriction_activation_redirect');
        
        // Only redirect if not configured and user can manage options
        if (current_user_can('manage_options')) {
            $validator = new WP_Email_Restriction_Validator();
            if (!$validator->is_domain_configured()) {
                wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=settings&welcome=1'));
                exit;
            }
        }
    }
}

// Start the plugin
wp_email_restriction();
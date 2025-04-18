<?php
/**
 * Plugin Name: WP Email Restriction
 * Description: Restrict email login to specific domain addresses.
 * Version: 1.1
 * Author: Raouf Rezouali
 * Text Domain: wp-email-restriction
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
    const VERSION = '1.1';

    /**
     * The single instance of the class.
     *
     * @var WP_Email_Restriction|null
     */
    private static $instance = null;

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
    }

    /**
     * Include required files from the /includes folder.
     */
    private function includes() {
        // Admin files
        if (is_admin()) {
            require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/admin/class-admin.php';
            require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/admin/class-email-list-table.php';
        }

        // Core classes (each defined only once in the /includes folder)
        require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/class-database.php';
        require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/class-email-validator.php';
        require_once WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/class-email-manager.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('WP_Email_Restriction', 'uninstall'));

        // Initialize admin
        if (is_admin()) {
            new WP_Email_Restriction_Admin();
        }
    }

    /**
     * Plugin activation: create database tables and update options.
     */
    public function activate() {
        $database = new WP_Email_Restriction_Database();
        $database->create_tables();
        update_option('wp_email_restriction_version', self::VERSION);
    }

    /**
     * Plugin deactivation: remove any transient or temporary data.
     */
    public function deactivate() {
        delete_transient('mcp_email_notice_delete');
    }

    /**
     * Plugin uninstall: drop database tables and delete options.
     */
    public static function uninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        delete_option('wp_email_restriction_version');
        delete_option('wp_email_restriction_settings');
    }
}

/**
 * Helper function to instantiate the plugin.
 *
 * @return WP_Email_Restriction
 */
function wp_email_restriction() {
    return WP_Email_Restriction::instance();
}

// Database migration on plugin load
add_action('plugins_loaded', 'wp_email_restriction_migrate_db');
function wp_email_restriction_migrate_db() {
    $db = new WP_Email_Restriction_Database();
    $db->migrate_data();
}

// Start the plugin.
wp_email_restriction();
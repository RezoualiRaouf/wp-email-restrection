<?php
/*
 * Plugin Name: WP Email Restriction
 * Description: Restrict email login to specific gmail addresses.
 * Version: 1.1
 * Author: Raouf Rezouali
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Include admin settings
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// Initialize database table on activation
function mcp_activate_plugin() {
    // Create the database table
    if (function_exists('mcp_create_db_table')) {
        mcp_create_db_table();
    }
}
register_activation_hook(__FILE__, 'mcp_activate_plugin');

// Cleanup on uninstall
function mcp_uninstall_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_restriction';
    
    // Drop the table
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'mcp_uninstall_plugin');
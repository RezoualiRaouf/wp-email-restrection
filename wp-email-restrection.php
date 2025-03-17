<?php
/*
 * Plugin Name: WP Email Restriction
 * Description: Restrict email login to specific gmail addresses.
 * Version: 1.1
 * Author: Raouf Rezouali
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include admin settings
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// Initialize database table on activation
function mcp_activate_plugin() {
    // Create the database table
    if (function_exists('mcp_create_db_table')) {
        mcp_create_db_table();
    }
    
    // Initialize legacy option for backward compatibility
    if (!get_option('mcp_options')) {
        update_option('mcp_options', ['allowed_emails' => '']);
    }
}
register_activation_hook(__FILE__, 'mcp_activate_plugin');

// Cleanup on uninstall
function mcp_uninstall_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_restriction';
    
    // Drop the table
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Delete options
    delete_option('mcp_options');
}
register_uninstall_hook(__FILE__, 'mcp_uninstall_plugin');

// Check if user email is allowed
function mcp_is_email_allowed($email) {
    if (!str_ends_with($email, '@gmail.com')) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_restriction';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE email = %s",
        $email
    ));
    
    return $count > 0;
}
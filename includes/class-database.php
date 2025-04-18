<?php
/**
 * Database functionality
 *
 * @package WP_Email_Restriction
 */
// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * Database class
 */
class WP_Email_Restriction_Database {
    /**
     * Table name
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'email_restriction';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            password varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY name (name(191))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Migrate data from old table structure to new structure
     */
    public function migrate_data() {
        global $wpdb;
        
        // Check if the table exists
        if (!$this->table_exists()) {
            $this->create_tables();
            return true;
        }
        
        // Check if columns need to be added
        $columns_to_check = ['name', 'password', 'created_at', 'updated_at'];
        $needs_migration = false;
        
        foreach ($columns_to_check as $column) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $this->table_name LIKE '$column'");
            if (empty($column_exists)) {
                $needs_migration = true;
                break;
            }
        }
        
        if ($needs_migration) {
            // Backup old data
            $old_data = $wpdb->get_results("SELECT * FROM $this->table_name");
            
            // Rename old table
            $backup_table = $this->table_name . '_backup_' . time();
            $wpdb->query("RENAME TABLE $this->table_name TO $backup_table");
            
            // Create new table
            $this->create_tables();
            
            // Migrate data
            if (!empty($old_data)) {
                foreach ($old_data as $row) {
                    // Extract time field from old data if exists
                    $time_field = isset($row->time) ? $row->time : current_time('mysql');
                    // Extract name field if exists or use default
                    $name_field = isset($row->name) ? $row->name : 'Migrated User';
                    
                    $wpdb->insert(
                        $this->table_name,
                        array(
                            'name' => $name_field,
                            'email' => $row->email,
                            'password' => wp_hash_password(wp_generate_password(16, true, true)),
                            'created_at' => $time_field
                        ),
                        array('%s', '%s', '%s', '%s')
                    );
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if table exists
     *
     * @return bool
     */
    public function table_exists() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name;
    }
}
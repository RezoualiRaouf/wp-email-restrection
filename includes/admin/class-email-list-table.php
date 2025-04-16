<?php
/**
 * Email List Table Class for WP Email Restriction
 *
 * @package WP_Email_Restriction
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WP_Email_Restriction_List_Table extends WP_List_Table {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'email',
            'plural'   => 'emails',
            'ajax'     => false
        ));
    }
    
    /**
     * Get columns
     * 
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'        => '<input type="checkbox" />',
            'id'        => __('ID', 'wp-email-restriction'),
            'email'     => __('Email Address', 'wp-email-restriction'),
            'date'      => __('Date Added', 'wp-email-restriction'),
        );
    }
    
    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        
        // Column headers
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Set pagination arguments
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Get search term
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Build query
        $offset = ($current_page - 1) * $per_page;
        
        if (!empty($search)) {
            $like_search_term = '%' . $wpdb->esc_like($search) . '%';
            $query = $wpdb->prepare(
                "SELECT email, time, id FROM $table_name WHERE email LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d",
                $like_search_term, $per_page, $offset
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT email, time, id FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            );
        }
        
        // Set items
        $this->items = $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get sortable columns
     * 
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'    => array('id', true),
            'email' => array('email', false),
            'date'  => array('time', false)
        );
    }
    
    /**
     * Column default
     * 
     * @param array $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            case 'email':
                return $item['email'];
            case 'date':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['time']));
            default:
                return print_r($item, true);
        }
    }
    
    /**
     * Column checkbox
     * 
     * @param array $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Column email
     * 
     * @param array $item
     * @return string
     */
    public function column_email($item) {
        $actions = array(
            'edit'   => sprintf('<a href="#" class="edit-email" data-id="%s" data-email="%s">Edit</a>', $item['id'], esc_attr($item['email'])),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Are you sure you want to delete this email?\')">Delete</a>', wp_nonce_url(admin_url('admin.php?page=wp-email-restriction&action=delete&id=' . $item['id']), 'delete_email_' . $item['id']))
        );
        
        return sprintf('%1$s %2$s', $item['email'], $this->row_actions($actions));
    }
    
    /**
     * Get bulk actions
     * 
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'delete' => 'Delete'
        );
    }
    
    /**
     * Process bulk action
     */
    public function process_bulk_action() {
        // Security check
        if ('delete' === $this->current_action()) {
            $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
            
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die('Security check failed');
            }
            
            $delete_ids = isset($_POST['bulk-delete']) ? $_POST['bulk-delete'] : array();
            
            // Loop through IDs and delete
            if (!empty($delete_ids)) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'email_restriction';
                
                foreach ($delete_ids as $id) {
                    $wpdb->delete(
                        $table_name,
                        array('id' => $id),
                        array('%d')
                    );
                }
                
                // Redirect
                wp_redirect(admin_url('admin.php?page=wp-email-restriction&bulk_deleted=1'));
                exit;
            }
        }
    }
}


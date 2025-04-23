<?php
/**
 * Email List Table Class for WP Email Restriction
 *
 * @package WP_Email_Restriction
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WP_Email_Restriction_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'email',
            'plural'   => 'emails',
            'ajax'     => true,
        ]);
    }

    public function get_columns() {
        return [
            'cb'    => '<input type="checkbox" />',
            'id'    => __('ID', 'wp-email-restriction'),
            'email' => __('Email Address', 'wp-email-restriction'),
            'date'  => __('Date Added', 'wp-email-restriction'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'   => ['id', true],
            'date' => ['created_at', false],
        ];
    }

    public function prepare_items() {
        // Set column headers
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
    
        // Process bulk action if any
        $this->process_bulk_action();
        
        // Get pagination parameters
        $per_page = 50;
        $current_page = $this->get_pagenum();
        
        // Get search parameters
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $search_field = isset($_REQUEST['search_field']) ? sanitize_text_field($_REQUEST['search_field']) : 'all';
        
        // Get sorting parameters
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        // Use the Email Manager's paginated method
        $manager = new WP_Email_Restriction_Email_Manager();
        $result = $manager->get_users_paginated(
            $current_page,
            $per_page,
            $search,
            $search_field,
            $orderby,
            $order
        );
    
        $this->items = $result['users'];
        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page'    => $per_page,
            'total_pages' => $result['total_pages']
        ]);
    }

    public function column_default($item, $col) {
        switch ($col) {
            case 'id':
                return $item['id'];
            case 'email':
                return $item['email'];
            case 'date':
                return date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($item['created_at'])
                );
            default:
                return print_r($item, true);
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    public function get_bulk_actions() {
        return [
            'bulk_delete' => __('Delete', 'wp-email-restriction'),
        ];
    }

    public function process_bulk_action() {
        if ($this->current_action() === 'bulk_delete') {
            if (
                ! isset($_REQUEST['_wpnonce']) ||
                ! wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-users')
            ) {
                wp_die(__('Security check failed', 'wp-email-restriction'));
            }
            $ids = isset($_POST['bulk-delete']) ? array_map('intval', $_POST['bulk-delete']) : [];
            if ($ids) {
                global $wpdb;
                $table = $wpdb->prefix . 'email_restriction';
                foreach ($ids as $id) {
                    $wpdb->delete($table, ['id' => $id], ['%d']);
                }
                wp_redirect(admin_url('admin.php?page=wp-email-restriction&bulk_deleted=1'));
                exit;
            }
        }
    }
}

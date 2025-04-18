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
            'ajax'     => false,
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
        global $wpdb;
        $table = $wpdb->prefix . 'email_restriction';

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->process_bulk_action();

        $per_page    = 20;
        $current     = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $offset = ($current - 1) * $per_page;

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql  = $wpdb->prepare(
                "SELECT id, email, created_at FROM {$table}
                 WHERE email LIKE %s
                 ORDER BY id DESC
                 LIMIT %d OFFSET %d",
                $like, $per_page, $offset
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT id, email, created_at
                 FROM {$table}
                 ORDER BY id DESC
                 LIMIT %d OFFSET %d",
                $per_page, $offset
            );
        }

        $this->items = $wpdb->get_results($sql, ARRAY_A);
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

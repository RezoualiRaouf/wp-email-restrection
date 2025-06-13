<?php
/**
 * Simplified Admin functionality
 *
 * @package WP_Email_Restriction
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Email_Restriction_Admin {
    private $email_manager;

    public function __construct() {
        $this->email_manager = new WP_Email_Restriction_Email_Manager();
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        $this->register_ajax_handlers();
    }

    public function add_admin_menu() {
        add_menu_page(
            'WP Email Restriction',
            'Email Restriction',
            'manage_options',
            'wp-email-restriction',
            [$this, 'render_admin_page'],
            'dashicons-email',
            100
        );
    }

    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Single delete
        if (
            isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) &&
            $_GET['action'] === 'delete'
        ) {
            $id = intval($_GET['id']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_email_' . $id)) {
                $res = $this->email_manager->delete_user($id);
                set_transient('mcp_email_notice_delete', $res['message'], 30);
                $tab = sanitize_text_field($_GET['tab'] ?? 'main');
                wp_redirect(admin_url("admin.php?page=wp-email-restriction&tab={$tab}"));
                exit;
            }
        }

        // Bulk delete
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
            if (
                ! isset($_POST['_wpnonce']) ||
                ! wp_verify_nonce($_POST['_wpnonce'], 'bulk-users')
            ) {
                wp_die(__('Security check failed', 'wp-email-restriction'));
            }
            $ids = isset($_POST['bulk-delete']) ? array_map('intval', $_POST['bulk-delete']) : [];
            $count = 0;
            foreach ($ids as $uid) {
                $r = $this->email_manager->delete_user($uid);
                if ($r['status'] === 'success') {
                    $count++;
                }
            }
            set_transient('mcp_email_notice_bulk_delete', sprintf(__('%d users deleted.', 'wp-email-restriction'), $count), 30);
            $tab = sanitize_text_field($_POST['tab'] ?? 'main');
            wp_redirect(admin_url("admin.php?page=wp-email-restriction&tab={$tab}"));
            exit;
        }

        // Add user
        if (
            isset($_POST['add_user'], $_POST['user_nonce']) &&
            wp_verify_nonce($_POST['user_nonce'], 'add_user_nonce')
        ) {
            $name     = sanitize_text_field($_POST['name']);
            $email    = sanitize_email($_POST['email']);
            $password = $_POST['password'] ?? '';
            $r        = $this->email_manager->add_user($name, $email, $password);

            add_settings_error(
                'mcp_email_messages',
                $r['status'],
                $r['message'],
                $r['status'] === 'success' ? 'success' : 'error'
            );
        }

        // Edit user
        if (
            isset($_POST['edit_user'], $_POST['user_edit_nonce']) &&
            wp_verify_nonce($_POST['user_edit_nonce'], 'user_edit_nonce')
        ) {
            $uid      = intval($_POST['user_id']);
            $name     = sanitize_text_field($_POST['name']);
            $email    = sanitize_email($_POST['email']);
            $password = $_POST['password'] ?? '';
            $r        = $this->email_manager->edit_user($uid, $name, $email, $password);
            add_settings_error(
                'mcp_email_messages',
                $r['status'],
                $r['message'],
                $r['status'] === 'success' ? 'success' : 'error'
            );
        }

        // Reset password
        if (
            isset($_POST['reset_password'], $_POST['reset_password_nonce']) &&
            wp_verify_nonce($_POST['reset_password_nonce'], 'reset_password_nonce')
        ) {
            $uid = intval($_POST['user_id_reset']);
            $r   = $this->email_manager->reset_password($uid);
            if ($r['status'] === 'success') {
                set_transient('mcp_temp_password_' . get_current_user_id(), $r['new_password'], 60);
                add_settings_error('mcp_email_messages', 'password_reset', $r['message'], 'success');
            } else {
                add_settings_error('mcp_email_messages', 'password_error', $r['message'], 'error');
            }
        }

        // File upload (CSV/JSON)
        if (
            isset($_POST['upload_file'], $_FILES['uploaded_file'], $_POST['file_upload_nonce']) &&
            wp_verify_nonce($_POST['file_upload_nonce'], 'file_upload_nonce')
        ) {
            $this->process_file_upload();
        }
    }

    private function process_file_upload() {
        $f = $_FILES['uploaded_file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=uploads&upload_status=error'));
            exit;
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'json'], true)) {
            wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=uploads&upload_status=error'));
            exit;
        }
        $cnt = $ext === 'csv'
            ? $this->email_manager->process_csv_file($f['tmp_name'])
            : $this->email_manager->process_json_file($f['tmp_name']);
        $loc = admin_url("admin.php?page=wp-email-restriction&tab=uploads");
        $loc .= $cnt > 0
            ? "&upload_status=success&added={$cnt}"
            : "&upload_status=error";
        wp_redirect($loc);
        exit;
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_wp-email-restriction') {
            return;
        }
        
        wp_enqueue_script(
            'wp-email-restriction-admin', 
            WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/js/admin.js', 
            ['jquery'], 
            WP_EMAIL_RESTRICTION_VERSION, 
            true
        );
        
        $temp = get_transient('mcp_temp_password_' . get_current_user_id());
        
        wp_localize_script('wp-email-restriction-admin', 'wpEmailRestriction', [
            'tempPassword' => $temp ?: '',
            'nonce' => wp_create_nonce('wp_email_restriction_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'deleteConfirm' => __('Are you sure you want to delete this user?', 'wp-email-restriction'),
            'bulkDeleteConfirm' => __('Are you sure you want to delete these users?', 'wp-email-restriction'),
            'initialLoad' => 100,
            'loadMoreSize' => 50
        ]);
        
        if ($temp) {
            delete_transient('mcp_temp_password_' . get_current_user_id());
        }
        
        wp_enqueue_style(
            'wp-email-restriction-admin', 
            WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/css/admin.css', 
            [], 
            WP_EMAIL_RESTRICTION_VERSION
        );
    }

    public function register_ajax_handlers() {
        add_action('wp_ajax_get_restricted_users', [$this, 'ajax_get_users']);
        add_action('wp_ajax_get_restricted_users_paginated', [$this, 'ajax_get_users_paginated']);
        add_action('wp_ajax_delete_restricted_user', [$this, 'ajax_delete_user']);
        add_action('wp_ajax_bulk_delete_restricted_users', [$this, 'ajax_bulk_delete_users']);
    }
    
    public function ajax_get_users_paginated() {
        check_ajax_referer('wp_email_restriction_nonce', 'security');
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        $search = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        $search_field = isset($_POST['search_field']) ? sanitize_text_field($_POST['search_field']) : 'all';
        $orderby = 'id';
        $order = 'DESC';
        
        $result = $this->email_manager->get_users_paginated(
            $page,
            $per_page,
            $search,
            $search_field,
            $orderby,
            $order
        );
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_users() {
        check_ajax_referer('wp_email_restriction_nonce', 'security');
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 25;
        $search = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        $search_field = isset($_POST['search_field']) ? sanitize_text_field($_POST['search_field']) : 'all';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'id';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';
        
        $result = $this->email_manager->get_users_paginated(
            $page,
            $per_page,
            $search,
            $search_field,
            $orderby,
            $order
        );
        
        wp_send_json_success($result);
    }
    
    public function ajax_delete_user() {
        check_ajax_referer('wp_email_restriction_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        $user_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'Invalid user ID']);
            return;
        }
        
        $result = $this->email_manager->delete_user($user_id);
        
        if ($result['status'] === 'success') {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_bulk_delete_users() {
        check_ajax_referer('wp_email_restriction_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        
        if (empty($ids)) {
            wp_send_json_error(['message' => 'No user IDs provided']);
            return;
        }
        
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($ids as $id) {
            $result = $this->email_manager->delete_user($id);
            if ($result['status'] === 'success') {
                $success_count++;
            } else {
                $failed_count++;
            }
        }
        
        wp_send_json_success([
            'deleted' => $success_count,
            'failed' => $failed_count,
            'message' => sprintf(__('%d users deleted successfully.', 'wp-email-restriction'), $success_count)
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-email-restriction'));
        }
        
        $active_tab   = sanitize_text_field($_GET['tab'] ?? 'main');
        $search_term  = sanitize_text_field($_POST['search_term']  ?? '');
        $search_field = sanitize_text_field($_POST['search_field'] ?? 'all');
        
        // Use paginated method for initial load (100 users)
        $user_data = $this->email_manager->get_users_paginated(
            1, // First page
            100, // 100 users initially
            $search_term,
            $search_field,
            'id',
            'DESC'
        );
        
        include WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/admin/views/admin-page.php';
    }

    public function render_users_table($user_data, $search_term, $search_field, $tab) {
        $users = $user_data['users'];
        $shown = $user_data['showing'];
        $total = $user_data['total'];
        $total_pages = $user_data['total_pages'];
        
        // Show transient notices
        if ($n = get_transient('mcp_email_notice_delete')) {
            echo "<div class='notice notice-success'><p>" . esc_html($n) . "</p></div>";
            delete_transient('mcp_email_notice_delete');
        }
        if ($n = get_transient('mcp_email_notice_bulk_delete')) {
            echo "<div class='notice notice-success'><p>" . esc_html($n) . "</p></div>";
            delete_transient('mcp_email_notice_bulk_delete');
        }
        
        if ($users) : ?>
            <form method="post" action="">
                <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
                <?php wp_nonce_field('bulk-users'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php _e('Bulk Actions', 'wp-email-restriction'); ?></option>
                            <option value="bulk_delete"><?php _e('Delete'); ?></option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="<?php _e('Apply'); ?>">
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf(__('Showing %d of %d users', 'wp-email-restriction'), $shown, $total); ?></span>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" /></th>
                            <th><?php _e('ID', 'wp-email-restriction'); ?></th>
                            <th><?php _e('Name', 'wp-email-restriction'); ?></th>
                            <th><?php _e('Email', 'wp-email-restriction'); ?></th>
                            <th><?php _e('Created At', 'wp-email-restriction'); ?></th>
                            <th><?php _e('Actions', 'wp-email-restriction'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u) : ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="bulk-delete[]" value="<?php echo esc_attr($u->id); ?>">
                                </th>
                                <td><?php echo esc_html($u->id); ?></td>
                                <td><strong><?php echo esc_html($u->name); ?></strong></td>
                                <td><?php echo esc_html($u->email); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($u->created_at))); ?></td>
                                <td class="actions">
                                    <button type="button" class="button edit-user"
                                        data-id="<?php echo esc_attr($u->id); ?>"
                                        data-name="<?php echo esc_attr($u->name); ?>"
                                        data-email="<?php echo esc_attr($u->email); ?>"
                                        data-tab="<?php echo esc_attr($tab); ?>"
                                        title="Edit user: <?php echo esc_attr($u->name); ?>">
                                        <?php _e('Edit', 'wp-email-restriction'); ?>
                                    </button>
                                    <button type="button" class="button delete-user" 
                                        data-id="<?php echo esc_attr($u->id); ?>"
                                        title="Delete user: <?php echo esc_attr($u->name); ?>">
                                        <?php _e('Delete', 'wp-email-restriction'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
            <!-- Load More Button -->
            <?php if ($shown < $total) : ?>
                <div class="load-more-container" style="text-align: center; margin: 20px 0;">
                    <button type="button" id="load-more-users" class="button button-primary">
                        <?php printf(__('Load More Users (%d remaining)', 'wp-email-restriction'), $total - $shown); ?>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Initialize pagination data for JavaScript -->
            <script>
            if (typeof window.wpEmailRestrictionPagination === 'undefined') {
                window.wpEmailRestrictionPagination = {
                    loadedUsers: <?php echo $shown; ?>,
                    totalUsers: <?php echo $total; ?>,
                    currentPage: 1
                };
            }
            </script>
            
        <?php else : ?>
            <div class="no-users-found">
                <p>
                    <?php if ($search_term) : ?>
                        <?php _e('No users found matching your search.', 'wp-email-restriction'); ?>
                        <br><br>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-email-restriction&tab=main')); ?>" 
                           class="button"><?php _e('Clear Search', 'wp-email-restriction'); ?></a>
                    <?php else : ?>
                        <?php _e('No users found. Start by adding some users!', 'wp-email-restriction'); ?>
                        <br><br>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-email-restriction&tab=settings')); ?>" 
                           class="button button-primary"><?php _e('Add Users', 'wp-email-restriction'); ?></a>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif;
    }
}
<?php
/**
 * Admin functionality
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
        wp_enqueue_script('wp-email-restriction-admin', WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WP_EMAIL_RESTRICTION_VERSION, true);
        $temp = get_transient('mcp_temp_password_' . get_current_user_id());
        wp_localize_script('wp-email-restriction-admin', 'wpEmailRestriction', ['tempPassword' => $temp ?: '']);
        if ($temp) {
            delete_transient('mcp_temp_password_' . get_current_user_id());
        }
        wp_enqueue_style('wp-email-restriction-admin', WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/css/admin.css', [], WP_EMAIL_RESTRICTION_VERSION);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-email-restriction'));
        }
        $active_tab   = sanitize_text_field($_GET['tab'] ?? 'main');
        $search_term  = sanitize_text_field($_POST['search_term']  ?? '');
        $search_field = sanitize_text_field($_POST['search_field'] ?? 'all');
        $user_data    = $this->email_manager->get_users($search_term, $search_field);
        include WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/admin/views/admin-page.php';
    }

    public function render_users_table($user_data, $search_term, $search_field, $tab) {
        $users    = $user_data['users'];
        $shown    = $user_data['showing'];
        $total    = $user_data['total'];
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
                                <th class="check-column"><input type="checkbox" name="bulk-delete[]" value="<?php echo esc_attr($u->id); ?>"></th>
                                <td><?php echo esc_html($u->id); ?></td>
                                <td><?php echo esc_html($u->name); ?></td>
                                <td><?php echo esc_html($u->email); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($u->created_at))); ?></td>
                                <td>
                                    <button type="button" class="button edit-user"
                                        data-id="<?php echo esc_attr($u->id); ?>"
                                        data-name="<?php echo esc_attr($u->name); ?>"
                                        data-email="<?php echo esc_attr($u->email); ?>"
                                        data-tab="<?php echo esc_attr($tab); ?>">
                                        <?php _e('Edit', 'wp-email-restriction'); ?>
                                    </button>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=wp-email-restriction&tab={$tab}&action=delete&id={$u->id}"), 'delete_email_' . $u->id)); ?>"
                                       class="button"
                                       onclick="return confirm('<?php _e('Are you sure?', 'wp-email-restriction'); ?>')">
                                        <?php _e('Delete', 'wp-email-restriction'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php else : ?>
            <p><?php _e('No users found.', 'wp-email-restriction'); ?></p>
        <?php endif;
    }
}

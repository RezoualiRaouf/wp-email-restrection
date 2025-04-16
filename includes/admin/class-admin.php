<?php
/**
 * Admin functionality
 *
 * @package WP_Email_Restriction
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * Admin class
 */
class WP_Email_Restriction_Admin {
    /**
     * Email manager instance
     */
    private $email_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->email_manager = new WP_Email_Restriction_Email_Manager();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'WP Email Restriction',
            'Email Restriction',
            'manage_options',
            'wp-email-restriction',
            array($this, 'render_admin_page'),
            'dashicons-email',
            100
        );
    }
    
    /**
     * Handle admin actions
     */
    public function handle_actions() {
        // Handle email deletion
        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
            $id = intval($_GET['id']);
            
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_email_' . $id)) {
                $result = $this->email_manager->delete_email($id);
                
                // Set transient for notification
                set_transient('mcp_email_notice_delete', $result['message'], 30);
                
                // Redirect to maintain the current tab
                $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'main';
                wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=' . $tab));
                exit;
            }
        }
        
        // Handle file uploads
        if (isset($_POST['upload_file']) && isset($_FILES['uploaded_file']) && isset($_POST['file_upload_nonce']) && wp_verify_nonce($_POST['file_upload_nonce'], 'file_upload_nonce')) {
            $this->process_file_upload();
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ('toplevel_page_wp-email-restriction' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'wp-email-restriction-admin',
            WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_EMAIL_RESTRICTION_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wp-email-restriction-admin',
            WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_EMAIL_RESTRICTION_VERSION
        );
    }
    
    /**
     * Process file upload
     */
    private function process_file_upload() {
        // Get uploaded file
        $file = $_FILES['uploaded_file'];
        
        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=uploads&upload_status=error'));
            exit;
        }
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check if file extension is allowed
        if (!in_array($file_ext, array('csv', 'json'))) {
            wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=uploads&upload_status=error'));
            exit;
        }
        
        // Process file based on extension
        $added_count = 0;
        
        if ($file_ext === 'csv') {
            $added_count = $this->process_csv_file($file['tmp_name']);
        } else if ($file_ext === 'json') {
            $added_count = $this->process_json_file($file['tmp_name']);
        }
        
        // Redirect with status
        if ($added_count > 0) {
            wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=uploads&upload_status=success&added=' . $added_count));
        } else {
            wp_redirect(admin_url('admin.php?page=wp-email-restriction&tab=uploads&upload_status=error'));
        }
        exit;
    }
    
    /**
     * Process CSV file
     * 
     * @param string $file_path
     * @return int
     */
    private function process_csv_file($file_path) {
        $added_count = 0;
        
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (isset($data[0])) {
                    $email = sanitize_email($data[0]);
                    $result = $this->email_manager->add_email($email);
                    
                    if ($result['status'] === 'success') {
                        $added_count++;
                    }
                }
            }
            fclose($handle);
        }
        
        return $added_count;
    }
    
    /**
     * Process JSON file
     * 
     * @param string $file_path
     * @return int
     */
    private function process_json_file($file_path) {
        $added_count = 0;
        $json_data = file_get_contents($file_path);
        $emails = json_decode($json_data, true);
        
        if (is_array($emails)) {
            foreach ($emails as $email) {
                if (is_string($email)) {
                    $email = sanitize_email($email);
                    $result = $this->email_manager->add_email($email);
                    
                    if ($result['status'] === 'success') {
                        $added_count++;
                    }
                } else if (is_array($email) && isset($email['email'])) {
                    $email_address = sanitize_email($email['email']);
                    $result = $this->email_manager->add_email($email_address);
                    
                    if ($result['status'] === 'success') {
                        $added_count++;
                    }
                }
            }
        }
        
        return $added_count;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Get current tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'main';
        
        // Process form submissions
        $this->process_form_submissions();
        
        // Get email data
        $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        $email_data = $this->email_manager->get_emails($search_term);
        
        // Include the view
        include WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/admin/views/admin-page.php';
    }
    
    /**
     * Process form submissions
     */
    private function process_form_submissions() {
        // Handle add email form
        if (isset($_POST['add_email']) && isset($_POST['email_nonce']) && wp_verify_nonce($_POST['email_nonce'], 'add_email_nonce')) {
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            $result = $this->email_manager->add_email($email);
            
            if ($result['status'] === 'success') {
                add_settings_error('mcp_email_messages', 'mcp_email_add_success', $result['message'], 'success');
            } else {
                add_settings_error('mcp_email_messages', 'mcp_email_add_error', $result['message'], 'error');
            }
        }
        
        // Handle edit email form
        if (isset($_POST['edit_email']) && isset($_POST['email_edit_nonce']) && wp_verify_nonce($_POST['email_edit_nonce'], 'email_edit_nonce')) {
            $email_id = isset($_POST['email_id']) ? intval($_POST['email_id']) : 0;
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            $result = $this->email_manager->edit_email($email_id, $email);
            
            if ($result['status'] === 'success') {
                add_settings_error('mcp_email_messages', 'mcp_email_updated', $result['message'], 'success');
            } else {
                add_settings_error('mcp_email_messages', 'mcp_email_error', $result['message'], 'error');
            }
        }
    }
    
    /**
     * Render emails table
     * 
     * @param array $email_data
     * @param string $search_term
     * @param string $tab
     */
    public function render_emails_table($email_data, $search_term, $tab) {
        $emails = $email_data['emails'];
        $showing_count = $email_data['showing'];
        $total_emails = $email_data['total'];
        
        // Show delete success or error notice if set
        if ($notice = get_transient('mcp_email_notice_delete')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
            delete_transient('mcp_email_notice_delete');
        }
        
        if (!empty($emails)) : ?>
            <!-- Display count of emails being shown -->
            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo sprintf('Showing %d of %d emails', $showing_count, $total_emails); ?></span>
                </div>
            </div>
            
            <div class="emails-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-counter">ID</th>
                            <th class="column-email">Email Address</th>
                            <th class="column-date">Date Added</th>
                            <th class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($emails as $email) : 
                        ?>
                            <tr>
                                <td><?php echo esc_html($counter++); ?></td>
                                <td class="email-data"><?php echo esc_html($email->email); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($email->time))); ?></td>
                                <td>
                                    <button type="button" class="button edit-email" data-id="<?php echo esc_attr($email->id); ?>" data-email="<?php echo esc_attr($email->email); ?>" data-tab="<?php echo esc_attr($tab); ?>">Edit</button>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-email-restriction&tab=' . $tab . '&action=delete&id=' . $email->id), 'delete_email_' . $email->id); ?>" class="button" onclick="return confirm('Are you sure you want to delete this email?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
                <p>No emails have been saved yet.</p>
            <?php endif;
            
        }
}

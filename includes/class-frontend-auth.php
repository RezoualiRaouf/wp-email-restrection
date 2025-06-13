<?php
/**
 * Enhanced Frontend Authentication with Preview Mode
 *
 * @package WP_Email_Restriction
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Email_Restriction_Frontend_Auth {
    private $email_manager;
    private $validator;
    
    public function __construct() {
        $this->email_manager = new WP_Email_Restriction_Email_Manager();
        $this->validator = new WP_Email_Restriction_Validator();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Hook into WordPress initialization
        add_action('init', [$this, 'start_session']);
        add_action('template_redirect', [$this, 'check_access']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // Handle login form submission
        add_action('wp_ajax_nopriv_restricted_login', [$this, 'handle_login']);
        add_action('wp_ajax_restricted_login', [$this, 'handle_login']);
        
        // Handle logout
        add_action('wp_ajax_restricted_logout', [$this, 'handle_logout']);
        add_action('wp_ajax_nopriv_restricted_logout', [$this, 'handle_logout']);
        
        // Add login/logout to menu
        add_filter('wp_nav_menu_items', [$this, 'add_login_logout_link'], 10, 2);
        
        // Handle export functionality
        add_action('admin_init', [$this, 'handle_export']);
    }
    
    public function start_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    public function check_access() {
        // Don't restrict admin areas, AJAX calls
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // PREVIEW MODE: Allow admins to see login page even when logged in
        if ($this->is_preview_mode() && $this->is_admin_user()) {
            $this->show_login_page();
            exit;
        }
        
        // Don't restrict login page itself
        if ($this->is_login_page()) {
            return;
        }
        
        // Allow logged-in WordPress admin users to access the site
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return;
        }
        
        // Allow access to WordPress login/register pages
        global $pagenow;
        if (in_array($pagenow, ['wp-login.php', 'wp-register.php']) || 
            strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false ||
            strpos($_SERVER['REQUEST_URI'], 'wp-admin') !== false) {
            return;
        }
        
        // Allow access to specific pages (customize as needed)
        $allowed_pages = apply_filters('wp_email_restriction_allowed_pages', [
            'wp-login.php',
            'wp-register.php',
            'wp-admin'
        ]);
        
        $current_page = basename($_SERVER['REQUEST_URI']);
        foreach ($allowed_pages as $allowed_page) {
            if (strpos($_SERVER['REQUEST_URI'], $allowed_page) !== false) {
                return;
            }
        }
        
        // Check if user is authenticated through our system
        if (!$this->is_user_authenticated()) {
            $this->show_login_page();
            exit;
        }
    }
    
    /**
     * Check if we're in preview mode
     */
    private function is_preview_mode() {
        return isset($_GET['restricted_login']) && $_GET['restricted_login'] === 'preview';
    }
    
    /**
     * Check if current user is WordPress admin
     */
    private function is_admin_user() {
        return is_user_logged_in() && current_user_can('manage_options');
    }
    
    private function is_login_page() {
        return isset($_GET['restricted_login']) || 
               (isset($_POST['action']) && $_POST['action'] === 'restricted_login');
    }
    
    public function is_user_authenticated() {
        // Admin users are always considered authenticated
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return true;
        }
        
        return isset($_SESSION['restricted_user_authenticated']) && 
               $_SESSION['restricted_user_authenticated'] === true &&
               isset($_SESSION['restricted_user_email']);
    }
    
    public function show_login_page() {
        $login_settings = get_option('wp_email_restriction_login_settings', [
            'title' => 'Access Restricted',
            'message' => 'Please login with your authorized email address to access this website.',
            'logo_url' => '',
            'background_color' => '#f1f1f1',
            'form_background' => '#ffffff',
            'primary_color' => '#0073aa',
            'text_color' => '#23282d'
        ]);
        
        // Get current URL for redirect after login
        $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                       "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        // Remove preview parameter from redirect URL
        $redirect_url = remove_query_arg('restricted_login', $redirect_url);
        
        // 🆕 Check if this is preview mode
        $is_preview_mode = $this->is_preview_mode();
        $is_admin = $this->is_admin_user();
        
        include WP_EMAIL_RESTRICTION_PLUGIN_DIR . 'includes/frontend/login-page.php';
    }
    
    public function handle_login() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'restricted_login_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $redirect_url = esc_url($_POST['redirect_url'] ?? home_url());
        $is_preview = $_POST['is_preview'] ?? false;
        
        // 🆕 PREVIEW MODE: Allow WordPress admin login
        if ($is_preview && $this->is_admin_user()) {
            $wp_user = wp_get_current_user();
            
            // Check if admin is trying to login with their WP credentials
            if ($email === $wp_user->user_email && wp_check_password($password, $wp_user->user_pass)) {
                // Set session for preview
                $_SESSION['restricted_user_authenticated'] = true;
                $_SESSION['restricted_user_email'] = $wp_user->user_email;
                $_SESSION['restricted_user_name'] = $wp_user->display_name;
                $_SESSION['restricted_user_id'] = 'wp_admin_' . $wp_user->ID;
                $_SESSION['restricted_preview_mode'] = true;
                
                wp_send_json_success([
                    'message' => 'Preview login successful (WordPress Admin)',
                    'redirect_url' => $redirect_url
                ]);
                return;
            }
        }
        
        // Validate email format and domain
        if (!$this->validator->is_valid($email)) {
            wp_send_json_error([
                'message' => 'Invalid email domain. Only ' . $this->validator->get_allowed_domain() . ' emails are allowed.'
            ]);
            return;
        }
        
        // Check if user exists in database
        $user = $this->get_user_by_email($email);
        if (!$user) {
            wp_send_json_error(['message' => 'Email not authorized for access.']);
            return;
        }
        
        // Verify password
        if (!wp_check_password($password, $user->password)) {
            wp_send_json_error(['message' => 'Invalid password.']);
            return;
        }
        
        // Set session variables
        $_SESSION['restricted_user_authenticated'] = true;
        $_SESSION['restricted_user_email'] = $email;
        $_SESSION['restricted_user_name'] = $user->name;
        $_SESSION['restricted_user_id'] = $user->id;
        $_SESSION['restricted_preview_mode'] = $is_preview;
        
        wp_send_json_success([
            'message' => 'Login successful',
            'redirect_url' => $redirect_url
        ]);
    }
    
    public function handle_logout() {
        // Clear session
        unset($_SESSION['restricted_user_authenticated']);
        unset($_SESSION['restricted_user_email']);
        unset($_SESSION['restricted_user_name']);
        unset($_SESSION['restricted_user_id']);
        unset($_SESSION['restricted_preview_mode']);
        
        if (wp_doing_ajax()) {
            wp_send_json_success(['message' => 'Logged out successfully']);
        } else {
            wp_redirect(home_url());
            exit;
        }
    }
    
    private function get_user_by_email($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s",
            $email
        ));
    }
    
    public function enqueue_frontend_scripts() {
        if ($this->is_login_page() || !$this->is_user_authenticated()) {
            wp_enqueue_script(
                'wp-email-restriction-frontend',
                WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                WP_EMAIL_RESTRICTION_VERSION,
                true
            );
            
            wp_localize_script('wp-email-restriction-frontend', 'wpEmailRestrictionFrontend', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('restricted_login_nonce'),
                'logout_nonce' => wp_create_nonce('restricted_logout_nonce'),
                'is_preview' => $this->is_preview_mode(),
                'is_admin' => $this->is_admin_user()
            ]);
            
            wp_enqueue_style(
                'wp-email-restriction-frontend',
                WP_EMAIL_RESTRICTION_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                WP_EMAIL_RESTRICTION_VERSION
            );
        }
    }
    
    public function add_login_logout_link($items, $args) {
        // Only add to primary menu (customize as needed)
        if ($args->theme_location !== 'primary') {
            return $items;
        }
        
        if ($this->is_user_authenticated() && !is_user_logged_in()) {
            $user_name = $_SESSION['restricted_user_name'] ?? 'User';
            $is_preview = $_SESSION['restricted_preview_mode'] ?? false;
            
            $items .= '<li class="menu-item restricted-user-info">';
            $items .= '<span>Welcome, ' . esc_html($user_name);
            if ($is_preview) {
                $items .= ' <em>(Preview Mode)</em>';
            }
            $items .= '</span>';
            $items .= ' | <a href="#" class="restricted-logout-link">Logout</a>';
            $items .= '</li>';
        }
        
        return $items;
    }
    
    public function get_current_user() {
        // If WordPress admin is logged in, return their info
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $wp_user = wp_get_current_user();
            return [
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'type' => 'wp_admin'
            ];
        }
        
        if (!$this->is_user_authenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['restricted_user_id'],
            'name' => $_SESSION['restricted_user_name'],
            'email' => $_SESSION['restricted_user_email'],
            'type' => $_SESSION['restricted_preview_mode'] ? 'preview' : 'restricted'
        ];
    }
    
    public function handle_export() {
        if (isset($_GET['action']) && $_GET['action'] === 'export_users' && 
            wp_verify_nonce($_GET['_wpnonce'], 'export_users') && 
            current_user_can('manage_options')) {
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'email_restriction';
            $users = $wpdb->get_results("SELECT id, name, email, created_at FROM $table_name ORDER BY id DESC");
            
            if (empty($users)) {
                wp_die('No users found to export.');
            }
            
            $filename = 'email-restriction-users-' . date('Y-m-d-H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($output, ['ID', 'Name', 'Email', 'Created At']);
            
            // Add user data
            foreach ($users as $user) {
                fputcsv($output, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->created_at
                ]);
            }
            
            fclose($output);
            exit;
        }
    }
}
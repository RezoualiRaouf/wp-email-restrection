<?php
/**
 * Frontend Authentication functionality
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
        
        // Disable WordPress default login for non-admin users
        add_action('login_init', [$this, 'disable_default_login']);
    }
    
    public function start_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    public function check_access() {
        // Don't restrict admin areas or AJAX calls
        if (is_admin() || wp_doing_ajax() || $this->is_login_page()) {
            return;
        }
        
        // Allow access to specific pages (customize as needed)
        $allowed_pages = apply_filters('wp_email_restriction_allowed_pages', [
            'wp-login.php',
            'wp-register.php'
        ]);
        
        $current_page = basename($_SERVER['REQUEST_URI']);
        if (in_array($current_page, $allowed_pages)) {
            return;
        }
        
        // Check if user is authenticated
        if (!$this->is_user_authenticated()) {
            $this->show_login_page();
            exit;
        }
    }
    
    private function is_login_page() {
        return isset($_GET['restricted_login']) || 
               (isset($_POST['action']) && $_POST['action'] === 'restricted_login');
    }
    
    public function is_user_authenticated() {
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
                'logout_nonce' => wp_create_nonce('restricted_logout_nonce')
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
        
        if ($this->is_user_authenticated()) {
            $user_name = $_SESSION['restricted_user_name'] ?? 'User';
            $items .= '<li class="menu-item restricted-user-info">';
            $items .= '<span>Welcome, ' . esc_html($user_name) . '</span>';
            $items .= ' | <a href="#" class="restricted-logout-link">Logout</a>';
            $items .= '</li>';
        }
        
        return $items;
    }
    
    public function disable_default_login() {
        // Redirect admin login attempts to our custom login
        if (!current_user_can('manage_options')) {
            wp_redirect(home_url('?restricted_login=1'));
            exit;
        }
    }
    
    public function get_current_user() {
        if (!$this->is_user_authenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['restricted_user_id'],
            'name' => $_SESSION['restricted_user_name'],
            'email' => $_SESSION['restricted_user_email']
        ];
    }
}
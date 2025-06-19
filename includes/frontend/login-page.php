<?php
/**
 * Enhanced frontend login page template with dynamic domain
 * 
 * @package WP_Email_Restriction
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($login_settings['title']); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        :root {
            --primary-color: <?php echo esc_attr($login_settings['primary_color']); ?>;
            --background-color: <?php echo esc_attr($login_settings['background_color']); ?>;
            --form-background: <?php echo esc_attr($login_settings['form_background']); ?>;
            --text-color: <?php echo esc_attr($login_settings['text_color']); ?>;
        }
    </style>
</head>
<body class="restricted-login-page">
    
    <div class="login-container">
        <div class="login-form-wrapper">
            <?php if (!empty($login_settings['logo_url'])) : ?>
                <div class="login-logo">
                    <img src="<?php echo esc_url($login_settings['logo_url']); ?>" alt="<?php bloginfo('name'); ?>">
                </div>
            <?php endif; ?>
            
            <div class="login-header">
                <h1><?php echo esc_html($login_settings['title']); ?></h1>
                <p><?php echo esc_html($login_settings['message']); ?></p>
            </div>
            
            <form id="restricted-login-form" class="login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="your.name<?php echo esc_attr($allowed_domain); ?>"
                           <?php if ($is_preview_mode && $is_admin) : ?>
                           value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                           <?php endif; ?>>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           <?php if ($is_preview_mode && $is_admin) : ?>
                           placeholder="Enter your WordPress password"
                           <?php else : ?>
                           placeholder="Enter your password"
                           <?php endif; ?>>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="login-button">
                        <span class="button-text">
                            <?php if ($is_preview_mode && $is_admin) : ?>
                                Test Login
                            <?php else : ?>
                                Login
                            <?php endif; ?>
                        </span>
                        <span class="button-spinner" style="display: none;">
                            <svg width="20" height="20" viewBox="0 0 50 50">
                                <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.416" stroke-dashoffset="31.416">
                                    <animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416" repeatCount="indefinite"/>
                                    <animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416" repeatCount="indefinite"/>
                                </circle>
                            </svg>
                        </span>
                    </button>
                </div>
                
                <input type="hidden" name="action" value="restricted_login">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('restricted_login_nonce'); ?>">
                <input type="hidden" name="redirect_url" value="<?php echo esc_attr($redirect_url); ?>">
                <input type="hidden" name="is_preview" value="<?php echo $is_preview_mode ? '1' : '0'; ?>">
            </form>
            
            <div id="login-message" class="login-message" style="display: none;"></div>
            
            <div class="login-footer">
                <p>Access restricted to authorized <?php echo esc_html($allowed_domain); ?> email addresses only.</p>
                <?php if ($is_preview_mode) : ?>
                    <p><a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=login-settings'); ?>">&larr; Back to Login Settings</a></p>
                <?php else : ?>
                    <p><a href="<?php echo home_url(); ?>">&larr; Back to <?php bloginfo('name'); ?></a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var emailField = document.getElementById('email');
            var passwordField = document.getElementById('password');
            
            <?php if ($is_preview_mode && $is_admin) : ?>
                if (passwordField) {
                    passwordField.focus();
                }
            <?php else : ?>
                if (emailField && !emailField.value) {
                    emailField.focus();
                }
            <?php endif; ?>
        });
        
        if (typeof jQuery === 'undefined') {
            document.getElementById('restricted-login-form').innerHTML += 
                '<div style="color: red; text-align: center; margin-top: 15px;">JavaScript is required for login functionality.</div>';
        }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
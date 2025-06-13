<?php
/**
 * Enhanced frontend login page template with preview mode
 * 
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
        
        /* Prevent caching issues */
        body.restricted-login-page * {
            box-sizing: border-box;
        }
        
        /* Preview Mode Banner */
        .preview-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 10px 20px;
            text-align: center;
            font-weight: 600;
            z-index: 10000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .preview-banner a {
            color: white;
            text-decoration: underline;
            font-weight: bold;
        }
        
        .preview-banner a:hover {
            text-decoration: none;
        }
        
        body.has-preview-banner {
            padding-top: 50px;
        }
        
    </style>
</head>
<body class="restricted-login-page <?php echo $is_preview_mode ? 'has-preview-banner' : ''; ?>">
    
    <?php if ($is_preview_mode && $is_admin) : ?>
        <div class="preview-banner">
            <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=login-settings'); ?>">← Back to Settings</a>
        </div>
    <?php endif; ?>
    
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
            
            <?php if ($is_preview_mode && $is_admin) : ?>
                <div class="test-user-hint">
                    <p>Use your WordPress admin credentials<br>
                    Email: <code><?php echo wp_get_current_user()->user_email; ?></code><br>
                    Password: <em>Your WordPress password</em></p>        
                </div>
            <?php endif; ?>
            
            <form id="restricted-login-form" class="login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="your.name@univ-bouira.dz"
                           <?php if ($is_preview_mode && $is_admin) : ?>
                           value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                           <?php endif; ?>>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="login-button">
                        <span class="button-text">Login</span>
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
                <p>Access restricted to authorized @univ-bouira.dz email addresses only.</p>
                <?php if ($is_preview_mode) : ?>
                    <p><a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=login-settings'); ?>">&larr; Back to Login Settings</a></p>
                <?php else : ?>
                    <p><a href="<?php echo home_url(); ?>">&larr; Back to <?php bloginfo('name'); ?></a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus email field for better UX
        document.addEventListener('DOMContentLoaded', function() {
            var emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
        });
        
        // Fallback for browsers with disabled JavaScript
        if (typeof jQuery === 'undefined') {
            document.getElementById('restricted-login-form').innerHTML += 
                '<div style="color: red; text-align: center; margin-top: 15px;">JavaScript is required for login functionality.</div>';
        }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
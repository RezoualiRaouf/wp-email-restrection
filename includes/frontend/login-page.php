<?php
/**
 * Enhanced frontend login page template with preview mode
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
        
        /* 🆕 Preview Mode Banner */
        .preview-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 12px 20px;
            text-align: center;
            font-weight: 600;
            z-index: 10000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            animation: slideDown 0.5s ease-out;
        }
        
        .preview-banner .banner-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .preview-banner .banner-message {
            flex: 1;
            text-align: center;
        }
        
        .preview-banner a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
            background: rgba(255,255,255,0.2);
            transition: background 0.3s ease;
        }
        
        .preview-banner a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        body.has-preview-banner {
            padding-top: 60px;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* 🆕 Admin Testing Instructions */
        .test-user-hint {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            margin: 15px 30px;
            border-radius: 8px;
            text-align: center;
            animation: pulseGlow 2s ease-in-out infinite alternate;
        }
        
        .test-user-hint h4 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .test-user-hint p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
        .test-user-hint code {
            background: rgba(255,255,255,0.2);
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            color: #fff;
            font-weight: bold;
        }
        
        @keyframes pulseGlow {
            from { 
                box-shadow: 0 0 15px rgba(102, 126, 234, 0.4);
                transform: scale(1);
            }
            to { 
                box-shadow: 0 0 25px rgba(118, 75, 162, 0.6);
                transform: scale(1.02);
            }
        }
        
        /* Mobile responsive for preview banner */
        @media (max-width: 768px) {
            .preview-banner .banner-content {
                flex-direction: column;
                gap: 10px;
            }
            
            .preview-banner .banner-message {
                font-size: 14px;
            }
            
            body.has-preview-banner {
                padding-top: 80px;
            }
        }
    </style>
</head>
<body class="restricted-login-page <?php echo $is_preview_mode ? 'has-preview-banner' : ''; ?>">
    
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
                           placeholder="your.name@univ-bouira.dz"
                           <?php if ($is_preview_mode && $is_admin) : ?>
                           value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                           <?php endif; ?>>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           <?php if ($is_preview_mode && $is_admin) : ?>
                           placeholder="Enter your password"
                           <?php endif; ?>>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="login-button">
                        <span class="button-text">
                            <?php if ($is_preview_mode && $is_admin) : ?>
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
        // Auto-focus appropriate field for better UX
        document.addEventListener('DOMContentLoaded', function() {
            var emailField = document.getElementById('email');
            var passwordField = document.getElementById('password');
            
            <?php if ($is_preview_mode && $is_admin) : ?>
                // In preview mode, focus password field since email is pre-filled
                if (passwordField) {
                    passwordField.focus();
                }
            <?php else : ?>
                // Normal mode, focus email field
                if (emailField && !emailField.value) {
                    emailField.focus();
                }
            <?php endif; ?>
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
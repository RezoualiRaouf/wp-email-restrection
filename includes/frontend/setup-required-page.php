<?php
/**
 * Setup required page template - shown when domain is not configured
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
    <title>Setup Required - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="setup-required-page">
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <div class="setup-icon">⚙️</div>
                <h1>Setup Required</h1>
                <p>Plugin configuration needed</p>
            </div>
            
            <div class="setup-content">
                <h2>Email Restriction Not Configured</h2>
                <p>The WP Email Restriction plugin needs to be configured before it can protect your website.</p>
                
                <div class="warning-box">
                    <span class="warning-icon">⚠️</span>
                    <p><strong>Access Control Disabled:</strong> Your website is currently accessible to everyone until an admin configures the allowed email domain.</p>
                </div>
                
                <div class="setup-instructions">
                    <h3>Administrator Setup Required:</h3>
                    <ol>
                        <li>Log in to WordPress admin dashboard</li>
                        <li>Navigate to <code>Email Restriction</code> in the admin menu</li>
                        <li>Go to the <code>Settings</code> tab</li>
                        <li>Enter your organization's email domain (e.g., <code>company.com</code>)</li>
                        <li>Save the configuration</li>
                    </ol>
                </div>
                
                <?php if (is_user_logged_in() && current_user_can('manage_options')) : ?>
                    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=settings'); ?>" class="admin-button">
                        Configure Plugin Now
                    </a>
                <?php else : ?>
                    <a href="<?php echo wp_login_url(); ?>" class="admin-button">
                        Admin Login
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="setup-footer">
                <p>WP Email Restriction Plugin</p>
                <p><a href="<?php echo home_url(); ?>">&larr; Back to <?php bloginfo('name'); ?></a></p>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>

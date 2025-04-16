<?php
/**
 * Admin page view
 *
 * @package WP_Email_Restriction
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>WP Email Restriction</h1>
    <?php settings_errors('mcp_email_messages'); ?>
    
    <!-- Tabs Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=main'); ?>" class="nav-tab <?php echo $active_tab == 'main' ? 'nav-tab-active' : ''; ?>">Main</a>
        <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=settings'); ?>" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
        <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=uploads'); ?>" class="nav-tab <?php echo $active_tab == 'uploads' ? 'nav-tab-active' : ''; ?>">Uploads</a>
    </h2>
    
    <!-- Main Tab Content -->
    <div id="tab-main" class="tab-content" <?php echo $active_tab != 'main' ? 'style="display:none;"' : ''; ?>>
        <!-- Search Form -->
        <div class="card">
            <h2>Search Emails</h2>
            <form method="post" action="" id="search-form">
                <?php wp_nonce_field('search_emails_nonce', 'search_nonce'); ?>
                <input type="hidden" name="tab" value="main">
                <div class="search-box">
                    <input type="text" name="search_term" id="search_term" value="<?php echo esc_attr($search_term); ?>" class="regular-text" placeholder="Search emails...">
                    <?php submit_button('Search', 'secondary', 'search_emails', false); ?>
                    <?php if (!empty($search_term)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-email-restriction&tab=main')); ?>" class="button">Refresh List</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Emails Table -->
        <h2>Allowed Emails</h2>
        <?php $this->render_emails_table($email_data, $search_term, 'main'); ?>
    </div>
    
    <!-- Settings Tab Content -->
    <div id="tab-settings" class="tab-content" <?php echo $active_tab != 'settings' ? 'style="display:none;"' : ''; ?>>
        <div class="card">
            <h2>Users Settings</h2>
            <!-- Add Email Form -->
            <div class="card">
                <h2>Add New User</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('add_email_nonce', 'email_nonce'); ?>
                    <input type="hidden" name="tab" value="settings">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="email">Email Address</label></th>
                            <td>
                                <input type="email" name="email" id="email" class="regular-text" placeholder="example@univ-bouira.dz" required>
                                <p class="description">Only @univ-bouira.dz addresses are allowed.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Add Email', 'primary', 'add_email'); ?>
                </form>
            </div>    
        </div>

        <h2>Allowed Emails</h2>
        <?php $this->render_emails_table($email_data, $search_term, 'settings'); ?>
    </div>

    <!-- Upload Tab Content -->
    <div id="tab-uploads" class="tab-content" <?php echo $active_tab != 'uploads' ? 'style="display:none;"' : '';?>>
        <h2>Upload a CSV / JSON file</h2>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('file_upload_nonce', 'file_upload_nonce'); ?>
            <input type="file" name="uploaded_file" accept=".json, .csv" required>
            <?php submit_button('Upload File', 'primary', 'upload_file'); ?>
        </form>
                
        <?php
        // Display messages (success or error)
        if (isset($_GET['upload_status'])) {
            if ($_GET['upload_status'] == 'success') {
                echo '<div class="notice notice-success"><p>File uploaded successfully!</p></div>';
            } elseif ($_GET['upload_status'] == 'error') {
                echo '<div class="notice notice-error"><p>File upload failed. Please try again.</p></div>';
            }
        }
        ?>
    </div>
    
    <!-- Edit Email Modal (shared between tabs) -->
    <div id="edit-email-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Edit Email</h2>
            <form method="post" action="">
                <?php wp_nonce_field('email_edit_nonce', 'email_edit_nonce'); ?>
                <input type="hidden" name="tab" id="edit_form_tab" value="<?php echo esc_attr($active_tab); ?>">
                <input type="hidden" id="email_id" name="email_id" value="">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="email_edit">Email Address</label></th>
                        <td>
                            <input type="email" name="email" id="email_edit" class="regular-text" required>
                            <p class="description">Only @univ-bouira.dz addresses are allowed.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Changes', 'primary', 'edit_email'); ?>
            </form>
        </div>
    </div>
</div>

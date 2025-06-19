<?php
/**
 * Admin page with selective enhancements
 *
 * @package WP_Email_Restriction
 */
if (!defined('ABSPATH')) {
    exit;
}

// Handle login settings form submission
if (isset($_POST['save_login_settings']) && wp_verify_nonce($_POST['login_settings_nonce'], 'save_login_settings')) {
    $login_settings = [
        'title' => sanitize_text_field($_POST['login_title']),
        'message' => sanitize_textarea_field($_POST['login_message']),
        'logo_url' => esc_url($_POST['logo_url']),
        'background_color' => sanitize_hex_color($_POST['background_color']),
        'form_background' => sanitize_hex_color($_POST['form_background']),
        'primary_color' => sanitize_hex_color($_POST['primary_color']),
        'text_color' => sanitize_hex_color($_POST['text_color'])
    ];
    
    update_option('wp_email_restriction_login_settings', $login_settings);
    add_settings_error('mcp_email_messages', 'settings_saved', 'Login page settings saved successfully!', 'success');
}

// Get current settings
$login_settings = get_option('wp_email_restriction_login_settings', [
    'title' => 'Access Restricted',
    'message' => 'Please login with your authorized email address to access this website.',
    'logo_url' => '',
    'background_color' => '#f1f1f1',
    'form_background' => '#ffffff',
    'primary_color' => '#0073aa',
    'text_color' => '#23282d'
]);
?>
<div class="wrap">
  <h1><?php _e('WP Email Restriction', 'wp-email-restriction'); ?></h1>
  <?php settings_errors('mcp_email_messages'); ?>

  <h2 class="nav-tab-wrapper">
    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=main'); ?>"
       class="nav-tab <?php echo $active_tab === 'main' ? 'nav-tab-active' : ''; ?>">
       <?php _e('Users', 'wp-email-restriction'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=settings'); ?>"
       class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
       <?php _e('Settings', 'wp-email-restriction'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=uploads'); ?>"
       class="nav-tab <?php echo $active_tab === 'uploads' ? 'nav-tab-active' : ''; ?>">
       <?php _e('Import/Export', 'wp-email-restriction'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=login-settings'); ?>"
       class="nav-tab <?php echo $active_tab === 'login-settings' ? 'nav-tab-active' : ''; ?>">
       <?php _e('Login Page', 'wp-email-restriction'); ?>
    </a>
  </h2>

  <!-- Main Tab -->
  <?php if ($active_tab === 'main') : ?>
    <div class="users-tab">
      <div class="card_container">
        <div class="card card_half">
          <h2><?php _e('Search Users', 'wp-email-restriction'); ?></h2>
          <form method="post" action="">
            <?php wp_nonce_field('search_users_nonce', 'search_nonce'); ?>
            <input type="hidden" name="tab" value="main">
            <div class="search-box">
              <input type="text" name="search_term" class="regular-text"
                     placeholder="<?php esc_attr_e('Search users...', 'wp-email-restriction'); ?>"
                     value="<?php echo esc_attr($search_term); ?>">
              <select name="search_field">
                <option value="all" <?php selected($search_field, 'all'); ?>><?php _e('All Fields'); ?></option>
                <option value="name" <?php selected($search_field, 'name'); ?>><?php _e('Name'); ?></option>
                <option value="email" <?php selected($search_field, 'email'); ?>><?php _e('Email'); ?></option>
              </select>
              <?php submit_button(__('Search'), 'secondary', 'search_users', false); ?>
              <?php if ($search_term) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-email-restriction&tab=main')); ?>"
                   class="button"><?php _e('Reset'); ?></a>
              <?php endif; ?>
            </div>
          </form>
        </div>
        <div class="card card_half">
          <h2><?php _e('Add New User', 'wp-email-restriction'); ?></h2>
          <form method="post" action="">
            <?php wp_nonce_field('add_user_nonce', 'user_nonce'); ?>
            <input type="hidden" name="tab" value="settings">
            <table class="form-table">
              <tr>
                <th><label for="name"><?php _e('Full Name'); ?></label></th>
                <td><input type="text" name="name" id="name" class="regular-text" required></td>
              </tr>
              <tr>
                <th><label for="email"><?php _e('Email Address'); ?></label></th>
                <td>
                  <input type="email" name="email" id="email" class="regular-text" required>
                  <p class="description"><?php _e('Only @univ-bouira.dz addresses are allowed.', 'wp-email-restriction'); ?></p>
                </td>
              </tr>
              <tr>
                <th><label for="password"><?php _e('Password'); ?></label></th>
                <td>
                  <input type="password" name="password" id="password" class="regular-text">
                  <p class="description"><?php _e('Leave blank to generate a random password.', 'wp-email-restriction'); ?></p>
                </td>
              </tr>
            </table>
            <?php submit_button(__('Add User'), 'primary', 'add_user'); ?>
          </form>
        </div>
      </div>
      <h2><?php _e('Registered Users', 'wp-email-restriction'); ?></h2>
      <?php $this->render_users_table($user_data, $search_term, $search_field, 'main'); ?>
    </div>
  <?php endif; ?>

  <!-- Import/Export Tab -->
  <?php if ($active_tab === 'uploads') : ?>
    <div class="uploads-tab">
      <div class="card_container">
        <!-- Import Card -->
        <div class="card card_half">
          <h2>
            <span class="dashicons dashicons-upload" style="margin-right: 8px; color: #00a32a;"></span>
            <?php _e('Import Users', 'wp-email-restriction'); ?>
          </h2>
          <p><?php _e('Upload a CSV or JSON file to import multiple users at once.', 'wp-email-restriction'); ?></p>
          
          <h4><?php _e('CSV Format', 'wp-email-restriction'); ?></h4>
          <p><?php _e('Your CSV file should have the following columns:', 'wp-email-restriction'); ?></p>
          <code>name,email,password</code>
          <p class="description"><?php _e('The password column is optional. If not provided, random passwords will be generated.', 'wp-email-restriction'); ?></p>
          
          <h4><?php _e('JSON Format', 'wp-email-restriction'); ?></h4>
          <p><?php _e('Your JSON file should be an array of user objects:', 'wp-email-restriction'); ?></p>
          <pre><code>[
      {"name": "John Doe", "email": "john@univ-bouira.dz", "password": "optional"},
      {"name": "Jane Smith", "email": "jane@univ-bouira.dz"}
    ]</code></pre>
          
          <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('file_upload_nonce', 'file_upload_nonce'); ?>
            <table class="form-table">
              <tr>
                <th><label for="uploaded_file"><?php _e('Select File'); ?></label></th>
                <td>
                  <input type="file" name="uploaded_file" id="uploaded_file" accept=".csv,.json" required>
                  <p class="description"><?php _e('Maximum file size: 2MB. Supported formats: CSV, JSON', 'wp-email-restriction'); ?></p>
                </td>
              </tr>
            </table>
          <p class="submit">
  <button type="submit" name="upload_file" class="button button-primary">
    <span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>
    <?php _e('Upload and Import', 'wp-email-restriction'); ?>
  </button>
</p>
          </form>
          
          <?php if (isset($_GET['upload_status'])) : ?>
            <?php if ($_GET['upload_status'] === 'success') : ?>
              <div class="notice notice-success"><p>
                <?php printf(__('File uploaded successfully! %d users added.'), intval($_GET['added'] ?? 0)); ?>
              </p></div>
            <?php else : ?>
              <div class="notice notice-error"><p><?php _e('Upload failed. Please check your file format and try again.'); ?></p></div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        
        <!-- Export Card -->
        <div class="card card_half">
          <h2>
            <span class="dashicons dashicons-download" style="margin-right: 8px; color: #0073aa;"></span>
            <?php _e('Export Users', 'wp-email-restriction'); ?>
          </h2>
          <p><?php _e('Download all current users for backup or migration purposes. Choose your preferred format:', 'wp-email-restriction'); ?></p>
          
          <!-- Export Options Container -->
          <div class="export-options">
            
            <!-- CSV Export Option -->
            <div class="export-option csv-export">
              <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <span class="dashicons dashicons-media-spreadsheet" style="font-size: 24px; color: #0f9b44; margin-right: 10px;"></span>
                <h4>CSV Format</h4>
              </div>
              
              <p>Perfect for Excel, Google Sheets, and other spreadsheet applications. Easy to view and edit.</p>
              
              <a href="<?php echo wp_nonce_url(
                     admin_url('admin.php?page=wp-email-restriction&action=export_users&tab=uploads'), 
                     'export_users'
                 ); ?>" 
                 class="button button-primary" 
                 style="width: 100%; text-align: center; text-decoration: none; justify-content: center; display: flex; align-items: center;">
                  <span class="dashicons dashicons-download" style="margin-right: 5px;"></span>
                  <?php _e('Export as CSV', 'wp-email-restriction'); ?>
              </a>
              
              <small><?php _e('Compatible with Excel', 'wp-email-restriction'); ?></small>
            </div>
            
            <!-- JSON Export Option -->
            <div class="export-option json-export">
              <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <span class="dashicons dashicons-media-code" style="font-size: 24px; color: #f39c12; margin-right: 10px;"></span>
                <h4>JSON Format</h4>
              </div>
              
              <p>Structured data format perfect for developers and API integrations. Includes metadata.</p>
              
              <a href="<?php echo wp_nonce_url(
                     admin_url('admin.php?page=wp-email-restriction&action=export_users_json&tab=uploads'), 
                     'export_users'
                 ); ?>" 
                 class="button button-secondary" 
                 style="width: 100%; text-align: center; text-decoration: none; justify-content: center; display: flex; align-items: center;">
                  <span class="dashicons dashicons-download" style="margin-right: 5px;"></span>
                  <?php _e('Export as JSON', 'wp-email-restriction'); ?>
              </a>
              
              <small><?php _e('Developer friendly', 'wp-email-restriction'); ?></small>
            </div>
          </div>
          
          <!-- Summary Info -->
          <div style="display: flex; justify-content: center; align-items: center; gap: 20px; padding: 15px; background: rgba(34, 113, 177, 0.1); border-radius: 4px;">
            <span class="description" style="margin: 0; font-weight: 500;">
              <?php printf(__('%d users available for export', 'wp-email-restriction'), $user_data['total']); ?>
            </span>
            
            <span style="color: #2271b1; font-size: 12px;">
              <?php printf(__('Last updated: %s', 'wp-email-restriction'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?>
            </span>
          </div>
          
          <!-- Error/Success Messages -->
          <?php if (isset($_GET['export_status']) && $_GET['export_status'] === 'no_users') : ?>
            <div class="notice notice-warning" style="margin-top: 15px;">
              <p><?php _e('No users found to export.', 'wp-email-restriction'); ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Settings Tab -->
  <?php if ($active_tab === 'settings') : ?>
    <div class="card">
      <h2><?php _e('Plugin Settings', 'wp-email-restriction'); ?></h2>
      <p><?php _e('Configure general plugin settings here.', 'wp-email-restriction'); ?></p>
      
      <table class="form-table">
        <tr>
          <th><label><?php _e('Allowed Domain'); ?></label></th>
          <td>
            <code>@univ-bouira.dz</code>
            <p class="description"><?php _e('Only emails from this domain will be allowed access.', 'wp-email-restriction'); ?></p>
          </td>
        </tr>
        <tr>
          <th><label><?php _e('Total Users'); ?></label></th>
          <td>
            <strong><?php echo number_format($user_data['total']); ?></strong>
            <p class="description"><?php _e('Total number of registered users in the system.', 'wp-email-restriction'); ?></p>
          </td>
        </tr>
      </table>
    </div>
  <?php endif; ?>

  <!-- Login Settings Tab -->
  <?php if ($active_tab === 'login-settings') : ?>
    <div class="card">
      <h2><?php _e('Customize Login Page', 'wp-email-restriction'); ?></h2>
      <p><?php _e('Customize the appearance and content of your login page.', 'wp-email-restriction'); ?></p>
      
      <form method="post" action="">
        <?php wp_nonce_field('save_login_settings', 'login_settings_nonce'); ?>
        <input type="hidden" name="tab" value="login-settings">
        
        <table class="form-table">
          <tr>
            <th><label for="login_title"><?php _e('Page Title'); ?></label></th>
            <td>
              <input type="text" name="login_title" id="login_title" class="regular-text" 
                     value="<?php echo esc_attr($login_settings['title']); ?>" required>
              <p class="description"><?php _e('The main heading displayed on the login page.'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="login_message"><?php _e('Welcome Message'); ?></label></th>
            <td>
              <textarea name="login_message" id="login_message" class="large-text" rows="3" required><?php echo esc_textarea($login_settings['message']); ?></textarea>
              <p class="description"><?php _e('A message displayed below the title to explain access restrictions.'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="logo_url"><?php _e('Logo URL'); ?></label></th>
            <td>
              <input type="url" name="logo_url" id="logo_url" class="regular-text" 
                     value="<?php echo esc_url($login_settings['logo_url']); ?>">
              <p class="description"><?php _e('Optional. URL to your organization logo.'); ?></p>
            </td>
          </tr>
        </table>
        
        <h3><?php _e('Color Scheme', 'wp-email-restriction'); ?></h3>
        <table class="form-table">
          <tr>
            <th><label for="primary_color"><?php _e('Primary Color'); ?></label></th>
            <td>
              <input type="color" name="primary_color" id="primary_color" 
                     value="<?php echo esc_attr($login_settings['primary_color']); ?>">
              <p class="description"><?php _e('Color for buttons and links.'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="background_color"><?php _e('Background Color'); ?></label></th>
            <td>
              <input type="color" name="background_color" id="background_color" 
                     value="<?php echo esc_attr($login_settings['background_color']); ?>">
              <p class="description"><?php _e('Page background color.'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="form_background"><?php _e('Form Background'); ?></label></th>
            <td>
              <input type="color" name="form_background" id="form_background" 
                     value="<?php echo esc_attr($login_settings['form_background']); ?>">
              <p class="description"><?php _e('Login form background color.'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="text_color"><?php _e('Text Color'); ?></label></th>
            <td>
              <input type="color" name="text_color" id="text_color" 
                     value="<?php echo esc_attr($login_settings['text_color']); ?>">
              <p class="description"><?php _e('Main text color.'); ?></p>
            </td>
          </tr>
        </table>
        
        <?php submit_button(__('Save Settings'), 'primary', 'save_login_settings'); ?>
      </form>
      
      <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #2271b1; border-radius: 4px;">
        <h4 style="margin-top: 0;"><?php _e('Preview Your Login Page'); ?></h4>
        <p><?php _e('See how your login page looks with the current settings:'); ?></p>
        <a href="<?php echo home_url('?restricted_login=preview'); ?>" target="_blank" 
           class="button button-primary">
           <span class="dashicons dashicons-external" style="margin-right: 5px;"></span>
           <?php _e('Preview Login Page'); ?>
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Edit User Modal -->
  <div id="edit-user-modal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <h2><?php _e('Edit User', 'wp-email-restriction'); ?></h2>
      
      <form method="post" action="">
        <?php wp_nonce_field('user_edit_nonce', 'user_edit_nonce'); ?>
        <input type="hidden" name="user_id" id="user_id">
        <input type="hidden" name="tab" id="edit_form_tab" value="<?php echo esc_attr($active_tab); ?>">
        
        <table class="form-table">
          <tr>
            <th><label for="name_edit"><?php _e('Name'); ?></label></th>
            <td><input type="text" name="name" id="name_edit" class="regular-text" required></td>
          </tr>
          <tr>
            <th><label for="email_edit"><?php _e('Email'); ?></label></th>
            <td><input type="email" name="email" id="email_edit" class="regular-text" required></td>
          </tr>
          <tr>
            <th><label for="password_edit"><?php _e('New Password'); ?></label></th>
            <td>
              <input type="password" name="password" id="password_edit" class="regular-text">
              <p class="description"><?php _e('Leave blank to keep current password.'); ?></p>
            </td>
          </tr>
        </table>
        
        <?php submit_button(__('Update User'), 'primary', 'edit_user'); ?>
      </form>
      
      <hr>
      
      <h3><?php _e('Reset Password', 'wp-email-restriction'); ?></h3>
      <form method="post" action="">
        <?php wp_nonce_field('reset_password_nonce', 'reset_password_nonce'); ?>
        <input type="hidden" name="user_id_reset" id="user_id_reset">
        <p><?php _e('Generate a new random password for this user.'); ?></p>
        <?php submit_button(__('Reset Password'), 'secondary', 'reset_password'); ?>
      </form>
    </div>
  </div>

  <!-- Password Display Modal -->
  <div id="password-display-modal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <h2><?php _e('New Password Generated', 'wp-email-restriction'); ?></h2>
      <p><?php _e('The new password has been generated. Please copy it and share it securely with the user.'); ?></p>
      
      <div class="password-display">
        <code id="new-password-display"></code>
        <button type="button" id="copy-password" class="button"><?php _e('Copy Password'); ?></button>
      </div>
      
      <p class="description"><?php _e('This password will only be shown once. Make sure to copy it before closing this window.'); ?></p>
    </div>
  </div>
</div>
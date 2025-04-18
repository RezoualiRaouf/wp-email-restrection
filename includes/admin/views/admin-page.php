<?php
/**
 * Admin page view
 *
 * @package WP_Email_Restriction
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
  <h1><?php _e('WP Email Restriction', 'wp-email-restriction'); ?></h1>
  <?php settings_errors('mcp_email_messages'); ?>

  <h2 class="nav-tab-wrapper">
    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=main'); ?>"
       class="nav-tab <?php echo $active_tab === 'main' ? 'nav-tab-active' : ''; ?>">
       <?php _e('Main', 'wp-email-restriction'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=settings'); ?>"
       class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
       <?php _e('Settings', 'wp-email-restriction'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wp-email-restriction&tab=uploads'); ?>"
       class="nav-tab <?php echo $active_tab === 'uploads' ? 'nav-tab-active' : ''; ?>">
       <?php _e('Uploads', 'wp-email-restriction'); ?>
    </a>
  </h2>

  <!-- Main Tab -->
  <div id="tab-main" class="tab-content" <?php echo $active_tab !== 'main' ? 'style="display:none;"' : ''; ?>>
    <div class="card">
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

    <h2><?php _e('Registered Users', 'wp-email-restriction'); ?></h2>
    <?php $this->render_users_table($user_data, $search_term, $search_field, 'main'); ?>
  </div>

  <!-- Settings Tab -->
  <div id="tab-settings" class="tab-content" <?php echo $active_tab !== 'settings' ? 'style="display:none;"' : ''; ?>>
    <div class="card">
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
              <input type="password" name="password" id="password" class="regular-text" required>
              <p class="description"><?php _e('Leave blank to generate a random password.', 'wp-email-restriction'); ?></p>
            </td>
          </tr>
        </table>
        <?php submit_button(__('Add User'), 'primary', 'add_user'); ?>
      </form>
    </div>

    <h2><?php _e('Registered Users', 'wp-email-restriction'); ?></h2>
    <?php $this->render_users_table($user_data, $search_term, $search_field, 'settings'); ?>
  </div>

  <!-- Uploads Tab -->
  <div id="tab-uploads" class="tab-content" <?php echo $active_tab !== 'uploads' ? 'style="display:none;"' : ''; ?>>
    <h2><?php _e('Upload a CSV / JSON file', 'wp-email-restriction'); ?></h2>
    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('file_upload_nonce', 'file_upload_nonce'); ?>
      <input type="file" name="uploaded_file" accept=".csv,.json" required>
      <?php submit_button(__('Upload File'), 'primary', 'upload_file'); ?>
    </form>
    <?php if (isset($_GET['upload_status'])) : ?>
      <?php if ($_GET['upload_status'] === 'success') : ?>
        <div class="notice notice-success"><p>
          <?php printf(__('File uploaded! %d users added.'), intval($_GET['added'] ?? 0)); ?>
        </p></div>
      <?php else : ?>
        <div class="notice notice-error"><p><?php _e('Upload failed.'); ?></p></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Edit Modal (shared) -->
  <div id="edit-user-modal" class="modal">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <h2><?php _e('Edit User'); ?></h2>
      <form method="post" action="">
        <?php wp_nonce_field('user_edit_nonce', 'user_edit_nonce'); ?>
        <input type="hidden" name="tab" id="edit_form_tab" value="<?php echo esc_attr($active_tab); ?>">
        <input type="hidden" name="user_id" id="user_id" value="">
        <table class="form-table">
          <tr>
            <th><label for="name_edit"><?php _e('Full Name'); ?></label></th>
            <td><input type="text" name="name" id="name_edit" class="regular-text" required></td>
          </tr>
          <tr>
            <th><label for="email_edit"><?php _e('Email Address'); ?></label></th>
            <td>
              <input type="email" name="email" id="email_edit" class="regular-text" required>
              <p class="description"><?php _e('Only @univ-bouira.dz addresses are allowed.'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="password_edit"><?php _e('Password'); ?></label></th>
            <td>
              <input type="password" name="password" id="password_edit" class="regular-text">
              <p class="description"><?php _e('Leave blank to keep existing password.'); ?></p>
            </td>
          </tr>
        </table>
        <?php submit_button(__('Save Changes'), 'primary', 'edit_user'); ?>
      </form>
      <div class="reset-password-section">
        <h3><?php _e('Reset Password'); ?></h3>
        <form method="post" action="">
          <?php wp_nonce_field('reset_password_nonce', 'reset_password_nonce'); ?>
          <input type="hidden" name="user_id_reset" id="user_id_reset" value="">
          <?php submit_button(__('Reset Password'), 'secondary', 'reset_password'); ?>
        </form>
      </div>
    </div>
  </div>

  <!-- Password Display Modal -->
  <div id="password-display-modal" class="modal">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <h2><?php _e('New Password Generated'); ?></h2>
      <div class="password-display">
        <code id="new-password-display"></code>
        <button id="copy-password" class="button"><?php _e('Copy Password'); ?></button>
      </div>
    </div>
  </div>
</div>

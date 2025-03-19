<?php
// Add admin menu
function mcp_add_admin_menu() {
    add_menu_page(
        'WP Email Restriction',
        'Email Restriction',
        'manage_options',
        'wp-email-restriction',
        'mcp_admin_page',
        'dashicons-email',
        100
    );
}
add_action('admin_menu', 'mcp_add_admin_menu');

// Create custom table on plugin activation
function mcp_create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_restriction';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email)
    ) $charset_collate;";
    
    // This is the correct path to WordPress's upgrade.php file
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Render admin page
function mcp_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_restriction';
    
    // Check if table exists, if not create it
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        mcp_create_db_table();
    }
    
    // Handle email add form submission
    if (isset($_POST['add_email']) && isset($_POST['email_nonce']) && wp_verify_nonce($_POST['email_nonce'], 'add_email_nonce')) {
        $email = sanitize_email($_POST['email']);
        
        if (!empty($email)) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && str_ends_with($email, '@gmail.com')) {
                // Check if email already exists
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
                
                if ($exists) {
                    add_settings_error('mcp_email_messages', 'mcp_email_duplicate', 'This email already exists in the list.', 'error');
                } else {
                    // Add email to database
                    $result = $wpdb->insert(
                        $table_name,
                        array('email' => $email),
                        array('%s')
                    );
                    
                    if ($result) {
                        add_settings_error('mcp_email_messages', 'mcp_email_success', 'Email added successfully.', 'success');
                    } else {
                        add_settings_error('mcp_email_messages', 'mcp_email_error', 'Failed to add email. Database error: ' . $wpdb->last_error, 'error');
                    }
                }
            } else {
                add_settings_error('mcp_email_messages', 'mcp_email_invalid', "Invalid email: $email. Only emails with @gmail.com are allowed.", 'error');
            }
        }
    }
    
    // Handle email edit form submission
    if (isset($_POST['edit_email']) && isset($_POST['email_edit_nonce']) && wp_verify_nonce($_POST['email_edit_nonce'], 'email_edit_nonce')) {
        $email_id = intval($_POST['email_id']);
        $email = sanitize_email($_POST['email']);
        
        if (!empty($email) && !empty($email_id)) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && str_ends_with($email, '@gmail.com')) {
                // Check if email already exists (excluding the current ID)
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE email = %s AND id != %d", 
                    $email, $email_id
                ));
                
                if ($exists) {
                    add_settings_error('mcp_email_messages', 'mcp_email_duplicate', 'This email already exists in the list.', 'error');
                } else {
                    // Update email in database
                    $result = $wpdb->update(
                        $table_name,
                        array('email' => $email),
                        array('id' => $email_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        add_settings_error('mcp_email_messages', 'mcp_email_updated', 'Email updated successfully.', 'success');
                    } else {
                        add_settings_error('mcp_email_messages', 'mcp_email_error', 'Failed to update email. Database error: ' . $wpdb->last_error, 'error');
                    }
                }
            } else {
                add_settings_error('mcp_email_messages', 'mcp_email_invalid', "Invalid email: $email. Only emails with @gmail.com are allowed.", 'error');
            }
        }
    }
    
    // Handle email delete
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
        $id = intval($_GET['id']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_email_' . $id)) {
            $wpdb->delete($table_name, array('id' => $id), array('%d'));
            add_settings_error('mcp_email_messages', 'mcp_email_deleted', 'Email deleted successfully.', 'success');
        }
    }
    
    // Initialize search variables
    $search_term = '';
    $search_where = '';
    $search_params = array();
    $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // Handle search form submission
    // This processes the search term and builds the SQL query parts
    if (isset($_POST['search_emails']) && isset($_POST['search_nonce']) && wp_verify_nonce($_POST['search_nonce'], 'search_emails_nonce')) {
        $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        
        if (empty($search_term)) {
            // Display error message if search term is empty
            add_settings_error('mcp_email_messages', 'mcp_search_empty', 'Please enter a search term.', 'error');
        } else {
            // Use esc_like to properly escape the search term for LIKE queries
            $like_search_term = '%' . $wpdb->esc_like($search_term) . '%';
            $search_where = "WHERE email LIKE %s";
            $search_params[] = $like_search_term;
        }
    }
    
    ?>
    <div class="wrap">
        <h1>WP Email Restriction</h1>
        <?php settings_errors('mcp_email_messages'); ?>
        
        <!-- Add Email Form -->
        <div class="card">
            <h2>Add New Gmail Address</h2>
            <form method="post" action="">
                <?php wp_nonce_field('add_email_nonce', 'email_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="email">Email Address</label></th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" placeholder="example@gmail.com" required>
                            <p class="description">Only @gmail.com addresses are allowed.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Add Email', 'primary', 'add_email'); ?>
            </form>
        </div>
        
        <!-- Migrate existing emails if needed -->
        <?php
        // Check if we need to migrate old emails
        $options = get_option('mcp_options');
        $allowed_emails = isset($options['allowed_emails']) ? $options['allowed_emails'] : '';
        
        if (!empty($allowed_emails)) {
            $emails = explode("\n", $allowed_emails);
            $migrated = false;
            
            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && str_ends_with($email, '@gmail.com')) {
                    // Check if email already exists
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
                    
                    if (!$exists) {
                        $wpdb->insert(
                            $table_name,
                            array('email' => $email),
                            array('%s')
                        );
                        $migrated = true;
                    }
                }
            }
            
            if ($migrated) {
                // Clear the old option after migration
                update_option('mcp_options', array('allowed_emails' => ''));
                echo '<div class="notice notice-success"><p>Existing emails have been migrated to the database.</p></div>';
            }
        }
        ?>
        
        <!-- Search Form -->
        <div class="card">
            <h2>Search Emails</h2>
            <form method="post" action="" id="search-form">
                <?php wp_nonce_field('search_emails_nonce', 'search_nonce'); ?>
                <div class="search-box">
                    <input type="text" name="search_term" id="search_term" value="<?php echo esc_attr($search_term); ?>" class="regular-text" placeholder="Search emails...">
                    
                    <?php submit_button('Search', 'secondary', 'search_emails', false); ?>
                    <?php if (!empty($search_term)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-email-restriction')); ?>" class="button">Refresh List</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Saved Emails Table -->
        <h2>Allowed Emails</h2>
        <?php
        // Get emails from database with search condition if present
        if (!empty($search_params)) {
            // Search query with prepared statement
            $query = $wpdb->prepare(
                "SELECT email, time, id FROM $table_name $search_where ORDER BY id DESC",
                $search_params
            );
            $emails = $wpdb->get_results($query);
            $showing_count = count($emails);
        } else {
            // Regular query without search
            $emails = $wpdb->get_results("SELECT email, time, id FROM $table_name ORDER BY id DESC");
            $showing_count = count($emails);
        }
        
        if (!empty($emails)) {
            // Display count of emails being shown
            echo '<div class="tablenav top">';
            echo '<div class="tablenav-pages">';
            echo '<span class="displaying-num">' . sprintf('Showing %d of %d emails', $showing_count, $total_emails) . '</span>';
            echo '</div>';
            echo '</div>';
            ?>
            <div class="emails-table-container" style="max-height: 400px; overflow-y: auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-counter" style="width: 50px;">#</th>
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
                                    <button type="button" class="button edit-email" data-id="<?php echo esc_attr($email->id); ?>" data-email="<?php echo esc_attr($email->email); ?>">Edit</button>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-email-restriction&action=delete&id=' . $email->id), 'delete_email_' . $email->id); ?>" class="button" onclick="return confirm('Are you sure you want to delete this email?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Edit Email Modal Form (hidden by default) -->
            <div id="edit-email-modal" style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
                <div style="background-color:#fff; margin:10% auto; padding:20px; border:1px solid #888; width:50%; border-radius:5px;">
                    <span class="close-modal" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
                    <h2>Edit Email</h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('email_edit_nonce', 'email_edit_nonce'); ?>
                        <input type="hidden" id="email_id" name="email_id" value="">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="email_edit">Email Address</label></th>
                                <td>
                                    <input type="email" name="email" id="email_edit" class="regular-text" required>
                                    <p class="description">Only @gmail.com addresses are allowed.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button('Save Changes', 'primary', 'edit_email'); ?>
                    </form>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Edit Email Modal
                // Opens the edit email modal and populates it with the selected email's data
                $('.edit-email').click(function() {
                    var id = $(this).data('id');
                    var email = $(this).data('email');
                    
                    $('#email_id').val(id);
                    $('#email_edit').val(email);
                    $('#edit-email-modal').show();
                });
                
                // Close modal when clicking on X
                $('.close-modal').click(function() {
                    $('#edit-email-modal').hide();
                });
                
                // Close modal when clicking outside
                $(window).click(function(event) {
                    if ($(event.target).is('#edit-email-modal')) {
                        $('#edit-email-modal').hide();
                    }
                });
                
                // Tooltips for search info
                $('.dashicons-info-outline').hover(
                    function() {
                        $(this).css('color', '#2271b1');
                    },
                    function() {
                        $(this).css('color', '');
                    }
                );
                
                // Form validation for search
                $('#search-form').submit(function(e) {
                    var searchTerm = $('#search_term').val().trim();
                    if (searchTerm === '') {
                        alert('Please enter a search term.');
                        e.preventDefault();
                    }
                });
            });
            </script>
            <?php
        } else {
            if (!empty($search_term)) {
                echo '<div class="notice notice-warning"><p>No emails found matching your search term: <strong>' . esc_html($search_term) . '</strong></p>';
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=wp-email-restriction')) . '" class="button">Refresh List</a></p></div>';
            } else {
                echo '<p>No emails have been saved yet.</p>';
            }
        }
        ?>
        
        <style>
        /* Custom styles for the emails table */
        .emails-table-container {
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .emails-table-container table {
            margin: 0;
        }
        
        .emails-table-container thead th {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 10;
            box-shadow: 0 1px 0 0 #ccd0d4;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        /* Ensures the table expands to fill container */
        .emails-table-container table {
            width: 100%;
        }
        
        /* Tooltip styles */
        .dashicons-info-outline {
            cursor: help;
            vertical-align: middle;
        }
        </style>
    </div>
    <?php
}

// Enqueue necessary scripts
function mcp_admin_scripts($hook) {
    if ('toplevel_page_wp-email-restriction' !== $hook) {
        return;
    }
    wp_enqueue_script('jquery');
    
    // Add custom styles
    wp_enqueue_style('wp-admin');
}
add_action('admin_enqueue_scripts', 'mcp_admin_scripts');
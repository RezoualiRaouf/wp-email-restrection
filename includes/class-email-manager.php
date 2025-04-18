<?php
/**
 * User management
 *
 * @package WP_Email_Restriction
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * User manager class
 */
class WP_Email_Restriction_Email_Manager {
    /**
     * Email validator instance
     */
    private $validator;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->validator = new WP_Email_Restriction_Validator();
    }
    
    /**
     * Add user
     * 
     * @param string $name
     * @param string $email
     * @param string $password
     * @return array
     */
    public function add_user($name, $email, $password = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array('status' => 'error', 'message' => '');
        
        // Validate email
        if (empty($email)) {
            $result['message'] = 'Please enter a valid email address.';
            return $result;
        }
        
        // Validate name
        if (empty($name)) {
            $result['message'] = 'Please enter a name.';
            return $result;
        }
        
        // Check if it's a valid email with the required domain
        if (!$this->validator->is_valid($email)) {
            $result['message'] = "Invalid email: $email. Only emails with " . $this->validator->get_allowed_domain() . " are allowed.";
            return $result;
        }
        
        // Check if email already exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
        if ($exists) {
            $result['message'] = 'This email already exists in the list.';
            return $result;
        }
        
        // Hash password if provided
        $hashed_password = '';
        if (!empty($password)) {
            $hashed_password = wp_hash_password($password);
        } else {
            // Generate a random password if not provided
            $random_password = wp_generate_password(12, true, true);
            $hashed_password = wp_hash_password($random_password);
        }
        
        // Add user to database
        $db_result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'email' => $email,
                'password' => $hashed_password
            ),
            array('%s', '%s', '%s')
        );
        
        if ($db_result) {
            $result['status'] = 'success';
            $result['message'] = 'User added successfully.';
        } else {
            $result['message'] = 'Failed to add user. Database error: ' . $wpdb->last_error;
        }
        
        return $result;
    }
    
    /**
     * Legacy method for backward compatibility
     * 
     * @param string $email
     * @return array
     */
    public function add_email($email) {
        return $this->add_user('Migrated User', $email);
    }
    
    /**
     * Edit user
     * 
     * @param int $user_id
     * @param string $name
     * @param string $email
     * @param string $password
     * @return array
     */
    public function edit_user($user_id, $name, $email, $password = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array('status' => 'error', 'message' => '');
        
        // Validate inputs
        if (empty($email) || empty($user_id) || empty($name)) {
            $result['message'] = 'Invalid email, name or user ID.';
            return $result;
        }
        
        // Check if it's a valid email with the required domain
        if (!$this->validator->is_valid($email)) {
            $result['message'] = "Invalid email: $email. Only emails with " . $this->validator->get_allowed_domain() . " are allowed.";
            return $result;
        }
        
        // Check if email already exists (excluding the current ID)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE email = %s AND id != %d", 
            $email, $user_id
        ));
        
        if ($exists) {
            $result['message'] = 'This email already exists in the list.';
            return $result;
        }
        
        // Prepare update data
        $update_data = array(
            'name' => $name,
            'email' => $email
        );
        $update_format = array('%s', '%s');
        
        // Update password if provided
        if (!empty($password)) {
            $update_data['password'] = wp_hash_password($password);
            $update_format[] = '%s';
        }
        
        // Update user in database
        $db_result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $user_id),
            $update_format,
            array('%d')
        );
        
        if ($db_result !== false) {
            $result['status'] = 'success';
            $result['message'] = 'User updated successfully.';
        } else {
            $result['message'] = 'Failed to update user. Database error: ' . $wpdb->last_error;
        }
        
        return $result;
    }
    
    /**
     * Legacy method for backward compatibility
     * 
     * @param int $email_id
     * @param string $email
     * @return array
     */
    public function edit_email($email_id, $email) {
        // Get user name from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $user = $wpdb->get_row($wpdb->prepare("SELECT name FROM $table_name WHERE id = %d", $email_id));
        $name = ($user && isset($user->name)) ? $user->name : 'Migrated User';
        
        return $this->edit_user($email_id, $name, $email);
    }
    
    /**
     * Reset user password
     * 
     * @param int $user_id
     * @return array
     */
    public function reset_password($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array('status' => 'error', 'message' => '');
        
        // Generate random password
        $new_password = wp_generate_password(12, true, true);
        $hashed_password = wp_hash_password($new_password);
        
        // Update in database
        $db_result = $wpdb->update(
            $table_name,
            array('password' => $hashed_password),
            array('id' => $user_id),
            array('%s'),
            array('%d')
        );
        
        if ($db_result !== false) {
            $result['status'] = 'success';
            $result['message'] = 'Password reset successfully.';
            $result['new_password'] = $new_password;
        } else {
            $result['message'] = 'Failed to reset password. Database error: ' . $wpdb->last_error;
        }
        
        return $result;
    }
    
    /**
     * Delete user
     * 
     * @param int $user_id
     * @return array
     */
    public function delete_user($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array('status' => 'error', 'message' => '');
        
        // Validate input
        $user_id = intval($user_id);
        if (empty($user_id)) {
            $result['message'] = 'Invalid user ID.';
            return $result;
        }
        
        // Delete user from database
        $db_result = $wpdb->delete(
            $table_name, 
            array('id' => $user_id), 
            array('%d')
        );
        
        if ($db_result !== false && $wpdb->rows_affected > 0) {
            $result['status'] = 'success';
            $result['message'] = 'User deleted successfully.';
        } else {
            $result['message'] = 'Failed to delete user. Database error: ' . $wpdb->last_error;
        }
        
        return $result;
    }
    
    /**
     * Legacy method for backward compatibility
     * 
     * @param int $email_id
     * @return array
     */
    public function delete_email($email_id) {
        return $this->delete_user($email_id);
    }
    
    /**
     * Get users with search and filter options
     * 
     * @param string $search_term
     * @param string $search_field
     * @return array
     */
    public function get_users($search_term = '', $search_field = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array(
            'users' => array(),
            'total' => 0,
            'showing' => 0
        );
        
        // Build WHERE clause for search
        $where_clause = '';
        $where_args = array();
        
        if (!empty($search_term)) {
            $like_term = '%' . $wpdb->esc_like($search_term) . '%';
            $conditions = array();
            
            if ($search_field === 'name' || $search_field === 'all') {
                $conditions[] = "name LIKE %s";
                $where_args[] = $like_term;
            }
            
            if ($search_field === 'email' || $search_field === 'all') {
                $conditions[] = "email LIKE %s";
                $where_args[] = $like_term;
            }
            
            if (!empty($conditions)) {
                $where_clause = " WHERE " . implode(" OR ", $conditions);
            }
        }
        
        // Get total count (without search filters)
        $result['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Build final query
        $query = "SELECT id, name, email, created_at, updated_at FROM $table_name";
        
        if (!empty($where_clause)) {
            $query .= $where_clause;
        }
        
        $query .= " ORDER BY id DESC";
        
        // Prepare and execute the query
        if (!empty($where_args)) {
            $prepared_query = $wpdb->prepare($query, $where_args);
            $result['users'] = $wpdb->get_results($prepared_query);
        } else {
            $result['users'] = $wpdb->get_results($query);
        }
        
        $result['showing'] = count($result['users']);
        
        return $result;
    }
    
    /**
     * Legacy method for backward compatibility
     * 
     * @param string $search_term
     * @return array
     */
    public function get_emails($search_term = '') {
        $result = $this->get_users($search_term);
        
        // Format for backward compatibility
        return array(
            'emails' => $result['users'],
            'total' => $result['total'],
            'showing' => $result['showing']
        );
    }
    
    /**
     * Process CSV file
     * 
     * @param string $file_path
     * @return int
     */
    public function process_csv_file($file_path) {
        $added_count = 0;
        
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            // Get header row
            $header = fgetcsv($handle, 1000, ",");
            $columns = array();
            
            // Map header columns to expected fields
            if ($header) {
                foreach ($header as $index => $column) {
                    $column = strtolower(trim($column));
                    if (in_array($column, array('name', 'email', 'password'))) {
                        $columns[$column] = $index;
                    }
                }
            }
            
            // Process data rows
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $email = isset($columns['email']) && isset($data[$columns['email']]) ? 
                    sanitize_email($data[$columns['email']]) : '';
                    
                $name = isset($columns['name']) && isset($data[$columns['name']]) ? 
                    sanitize_text_field($data[$columns['name']]) : 'Imported User';
                    
                $password = isset($columns['password']) && isset($data[$columns['password']]) ? 
                    $data[$columns['password']] : wp_generate_password(12, true, true);
                
                // If no header row or couldn't map columns
                if (empty($email) && isset($data[0])) {
                    $email = sanitize_email($data[0]);
                    $name = isset($data[1]) ? sanitize_text_field($data[1]) : 'Imported User';
                    $password = isset($data[2]) ? $data[2] : wp_generate_password(12, true, true);
                }
                
                if (!empty($email)) {
                    $result = $this->add_user($name, $email, $password);
                    
                    if ($result['status'] === 'success') {
                        $added_count++;
                    }
                }
            }
            fclose($handle);
        }
        
        return $added_count;
    }
    
    /**
     * Process JSON file
     * 
     * @param string $file_path
     * @return int
     */
    public function process_json_file($file_path) {
        $added_count = 0;
        $json_data = file_get_contents($file_path);
        $users = json_decode($json_data, true);
        
        if (is_array($users)) {
            foreach ($users as $user) {
                if (is_string($user)) {
                    // Handle simple array of emails
                    $email = sanitize_email($user);
                    if (!empty($email)) {
                        $result = $this->add_user('Imported User', $email);
                        if ($result['status'] === 'success') {
                            $added_count++;
                        }
                    }
                } else if (is_array($user)) {
                    // Handle array of objects
                    $email = isset($user['email']) ? sanitize_email($user['email']) : '';
                    $name = isset($user['name']) ? sanitize_text_field($user['name']) : 'Imported User';
                    $password = isset($user['password']) ? $user['password'] : wp_generate_password(12, true, true);
                    
                    if (!empty($email)) {
                        $result = $this->add_user($name, $email, $password);
                        if ($result['status'] === 'success') {
                            $added_count++;
                        }
                    }
                }
            }
        }
        
        return $added_count;
    }
}
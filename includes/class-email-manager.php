<?php
/**
 * Email management
 *
 * @package WP_Email_Restriction
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * Email manager class
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
     * Add email
     * 
     * @param string $email
     * @return array
     */
    public function add_email($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array('status' => 'error', 'message' => '');
        
        // Validate email
        if (empty($email)) {
            $result['message'] = 'Please enter a valid email address.';
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
        
        // Add email to database
        $db_result = $wpdb->insert(
            $table_name,
            array('email' => $email),
            array('%s')
        );
        
        if ($db_result) {
            $result['status'] = 'success';
            $result['message'] = 'Email added successfully.';
        } else {
            $result['message'] = 'Failed to add email. Database error: ' . $wpdb->last_error;
        }
        
        return $result;
    }
    
    /**
     * Edit email
     * 
     * @param int $email_id
     * @param string $email
     * @return array
     */
    public function edit_email($email_id, $email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array('status' => 'error', 'message' => '');
        
        // Validate inputs
        if (empty($email) || empty($email_id)) {
            $result['message'] = 'Invalid email or email ID.';
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
            $email, $email_id
        ));
        
        if ($exists) {
            $result['message'] = 'This email already exists in the list.';
            return $result;
        }
        
        // Update email in database
        $db_result = $wpdb->update(
            $table_name,
            array('email' => $email),
            array('id' => $email_id),
            array('%s'),
            array('%d')
        );
        
        if ($db_result !== false) {
            $result['status'] = 'success';
            $result['message'] = 'Email updated successfully.';
        } else {
            $result['message'] = 'Failed to update email. Database error: ' . $wpdb->last_error;
        }
        
        return $result;
    }
    
    /**
     * Delete email
     * 
     * @param int $email_id
     * @return array
     */
    public function delete_email($email_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array('status' => 'error', 'message' => '');
        
        // Validate input
        $email_id = intval($email_id);
        if (empty($email_id)) {
            $result['message'] = 'Invalid email ID.';
            return $result;
        }
        
        // Delete email from database
        $db_result = $wpdb->delete(
            $table_name, 
            array('id' => $email_id), 
            array('%d')
        );
        
        if ($db_result !== false && $wpdb->rows_affected > 0) {
            $result['status'] = 'success';
            $result['message'] = 'Email deleted successfully.';
        } else {
            $result['message'] = 'Failed to delete email. Database error: ' . $wpdb->last_error;
        }
        
        return $result;
    }
    
    /**
     * Get emails
     * 
     * @param string $search_term
     * @return array
     */
    public function get_emails($search_term = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_restriction';
        $result = array(
            'emails' => array(),
            'total' => 0,
            'showing' => 0
        );
        
        // Get total count
        $result['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Handle search
        if (!empty($search_term)) {
            $like_search_term = '%' . $wpdb->esc_like($search_term) . '%';
            $query = $wpdb->prepare(
                "SELECT email, time, id FROM $table_name WHERE email LIKE %s ORDER BY id DESC",
                $like_search_term
            );
        } else {
            $query = "SELECT email, time, id FROM $table_name ORDER BY id DESC";
        }
        
        $result['emails'] = $wpdb->get_results($query);
        $result['showing'] = count($result['emails']);
        
        return $result;
    }
}

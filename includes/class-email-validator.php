<?php
/**
 * Email validator
 *
 * @package WP_Email_Restriction
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * Email validator class
 */
class WP_Email_Restriction_Validator {
    /**
     * The domain to validate against
     */
    private $allowed_domain = '@univ-bouira.dz';
    
    /**
     * Validate email
     * 
     * @param string $email
     * @return bool
     */
    public function is_valid($email) {
        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Domain validation
        if (substr($email, -strlen($this->allowed_domain)) !== $this->allowed_domain) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Set allowed domain
     * 
     * @param string $domain
     */
    public function set_allowed_domain($domain) {
        $this->allowed_domain = $domain;
    }
    
    /**
     * Get allowed domain
     * 
     * @return string
     */
    public function get_allowed_domain() {
        return $this->allowed_domain;
    }
}

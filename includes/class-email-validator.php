<?php
/**
 * Email validator class with required domain configuration
 * 
 * @package WP_Email_Restriction
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email validator class with configurable domain
 */
class WP_Email_Restriction_Validator {
    
    /**
     * Check if domain is configured
     *
     * @return bool
     */
    public function is_domain_configured() {
        $settings = get_option('wp_email_restriction_settings', []);
        return !empty($settings['allowed_domain']);
    }

    /**
     * Get the currently configured allowed domain
     *
     * @return string|null
     */
    private function get_configured_domain() {
        if (!$this->is_domain_configured()) {
            return null;
        }
        
        $settings = get_option('wp_email_restriction_settings', []);
        return $settings['allowed_domain'];
    }

    /**
     * Validate email against configured domain
     *
     * @param string $email
     * @return bool
     */
    public function is_valid( $email ) {
        // Check if domain is configured first
        if (!$this->is_domain_configured()) {
            return false;
        }
        
        // Basic WP email format check
        if ( ! is_email( $email ) ) {
            return false;
        }

        // Get the configured domain
        $allowed_domain = $this->get_configured_domain();
        
        // Grab what comes after the "@"
        $email_domain = substr( strrchr( $email, '@' ), 1 );
        
        return strtolower( $email_domain ) === strtolower( $allowed_domain );
    }

    /**
     * Set allowed domain and save to database
     *
     * @param string $domain
     * @return array Result with status and message
     */
    public function set_allowed_domain( $domain ) {
        // Strip any leading "@" and clean the domain
        $domain = ltrim( trim($domain), '@' );
        
        // Validate domain format
        $validation = $this->validate_domain_format($domain);
        if (!$validation['valid']) {
            return $validation;
        }
        
        // Get current settings
        $settings = get_option('wp_email_restriction_settings', []);
        $settings['allowed_domain'] = $domain;
        
        // Save settings
        $success = update_option('wp_email_restriction_settings', $settings);
        
        if ($success) {
            return [
                'valid' => true,
                'status' => 'success',
                'message' => 'Domain configured successfully: @' . $domain
            ];
        } else {
            return [
                'valid' => false,
                'status' => 'error', 
                'message' => 'Failed to save domain configuration.'
            ];
        }
    }

    /**
     * Get allowed domain with "@" prefix for display
     *
     * @return string|null
     */
    public function get_allowed_domain() {
        $domain = $this->get_configured_domain();
        return $domain ? '@' . $domain : null;
    }

    /**
     * Get allowed domain without "@" prefix (raw format)
     *
     * @return string|null
     */
    public function get_allowed_domain_raw() {
        return $this->get_configured_domain();
    }

    /**
     * Validate domain format with detailed feedback
     *
     * @param string $domain
     * @return array
     */
    public function validate_domain_format($domain) {
        $result = [
            'valid' => false,
            'status' => 'error',
            'message' => ''
        ];
        
        // Check if empty
        if (empty($domain)) {
            $result['message'] = 'Domain cannot be empty.';
            return $result;
        }
        
        // Remove any @ symbols (shouldn't be there)
        if (strpos($domain, '@') !== false) {
            $result['message'] = 'Domain should not contain "@" symbol. Enter only the domain part (e.g., "company.com").';
            return $result;
        }
        
        // Check for spaces
        if (strpos($domain, ' ') !== false) {
            $result['message'] = 'Domain cannot contain spaces.';
            return $result;
        }
        
        // Check for minimum domain structure (at least one dot)
        if (strpos($domain, '.') === false) {
            $result['message'] = 'Invalid domain format. Domain must contain at least one dot (e.g., "company.com").';
            return $result;
        }
        
        // Check for valid characters only
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            $result['message'] = 'Domain contains invalid characters. Only letters, numbers, dots, and hyphens are allowed.';
            return $result;
        }
        
        // Check if it starts or ends with dot or hyphen
        if (preg_match('/^[.-]|[.-]$/', $domain)) {
            $result['message'] = 'Domain cannot start or end with a dot or hyphen.';
            return $result;
        }
        
        // Check for consecutive dots
        if (strpos($domain, '..') !== false) {
            $result['message'] = 'Domain cannot contain consecutive dots.';
            return $result;
        }
        
        // Test with actual email validation
        if (!filter_var('test@' . $domain, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = 'Invalid domain format. Please enter a valid domain like "company.com".';
            return $result;
        }
        
        // Additional length checks
        if (strlen($domain) < 4) {
            $result['message'] = 'Domain is too short. Please enter a valid domain.';
            return $result;
        }
        
        if (strlen($domain) > 253) {
            $result['message'] = 'Domain is too long. Maximum length is 253 characters.';
            return $result;
        }
        
        // If we get here, domain is valid
        $result['valid'] = true;
        $result['status'] = 'success';
        $result['message'] = 'Domain format is valid.';
        
        return $result;
    }

    /**
     * Get setup status for the plugin
     *
     * @return array
     */
    public function get_setup_status() {
        if ($this->is_domain_configured()) {
            return [
                'configured' => true,
                'domain' => $this->get_allowed_domain(),
                'message' => 'Plugin is configured and ready to use.'
            ];
        } else {
            return [
                'configured' => false,
                'domain' => null,
                'message' => 'Domain configuration required. Please set up your allowed email domain to activate the plugin.'
            ];
        }
    }

    /**
     * Clear domain configuration
     *
     * @return bool
     */
    public function clear_domain_configuration() {
        $settings = get_option('wp_email_restriction_settings', []);
        unset($settings['allowed_domain']);
        return update_option('wp_email_restriction_settings', $settings);
    }
}
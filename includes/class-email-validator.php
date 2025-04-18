<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email validator class
 */
class WP_Email_Restriction_Validator {
    /**
     * The domain to validate against (no leading “@” here)
     *
     * @var string
     */
    private $allowed_domain = 'univ-bouira.dz';

    /**
     * Validate email
     *
     * @param string $email
     * @return bool
     */
    public function is_valid( $email ) {
        // Basic WP email format check
        if ( ! is_email( $email ) ) {
            return false;
        }

        // Grab what comes after the “@”
        $email_domain = substr( strrchr( $email, '@' ), 1 );
        return strtolower( $email_domain ) === strtolower( $this->allowed_domain );
    }

    /**
     * Set allowed domain (without leading “@”)
     *
     * @param string $domain
     */
    public function set_allowed_domain( $domain ) {
        // Strip any leading “@” so it’s stored cleanly
        $this->allowed_domain = ltrim( $domain, '@' );
    }

    /**
     * Get allowed domain (add the “@” back for error messages)
     *
     * @return string
     */
    public function get_allowed_domain() {
        return '@' . $this->allowed_domain;
    }
}

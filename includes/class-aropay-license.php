<?php
/**
 * AROPay License — checks subscription status for Own API mode.
 *
 * Merchants who use their own Yo Uganda / Pesapal API credentials must have
 * an active $2/month AROPay subscription validated against the AROSOFT license server.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_License {

    const LICENSE_SERVER = 'https://arosoftlabs.com/wp-json/arosoft-licenses/v1/';
    const TRANSIENT_KEY  = 'aropay_license_status';
    const CACHE_HOURS    = 12;

    /**
     * Get the current collection mode.
     *
     * @return string 'managed' | 'own_api'
     */
    public static function get_mode() {
        return get_option( 'aropay_collection_mode', 'managed' );
    }

    /**
     * Is this site in managed (AROSOFT float) mode?
     *
     * @return bool
     */
    public static function is_managed() {
        return 'managed' === self::get_mode();
    }

    /**
     * Is this site in Own API mode?
     *
     * @return bool
     */
    public static function is_own_api() {
        return 'own_api' === self::get_mode();
    }

    /**
     * Get the stored license key.
     *
     * @return string
     */
    public static function get_key() {
        return (string) get_option( 'aropay_license_key', '' );
    }

    /**
     * Check if the license is active and the subscription is valid.
     * Result is cached for CACHE_HOURS to avoid hammering the license server.
     *
     * @param bool $force  Bypass cache and re-check immediately.
     * @return array { valid: bool, status: string, expires: string, message: string }
     */
    public static function check( $force = false ) {
        if ( ! self::is_own_api() ) {
            return array( 'valid' => true, 'status' => 'managed', 'message' => 'Managed mode — no license required.' );
        }

        $key = self::get_key();
        if ( empty( $key ) ) {
            return array( 'valid' => false, 'status' => 'no_key', 'message' => 'No license key entered.' );
        }

        if ( ! $force ) {
            $cached = get_transient( self::TRANSIENT_KEY );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Ping license server
        $response = wp_remote_post( self::LICENSE_SERVER . 'check', array(
            'timeout' => 15,
            'body'    => array(
                'license_key' => $key,
                'product'     => 'aropay',
                'site_url'    => home_url(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            // On network failure, use last cached result or assume valid to not break live sites
            $fallback = get_option( 'aropay_license_last_valid', false );
            $result   = array(
                'valid'   => (bool) $fallback,
                'status'  => 'network_error',
                'message' => 'Could not reach license server. Using last known status.',
            );
            set_transient( self::TRANSIENT_KEY, $result, HOUR_IN_SECONDS );
            return $result;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $valid   = isset( $body['valid'] ) && true === $body['valid'];
        $expires = $body['expires'] ?? '';
        $status  = $body['status'] ?? ( $valid ? 'active' : 'invalid' );
        $message = $body['message'] ?? ( $valid ? 'License is active.' : 'Invalid or expired license.' );

        $result = compact( 'valid', 'status', 'expires', 'message' );

        // Cache and persist last known valid state
        set_transient( self::TRANSIENT_KEY, $result, self::CACHE_HOURS * HOUR_IN_SECONDS );
        if ( $valid ) {
            update_option( 'aropay_license_last_valid', true );
        }

        return $result;
    }

    /**
     * Activate / save a license key and immediately check it.
     *
     * @param string $key
     * @return array License check result
     */
    public static function activate( $key ) {
        update_option( 'aropay_license_key', sanitize_text_field( $key ) );
        delete_transient( self::TRANSIENT_KEY );
        return self::check( true );
    }

    /**
     * Deactivate and clear the license key.
     */
    public static function deactivate() {
        delete_option( 'aropay_license_key' );
        delete_option( 'aropay_license_last_valid' );
        delete_transient( self::TRANSIENT_KEY );
        update_option( 'aropay_collection_mode', 'managed' );
    }

    /**
     * Is Own API mode currently unlocked (mode = own_api AND license valid)?
     *
     * @return bool
     */
    public static function own_api_active() {
        if ( ! self::is_own_api() ) {
            return false;
        }
        $check = self::check();
        return $check['valid'];
    }

    /**
     * Monthly subscription price label.
     *
     * @return string
     */
    public static function subscription_price() {
        return '$2.00 / month';
    }

    /**
     * URL to purchase / manage subscription.
     *
     * @return string
     */
    public static function purchase_url() {
        return 'https://arosoftlabs.com/market/plugins/aropay/subscription';
    }
}

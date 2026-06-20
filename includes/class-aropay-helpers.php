<?php
/**
 * AROPay Helpers — shared utility functions.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Helpers {

    /**
     * Generate a unique internal transaction reference.
     *
     * @param string $prefix e.g. 'YO' or 'PP'
     * @return string
     */
    public static function generate_ref( $prefix = 'ARO' ) {
        return strtoupper( $prefix ) . '-' . strtoupper( wp_generate_password( 12, false ) ) . '-' . time();
    }

    /**
     * Format a Ugandan phone number to international format (256XXXXXXXXX).
     *
     * @param string $phone
     * @return string
     */
    public static function format_phone( $phone ) {
        $phone = preg_replace( '/\D/', '', $phone );

        if ( substr( $phone, 0, 3 ) === '256' ) {
            return $phone;
        }
        if ( substr( $phone, 0, 1 ) === '0' ) {
            return '256' . substr( $phone, 1 );
        }
        if ( strlen( $phone ) === 9 ) {
            return '256' . $phone;
        }
        return $phone;
    }

    /**
     * Mask phone for logs: 25677****567.
     *
     * @param string $phone
     * @return string
     */
    public static function mask_phone( $phone ) {
        $phone = self::format_phone( $phone );
        return substr( $phone, 0, 5 ) . '****' . substr( $phone, -3 );
    }

    /**
     * Calculate transaction fee for a merchant.
     *
     * @param float $amount
     * @param int   $merchant_id
     * @return float
     */
    public static function calculate_fee( $amount, $merchant_id = 0 ) {
        $percent = (float) get_option( 'aropay_default_fee_percent', 1.50 );
        $min_fee = (float) get_option( 'aropay_min_fee_ugx', 500 );

        if ( $merchant_id ) {
            global $wpdb;
            $merchant_percent = $wpdb->get_var( $wpdb->prepare(
                "SELECT transaction_fee_percent FROM {$wpdb->prefix}aropay_merchants WHERE id = %d",
                $merchant_id
            ) );
            if ( $merchant_percent !== null ) {
                $percent = (float) $merchant_percent;
            }
        }

        $fee = ( $amount * $percent ) / 100;
        return max( $fee, $min_fee );
    }

    /**
     * Format UGX amount for display.
     *
     * @param float $amount
     * @return string
     */
    public static function format_ugx( $amount ) {
        return 'UGX ' . number_format( $amount, 0, '.', ',' );
    }

    /**
     * Detect MTN or Airtel from phone number.
     *
     * @param string $phone
     * @return string 'mtn' | 'airtel' | 'unknown'
     */
    public static function detect_network( $phone ) {
        $phone  = self::format_phone( $phone );
        $prefix = substr( $phone, 3, 2 ); // digits after 256

        $mtn    = array( '77', '78', '76', '39', '31' );
        $airtel = array( '70', '75', '74', '20' );

        if ( in_array( $prefix, $mtn, true ) ) {
            return 'mtn';
        }
        if ( in_array( $prefix, $airtel, true ) ) {
            return 'airtel';
        }
        return 'unknown';
    }

    /**
     * Is the plugin in test/sandbox mode for a given provider?
     *
     * @param string $provider 'yo' | 'pesapal'
     * @return bool
     */
    public static function is_test_mode( $provider ) {
        return 'yes' === get_option( "aropay_{$provider}_test_mode", 'yes' );
    }

    /**
     * Log a message via WooCommerce logger.
     *
     * @param string $message
     * @param string $level   debug|info|warning|error
     * @param array  $context
     */
    public static function log( $message, $level = 'info', $context = array() ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }
        $logger = wc_get_logger();
        $logger->log( $level, $message, array_merge( array( 'source' => 'aropay' ), $context ) );
    }

    /**
     * Get the plugin's support info string.
     *
     * @return string
     */
    public static function support_info() {
        $email = get_option( 'aropay_support_email', '' );
        $phone = get_option( 'aropay_support_phone', '' );
        $parts = array();
        if ( $email ) {
            $parts[] = $email;
        }
        if ( $phone ) {
            $parts[] = $phone;
        }
        return implode( ' | ', $parts );
    }

    /**
     * Sanitize and validate a Ugandan phone number.
     *
     * @param string $phone
     * @return bool
     */
    public static function is_valid_ug_phone( $phone ) {
        $formatted = self::format_phone( $phone );
        return preg_match( '/^256[0-9]{9}$/', $formatted ) === 1;
    }

    /**
     * Generate a secure API key pair.
     *
     * @return array { key, secret }
     */
    public static function generate_api_keypair() {
        return array(
            'key'    => 'ak_' . bin2hex( random_bytes( 16 ) ),
            'secret' => 'as_' . bin2hex( random_bytes( 32 ) ),
        );
    }
}

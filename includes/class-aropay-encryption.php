<?php
/**
 * AROPay Encryption — encrypts/decrypts sensitive values using WP secret keys.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Encryption {

    /**
     * Derive a 32-byte key from WordPress secret keys.
     */
    private static function get_key() {
        $salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : wp_salt( 'secure_auth' );
        return substr( hash( 'sha256', $salt . 'aropay_enc_v1', true ), 0, 32 );
    }

    /**
     * Encrypt a string.
     *
     * @param string $plaintext
     * @return string|false Base64-encoded ciphertext, or false on failure.
     */
    public static function encrypt( $plaintext ) {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $plaintext ); // graceful fallback
        }
        $iv         = openssl_random_pseudo_bytes( 16 );
        $ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', self::get_key(), OPENSSL_RAW_DATA, $iv );
        if ( false === $ciphertext ) {
            return false;
        }
        return base64_encode( $iv . $ciphertext );
    }

    /**
     * Decrypt a string.
     *
     * @param string $ciphertext Base64-encoded ciphertext.
     * @return string|false Plaintext, or false on failure.
     */
    public static function decrypt( $ciphertext ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return base64_decode( $ciphertext ); // graceful fallback
        }
        $data       = base64_decode( $ciphertext );
        $iv         = substr( $data, 0, 16 );
        $encrypted  = substr( $data, 16 );
        return openssl_decrypt( $encrypted, 'AES-256-CBC', self::get_key(), OPENSSL_RAW_DATA, $iv );
    }

    /**
     * Store an encrypted option.
     *
     * @param string $key
     * @param string $value
     */
    public static function set_option( $key, $value ) {
        update_option( $key, self::encrypt( $value ) );
    }

    /**
     * Retrieve and decrypt an option.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get_option( $key, $default = '' ) {
        $raw = get_option( $key, '' );
        if ( empty( $raw ) ) {
            return $default;
        }
        $decrypted = self::decrypt( $raw );
        return $decrypted !== false ? $decrypted : $default;
    }
}

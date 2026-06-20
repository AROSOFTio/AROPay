<?php
/**
 * AROPay Pesapal API Wrapper — v3 REST/OAuth2.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Pesapal_API {

    const LIVE_BASE = 'https://pay.pesapal.com/v3/api';
    const TEST_BASE = 'https://cybqa.pesapal.com/pesapalv3/api';

    private $consumer_key;
    private $consumer_secret;
    private $test_mode;
    private $base_url;
    private $token_option = 'aropay_pesapal_token';
    private $token_expiry_option = 'aropay_pesapal_token_expiry';

    public function __construct() {
        $this->test_mode       = AROPay_Helpers::is_test_mode( 'pesapal' );
        $this->consumer_key    = AROPay_Encryption::get_option( 'aropay_pesapal_consumer_key' );
        $this->consumer_secret = AROPay_Encryption::get_option( 'aropay_pesapal_consumer_secret' );
        $this->base_url        = $this->test_mode ? self::TEST_BASE : self::LIVE_BASE;
    }

    /**
     * Get (or refresh) a valid OAuth2 bearer token.
     *
     * @return string|WP_Error
     */
    public function get_token() {
        $cached_token  = get_transient( $this->token_option );
        if ( $cached_token ) {
            return $cached_token;
        }

        $response = wp_remote_post( $this->base_url . '/Auth/RequestToken', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'consumer_key'    => $this->consumer_key,
                'consumer_secret' => $this->consumer_secret,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['token'] ) ) {
            return new WP_Error( 'pesapal_auth_failed', __( 'Pesapal authentication failed.', 'aropay' ) );
        }

        // Cache for 55 minutes (tokens valid 60 min)
        set_transient( $this->token_option, $body['token'], 55 * MINUTE_IN_SECONDS );
        return $body['token'];
    }

    /**
     * Register the IPN URL with Pesapal (only needs doing once).
     *
     * @return string|WP_Error IPN ID from Pesapal.
     */
    public function register_ipn() {
        $cached = get_option( 'aropay_pesapal_ipn_id', '' );
        if ( $cached ) {
            return $cached;
        }

        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = $this->post( '/URLSetup/RegisterIPN', array(
            'url'  => self::get_ipn_url(),
            'ipn_notification_type' => 'GET',
        ), $token );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $ipn_id = $response['ipn_id'] ?? '';
        if ( $ipn_id ) {
            update_option( 'aropay_pesapal_ipn_id', $ipn_id );
        }

        return $ipn_id;
    }

    /**
     * Submit a payment order to Pesapal and get the redirect URL.
     *
     * @param array $params { amount, currency, description, reference, customer, billing }
     * @return array|WP_Error { order_tracking_id, redirect_url }
     */
    public function submit_order( $params ) {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $ipn_id = $this->register_ipn();
        if ( is_wp_error( $ipn_id ) ) {
            return $ipn_id;
        }

        $payload = array(
            'id'                      => sanitize_text_field( $params['reference'] ),
            'currency'                => sanitize_text_field( $params['currency'] ?? 'UGX' ),
            'amount'                  => (float) $params['amount'],
            'description'             => sanitize_text_field( $params['description'] ?? 'AROPay Order' ),
            'callback_url'            => $params['callback_url'] ?? wc_get_checkout_url(),
            'notification_id'         => $ipn_id,
            'branch'                  => sanitize_text_field( get_option( 'aropay_plugin_display_name', 'AROPay' ) ),
            'billing_address'         => array(
                'email_address' => sanitize_email( $params['billing']['email'] ?? '' ),
                'phone_number'  => sanitize_text_field( $params['billing']['phone'] ?? '' ),
                'first_name'    => sanitize_text_field( $params['billing']['first_name'] ?? '' ),
                'last_name'     => sanitize_text_field( $params['billing']['last_name'] ?? '' ),
                'country_code'  => 'UG',
                'city'          => sanitize_text_field( $params['billing']['city'] ?? 'Kampala' ),
                'postal_code'   => sanitize_text_field( $params['billing']['postcode'] ?? '00000' ),
                'zip_code'      => sanitize_text_field( $params['billing']['postcode'] ?? '00000' ),
                'line_1'        => sanitize_text_field( $params['billing']['address'] ?? '' ),
            ),
        );

        $response = $this->post( '/Transactions/SubmitOrderRequest', $payload, $token );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( empty( $response['redirect_url'] ) ) {
            return new WP_Error( 'pesapal_no_redirect', __( 'Pesapal did not return a redirect URL.', 'aropay' ) );
        }

        return array(
            'order_tracking_id' => $response['order_tracking_id'] ?? '',
            'redirect_url'      => $response['redirect_url'],
        );
    }

    /**
     * Get the transaction status from Pesapal.
     *
     * @param string $order_tracking_id
     * @return array|WP_Error { payment_status_description, confirmation_code }
     */
    public function get_transaction_status( $order_tracking_id ) {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $url      = $this->base_url . '/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode( $order_tracking_id );
        $response = wp_remote_get( $url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body ) ) {
            return new WP_Error( 'pesapal_status_error', __( 'Failed to retrieve Pesapal transaction status.', 'aropay' ) );
        }

        return $body;
    }

    /**
     * POST JSON to a Pesapal endpoint.
     *
     * @param string $path
     * @param array  $data
     * @param string $token
     * @return array|WP_Error
     */
    private function post( $path, $data, $token = null ) {
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        );
        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $start    = microtime( true );
        $response = wp_remote_post( $this->base_url . $path, array(
            'timeout' => 45,
            'headers' => $headers,
            'body'    => wp_json_encode( $data ),
        ) );

        $duration = (int) ( ( microtime( true ) - $start ) * 1000 );
        $code     = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
        $body_raw = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );

        $this->log_api( $path, $data, $body_raw, $code, $duration );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( $body_raw, true );
        if ( $code < 200 || $code >= 300 ) {
            $msg = $body['message'] ?? sprintf( __( 'Pesapal API error %d', 'aropay' ), $code );
            return new WP_Error( 'pesapal_error', $msg );
        }

        return $body ?? array();
    }

    /**
     * Get the IPN URL.
     */
    public static function get_ipn_url() {
        return rest_url( 'aropay/v1/ipn/pesapal' );
    }

    /**
     * Log API call.
     */
    private function log_api( $path, $request, $response, $code, $duration ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aropay_api_logs',
            array(
                'provider'      => 'pesapal',
                'request_type'  => $path,
                'request_body'  => wp_json_encode( $request ),
                'response_body' => is_string( $response ) ? substr( $response, 0, 5000 ) : wp_json_encode( $response ),
                'status_code'   => $code,
                'duration_ms'   => $duration,
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%d' )
        );
    }
}

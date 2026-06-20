<?php
/**
 * AROPay Webhook — generic inbound webhook dispatcher.
 *
 * Routes raw HTTP callbacks to the appropriate provider handler.
 * REST API endpoints (aropay/v1/ipn/*) are preferred; this class
 * exists as a fallback wc-api hook and for future extensibility.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Webhook {

    public function __construct() {
        // Fallback WC API hooks (in addition to REST endpoints)
        add_action( 'woocommerce_api_aropay_yo',      array( $this, 'handle_yo' ) );
        add_action( 'woocommerce_api_aropay_pesapal', array( $this, 'handle_pesapal' ) );
    }

    /**
     * Handle Yo Uganda callback via wc-api.
     */
    public function handle_yo() {
        $handler = new AROPay_Yo_Callback();

        // Build a minimal WP_REST_Request from the raw POST body
        $request = new WP_REST_Request( 'POST' );
        $request->set_body( file_get_contents( 'php://input' ) );

        $response = $handler->process( $request );

        status_header( $response->get_status() );
        echo wp_json_encode( $response->get_data() );
        exit;
    }

    /**
     * Handle Pesapal IPN via wc-api.
     */
    public function handle_pesapal() {
        $handler = new AROPay_Pesapal_IPN();

        $request = new WP_REST_Request( 'GET' );
        // phpcs:ignore WordPress.Security.NonceVerification
        foreach ( $_GET as $key => $value ) {
            $request->set_param( sanitize_key( $key ), sanitize_text_field( wp_unslash( $value ) ) );
        }

        $response = $handler->process( $request );

        status_header( $response->get_status() );
        echo wp_json_encode( $response->get_data() );
        exit;
    }
}

new AROPay_Webhook();

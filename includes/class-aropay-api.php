<?php
/**
 * AROPay REST API — registers /wp-json/aropay/v1/ endpoints.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_API {

    const NAMESPACE = 'aropay/v1';

    public function register_routes() {

        // Validate merchant credentials
        register_rest_route( self::NAMESPACE, '/validate-merchant', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'validate_merchant' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'api_key'    => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'api_secret' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        // Transaction status (public — polled by frontend)
        register_rest_route( self::NAMESPACE, '/transaction-status/(?P<ref>[a-zA-Z0-9\-_]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_transaction_status' ),
            'permission_callback' => '__return_true',
        ) );

        // Yo Uganda IPN callback
        register_rest_route( self::NAMESPACE, '/ipn/yo', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_yo_ipn' ),
            'permission_callback' => '__return_true',
        ) );

        // Pesapal IPN callback
        register_rest_route( self::NAMESPACE, '/ipn/pesapal', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'handle_pesapal_ipn' ),
            'permission_callback' => '__return_true',
        ) );

        // Merchant stats (authenticated)
        register_rest_route( self::NAMESPACE, '/merchant/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_merchant_stats' ),
            'permission_callback' => array( $this, 'merchant_auth' ),
        ) );

        // Refund (admin only)
        register_rest_route( self::NAMESPACE, '/refund', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'initiate_refund' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_woocommerce' );
            },
        ) );
    }

    /**
     * Validate merchant credentials — used by plugin on first setup.
     */
    public function validate_merchant( WP_REST_Request $request ) {
        $merchant = AROPay_Merchant::validate_credentials(
            $request->get_param( 'api_key' ),
            $request->get_param( 'api_secret' )
        );

        if ( ! $merchant ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid credentials or account suspended.', 'aropay' ),
            ), 401 );
        }

        // Store merchant ID on this WP site
        update_option( 'aropay_merchant_id', $merchant->id );
        update_option( 'aropay_merchant_name', $merchant->business_name );

        return new WP_REST_Response( array(
            'success'       => true,
            'merchant_name' => $merchant->business_name,
            'fee_percent'   => $merchant->transaction_fee_percent,
        ), 200 );
    }

    /**
     * Get live transaction status — polled by frontend JS.
     */
    public function get_transaction_status( WP_REST_Request $request ) {
        $ref         = $request->get_param( 'ref' );
        $transaction = AROPay_Transaction::get_by_ref( $ref );

        if ( ! $transaction ) {
            return new WP_REST_Response( array( 'status' => 'not_found' ), 404 );
        }

        $redirect = '';
        if ( 'completed' === $transaction->status && $transaction->wc_order_id ) {
            $order    = wc_get_order( $transaction->wc_order_id );
            $redirect = $order ? $order->get_checkout_order_received_url() : '';
        }

        return new WP_REST_Response( array(
            'status'   => $transaction->status,
            'redirect' => $redirect,
        ), 200 );
    }

    /**
     * Handle Yo Uganda callback (IPN).
     */
    public function handle_yo_ipn( WP_REST_Request $request ) {
        $handler = new AROPay_Yo_Callback();
        return $handler->process( $request );
    }

    /**
     * Handle Pesapal IPN.
     */
    public function handle_pesapal_ipn( WP_REST_Request $request ) {
        $handler = new AROPay_Pesapal_IPN();
        return $handler->process( $request );
    }

    /**
     * Get stats for authenticated merchant.
     */
    public function get_merchant_stats( WP_REST_Request $request ) {
        $api_key  = $request->get_header( 'X-AROPay-Key' );
        $merchant = AROPay_Merchant::get_by_api_key( $api_key );
        if ( ! $merchant ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }
        $stats = AROPay_Merchant::get_stats( $merchant->id );
        return new WP_REST_Response( $stats, 200 );
    }

    /**
     * Initiate refund (admin only).
     */
    public function initiate_refund( WP_REST_Request $request ) {
        $transaction_id = absint( $request->get_param( 'transaction_id' ) );
        $transaction    = AROPay_Transaction::get( $transaction_id );

        if ( ! $transaction || 'completed' !== $transaction->status ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Transaction not found or not eligible for refund.', 'aropay' ),
            ), 400 );
        }

        // Mark as refunded in our system (manual payout handled separately for now)
        AROPay_Transaction::update_status( $transaction_id, 'refunded', 'REFUNDED' );
        AROPay_Helpers::log( "Refund initiated for transaction #{$transaction_id}", 'info' );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Transaction marked as refunded.', 'aropay' ),
        ), 200 );
    }

    /**
     * Auth callback for merchant-authenticated endpoints.
     */
    public function merchant_auth( WP_REST_Request $request ) {
        $api_key = $request->get_header( 'X-AROPay-Key' );
        return ! empty( $api_key ) && AROPay_Merchant::get_by_api_key( $api_key ) !== null;
    }
}

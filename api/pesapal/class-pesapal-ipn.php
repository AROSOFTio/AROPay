<?php
/**
 * AROPay Pesapal IPN Handler — processes inbound Pesapal notifications.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Pesapal_IPN {

    /**
     * Process an inbound Pesapal IPN GET request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function process( WP_REST_Request $request ) {
        $order_tracking_id = sanitize_text_field( $request->get_param( 'OrderTrackingId' ) ?? '' );
        $merchant_ref      = sanitize_text_field( $request->get_param( 'OrderMerchantReference' ) ?? '' );
        $notification_type = sanitize_text_field( $request->get_param( 'OrderNotificationType' ) ?? '' );

        AROPay_Helpers::log(
            "Pesapal IPN received: tracking={$order_tracking_id} ref={$merchant_ref} type={$notification_type}",
            'info'
        );

        if ( empty( $order_tracking_id ) || empty( $merchant_ref ) ) {
            return new WP_REST_Response( array( 'orderNotificationType' => $notification_type, 'orderTrackingId' => '', 'orderMerchantReference' => '', 'status' => '200' ), 200 );
        }

        // Find the transaction by internal ref (merchant ref = our internal_ref)
        $transaction = AROPay_Transaction::get_by_ref( $merchant_ref );
        if ( ! $transaction ) {
            AROPay_Helpers::log( "Pesapal IPN: no transaction found for ref {$merchant_ref}", 'warning' );
            return new WP_REST_Response( array( 'status' => '200' ), 200 );
        }

        // Fetch live status from Pesapal
        $pesapal = new AROPay_Pesapal_API();
        $status_data = $pesapal->get_transaction_status( $order_tracking_id );

        if ( is_wp_error( $status_data ) ) {
            AROPay_Helpers::log( 'Pesapal IPN: status fetch failed — ' . $status_data->get_error_message(), 'error' );
            return new WP_REST_Response( array( 'status' => '200' ), 200 );
        }

        $pesapal_status = strtoupper( $status_data['payment_status_description'] ?? '' );
        $confirmation   = sanitize_text_field( $status_data['confirmation_code'] ?? $order_tracking_id );

        $status_map = array(
            'COMPLETED' => 'completed',
            'FAILED'    => 'failed',
            'REVERSED'  => 'refunded',
            'PENDING'   => 'pending',
            'INVALID'   => 'failed',
        );

        $new_status = $status_map[ $pesapal_status ] ?? 'pending';

        if ( $transaction->status !== $new_status ) {
            AROPay_Transaction::update_status( $transaction->id, $new_status, $pesapal_status, $confirmation );
            AROPay_Transaction::save_ipn_data( $transaction->id, $status_data );

            AROPay_Helpers::log(
                "Pesapal IPN: transaction #{$transaction->id} updated to {$new_status} (Pesapal: {$pesapal_status})",
                'info'
            );
        }

        // Pesapal requires this specific response format
        return new WP_REST_Response( array(
            'orderNotificationType'  => $notification_type,
            'orderTrackingId'        => $order_tracking_id,
            'orderMerchantReference' => $merchant_ref,
            'status'                 => '200',
        ), 200 );
    }
}

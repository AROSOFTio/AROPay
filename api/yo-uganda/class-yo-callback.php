<?php
/**
 * AROPay Yo Uganda Callback Handler — processes inbound Yo payment notifications.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Yo_Callback {

    /**
     * Process an inbound Yo IPN/callback POST request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function process( WP_REST_Request $request ) {
        $body = $request->get_body();

        AROPay_Helpers::log( 'Yo callback received: ' . substr( $body, 0, 300 ), 'debug' );

        // Parse XML body
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $body );
        if ( false === $xml ) {
            AROPay_Helpers::log( 'Yo callback: invalid XML', 'error' );
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Invalid XML' ), 400 );
        }

        $data             = $this->xml_to_array( $xml );
        $internal_ref     = sanitize_text_field( $data['InternalReferenceID'] ?? '' );
        $transaction_ref  = sanitize_text_field( $data['TransactionReferenceId'] ?? '' );
        $yo_status        = strtoupper( sanitize_text_field( $data['TransactionStatus'] ?? '' ) );

        if ( empty( $internal_ref ) ) {
            AROPay_Helpers::log( 'Yo callback: missing InternalReferenceID', 'error' );
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Missing reference' ), 400 );
        }

        $transaction = AROPay_Transaction::get_by_ref( $internal_ref );
        if ( ! $transaction ) {
            AROPay_Helpers::log( "Yo callback: transaction not found for ref {$internal_ref}", 'warning' );
            return new WP_REST_Response( array( 'status' => 'not_found' ), 404 );
        }

        // Map Yo status to our internal status
        $status_map = array(
            'SUCCEEDED'     => 'completed',
            'SUCCESSFUL'    => 'completed',
            'FAILED'        => 'failed',
            'PENDING'       => 'pending',
            'INDETERMINATE' => 'pending',
        );

        $new_status = $status_map[ $yo_status ] ?? 'pending';

        // Only update if status has changed
        if ( $transaction->status !== $new_status ) {
            AROPay_Transaction::update_status( $transaction->id, $new_status, $yo_status, $transaction_ref );
            AROPay_Transaction::save_ipn_data( $transaction->id, $data );

            AROPay_Helpers::log(
                "Yo callback: transaction #{$transaction->id} updated to {$new_status} (Yo: {$yo_status})",
                'info'
            );
        }

        return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
    }

    /**
     * Convert SimpleXMLElement to plain array.
     *
     * @param \SimpleXMLElement $xml
     * @return array
     */
    private function xml_to_array( $xml ) {
        $data = array();
        foreach ( $xml as $key => $value ) {
            $data[ (string) $key ] = (string) $value;
        }
        return $data;
    }
}

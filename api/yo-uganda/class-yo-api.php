<?php
/**
 * AROPay Yo Uganda API Wrapper.
 *
 * Communicates with the Yo! Payments API (XML/SOAP).
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Yo_API {

    const LIVE_ENDPOINT = 'https://paymentsapi.yo.co.ug/ybs/task.php';
    const TEST_ENDPOINT = 'https://paymentsapi1.yo.co.ug/ybs/task.php';

    private $username;
    private $password;
    private $test_mode;
    private $endpoint;

    public function __construct() {
        $this->test_mode = AROPay_Helpers::is_test_mode( 'yo' );
        $this->username  = AROPay_Encryption::get_option( 'aropay_yo_username' );
        $this->password  = AROPay_Encryption::get_option( 'aropay_yo_password' );
        $this->endpoint  = $this->test_mode ? self::TEST_ENDPOINT : self::LIVE_ENDPOINT;
    }

    /**
     * Send a payment request (STK push) to a customer's phone.
     *
     * @param array $params { amount, phone, network, reference, narrative }
     * @return array|WP_Error { transaction_reference } or WP_Error
     */
    public function request_payment( $params ) {
        $xml = $this->build_payment_request_xml( $params );
        $response = $this->post( $xml );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $parsed = $this->parse_response( $response );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        if ( isset( $parsed['status'] ) && 'OK' === strtoupper( $parsed['status'] ) ) {
            return array(
                'transaction_reference' => $parsed['TransactionReferenceId'] ?? $params['reference'],
            );
        }

        $error_msg = $parsed['StatusMessage'] ?? __( 'Payment request failed.', 'aropay' );
        return new WP_Error( 'yo_payment_failed', $error_msg );
    }

    /**
     * Check the status of a previously submitted payment.
     *
     * @param string $provider_ref The Yo transaction reference.
     * @return array|false { status, provider_status } or false.
     */
    public function check_transaction_status( $provider_ref ) {
        $xml = $this->build_status_check_xml( $provider_ref );
        $response = $this->post( $xml );

        if ( is_wp_error( $response ) ) {
            AROPay_Helpers::log( 'Yo status check failed: ' . $response->get_error_message(), 'error' );
            return false;
        }

        $parsed = $this->parse_response( $response );
        if ( is_wp_error( $parsed ) ) {
            return false;
        }

        $yo_status = strtoupper( $parsed['TransactionStatus'] ?? '' );

        $status_map = array(
            'SUCCEEDED' => 'completed',
            'FAILED'    => 'failed',
            'PENDING'   => 'pending',
            'INDETERMINATE' => 'pending',
        );

        return array(
            'status'          => $status_map[ $yo_status ] ?? 'pending',
            'provider_status' => $yo_status,
        );
    }

    /**
     * Build the ACTransferFunds XML request body.
     */
    private function build_payment_request_xml( $params ) {
        $narrative = esc_xml( $params['narrative'] ?? 'AROPay Payment' );
        $phone     = esc_xml( $params['phone'] );
        $amount    = (int) round( $params['amount'] );
        $reference = esc_xml( $params['reference'] );
        $username  = esc_xml( $this->username );
        $password  = esc_xml( $this->password );

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<AutoCreate>
  <Request>
    <APIUsername>{$username}</APIUsername>
    <APIPassword>{$password}</APIPassword>
    <Method>acpaymentrequest</Method>
    <Amount>{$amount}</Amount>
    <Account>{$phone}</Account>
    <Currency>UGX</Currency>
    <ExternalReference>{$reference}</ExternalReference>
    <Narrative>{$narrative}</Narrative>
    <InternalReferenceID>{$reference}</InternalReferenceID>
    <NoOfRetries>3</NoOfRetries>
    <NotificationURL>{$this->get_callback_url()}</NotificationURL>
    <ProviderReferenceNumber>{$reference}</ProviderReferenceNumber>
  </Request>
</AutoCreate>
XML;
    }

    /**
     * Build the transaction status check XML.
     */
    private function build_status_check_xml( $provider_ref ) {
        $username  = esc_xml( $this->username );
        $password  = esc_xml( $this->password );
        $ref       = esc_xml( $provider_ref );

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<AutoCreate>
  <Request>
    <APIUsername>{$username}</APIUsername>
    <APIPassword>{$password}</APIPassword>
    <Method>actransactioncheck</Method>
    <InternalReferenceID>{$ref}</InternalReferenceID>
  </Request>
</AutoCreate>
XML;
    }

    /**
     * POST XML to Yo endpoint.
     *
     * @param string $xml_body
     * @return string|WP_Error Raw response body.
     */
    private function post( $xml_body ) {
        $start    = microtime( true );
        $response = wp_remote_post( $this->endpoint, array(
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => array(
                'Content-Type' => 'text/xml; charset=UTF-8',
            ),
            'body'    => $xml_body,
            'sslverify' => ! $this->test_mode,
        ) );

        $duration = (int) ( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            $this->log_api( 'request_payment', $xml_body, $response->get_error_message(), 0, $duration );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        $this->log_api( 'request_payment', '[XML body hidden]', substr( $body, 0, 500 ), $code, $duration );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'yo_http_error', sprintf( __( 'Yo API returned HTTP %d.', 'aropay' ), $code ) );
        }

        return $body;
    }

    /**
     * Parse Yo XML response into associative array.
     *
     * @param string $xml
     * @return array|WP_Error
     */
    private function parse_response( $xml ) {
        libxml_use_internal_errors( true );
        $obj = simplexml_load_string( $xml );
        if ( false === $obj ) {
            return new WP_Error( 'yo_parse_error', __( 'Failed to parse Yo API response.', 'aropay' ) );
        }

        $response = $obj->Response ?? $obj;
        $data     = array();
        foreach ( $response as $key => $value ) {
            $data[ (string) $key ] = (string) $value;
        }
        return $data;
    }

    /**
     * Send money to a mobile wallet (B2C — Account to Customer withdrawal).
     *
     * @param array $params { amount, phone, network, reference, narrative }
     * @return array|WP_Error { transaction_reference } or WP_Error
     */
    public function send_money( $params ) {
        $xml      = $this->build_send_money_xml( $params );
        $response = $this->post( $xml );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $parsed = $this->parse_response( $response );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        if ( isset( $parsed['Status'] ) && 'OK' === strtoupper( $parsed['Status'] ) ) {
            return array(
                'transaction_reference' => $parsed['TransactionReferenceId'] ?? $params['reference'],
            );
        }

        $error_msg = $parsed['StatusMessage'] ?? $parsed['ErrorMessage'] ?? __( 'Withdrawal request failed.', 'aropay' );
        return new WP_Error( 'yo_withdrawal_failed', $error_msg );
    }

    /**
     * Build the ACWithdrawal (B2C) XML request body.
     */
    private function build_send_money_xml( $params ) {
        $narrative = esc_xml( $params['narrative'] ?? 'AROPay Wallet Withdrawal' );
        $phone     = esc_xml( $params['phone'] );
        $amount    = (int) round( $params['amount'] );
        $reference = esc_xml( $params['reference'] );
        $username  = esc_xml( $this->username );
        $password  = esc_xml( $this->password );
        $callback  = esc_xml( self::get_withdrawal_callback_url() );

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<AutoCreate>
  <Request>
    <APIUsername>{$username}</APIUsername>
    <APIPassword>{$password}</APIPassword>
    <Method>acwithdrawal</Method>
    <Amount>{$amount}</Amount>
    <Account>{$phone}</Account>
    <Currency>UGX</Currency>
    <ExternalReference>{$reference}</ExternalReference>
    <Narrative>{$narrative}</Narrative>
    <InternalReferenceID>{$reference}</InternalReferenceID>
    <NotificationURL>{$callback}</NotificationURL>
    <ProviderReferenceNumber>{$reference}</ProviderReferenceNumber>
  </Request>
</AutoCreate>
XML;
    }

    /**
     * Build the IPN/callback URL for Yo.
     *
     * @return string
     */
    public static function get_callback_url() {
        return rest_url( 'aropay/v1/ipn/yo' );
    }

    /**
     * Build the IPN callback URL for wallet withdrawals.
     *
     * @return string
     */
    public static function get_withdrawal_callback_url() {
        return rest_url( 'aropay/v1/ipn/withdrawal' );
    }

    /**
     * Log API call to DB.
     */
    private function log_api( $type, $request, $response, $code, $duration ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aropay_api_logs',
            array(
                'provider'      => 'yo',
                'request_type'  => $type,
                'request_body'  => substr( $request, 0, 5000 ),
                'response_body' => substr( $response, 0, 5000 ),
                'status_code'   => $code,
                'duration_ms'   => $duration,
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%d' )
        );
    }
}

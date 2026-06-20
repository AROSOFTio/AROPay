<?php
/**
 * AROPay Abstract Gateway Base — extends WC_Payment_Gateway.
 *
 * All AROPay gateways extend this class.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

abstract class AROPay_Gateway_Base extends WC_Payment_Gateway {

    /**
     * Whether this gateway is in test/sandbox mode.
     * @var bool
     */
    protected $test_mode = true;

    /**
     * Whether merchant credentials are validated.
     * @var bool
     */
    protected $credentials_valid = false;

    /**
     * Return the AROPay plugin version string for display.
     * @return string
     */
    protected function get_plugin_version() {
        return AROPAY_VERSION;
    }

    /**
     * Check if merchant credentials are configured.
     * @return bool
     */
    protected function has_merchant_credentials() {
        $key    = get_option( 'aropay_merchant_api_key', '' );
        $secret = get_option( 'aropay_merchant_api_secret', '' );
        return ! empty( $key ) && ! empty( $secret );
    }

    /**
     * Output an admin notice if credentials are missing.
     */
    protected function admin_credentials_notice() {
        echo '<div class="notice notice-warning inline"><p>' .
            esc_html__( 'AROPay: Please enter your Merchant API Key and Secret in the gateway settings to activate payments.', 'aropay' ) .
            '</p></div>';
    }

    /**
     * Validate that the order currency is UGX.
     *
     * @param WC_Order $order
     * @return bool
     */
    protected function validate_currency( $order ) {
        if ( 'UGX' !== $order->get_currency() ) {
            wc_add_notice(
                __( 'AROPay only supports UGX (Uganda Shillings). Please contact the store owner.', 'aropay' ),
                'error'
            );
            return false;
        }
        return true;
    }

    /**
     * Return a failed payment result with a notice.
     *
     * @param string $message
     * @return array
     */
    protected function payment_failed( $message = '' ) {
        if ( $message ) {
            wc_add_notice( $message, 'error' );
        }
        return array( 'result' => 'fail' );
    }

    /**
     * Return a successful redirect result.
     *
     * @param string $url
     * @return array
     */
    protected function payment_redirect( $url ) {
        return array(
            'result'   => 'success',
            'redirect' => $url,
        );
    }

    /**
     * Get merchant-specific fee percent.
     *
     * @return float
     */
    protected function get_fee_percent() {
        $merchant_id = AROPay_Merchant::get_merchant_id_for_site();
        if ( $merchant_id ) {
            $merchant = AROPay_Merchant::get( $merchant_id );
            if ( $merchant ) {
                return (float) $merchant->transaction_fee_percent;
            }
        }
        return (float) get_option( 'aropay_default_fee_percent', 1.50 );
    }

    /**
     * Log a message specific to this gateway.
     *
     * @param string $message
     * @param string $level
     */
    protected function log( $message, $level = 'info' ) {
        AROPay_Helpers::log( "[{$this->id}] {$message}", $level );
    }

    /**
     * Get gateway icon HTML — shows provider logos.
     * Overridden in child classes.
     *
     * @return string
     */
    public function get_icon() {
        return '';
    }

    /**
     * Display gateway description in checkout (theme-neutral).
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo '<p class="aropay-description">' . wp_kses_post( $this->description ) . '</p>';
        }
        $this->render_payment_fields();
    }

    /**
     * Child classes render their specific input fields here.
     */
    abstract protected function render_payment_fields();
}

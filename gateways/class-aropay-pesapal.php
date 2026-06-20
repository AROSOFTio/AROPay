<?php
/**
 * AROPay Gateway — Pesapal OpenFloat (Cards + MoMo).
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Gateway_Pesapal extends AROPay_Gateway_Base {

    public function __construct() {
        $this->id                 = 'aropay_pesapal';
        $this->method_title       = __( 'AROPay — Cards & MoMo (Pesapal)', 'aropay' );
        $this->method_description = __( 'Accept Visa, Mastercard, MTN MoMo and Airtel Money via Pesapal OpenFloat. Powered by AROSOFT.', 'aropay' );
        $this->has_fields         = false;
        $this->supports           = array( 'products', 'refunds' );
        $this->test_mode          = AROPay_Helpers::is_test_mode( 'pesapal' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', __( 'Card / Mobile Money (Pesapal)', 'aropay' ) );
        $this->description = $this->get_option( 'description', __( 'Pay securely with Visa, Mastercard, MTN MoMo or Airtel Money via Pesapal.', 'aropay' ) );
        $this->enabled     = $this->get_option( 'enabled', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_aropay_pesapal_return', array( $this, 'handle_pesapal_return' ) );
    }

    /**
     * Admin settings.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable / Disable', 'aropay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable AROPay Pesapal Gateway', 'aropay' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'    => __( 'Title', 'aropay' ),
                'type'     => 'text',
                'default'  => __( 'Card / Mobile Money (Pesapal)', 'aropay' ),
                'desc_tip' => true,
                'description' => __( 'Payment method title shown at checkout.', 'aropay' ),
            ),
            'description' => array(
                'title'   => __( 'Description', 'aropay' ),
                'type'    => 'textarea',
                'default' => __( 'Pay securely with Visa, Mastercard, MTN MoMo or Airtel Money via Pesapal.', 'aropay' ),
            ),
            'merchant_credentials' => array(
                'title'       => __( 'AROPay Merchant Credentials', 'aropay' ),
                'type'        => 'title',
                'description' => __( 'Use the same credentials as the Mobile Money gateway if already entered.', 'aropay' ),
            ),
            'merchant_api_key' => array(
                'title'       => __( 'Merchant API Key', 'aropay' ),
                'type'        => 'text',
                'description' => __( 'Your AROPay merchant API key.', 'aropay' ),
                'default'     => get_option( 'aropay_merchant_api_key', '' ),
                'desc_tip'    => true,
            ),
            'merchant_api_secret' => array(
                'title'       => __( 'Merchant API Secret', 'aropay' ),
                'type'        => 'password',
                'description' => __( 'Your AROPay merchant API secret.', 'aropay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Gateway icon — Visa, Mastercard, MTN, Airtel, Pesapal.
     */
    public function get_icon() {
        $icon_html  = '<span class="aropay-icons">';
        $icon_html .= '<span class="aropay-icon aropay-icon-visa" title="Visa" aria-label="Visa"></span>';
        $icon_html .= '<span class="aropay-icon aropay-icon-mastercard" title="Mastercard" aria-label="Mastercard"></span>';
        $icon_html .= '<span class="aropay-icon aropay-icon-mtn" title="MTN MoMo" aria-label="MTN Mobile Money"></span>';
        $icon_html .= '<span class="aropay-icon aropay-icon-airtel" title="Airtel Money" aria-label="Airtel Money"></span>';
        $icon_html .= '</span>';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    /**
     * No custom fields — redirects to Pesapal hosted page.
     */
    protected function render_payment_fields() {
        if ( $this->test_mode ) {
            echo '<p class="aropay-test-mode-notice">⚠️ ' . esc_html__( 'TEST MODE — No real transactions will be processed.', 'aropay' ) . '</p>';
        }
    }

    /**
     * Process payment — build Pesapal order and redirect.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return $this->payment_failed( __( 'Order not found.', 'aropay' ) );
        }

        if ( ! $this->validate_currency( $order ) ) {
            return $this->payment_failed();
        }

        $internal_ref = AROPay_Helpers::generate_ref( 'PP' );
        $merchant_id  = AROPay_Merchant::get_merchant_id_for_site();
        $fee          = AROPay_Helpers::calculate_fee( $order->get_total(), $merchant_id );

        $pesapal = new AROPay_Pesapal_API();
        $result  = $pesapal->submit_order( array(
            'reference'   => $internal_ref,
            'amount'      => $order->get_total(),
            'currency'    => 'UGX',
            'description' => sprintf( __( 'Order #%s from %s', 'aropay' ), $order->get_order_number(), get_bloginfo( 'name' ) ),
            'callback_url' => add_query_arg(
                array( 'wc-api' => 'aropay_pesapal_return', 'order_id' => $order_id ),
                home_url( '/' )
            ),
            'billing'     => array(
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'city'       => $order->get_billing_city(),
                'postcode'   => $order->get_billing_postcode(),
                'address'    => $order->get_billing_address_1(),
            ),
        ) );

        if ( is_wp_error( $result ) ) {
            $this->log( 'Pesapal submit failed: ' . $result->get_error_message(), 'error' );
            return $this->payment_failed(
                __( 'Could not connect to Pesapal. Please try again or use Mobile Money.', 'aropay' )
            );
        }

        // Record transaction as pending
        AROPay_Transaction::create( array(
            'merchant_id'    => $merchant_id,
            'wc_order_id'    => $order_id,
            'order_id'       => $order->get_order_number(),
            'payment_method' => 'pesapal',
            'provider'       => 'pesapal',
            'amount'         => $order->get_total(),
            'currency'       => 'UGX',
            'fee_amount'     => $fee,
            'net_amount'     => $order->get_total() - $fee,
            'customer_phone' => $order->get_billing_phone(),
            'customer_email' => $order->get_billing_email(),
            'customer_name'  => $order->get_formatted_billing_full_name(),
            'provider_ref'   => $result['order_tracking_id'],
            'internal_ref'   => $internal_ref,
            'status'         => 'pending',
        ) );

        $order->update_status( 'pending', sprintf(
            __( 'AROPay: Redirected to Pesapal. Tracking ID: %s', 'aropay' ),
            $result['order_tracking_id']
        ) );
        $order->update_meta_data( '_aropay_internal_ref', $internal_ref );
        $order->update_meta_data( '_aropay_pesapal_tracking_id', $result['order_tracking_id'] );
        $order->update_meta_data( '_aropay_provider', 'pesapal' );
        $order->save();

        $this->log( "Redirecting order #{$order_id} to Pesapal: {$result['redirect_url']}", 'info' );

        return $this->payment_redirect( $result['redirect_url'] );
    }

    /**
     * Handle Pesapal return callback after payment.
     */
    public function handle_pesapal_return() {
        $order_id          = absint( $_GET['order_id'] ?? 0 );
        $order_tracking_id = sanitize_text_field( $_GET['OrderTrackingId'] ?? '' );

        if ( ! $order_id ) {
            wp_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        }

        if ( $order_tracking_id ) {
            $pesapal     = new AROPay_Pesapal_API();
            $status_data = $pesapal->get_transaction_status( $order_tracking_id );

            if ( ! is_wp_error( $status_data ) ) {
                $pesapal_status = strtoupper( $status_data['payment_status_description'] ?? '' );
                $internal_ref   = $order->get_meta( '_aropay_internal_ref' );
                $transaction    = $internal_ref ? AROPay_Transaction::get_by_ref( $internal_ref ) : null;

                if ( $transaction ) {
                    $status_map = array(
                        'COMPLETED' => 'completed',
                        'FAILED'    => 'failed',
                        'REVERSED'  => 'refunded',
                        'PENDING'   => 'pending',
                    );
                    $new_status = $status_map[ $pesapal_status ] ?? 'pending';
                    AROPay_Transaction::update_status(
                        $transaction->id,
                        $new_status,
                        $pesapal_status,
                        $status_data['confirmation_code'] ?? $order_tracking_id
                    );
                }
            }
        }

        // Redirect to thank-you page (WC will determine if paid)
        wp_redirect( $order->get_checkout_order_received_url() );
        exit;
    }

    /**
     * Process refund (marked in system; manual via Pesapal dashboard).
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $transaction = AROPay_Transaction::get_by_order( $order_id );
        if ( ! $transaction ) {
            return new WP_Error( 'aropay_refund_error', __( 'No AROPay transaction found for this order.', 'aropay' ) );
        }
        AROPay_Transaction::update_status( $transaction->id, 'refunded', 'REFUNDED' );
        $this->log( "Refund recorded for order #{$order_id} (manual refund via Pesapal dashboard required)", 'info' );
        return true;
    }
}

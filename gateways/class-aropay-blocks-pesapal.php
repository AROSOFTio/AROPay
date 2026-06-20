<?php
/**
 * AROPay WooCommerce Blocks — Pesapal payment method registration.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class AROPay_Blocks_Pesapal extends AbstractPaymentMethodType {

    protected $name = 'aropay_pesapal';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_aropay_pesapal_settings', array() );
    }

    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'aropay-blocks-pesapal',
            AROPAY_PLUGIN_URL . 'public/js/aropay-blocks-pesapal.js',
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
            AROPAY_VERSION,
            true
        );
        return array( 'aropay-blocks-pesapal' );
    }

    public function get_payment_method_data() {
        return array(
            'title'       => $this->settings['title']       ?? __( 'Card / Mobile Money (Pesapal)', 'aropay' ),
            'description' => $this->settings['description'] ?? __( 'Pay securely with Visa, Mastercard, MTN MoMo or Airtel Money.', 'aropay' ),
            'supports'    => $this->get_supported_features(),
            'logo_url'    => AROPAY_PLUGIN_URL . 'assets/images/pesapal-logo.png',
        );
    }

    public function get_supported_features() {
        $gateway = new AROPay_Gateway_Pesapal();
        return array_filter( $gateway->supports, array( $gateway, 'supports' ) );
    }
}

<?php
/**
 * AROPay WooCommerce Blocks — Yo Uganda payment method registration.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class AROPay_Blocks_Yo extends AbstractPaymentMethodType {

    protected $name = 'aropay_yo';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_aropay_yo_settings', array() );
    }

    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'aropay-blocks-yo',
            AROPAY_PLUGIN_URL . 'public/js/aropay-blocks-yo.js',
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
            AROPAY_VERSION,
            true
        );
        return array( 'aropay-blocks-yo' );
    }

    public function get_payment_method_data() {
        return array(
            'title'       => $this->settings['title']       ?? __( 'Mobile Money (MTN / Airtel)', 'aropay' ),
            'description' => $this->settings['description'] ?? __( 'Pay with MTN MoMo or Airtel Money.', 'aropay' ),
            'supports'    => $this->get_supported_features(),
            'logo_url'    => AROPAY_PLUGIN_URL . 'assets/images/mtn-momo-logo.png',
        );
    }

    public function get_supported_features() {
        $gateway = new AROPay_Gateway_Yo_Uganda();
        return array_filter( $gateway->supports, array( $gateway, 'supports' ) );
    }
}

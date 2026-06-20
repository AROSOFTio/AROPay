<?php
/**
 * AROPay Gateway — Yo Uganda (MTN MoMo & Airtel Money).
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Gateway_Yo_Uganda extends AROPay_Gateway_Base {

    public function __construct() {
        $this->id                 = 'aropay_yo';
        $this->method_title       = __( 'AROPay — MTN & Airtel MoMo', 'aropay' );
        $this->method_description = __( 'Accept MTN Mobile Money and Airtel Money payments via Yo Uganda. Powered by AROSOFT.', 'aropay' );
        $this->has_fields         = true;
        $this->supports           = array( 'products', 'refunds' );
        $this->test_mode          = AROPay_Helpers::is_test_mode( 'yo' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', __( 'Mobile Money (MTN / Airtel)', 'aropay' ) );
        $this->description = $this->get_option( 'description', __( 'Pay with MTN Mobile Money or Airtel Money. You will receive a prompt on your phone.', 'aropay' ) );
        $this->enabled     = $this->get_option( 'enabled', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'validate_and_save_credentials' ) );
    }

    /**
     * Admin settings fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable / Disable', 'aropay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable AROPay Mobile Money', 'aropay' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Title', 'aropay' ),
                'type'        => 'text',
                'description' => __( 'Payment method title shown at checkout.', 'aropay' ),
                'default'     => __( 'Mobile Money (MTN / Airtel)', 'aropay' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'aropay' ),
                'type'        => 'textarea',
                'description' => __( 'Shown to the customer at checkout.', 'aropay' ),
                'default'     => __( 'Pay with MTN Mobile Money or Airtel Money. You will receive a prompt on your phone.', 'aropay' ),
            ),
            'merchant_credentials' => array(
                'title'       => __( 'AROPay Merchant Credentials', 'aropay' ),
                'type'        => 'title',
                'description' => sprintf(
                    __( 'Enter your AROPay Merchant API Key and Secret. Get yours at %s', 'aropay' ),
                    '<a href="https://arosoftlabs.com/market/plugins/aropay" target="_blank">arosoftlabs.com</a>'
                ),
            ),
            'merchant_api_key' => array(
                'title'       => __( 'Merchant API Key', 'aropay' ),
                'type'        => 'text',
                'description' => __( 'Your AROPay merchant API key.', 'aropay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'merchant_api_secret' => array(
                'title'       => __( 'Merchant API Secret', 'aropay' ),
                'type'        => 'password',
                'description' => __( 'Your AROPay merchant API secret. Keep this confidential.', 'aropay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'auto_detect_network' => array(
                'title'   => __( 'Auto-detect Network', 'aropay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Automatically detect MTN or Airtel from phone number', 'aropay' ),
                'default' => 'yes',
            ),
        );
    }

    /**
     * After saving, validate merchant credentials with AROPay platform.
     */
    public function validate_and_save_credentials() {
        $key    = sanitize_text_field( $_POST['woocommerce_aropay_yo_merchant_api_key'] ?? '' );
        $secret = sanitize_text_field( $_POST['woocommerce_aropay_yo_merchant_api_secret'] ?? '' );

        if ( empty( $key ) || empty( $secret ) ) {
            return;
        }

        // Store for other gateways too
        update_option( 'aropay_merchant_api_key', $key );
        update_option( 'aropay_merchant_api_secret', $secret );

        // Validate against AROPay platform
        $merchant = AROPay_Merchant::validate_credentials( $key, $secret );
        if ( $merchant ) {
            update_option( 'aropay_merchant_id', $merchant->id );
            update_option( 'aropay_merchant_name', $merchant->business_name );
            WC_Admin_Settings::add_message( __( 'AROPay: Merchant credentials verified successfully.', 'aropay' ) );
        } else {
            WC_Admin_Settings::add_error( __( 'AROPay: Could not verify merchant credentials. Please check your API Key and Secret.', 'aropay' ) );
        }
    }

    /**
     * Gateway icon — MTN + Airtel logos.
     */
    public function get_icon() {
        $icon_html  = '<span class="aropay-icons">';
        $icon_html .= '<span class="aropay-icon aropay-icon-mtn" title="MTN Mobile Money" aria-label="MTN Mobile Money"></span>';
        $icon_html .= '<span class="aropay-icon aropay-icon-airtel" title="Airtel Money" aria-label="Airtel Money"></span>';
        $icon_html .= '</span>';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    /**
     * Render checkout fields — phone number + network selector.
     */
    protected function render_payment_fields() {
        $auto_detect = 'yes' === $this->get_option( 'auto_detect_network', 'yes' );
        ?>
        <fieldset class="aropay-momo-fields" id="aropay-yo-fields">
            <div class="aropay-field-row">
                <label for="aropay_phone">
                    <?php esc_html_e( 'Mobile Money Phone Number', 'aropay' ); ?>
                    <abbr class="required" title="<?php esc_attr_e( 'required', 'aropay' ); ?>">*</abbr>
                </label>
                <input
                    type="tel"
                    id="aropay_phone"
                    name="aropay_phone"
                    placeholder="<?php esc_attr_e( 'e.g. 0771234567', 'aropay' ); ?>"
                    autocomplete="tel"
                    class="input-text aropay-phone-input"
                    maxlength="13"
                />
                <span class="aropay-network-badge" id="aropay-network-badge" aria-live="polite"></span>
            </div>

            <?php if ( ! $auto_detect ) : ?>
            <div class="aropay-field-row">
                <label for="aropay_network"><?php esc_html_e( 'Network', 'aropay' ); ?></label>
                <select id="aropay_network" name="aropay_network" class="aropay-network-select">
                    <option value="mtn"><?php esc_html_e( 'MTN Mobile Money', 'aropay' ); ?></option>
                    <option value="airtel"><?php esc_html_e( 'Airtel Money', 'aropay' ); ?></option>
                </select>
            </div>
            <?php endif; ?>

            <p class="aropay-momo-hint">
                <?php esc_html_e( 'You will receive a payment prompt on this number. Enter your PIN to confirm.', 'aropay' ); ?>
            </p>

            <?php if ( $this->test_mode ) : ?>
                <p class="aropay-test-mode-notice">
                    ⚠️ <?php esc_html_e( 'TEST MODE — No real transactions will be processed.', 'aropay' ); ?>
                </p>
            <?php endif; ?>
        </fieldset>

        <div class="aropay-pending-screen" id="aropay-pending-screen" style="display:none;" aria-live="polite" role="status">
            <div class="aropay-spinner" aria-hidden="true"></div>
            <p class="aropay-pending-message"><?php esc_html_e( 'Please check your phone and enter your PIN to complete payment.', 'aropay' ); ?></p>
            <p class="aropay-pending-timer" id="aropay-pending-timer"></p>
        </div>
        <?php
    }

    /**
     * Validate fields before processing.
     */
    public function validate_fields() {
        $phone = sanitize_text_field( wp_unslash( $_POST['aropay_phone'] ?? '' ) );

        if ( empty( $phone ) ) {
            wc_add_notice( __( 'Please enter your Mobile Money phone number.', 'aropay' ), 'error' );
            return false;
        }

        if ( ! AROPay_Helpers::is_valid_ug_phone( $phone ) ) {
            wc_add_notice( __( 'Please enter a valid Ugandan phone number (e.g. 0771234567).', 'aropay' ), 'error' );
            return false;
        }

        return true;
    }

    /**
     * Process payment — initiate STK push and return pending status.
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

        $phone   = sanitize_text_field( wp_unslash( $_POST['aropay_phone'] ?? '' ) );
        $network = sanitize_text_field( wp_unslash( $_POST['aropay_network'] ?? '' ) );

        if ( empty( $network ) ) {
            $network = AROPay_Helpers::detect_network( $phone );
            if ( 'unknown' === $network ) {
                $network = 'mtn';
            }
        }

        $phone_formatted = AROPay_Helpers::format_phone( $phone );
        $internal_ref    = AROPay_Helpers::generate_ref( 'YO' );
        $merchant_id     = AROPay_Merchant::get_merchant_id_for_site();
        $fee             = AROPay_Helpers::calculate_fee( $order->get_total(), $merchant_id );

        $yo     = new AROPay_Yo_API();
        $result = $yo->request_payment( array(
            'amount'    => $order->get_total(),
            'phone'     => $phone_formatted,
            'network'   => $network,
            'reference' => $internal_ref,
            'narrative' => sprintf( __( 'Payment for Order #%s - %s', 'aropay' ), $order->get_order_number(), get_bloginfo( 'name' ) ),
        ) );

        if ( is_wp_error( $result ) ) {
            $this->log( 'STK push failed: ' . $result->get_error_message(), 'error' );
            return $this->payment_failed(
                __( 'Payment initiation failed. Please try again or use a different payment method.', 'aropay' )
            );
        }

        // Record transaction
        AROPay_Transaction::create( array(
            'merchant_id'    => $merchant_id,
            'wc_order_id'    => $order_id,
            'order_id'       => $order->get_order_number(),
            'payment_method' => $network . '_momo',
            'provider'       => 'yo',
            'amount'         => $order->get_total(),
            'currency'       => 'UGX',
            'fee_amount'     => $fee,
            'net_amount'     => $order->get_total() - $fee,
            'customer_phone' => AROPay_Helpers::mask_phone( $phone ),
            'customer_email' => $order->get_billing_email(),
            'customer_name'  => $order->get_formatted_billing_full_name(),
            'provider_ref'   => $result['transaction_reference'],
            'internal_ref'   => $internal_ref,
            'status'         => 'pending',
        ) );

        // Update WC order
        $order->update_status( 'pending', sprintf(
            __( 'AROPay: MoMo payment requested to %s via %s. Ref: %s', 'aropay' ),
            AROPay_Helpers::mask_phone( $phone ),
            strtoupper( $network ),
            $internal_ref
        ) );
        $order->update_meta_data( '_aropay_internal_ref', $internal_ref );
        $order->update_meta_data( '_aropay_provider', 'yo' );
        $order->update_meta_data( '_aropay_network', $network );
        $order->save();

        $this->log( "STK push sent for order #{$order_id}, ref: {$internal_ref}", 'info' );

        // Redirect to receipt page where JS polls for completion
        return $this->payment_redirect( $order->get_checkout_payment_url( true ) );
    }

    /**
     * Receipt page — show pending screen while JS polls.
     *
     * @param int $order_id
     */
    public function receipt_page( $order_id ) {
        $order        = wc_get_order( $order_id );
        $internal_ref = $order ? $order->get_meta( '_aropay_internal_ref' ) : '';
        $network      = $order ? $order->get_meta( '_aropay_network' ) : 'momo';
        include AROPAY_PLUGIN_DIR . 'public/partials/aropay-payment-pending.php';
    }

    /**
     * Process refund (marks as refunded; manual payout via Yo dashboard).
     *
     * @param int    $order_id
     * @param float  $amount
     * @param string $reason
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $transaction = AROPay_Transaction::get_by_order( $order_id );
        if ( ! $transaction ) {
            return new WP_Error( 'aropay_refund_error', __( 'No AROPay transaction found for this order.', 'aropay' ) );
        }

        AROPay_Transaction::update_status( $transaction->id, 'refunded', 'REFUNDED' );
        $this->log( "Refund recorded for order #{$order_id} (manual payout required via Yo dashboard)", 'info' );

        return true;
    }
}

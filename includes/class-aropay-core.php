<?php
/**
 * AROPay Core — singleton that wires all hooks and registers gateways.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

final class AROPay_Core {

    /** @var AROPay_Core */
    private static $instance = null;

    /** @var AROPay_Loader */
    private $loader;

    private function __construct() {
        $this->loader = new AROPay_Loader();
        $this->register_hooks();
        $this->loader->run();
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function register_hooks() {
        // Register WooCommerce payment gateways
        $this->loader->add_filter( 'woocommerce_payment_gateways', $this, 'register_gateways' );

        // REST API routes
        $this->loader->add_action( 'rest_api_init', $this, 'register_rest_routes' );

        // Cron handlers
        $this->loader->add_action( 'aropay_daily_settlement', $this, 'run_daily_settlement' );
        $this->loader->add_action( 'aropay_cleanup_logs', $this, 'cleanup_old_logs' );

        // Enqueue public assets (only on checkout)
        $this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_assets' );

        // WooCommerce Blocks support
        $this->loader->add_action( 'woocommerce_blocks_loaded', $this, 'register_block_support' );

        // AJAX handlers (logged-in and non-logged-in)
        $this->loader->add_action( 'wp_ajax_aropay_check_payment',        $this, 'ajax_check_payment' );
        $this->loader->add_action( 'wp_ajax_nopriv_aropay_check_payment', $this, 'ajax_check_payment' );
        $this->loader->add_action( 'wp_ajax_aropay_initiate_momo',        $this, 'ajax_initiate_momo' );
        $this->loader->add_action( 'wp_ajax_nopriv_aropay_initiate_momo', $this, 'ajax_initiate_momo' );
    }

    /**
     * Register AROPay gateways with WooCommerce.
     */
    public function register_gateways( $gateways ) {
        $gateways[] = 'AROPay_Gateway_Yo_Uganda';
        $gateways[] = 'AROPay_Gateway_Pesapal';
        return $gateways;
    }

    /**
     * Register custom REST API routes.
     */
    public function register_rest_routes() {
        $api = new AROPay_API();
        $api->register_routes();
    }

    /**
     * Enqueue frontend assets — only on checkout pages.
     */
    public function enqueue_public_assets() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        wp_enqueue_style(
            'aropay-public',
            AROPAY_PLUGIN_URL . 'public/css/aropay-public.css',
            array(),
            AROPAY_VERSION
        );

        wp_enqueue_script(
            'aropay-public',
            AROPAY_PLUGIN_URL . 'public/js/aropay-public.js',
            array( 'jquery' ),
            AROPAY_VERSION,
            true
        );

        wp_localize_script( 'aropay-public', 'aropay_params', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'rest_url'      => rest_url( 'aropay/v1/' ),
            'nonce'         => wp_create_nonce( 'aropay_nonce' ),
            'poll_interval' => 5000,   // ms
            'poll_timeout'  => 120000, // 2 minutes
            'i18n'          => array(
                'check_phone'  => __( 'Please check your phone and enter your PIN to complete payment.', 'aropay' ),
                'processing'   => __( 'Processing payment…', 'aropay' ),
                'timeout'      => __( 'Payment timed out. Please try again.', 'aropay' ),
                'failed'       => __( 'Payment failed. Please try again.', 'aropay' ),
                'success'      => __( 'Payment successful! Redirecting…', 'aropay' ),
            ),
        ) );
    }

    /**
     * Register WooCommerce Blocks payment methods.
     */
    public function register_block_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            require_once AROPAY_PLUGIN_DIR . 'gateways/class-aropay-blocks-yo.php';
            require_once AROPAY_PLUGIN_DIR . 'gateways/class-aropay-blocks-pesapal.php';

            add_action( 'woocommerce_blocks_payment_method_type_registration', function( $registry ) {
                $registry->register( new AROPay_Blocks_Yo() );
                $registry->register( new AROPay_Blocks_Pesapal() );
            } );
        }
    }

    /**
     * AJAX: Check payment status for a given transaction.
     */
    public function ajax_check_payment() {
        check_ajax_referer( 'aropay_nonce', 'nonce' );

        $internal_ref = sanitize_text_field( wp_unslash( $_POST['internal_ref'] ?? '' ) );
        if ( empty( $internal_ref ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing reference.', 'aropay' ) ) );
        }

        $transaction = AROPay_Transaction::get_by_ref( $internal_ref );
        if ( ! $transaction ) {
            wp_send_json_error( array( 'message' => __( 'Transaction not found.', 'aropay' ) ) );
        }

        // If still pending, ping provider for live status
        if ( 'pending' === $transaction->status && 'yo' === $transaction->provider ) {
            $yo  = new AROPay_Yo_API();
            $status = $yo->check_transaction_status( $transaction->provider_ref );
            if ( $status ) {
                AROPay_Transaction::update_status( $transaction->id, $status['status'], $status['provider_status'] );
                $transaction->status = $status['status'];
            }
        }

        $redirect = '';
        if ( 'completed' === $transaction->status && $transaction->wc_order_id ) {
            $order    = wc_get_order( $transaction->wc_order_id );
            $redirect = $order ? $order->get_checkout_order_received_url() : wc_get_page_permalink( 'shop' );
        }

        wp_send_json_success( array(
            'status'   => $transaction->status,
            'redirect' => $redirect,
        ) );
    }

    /**
     * AJAX: Initiate MoMo STK push.
     */
    public function ajax_initiate_momo() {
        check_ajax_referer( 'aropay_nonce', 'nonce' );

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $phone    = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $network  = sanitize_text_field( wp_unslash( $_POST['network'] ?? 'mtn' ) );

        if ( ! $order_id || ! $phone ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'aropay' ) ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Order not found.', 'aropay' ) ) );
        }

        $yo           = new AROPay_Yo_API();
        $internal_ref = AROPay_Helpers::generate_ref( 'YO' );
        $result       = $yo->request_payment( array(
            'amount'       => $order->get_total(),
            'phone'        => AROPay_Helpers::format_phone( $phone ),
            'network'      => $network,
            'reference'    => $internal_ref,
            'narrative'    => sprintf( __( 'Payment for Order #%s', 'aropay' ), $order->get_order_number() ),
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Record transaction
        $merchant_id = AROPay_Merchant::get_merchant_id_for_site();
        $fee         = AROPay_Helpers::calculate_fee( $order->get_total(), $merchant_id );

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

        // Mark WC order as pending payment
        $order->update_status( 'pending', __( 'Awaiting MoMo confirmation.', 'aropay' ) );
        $order->update_meta_data( '_aropay_internal_ref', $internal_ref );
        $order->save();

        wp_send_json_success( array(
            'internal_ref' => $internal_ref,
            'message'      => __( 'STK push sent. Please check your phone.', 'aropay' ),
        ) );
    }

    /**
     * Run daily settlement cron.
     */
    public function run_daily_settlement() {
        $settlement = new AROPay_Settlement();
        $settlement->process_all_pending();
    }

    /**
     * Clean up API logs older than 90 days.
     */
    public function cleanup_old_logs() {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}aropay_api_logs WHERE created_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
            )
        );
    }
}

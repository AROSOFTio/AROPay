<?php
/**
 * AROPay Wallet UI — WooCommerce My Account tab, AJAX handlers, and shortcode.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Wallet_UI {

    public function __construct() {
        // WooCommerce My Account endpoint
        add_action( 'init',                                array( $this, 'register_endpoint' ) );
        add_filter( 'query_vars',                          array( $this, 'add_query_vars' ) );
        add_filter( 'woocommerce_account_menu_items',      array( $this, 'add_menu_item' ) );
        add_action( 'woocommerce_account_aropay-wallet_endpoint', array( $this, 'render_wallet_page' ) );

        // Shortcode
        add_shortcode( 'aropay_wallet', array( $this, 'render_shortcode' ) );

        // Enqueue assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX: Withdrawal
        add_action( 'wp_ajax_aropay_wallet_withdraw',       array( $this, 'ajax_withdraw' ) );

        // AJAX: Register phone
        add_action( 'wp_ajax_aropay_register_phone',        array( $this, 'ajax_register_phone' ) );

        // AJAX: Deactivate phone
        add_action( 'wp_ajax_aropay_deactivate_phone',      array( $this, 'ajax_deactivate_phone' ) );

        // AJAX: Reactivate phone
        add_action( 'wp_ajax_aropay_reactivate_phone',      array( $this, 'ajax_reactivate_phone' ) );

        // AJAX: Request phone change
        add_action( 'wp_ajax_aropay_request_phone_change',  array( $this, 'ajax_request_phone_change' ) );

        // AJAX: Get live balance
        add_action( 'wp_ajax_aropay_wallet_balance',        array( $this, 'ajax_wallet_balance' ) );

        // AJAX: Set / change PIN
        add_action( 'wp_ajax_aropay_set_wallet_pin',        array( $this, 'ajax_set_pin' ) );

        // AJAX: Get withdrawal fee preview
        add_action( 'wp_ajax_aropay_withdrawal_fee',        array( $this, 'ajax_get_fee' ) );
        add_action( 'wp_ajax_nopriv_aropay_withdrawal_fee', array( $this, 'ajax_get_fee' ) );
    }

    // ── Endpoint Registration ───────────────────────────────────────────────

    public function register_endpoint() {
        add_rewrite_endpoint( 'aropay-wallet', EP_ROOT | EP_PAGES );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'aropay-wallet';
        return $vars;
    }

    public function add_menu_item( $items ) {
        $logout = $items['customer-logout'] ?? null;
        unset( $items['customer-logout'] );
        $items['aropay-wallet'] = __( '💰 My Wallet', 'aropay' );
        if ( $logout ) {
            $items['customer-logout'] = $logout;
        }
        return $items;
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    public function render_wallet_page() {
        if ( ! is_user_logged_in() ) {
            echo '<p>' . esc_html__( 'Please log in to access your wallet.', 'aropay' ) . '</p>';
            return;
        }
        $this->render_dashboard( get_current_user_id() );
    }

    public function render_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="aropay-wallet-login-prompt">' .
                sprintf(
                    __( 'Please <a href="%s">log in</a> to access your wallet.', 'aropay' ),
                    esc_url( wp_login_url( get_permalink() ) )
                ) .
            '</p>';
        }
        ob_start();
        $this->render_dashboard( get_current_user_id() );
        return ob_get_clean();
    }

    private function render_dashboard( $user_id ) {
        $balance      = AROPay_Wallet::get_balance( $user_id );
        $phones       = AROPay_Wallet_Phone::get_all( $user_id );
        $active_phones = AROPay_Wallet_Phone::get_approved_active( $user_id );
        $history      = AROPay_Withdrawal::get_list( $user_id, 1, 10 );
        $has_pin      = AROPay_Withdrawal::has_pin( $user_id );
        $min_amount   = (float) get_option( 'aropay_min_withdrawal_ugx', 5000 );
        $fee_percent  = (float) get_option( 'aropay_withdrawal_fee_percent', 1.50 );

        // Organise phones by network slot
        $phone_slots = array( 'mtn' => null, 'airtel' => null );
        foreach ( $phones as $p ) {
            if ( isset( $phone_slots[ $p->network ] ) && null === $phone_slots[ $p->network ] ) {
                // Show the most recent non-rejected for each slot
                if ( 'rejected' !== $p->status ) {
                    $phone_slots[ $p->network ] = $p;
                }
            }
        }

        include AROPAY_PLUGIN_DIR . 'public/partials/aropay-wallet-dashboard.php';
    }

    // ── Asset Enqueue ───────────────────────────────────────────────────────

    public function enqueue_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $is_account   = function_exists( 'is_account_page' ) && is_account_page();
        $has_shortcode = is_a( get_post(), 'WP_Post' ) && has_shortcode( get_post()->post_content, 'aropay_wallet' );

        if ( ! $is_account && ! $has_shortcode ) {
            return;
        }

        wp_enqueue_style(
            'aropay-wallet',
            AROPAY_PLUGIN_URL . 'public/css/aropay-wallet.css',
            array(),
            AROPAY_VERSION
        );

        wp_enqueue_script(
            'aropay-wallet',
            AROPAY_PLUGIN_URL . 'public/js/aropay-wallet.js',
            array( 'jquery' ),
            AROPAY_VERSION,
            true
        );

        wp_localize_script( 'aropay-wallet', 'aropay_wallet', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'aropay_wallet_nonce' ),
            'currency'    => 'UGX',
            'min_amount'  => (int) get_option( 'aropay_min_withdrawal_ugx', 5000 ),
            'fee_percent' => (float) get_option( 'aropay_withdrawal_fee_percent', 1.50 ),
            'i18n'        => array(
                'confirm_withdraw'   => __( 'Confirm withdrawal of %s to %s?', 'aropay' ),
                'confirm_deactivate' => __( 'Are you sure you want to deactivate this phone?', 'aropay' ),
                'processing'         => __( 'Processing…', 'aropay' ),
                'success'            => __( 'Success!', 'aropay' ),
                'error'              => __( 'An error occurred. Please try again.', 'aropay' ),
                'pin_required'       => __( 'Please enter your withdrawal PIN.', 'aropay' ),
                'amount_required'    => __( 'Please enter an amount.', 'aropay' ),
            ),
        ) );
    }

    // ── AJAX Handlers ───────────────────────────────────────────────────────

    public function ajax_withdraw() {
        check_ajax_referer( 'aropay_wallet_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'aropay' ) ) );
        }

        $user_id  = get_current_user_id();
        $phone_id = absint( $_POST['phone_id'] ?? 0 );
        $amount   = (float) ( $_POST['amount'] ?? 0 );
        $pin      = sanitize_text_field( wp_unslash( $_POST['pin'] ?? '' ) );

        if ( ! $phone_id || ! $amount || ! $pin ) {
            wp_send_json_error( array( 'message' => __( 'All fields are required.', 'aropay' ) ) );
        }

        $result = AROPay_Withdrawal::initiate( $user_id, $phone_id, $amount, $pin );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $balance = AROPay_Wallet::get_balance( $user_id );
        wp_send_json_success( array(
            'message'     => $result['message'],
            'new_balance' => AROPay_Helpers::format_ugx( $balance['balance'] ),
        ) );
    }

    public function ajax_register_phone() {
        check_ajax_referer( 'aropay_wallet_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'aropay' ) ) );
        }

        $user_id = get_current_user_id();
        $phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $label   = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );

        if ( ! $phone ) {
            wp_send_json_error( array( 'message' => __( 'Phone number is required.', 'aropay' ) ) );
        }

        $result = AROPay_Wallet_Phone::register( $user_id, $phone, $label );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Phone registered successfully. Awaiting admin approval.', 'aropay' ),
        ) );
    }

    public function ajax_deactivate_phone() {
        check_ajax_referer( 'aropay_wallet_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'aropay' ) ) );
        }

        $user_id  = get_current_user_id();
        $phone_id = absint( $_POST['phone_id'] ?? 0 );

        $result = AROPay_Wallet_Phone::deactivate( $phone_id, $user_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Phone deactivated successfully.', 'aropay' ) ) );
    }

    public function ajax_reactivate_phone() {
        check_ajax_referer( 'aropay_wallet_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'aropay' ) ) );
        }

        $user_id  = get_current_user_id();
        $phone_id = absint( $_POST['phone_id'] ?? 0 );

        $result = AROPay_Wallet_Phone::reactivate( $phone_id, $user_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Phone reactivated successfully.', 'aropay' ) ) );
    }

    public function ajax_request_phone_change() {
        check_ajax_referer( 'aropay_wallet_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'aropay' ) ) );
        }

        $user_id   = get_current_user_id();
        $phone_id  = absint( $_POST['phone_id'] ?? 0 );
        $new_phone = sanitize_text_field( wp_unslash( $_POST['new_phone'] ?? '' ) );
        $new_label = sanitize_text_field( wp_unslash( $_POST['new_label'] ?? '' ) );

        if ( ! $phone_id || ! $new_phone ) {
            wp_send_json_error( array( 'message' => __( 'Phone ID and new number are required.', 'aropay' ) ) );
        }

        $result = AROPay_Wallet_Phone::request_change( $phone_id, $user_id, $new_phone, $new_label );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Change request submitted. Admin will review within 24 hours.', 'aropay' ) ) );
    }

    public function ajax_wallet_balance() {
        check_ajax_referer( 'aropay_wallet_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error();
        }

        $balance = AROPay_Wallet::get_balance( get_current_user_id() );
        wp_send_json_success( array(
            'balance'         => $balance['balance'],
            'pending_balance' => $balance['pending_balance'],
            'balance_fmt'     => AROPay_Helpers::format_ugx( $balance['balance'] ),
            'pending_fmt'     => AROPay_Helpers::format_ugx( $balance['pending_balance'] ),
        ) );
    }

    public function ajax_set_pin() {
        check_ajax_referer( 'aropay_wallet_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'aropay' ) ) );
        }

        $user_id     = get_current_user_id();
        $pin         = sanitize_text_field( wp_unslash( $_POST['pin'] ?? '' ) );
        $pin_confirm = sanitize_text_field( wp_unslash( $_POST['pin_confirm'] ?? '' ) );

        if ( $pin !== $pin_confirm ) {
            wp_send_json_error( array( 'message' => __( 'PINs do not match. Please try again.', 'aropay' ) ) );
        }

        // If changing PIN, verify current PIN first
        $current_pin = sanitize_text_field( wp_unslash( $_POST['current_pin'] ?? '' ) );
        if ( AROPay_Withdrawal::has_pin( $user_id ) && ! AROPay_Withdrawal::verify_pin( $user_id, $current_pin ) ) {
            AROPay_Wallet::write_audit( $user_id, 'pin_fail', null, array( 'context' => 'pin_change' ) );
            wp_send_json_error( array( 'message' => __( 'Current PIN is incorrect.', 'aropay' ) ) );
        }

        $result = AROPay_Withdrawal::set_pin( $user_id, $pin );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'PIN updated successfully.', 'aropay' ) ) );
    }

    public function ajax_get_fee() {
        $amount = (float) ( $_GET['amount'] ?? 0 );
        if ( $amount <= 0 ) {
            wp_send_json_error();
        }
        $fee = AROPay_Withdrawal::get_fee( $amount );
        wp_send_json_success( array(
            'fee'       => $fee,
            'fee_fmt'   => AROPay_Helpers::format_ugx( $fee ),
            'total_fmt' => AROPay_Helpers::format_ugx( $amount + $fee ),
        ) );
    }
}

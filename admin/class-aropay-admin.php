<?php
/**
 * AROPay Admin — registers admin menus, pages, and handles admin actions.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_aropay_approve_merchant',  array( $this, 'handle_approve_merchant' ) );
        add_action( 'admin_post_aropay_suspend_merchant',  array( $this, 'handle_suspend_merchant' ) );
        add_action( 'admin_post_aropay_save_settings',     array( $this, 'handle_save_settings' ) );
        add_action( 'admin_post_aropay_trigger_settlement',array( $this, 'handle_trigger_settlement' ) );
        add_action( 'admin_post_aropay_mark_settled',      array( $this, 'handle_mark_settled' ) );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menus() {
        add_menu_page(
            __( 'AROPay', 'aropay' ),
            __( 'AROPay', 'aropay' ),
            'manage_woocommerce',
            'aropay',
            array( $this, 'page_dashboard' ),
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>' ),
            56
        );

        add_submenu_page( 'aropay', __( 'Dashboard', 'aropay' ),    __( 'Dashboard', 'aropay' ),    'manage_woocommerce', 'aropay',                   array( $this, 'page_dashboard' ) );
        add_submenu_page( 'aropay', __( 'Transactions', 'aropay' ), __( 'Transactions', 'aropay' ), 'manage_woocommerce', 'aropay-transactions',       array( $this, 'page_transactions' ) );
        add_submenu_page( 'aropay', __( 'Merchants', 'aropay' ),    __( 'Merchants', 'aropay' ),    'manage_woocommerce', 'aropay-merchants',          array( $this, 'page_merchants' ) );
        add_submenu_page( 'aropay', __( 'Settlements', 'aropay' ),  __( 'Settlements', 'aropay' ),  'manage_woocommerce', 'aropay-settlements',        array( $this, 'page_settlements' ) );
        add_submenu_page( 'aropay', __( 'Settings', 'aropay' ),     __( 'Settings', 'aropay' ),     'manage_woocommerce', 'aropay-settings',           array( $this, 'page_settings' ) );
        add_submenu_page( 'aropay', __( 'API Logs', 'aropay' ),     __( 'API Logs', 'aropay' ),     'manage_woocommerce', 'aropay-logs',               array( $this, 'page_logs' ) );
    }

    /**
     * Enqueue admin CSS and JS.
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'aropay' ) === false ) {
            return;
        }
        wp_enqueue_style(  'aropay-admin', AROPAY_PLUGIN_URL . 'admin/css/aropay-admin.css', array(), AROPAY_VERSION );
        wp_enqueue_script( 'aropay-admin', AROPAY_PLUGIN_URL . 'admin/js/aropay-admin.js',  array( 'jquery', 'wp-util' ), AROPAY_VERSION, true );
        wp_localize_script( 'aropay-admin', 'aropay_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'aropay_admin_nonce' ),
        ) );
    }

    // ── Page renderers ───────────────────────────────────────────────────

    public function page_dashboard() {
        $today_stats = AROPay_Transaction::get_today_stats();
        global $wpdb;
        $active_merchants  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_merchants WHERE status='active'" );
        $pending_merchants = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_merchants WHERE status='pending'" );
        $pending_settlements = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_settlements WHERE status='pending'" );
        include AROPAY_PLUGIN_DIR . 'admin/partials/aropay-admin-dashboard.php';
    }

    public function page_transactions() {
        $page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $filters  = array(
            'status'      => sanitize_text_field( $_GET['status'] ?? 'all' ),
            'provider'    => sanitize_text_field( $_GET['provider'] ?? 'all' ),
            'date_from'   => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'     => sanitize_text_field( $_GET['date_to'] ?? '' ),
        );
        $result = AROPay_Transaction::get_list( $filters, $page, 25 );
        $transactions = $result['transactions'];
        $total        = $result['total'];
        $total_pages  = ceil( $total / 25 );
        include AROPAY_PLUGIN_DIR . 'admin/partials/aropay-admin-transactions.php';
    }

    public function page_merchants() {
        $page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $status = sanitize_text_field( $_GET['status'] ?? 'all' );
        $result = AROPay_Merchant::get_list( $page, 20, $status );
        $merchants   = $result['merchants'];
        $total       = $result['total'];
        $total_pages = ceil( $total / 20 );
        include AROPAY_PLUGIN_DIR . 'admin/partials/aropay-admin-merchants.php';
    }

    public function page_settlements() {
        $settlement_obj = new AROPay_Settlement();
        $page    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $result  = $settlement_obj->get_list( $page, 20 );
        $settlements = $result['settlements'];
        $total       = $result['total'];
        $total_pages = ceil( $total / 20 );
        include AROPAY_PLUGIN_DIR . 'admin/partials/aropay-admin-settlements.php';
    }

    public function page_settings() {
        $active_tab = sanitize_text_field( $_GET['tab'] ?? 'yo' );
        include AROPAY_PLUGIN_DIR . 'admin/partials/aropay-admin-settings.php';
    }

    public function page_logs() {
        global $wpdb;
        $page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset = ( $page - 1 ) * 50;
        $provider = sanitize_text_field( $_GET['provider'] ?? 'all' );
        $where = 'all' !== $provider ? $wpdb->prepare( 'WHERE provider = %s', $provider ) : '';
        $logs  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_api_logs $where ORDER BY created_at DESC LIMIT 50 OFFSET %d",
            $offset
        ) );
        $total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_api_logs $where" );
        $total_pages = ceil( $total / 50 );
        include AROPAY_PLUGIN_DIR . 'admin/partials/aropay-admin-logs.php';
    }

    // ── Action handlers ──────────────────────────────────────────────────

    public function handle_approve_merchant() {
        check_admin_referer( 'aropay_merchant_action' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );
        $id = absint( $_POST['merchant_id'] ?? 0 );
        AROPay_Merchant::update_status( $id, 'active' );
        wp_redirect( admin_url( 'admin.php?page=aropay-merchants&msg=approved' ) );
        exit;
    }

    public function handle_suspend_merchant() {
        check_admin_referer( 'aropay_merchant_action' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );
        $id = absint( $_POST['merchant_id'] ?? 0 );
        AROPay_Merchant::update_status( $id, 'suspended' );
        wp_redirect( admin_url( 'admin.php?page=aropay-merchants&msg=suspended' ) );
        exit;
    }

    public function handle_save_settings() {
        check_admin_referer( 'aropay_save_settings' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        $tab = sanitize_text_field( $_POST['aropay_tab'] ?? 'yo' );

        if ( 'yo' === $tab ) {
            AROPay_Encryption::set_option( 'aropay_yo_username', sanitize_text_field( $_POST['yo_username'] ?? '' ) );
            AROPay_Encryption::set_option( 'aropay_yo_password', sanitize_text_field( $_POST['yo_password'] ?? '' ) );
            update_option( 'aropay_yo_test_mode', sanitize_text_field( $_POST['yo_test_mode'] ?? 'yes' ) );
        }

        if ( 'pesapal' === $tab ) {
            AROPay_Encryption::set_option( 'aropay_pesapal_consumer_key',    sanitize_text_field( $_POST['pesapal_consumer_key'] ?? '' ) );
            AROPay_Encryption::set_option( 'aropay_pesapal_consumer_secret', sanitize_text_field( $_POST['pesapal_consumer_secret'] ?? '' ) );
            update_option( 'aropay_pesapal_test_mode', sanitize_text_field( $_POST['pesapal_test_mode'] ?? 'yes' ) );
            // Clear cached token so next call re-authenticates
            delete_transient( 'aropay_pesapal_token' );
            delete_option( 'aropay_pesapal_ipn_id' );
        }

        if ( 'fees' === $tab ) {
            update_option( 'aropay_default_fee_percent',  (float) ( $_POST['default_fee_percent'] ?? 1.50 ) );
            update_option( 'aropay_min_fee_ugx',          absint( $_POST['min_fee_ugx'] ?? 500 ) );
            update_option( 'aropay_settlement_schedule',  sanitize_text_field( $_POST['settlement_schedule'] ?? 'daily' ) );
        }

        if ( 'branding' === $tab ) {
            update_option( 'aropay_plugin_display_name', sanitize_text_field( $_POST['display_name'] ?? 'AROPay' ) );
            update_option( 'aropay_support_email',       sanitize_email( $_POST['support_email'] ?? '' ) );
            update_option( 'aropay_support_phone',       sanitize_text_field( $_POST['support_phone'] ?? '' ) );
        }

        wp_redirect( admin_url( "admin.php?page=aropay-settings&tab={$tab}&saved=1" ) );
        exit;
    }

    public function handle_trigger_settlement() {
        check_admin_referer( 'aropay_settlement_action' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );
        $merchant_id = absint( $_POST['merchant_id'] ?? 0 );
        $obj = new AROPay_Settlement();
        if ( $merchant_id ) {
            $obj->process_for_merchant( $merchant_id );
        } else {
            $obj->process_all_pending();
        }
        wp_redirect( admin_url( 'admin.php?page=aropay-settlements&msg=triggered' ) );
        exit;
    }

    public function handle_mark_settled() {
        check_admin_referer( 'aropay_settlement_action' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );
        $id  = absint( $_POST['settlement_id'] ?? 0 );
        $ref = sanitize_text_field( $_POST['settlement_ref'] ?? '' );
        $obj = new AROPay_Settlement();
        $obj->mark_settled( $id, $ref );
        wp_redirect( admin_url( 'admin.php?page=aropay-settlements&msg=settled' ) );
        exit;
    }
}

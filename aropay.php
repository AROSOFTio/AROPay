<?php
/**
 * Plugin Name:       AROPay
 * Plugin URI:        https://arosoftlabs.com/market/plugins/aropay
 * Description:       Uganda's premier payment gateway — MTN MoMo, Airtel Money & Cards via Yo Uganda and Pesapal OpenFloat. Powered by AROSOFT.
 * Version:           1.0.0
 * Author:            AROSOFT
 * Author URI:        https://arosoftlabs.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aropay
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * WC requires at least: 4.0
 * WC tested up to:   8.0
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'AROPAY_VERSION',      '1.0.0' );
define( 'AROPAY_PLUGIN_FILE',  __FILE__ );
define( 'AROPAY_PLUGIN_DIR',   plugin_dir_path( __FILE__ ) );
define( 'AROPAY_PLUGIN_URL',   plugin_dir_url( __FILE__ ) );
define( 'AROPAY_PLUGIN_BASE',  plugin_basename( __FILE__ ) );
define( 'AROPAY_MIN_WC',       '4.0' );
define( 'AROPAY_MIN_PHP',      '7.4' );
define( 'AROPAY_CURRENCY',     'UGX' );
define( 'AROPAY_TIMEZONE',     'Africa/Kampala' );

/**
 * Check dependencies before loading anything.
 */
function aropay_check_dependencies() {
    $errors = array();

    if ( ! defined( 'WC_VERSION' ) ) {
        $errors[] = __( 'AROPay requires WooCommerce to be installed and active.', 'aropay' );
    } elseif ( version_compare( WC_VERSION, AROPAY_MIN_WC, '<' ) ) {
        $errors[] = sprintf(
            __( 'AROPay requires WooCommerce %s or higher. You are running %s.', 'aropay' ),
            AROPAY_MIN_WC,
            WC_VERSION
        );
    }

    if ( version_compare( PHP_VERSION, AROPAY_MIN_PHP, '<' ) ) {
        $errors[] = sprintf(
            __( 'AROPay requires PHP %s or higher. You are running PHP %s.', 'aropay' ),
            AROPAY_MIN_PHP,
            PHP_VERSION
        );
    }

    return $errors;
}

/**
 * Show admin notice if dependencies are missing.
 */
function aropay_dependency_notice() {
    $errors = aropay_check_dependencies();
    foreach ( $errors as $error ) {
        echo '<div class="notice notice-error"><p><strong>AROPay:</strong> ' . esc_html( $error ) . '</p></div>';
    }
}

/**
 * Bootstrap the plugin.
 */
function aropay_init() {
    $errors = aropay_check_dependencies();
    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', 'aropay_dependency_notice' );
        return;
    }

    // Load text domain
    load_plugin_textdomain( 'aropay', false, dirname( AROPAY_PLUGIN_BASE ) . '/languages' );

    // Load core files
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-loader.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-helpers.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-encryption.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-merchant.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-transaction.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-settlement.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-webhook.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-api.php';
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-core.php';

    // Load gateway files
    require_once AROPAY_PLUGIN_DIR . 'gateways/class-aropay-gateway-base.php';
    require_once AROPAY_PLUGIN_DIR . 'api/yo-uganda/class-yo-api.php';
    require_once AROPAY_PLUGIN_DIR . 'api/yo-uganda/class-yo-callback.php';
    require_once AROPAY_PLUGIN_DIR . 'api/pesapal/class-pesapal-api.php';
    require_once AROPAY_PLUGIN_DIR . 'api/pesapal/class-pesapal-ipn.php';
    require_once AROPAY_PLUGIN_DIR . 'gateways/class-aropay-yo-uganda.php';
    require_once AROPAY_PLUGIN_DIR . 'gateways/class-aropay-pesapal.php';

    // Admin files
    if ( is_admin() ) {
        require_once AROPAY_PLUGIN_DIR . 'admin/class-aropay-admin.php';
        new AROPay_Admin();
    }

    // Boot the core
    AROPay_Core::instance();
}
add_action( 'plugins_loaded', 'aropay_init', 0 );

/**
 * Activation hook.
 */
function aropay_activate() {
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-activator.php';
    AROPay_Activator::activate();
}
register_activation_hook( __FILE__, 'aropay_activate' );

/**
 * Deactivation hook.
 */
function aropay_deactivate() {
    require_once AROPAY_PLUGIN_DIR . 'includes/class-aropay-deactivator.php';
    AROPay_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'aropay_deactivate' );

/**
 * Declare WooCommerce HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

/**
 * Add plugin action links.
 */
function aropay_plugin_action_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=aropay-settings' ) . '">' . __( 'Settings', 'aropay' ) . '</a>',
        '<a href="https://arosoftlabs.com/market/plugins/aropay" target="_blank">' . __( 'Docs', 'aropay' ) . '</a>',
    );
    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . AROPAY_PLUGIN_BASE, 'aropay_plugin_action_links' );

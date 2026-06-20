<?php
/**
 * AROPay Uninstall — runs when the plugin is deleted from WordPress.
 * Drops all plugin tables and removes all options.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/* ── Drop custom tables ── */
$tables = array(
    $wpdb->prefix . 'aropay_merchants',
    $wpdb->prefix . 'aropay_transactions',
    $wpdb->prefix . 'aropay_settlements',
    $wpdb->prefix . 'aropay_api_logs',
);

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

/* ── Delete all plugin options ── */
$options = array(
    'aropay_version',
    'aropay_activated_at',
    'aropay_yo_test_mode',
    'aropay_yo_username',
    'aropay_yo_password',
    'aropay_pesapal_test_mode',
    'aropay_pesapal_consumer_key',
    'aropay_pesapal_consumer_secret',
    'aropay_pesapal_ipn_id',
    'aropay_default_fee_percent',
    'aropay_min_fee_ugx',
    'aropay_settlement_schedule',
    'aropay_support_email',
    'aropay_support_phone',
    'aropay_plugin_display_name',
    'aropay_merchant_id',
    'aropay_merchant_name',
    'aropay_merchant_api_key',
    'aropay_merchant_api_secret',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

/* ── Clear transients ── */
delete_transient( 'aropay_pesapal_token' );

/* ── Remove cron events ── */
wp_clear_scheduled_hook( 'aropay_daily_settlement' );
wp_clear_scheduled_hook( 'aropay_cleanup_logs' );

/* ── Remove WooCommerce gateway settings ── */
delete_option( 'woocommerce_aropay_yo_settings' );
delete_option( 'woocommerce_aropay_pesapal_settings' );

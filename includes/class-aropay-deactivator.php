<?php
/**
 * AROPay Deactivator.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'aropay_daily_settlement' );
        wp_clear_scheduled_hook( 'aropay_cleanup_logs' );
        flush_rewrite_rules();
    }
}

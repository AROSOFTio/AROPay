<?php
/**
 * AROPay Activator — runs on plugin activation, creates all DB tables.
 *
 * @package AROPay
 * @author  AROSOFT
 * @link    https://arosoftlabs.com/market/plugins/aropay
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Activator {

    /**
     * Run activation routines.
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::schedule_events();
        update_option( 'aropay_version', AROPAY_VERSION );
        update_option( 'aropay_activated_at', current_time( 'mysql' ) );
        flush_rewrite_rules();
    }

    /**
     * Create all required database tables.
     */
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── Merchants ────────────────────────────────────────────────
        $sql_merchants = "CREATE TABLE {$wpdb->prefix}aropay_merchants (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20) UNSIGNED DEFAULT NULL,
            business_name   VARCHAR(255) NOT NULL,
            business_type   VARCHAR(100) DEFAULT NULL,
            phone           VARCHAR(20)  NOT NULL,
            email           VARCHAR(150) NOT NULL,
            tin_number      VARCHAR(50)  DEFAULT NULL,
            status          ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
            api_key         VARCHAR(64)  NOT NULL,
            api_secret      VARCHAR(255) NOT NULL,
            settlement_account   VARCHAR(50)  DEFAULT NULL,
            settlement_type      ENUM('mtn','airtel','bank') DEFAULT 'mtn',
            transaction_fee_percent DECIMAL(5,2) NOT NULL DEFAULT '1.50',
            notes           TEXT         DEFAULT NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;";

        // ── Transactions ─────────────────────────────────────────────
        $sql_transactions = "CREATE TABLE {$wpdb->prefix}aropay_transactions (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            merchant_id      BIGINT(20) UNSIGNED NOT NULL,
            order_id         VARCHAR(100) DEFAULT NULL,
            wc_order_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            payment_method   VARCHAR(50)  NOT NULL,
            provider         ENUM('yo','pesapal') NOT NULL,
            amount           DECIMAL(15,2) NOT NULL,
            currency         VARCHAR(10)  NOT NULL DEFAULT 'UGX',
            fee_amount       DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            net_amount       DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            customer_phone   VARCHAR(20)  DEFAULT NULL,
            customer_email   VARCHAR(150) DEFAULT NULL,
            customer_name    VARCHAR(255) DEFAULT NULL,
            provider_ref     VARCHAR(255) DEFAULT NULL,
            internal_ref     VARCHAR(100) NOT NULL,
            status           ENUM('pending','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
            provider_status  VARCHAR(100) DEFAULT NULL,
            ipn_data         LONGTEXT     DEFAULT NULL,
            failure_reason   TEXT         DEFAULT NULL,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY internal_ref (internal_ref),
            KEY merchant_id (merchant_id),
            KEY wc_order_id (wc_order_id),
            KEY status (status),
            KEY provider (provider),
            KEY created_at (created_at)
        ) $charset;";

        // ── Settlements ──────────────────────────────────────────────
        $sql_settlements = "CREATE TABLE {$wpdb->prefix}aropay_settlements (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            merchant_id      BIGINT(20) UNSIGNED NOT NULL,
            period_start     DATETIME     NOT NULL,
            period_end       DATETIME     NOT NULL,
            total_amount     DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            fee_amount       DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            net_amount       DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            transaction_count INT(11) NOT NULL DEFAULT '0',
            status           ENUM('pending','processing','settled','failed') NOT NULL DEFAULT 'pending',
            settlement_method VARCHAR(50)  DEFAULT NULL,
            settlement_ref   VARCHAR(255) DEFAULT NULL,
            notes            TEXT         DEFAULT NULL,
            settled_at       DATETIME     DEFAULT NULL,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY merchant_id (merchant_id),
            KEY status (status)
        ) $charset;";

        // ── API Logs ─────────────────────────────────────────────────
        $sql_logs = "CREATE TABLE {$wpdb->prefix}aropay_api_logs (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            merchant_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            transaction_id   BIGINT(20) UNSIGNED DEFAULT NULL,
            provider         VARCHAR(50)  NOT NULL,
            request_type     VARCHAR(100) NOT NULL,
            request_body     LONGTEXT     DEFAULT NULL,
            response_body    LONGTEXT     DEFAULT NULL,
            status_code      INT(11)      DEFAULT NULL,
            duration_ms      INT(11)      DEFAULT NULL,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY merchant_id (merchant_id),
            KEY provider (provider),
            KEY created_at (created_at)
        ) $charset;";

        // ── Wallets ──────────────────────────────────────────────────
        $sql_wallets = "CREATE TABLE {$wpdb->prefix}aropay_wallets (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id           BIGINT(20) UNSIGNED NOT NULL,
            balance           DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            pending_balance   DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            currency          VARCHAR(10)  NOT NULL DEFAULT 'UGX',
            total_credited    DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            total_withdrawn   DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset;";

        // ── Wallet Phones ─────────────────────────────────────────────
        $sql_wallet_phones = "CREATE TABLE {$wpdb->prefix}aropay_wallet_phones (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id           BIGINT(20) UNSIGNED NOT NULL,
            phone             VARCHAR(20)  NOT NULL,
            network           ENUM('mtn','airtel') NOT NULL,
            label             VARCHAR(100) DEFAULT NULL,
            status            ENUM('pending','approved','rejected','inactive') NOT NULL DEFAULT 'pending',
            is_active         TINYINT(1)   NOT NULL DEFAULT 1,
            approved_by       BIGINT(20) UNSIGNED DEFAULT NULL,
            approved_at       DATETIME     DEFAULT NULL,
            rejection_note    TEXT         DEFAULT NULL,
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY network (network)
        ) $charset;";

        // ── Wallet Phone Change Requests ──────────────────────────────
        $sql_phone_changes = "CREATE TABLE {$wpdb->prefix}aropay_wallet_phone_changes (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id           BIGINT(20) UNSIGNED NOT NULL,
            phone_id          BIGINT(20) UNSIGNED NOT NULL,
            new_phone         VARCHAR(20)  NOT NULL,
            new_label         VARCHAR(100) DEFAULT NULL,
            network           ENUM('mtn','airtel') NOT NULL,
            status            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewed_by       BIGINT(20) UNSIGNED DEFAULT NULL,
            reviewed_at       DATETIME     DEFAULT NULL,
            notes             TEXT         DEFAULT NULL,
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY phone_id (phone_id),
            KEY status (status)
        ) $charset;";

        // ── Wallet Withdrawals ────────────────────────────────────────
        $sql_withdrawals = "CREATE TABLE {$wpdb->prefix}aropay_wallet_withdrawals (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wallet_id         BIGINT(20) UNSIGNED NOT NULL,
            user_id           BIGINT(20) UNSIGNED NOT NULL,
            phone_id          BIGINT(20) UNSIGNED NOT NULL,
            amount            DECIMAL(15,2) NOT NULL,
            fee               DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            net_amount        DECIMAL(15,2) NOT NULL DEFAULT '0.00',
            provider_ref      VARCHAR(255) DEFAULT NULL,
            internal_ref      VARCHAR(100) NOT NULL,
            status            ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            failure_reason    TEXT         DEFAULT NULL,
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY internal_ref (internal_ref),
            KEY wallet_id (wallet_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset;";

        // ── Wallet Audit Log ──────────────────────────────────────────
        $sql_audit = "CREATE TABLE {$wpdb->prefix}aropay_wallet_audit_log (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id           BIGINT(20) UNSIGNED NOT NULL,
            action            VARCHAR(60)  NOT NULL,
            amount            DECIMAL(15,2) DEFAULT NULL,
            meta              LONGTEXT     DEFAULT NULL,
            ip_address        VARCHAR(45)  DEFAULT NULL,
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset;";

        dbDelta( $sql_merchants );
        dbDelta( $sql_transactions );
        dbDelta( $sql_settlements );
        dbDelta( $sql_logs );
        dbDelta( $sql_wallets );
        dbDelta( $sql_wallet_phones );
        dbDelta( $sql_phone_changes );
        dbDelta( $sql_withdrawals );
        dbDelta( $sql_audit );
    }

    /**
     * Set sensible default options.
     */
    private static function set_default_options() {
        $defaults = array(
            'aropay_yo_test_mode'            => 'yes',
            'aropay_pesapal_test_mode'       => 'yes',
            'aropay_default_fee_percent'     => '1.50',
            'aropay_min_fee_ugx'             => '500',
            'aropay_settlement_schedule'     => 'daily',
            'aropay_support_email'           => get_option( 'admin_email' ),
            'aropay_support_phone'           => '',
            'aropay_plugin_display_name'     => 'AROPay',
            'aropay_yo_username'             => '',
            'aropay_yo_password'             => '',
            'aropay_pesapal_consumer_key'    => '',
            'aropay_pesapal_consumer_secret' => '',
            'aropay_withdrawal_fee_percent'  => '1.50',
            'aropay_min_withdrawal_ugx'      => '5000',
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Schedule WP-Cron events.
     */
    private static function schedule_events() {
        if ( ! wp_next_scheduled( 'aropay_daily_settlement' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 02:00:00' ), 'daily', 'aropay_daily_settlement' );
        }
        if ( ! wp_next_scheduled( 'aropay_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'weekly', 'aropay_cleanup_logs' );
        }
    }
}

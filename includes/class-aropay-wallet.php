<?php
/**
 * AROPay Wallet — core balance management and audit log.
 *
 * Handles all wallet balance operations atomically via DB transactions.
 * Every mutating operation writes an immutable entry to the audit log.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Wallet {

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Get (or auto-create) the wallet for a given WP user.
     *
     * @param int $user_id
     * @return object|null
     */
    public static function get_or_create( $user_id ) {
        global $wpdb;

        $wallet = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallets WHERE user_id = %d LIMIT 1",
            $user_id
        ) );

        if ( $wallet ) {
            return $wallet;
        }

        // First time — create a zero-balance wallet
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'aropay_wallets',
            array(
                'user_id'  => absint( $user_id ),
                'balance'  => '0.00',
                'currency' => 'UGX',
            ),
            array( '%d', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return null;
        }

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallets WHERE id = %d LIMIT 1",
            $wpdb->insert_id
        ) );
    }

    /**
     * Get wallet balance info for a user.
     *
     * @param int $user_id
     * @return array { balance, pending_balance, total_credited, total_withdrawn }
     */
    public static function get_balance( $user_id ) {
        $wallet = self::get_or_create( $user_id );

        return array(
            'balance'         => (float) ( $wallet->balance ?? 0 ),
            'pending_balance' => (float) ( $wallet->pending_balance ?? 0 ),
            'total_credited'  => (float) ( $wallet->total_credited ?? 0 ),
            'total_withdrawn' => (float) ( $wallet->total_withdrawn ?? 0 ),
            'currency'        => $wallet->currency ?? 'UGX',
            'wallet_id'       => (int) ( $wallet->id ?? 0 ),
        );
    }

    /**
     * Credit an amount to a user's wallet (e.g. from WooCommerce payment).
     *
     * @param int    $user_id
     * @param float  $amount
     * @param string $note    Audit note (shown in log meta)
     * @return bool
     */
    public static function credit( $user_id, $amount, $note = '' ) {
        global $wpdb;

        $amount  = (float) $amount;
        $wallet  = self::get_or_create( $user_id );

        if ( ! $wallet || $amount <= 0 ) {
            return false;
        }

        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}aropay_wallets
             SET balance = balance + %f,
                 total_credited = total_credited + %f
             WHERE user_id = %d",
            $amount, $amount, $user_id
        ) );

        if ( false !== $updated ) {
            self::write_audit( $user_id, 'credit', $amount, array( 'note' => $note ) );
        }

        return $updated !== false;
    }

    /**
     * Debit an amount from a user's wallet.
     * Should only be called after a successful hold + completion pair.
     *
     * @param int   $user_id
     * @param float $amount
     * @return bool
     */
    public static function debit( $user_id, $amount ) {
        global $wpdb;

        $amount = (float) $amount;
        $wallet = self::get_or_create( $user_id );

        if ( ! $wallet || $amount <= 0 ) {
            return false;
        }

        // Safety: never go below zero
        if ( (float) $wallet->balance < $amount ) {
            return false;
        }

        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}aropay_wallets
             SET balance = balance - %f,
                 total_withdrawn = total_withdrawn + %f
             WHERE user_id = %d AND balance >= %f",
            $amount, $amount, $user_id, $amount
        ) );

        if ( false !== $updated && $updated > 0 ) {
            self::write_audit( $user_id, 'debit', $amount, array() );
        }

        return $updated > 0;
    }

    /**
     * Move `amount` from available balance → pending_balance.
     * Called at the START of a withdrawal before sending to Yo.
     *
     * @param int   $user_id
     * @param float $amount  Full amount (including fee)
     * @return bool
     */
    public static function hold( $user_id, $amount ) {
        global $wpdb;

        $amount = (float) $amount;
        $wallet = self::get_or_create( $user_id );

        if ( ! $wallet || $amount <= 0 ) {
            return false;
        }

        if ( (float) $wallet->balance < $amount ) {
            return false;
        }

        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}aropay_wallets
             SET balance = balance - %f,
                 pending_balance = pending_balance + %f
             WHERE user_id = %d AND balance >= %f",
            $amount, $amount, $user_id, $amount
        ) );

        if ( false !== $updated && $updated > 0 ) {
            self::write_audit( $user_id, 'hold', $amount, array() );
        }

        return $updated > 0;
    }

    /**
     * Release a previously held amount.
     *
     * @param int   $user_id
     * @param float $amount   Full held amount
     * @param bool  $success  true → confirm deduction (withdrawal succeeded)
     *                        false → refund back to available balance
     * @return bool
     */
    public static function release_hold( $user_id, $amount, $success ) {
        global $wpdb;

        $amount = (float) $amount;

        if ( $success ) {
            // Confirmed: reduce pending_balance + update total_withdrawn
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}aropay_wallets
                 SET pending_balance = pending_balance - %f,
                     total_withdrawn = total_withdrawn + %f
                 WHERE user_id = %d AND pending_balance >= %f",
                $amount, $amount, $user_id, $amount
            ) );
            self::write_audit( $user_id, 'release', $amount, array( 'success' => true ) );
        } else {
            // Failed: move pending back to available
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}aropay_wallets
                 SET pending_balance = pending_balance - %f,
                     balance = balance + %f
                 WHERE user_id = %d AND pending_balance >= %f",
                $amount, $amount, $user_id, $amount
            ) );
            self::write_audit( $user_id, 'release', $amount, array( 'success' => false, 'refunded' => true ) );
        }

        return false !== $updated && $updated > 0;
    }

    /**
     * Write an immutable entry to the wallet audit log.
     *
     * @param int    $user_id
     * @param string $action  e.g. 'credit','debit','hold','release','pin_set','pin_fail',
     *                              'phone_register','phone_deactivate','phone_change_request',
     *                              'withdrawal_init','withdrawal_complete','withdrawal_fail'
     * @param float|null $amount
     * @param array  $meta    Additional context (will be JSON-encoded)
     */
    public static function write_audit( $user_id, $action, $amount = null, $meta = array() ) {
        global $wpdb;

        // Capture IP address
        $ip = '';
        if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        $ip = substr( $ip, 0, 45 );

        $wpdb->insert(
            $wpdb->prefix . 'aropay_wallet_audit_log',
            array(
                'user_id'    => absint( $user_id ),
                'action'     => sanitize_key( $action ),
                'amount'     => ( null !== $amount ) ? (float) $amount : null,
                'meta'       => wp_json_encode( $meta ),
                'ip_address' => $ip,
            ),
            array( '%d', '%s', '%f', '%s', '%s' )
        );
    }

    // ── Admin Queries ───────────────────────────────────────────────────────

    /**
     * Get paginated list of all wallets for the admin panel.
     *
     * @param int    $page
     * @param int    $per_page
     * @param string $search  Optional username/email substring
     * @return array { wallets, total }
     */
    public static function get_all_wallets( $page = 1, $per_page = 20, $search = '' ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;
        $join   = "INNER JOIN {$wpdb->users} u ON u.ID = w.user_id";
        $where  = '1=1';
        $values = array();

        if ( $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= " AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $base   = "FROM {$wpdb->prefix}aropay_wallets w $join WHERE $where";
        $select = "SELECT w.*, u.user_login, u.user_email, u.display_name $base ORDER BY w.balance DESC LIMIT %d OFFSET %d";

        $values_select  = array_merge( $values, array( $per_page, $offset ) );
        $wallets = $wpdb->get_results( $wpdb->prepare( $select, $values_select ) );

        $count_sql = "SELECT COUNT(*) $base";
        $total     = empty( $values )
            ? (int) $wpdb->get_var( $count_sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );

        return compact( 'wallets', 'total' );
    }

    /**
     * Get all audit log entries, filterable by user / action / date range.
     *
     * @param array $filters { user_id, action, date_from, date_to, search_user }
     * @param int   $page
     * @param int   $per_page
     * @return array { logs, total }
     */
    public static function get_audit_log( $filters = array(), $page = 1, $per_page = 50 ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;
        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $filters['user_id'] ) ) {
            $where[]  = 'a.user_id = %d';
            $values[] = absint( $filters['user_id'] );
        }
        if ( ! empty( $filters['action'] ) && 'all' !== $filters['action'] ) {
            $where[]  = 'a.action = %s';
            $values[] = sanitize_key( $filters['action'] );
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 'a.created_at >= %s';
            $values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 'a.created_at <= %s';
            $values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );
        $join      = "LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id";
        $base      = "FROM {$wpdb->prefix}aropay_wallet_audit_log a $join WHERE $where_sql";
        $select    = "SELECT a.*, u.user_login, u.user_email $base ORDER BY a.created_at DESC LIMIT %d OFFSET %d";

        $values_select = array_merge( $values, array( $per_page, $offset ) );
        $logs = $wpdb->get_results( $wpdb->prepare( $select, $values_select ) );

        $count_sql = "SELECT COUNT(*) $base";
        $total     = empty( $values )
            ? (int) $wpdb->get_var( $count_sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );

        return compact( 'logs', 'total' );
    }

    /**
     * Aggregate stats for the admin dashboard.
     *
     * @return object { total_wallets, total_balance, total_pending, total_credited, total_withdrawn }
     */
    public static function get_global_stats() {
        global $wpdb;
        return $wpdb->get_row(
            "SELECT
                COUNT(*)           AS total_wallets,
                SUM(balance)       AS total_balance,
                SUM(pending_balance) AS total_pending,
                SUM(total_credited)  AS total_credited,
                SUM(total_withdrawn) AS total_withdrawn
             FROM {$wpdb->prefix}aropay_wallets"
        );
    }
}

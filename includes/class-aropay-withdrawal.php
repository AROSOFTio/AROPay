<?php
/**
 * AROPay Withdrawal — orchestrates the full payout flow.
 *
 * Flow:
 *  1. Validate PIN, phone usability, minimum amount, sufficient balance
 *  2. Hold the amount atomically in the wallet
 *  3. Call Yo Uganda B2C API
 *  4. Record the withdrawal in DB + write audit log
 *  5. On IPN callback: update status + release hold
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Withdrawal {

    // ── PIN Management ──────────────────────────────────────────────────────

    /**
     * Set or change a user's withdrawal PIN.
     *
     * @param int    $user_id
     * @param string $new_pin   Plain-text (will be hashed immediately)
     * @return bool|WP_Error
     */
    public static function set_pin( $user_id, $new_pin ) {
        if ( strlen( $new_pin ) < 4 || strlen( $new_pin ) > 8 ) {
            return new WP_Error( 'invalid_pin', __( 'PIN must be between 4 and 8 digits.', 'aropay' ) );
        }
        if ( ! ctype_digit( $new_pin ) ) {
            return new WP_Error( 'invalid_pin', __( 'PIN must contain digits only.', 'aropay' ) );
        }

        $hashed = wp_hash_password( $new_pin );
        update_user_meta( $user_id, 'aropay_wallet_pin', $hashed );

        AROPay_Wallet::write_audit( $user_id, 'pin_set', null, array() );

        return true;
    }

    /**
     * Check if a user has a PIN set.
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_pin( $user_id ) {
        return (bool) get_user_meta( $user_id, 'aropay_wallet_pin', true );
    }

    /**
     * Verify a plain-text PIN against the stored hash.
     *
     * @param int    $user_id
     * @param string $plain_pin
     * @return bool
     */
    public static function verify_pin( $user_id, $plain_pin ) {
        $hash = get_user_meta( $user_id, 'aropay_wallet_pin', true );
        if ( ! $hash ) {
            return false;
        }
        return wp_check_password( $plain_pin, $hash, $user_id );
    }

    // ── Withdrawal Initiation ───────────────────────────────────────────────

    /**
     * Initiate a withdrawal.
     *
     * @param int    $user_id
     * @param int    $phone_id
     * @param float  $amount   Amount the user wants to receive (net)
     * @param string $pin      Plain-text PIN entered by user
     * @return array|WP_Error  { internal_ref, message } or WP_Error
     */
    public static function initiate( $user_id, $phone_id, $amount, $pin ) {
        $amount = (float) $amount;

        // 1. PIN check
        if ( ! self::has_pin( $user_id ) ) {
            AROPay_Wallet::write_audit( $user_id, 'pin_fail', null, array( 'reason' => 'no_pin_set' ) );
            return new WP_Error( 'no_pin', __( 'You have not set a withdrawal PIN yet. Please set your PIN first.', 'aropay' ) );
        }

        if ( ! self::verify_pin( $user_id, $pin ) ) {
            AROPay_Wallet::write_audit( $user_id, 'pin_fail', null, array( 'phone_id' => $phone_id, 'amount' => $amount ) );
            return new WP_Error( 'wrong_pin', __( 'Incorrect withdrawal PIN. Please try again.', 'aropay' ) );
        }

        // 2. Minimum amount check
        $min = (float) get_option( 'aropay_min_withdrawal_ugx', 5000 );
        if ( $amount < $min ) {
            return new WP_Error(
                'below_minimum',
                sprintf( __( 'Minimum withdrawal is %s.', 'aropay' ), AROPay_Helpers::format_ugx( $min ) )
            );
        }

        // 3. Phone usability check
        if ( ! AROPay_Wallet_Phone::is_usable( $phone_id, $user_id ) ) {
            return new WP_Error( 'phone_not_usable', __( 'The selected phone number is not approved or is inactive.', 'aropay' ) );
        }

        // 4. Compute fee and total deduction
        $fee          = self::get_fee( $amount );
        $total_deduct = $amount + $fee;

        // 5. Balance check
        $balance_info = AROPay_Wallet::get_balance( $user_id );
        if ( $balance_info['balance'] < $total_deduct ) {
            return new WP_Error(
                'insufficient_funds',
                sprintf(
                    __( 'Insufficient balance. You need %s (including %s fee) but have %s.', 'aropay' ),
                    AROPay_Helpers::format_ugx( $total_deduct ),
                    AROPay_Helpers::format_ugx( $fee ),
                    AROPay_Helpers::format_ugx( $balance_info['balance'] )
                )
            );
        }

        // 6. Hold the funds atomically
        if ( ! AROPay_Wallet::hold( $user_id, $total_deduct ) ) {
            return new WP_Error( 'hold_failed', __( 'Could not hold funds. Please try again.', 'aropay' ) );
        }

        // 7. Get phone details for Yo call
        global $wpdb;
        $phone_record = $wpdb->get_row( $wpdb->prepare(
            "SELECT phone, network FROM {$wpdb->prefix}aropay_wallet_phones WHERE id = %d LIMIT 1",
            $phone_id
        ) );

        // 8. Call Yo Uganda B2C API
        $internal_ref = AROPay_Helpers::generate_ref( 'WD' );
        $yo           = new AROPay_Yo_API();
        $result       = $yo->send_money( array(
            'amount'    => $amount,
            'phone'     => $phone_record->phone,
            'network'   => $phone_record->network,
            'reference' => $internal_ref,
            'narrative' => sprintf( __( 'AROPay Wallet Withdrawal — %s', 'aropay' ), $internal_ref ),
        ) );

        // 9. If API call failed, release hold and return error
        if ( is_wp_error( $result ) ) {
            AROPay_Wallet::release_hold( $user_id, $total_deduct, false );
            AROPay_Wallet::write_audit( $user_id, 'withdrawal_fail', $amount, array(
                'phone_id' => $phone_id,
                'reason'   => $result->get_error_message(),
            ) );
            return $result;
        }

        // 10. Record withdrawal in DB
        $wallet_info  = AROPay_Wallet::get_balance( $user_id );
        $withdrawal_id = self::create_record( array(
            'wallet_id'    => $wallet_info['wallet_id'],
            'user_id'      => $user_id,
            'phone_id'     => $phone_id,
            'amount'       => $amount,
            'fee'          => $fee,
            'net_amount'   => $amount - $fee, // what user actually receives
            'provider_ref' => $result['transaction_reference'] ?? '',
            'internal_ref' => $internal_ref,
            'status'       => 'processing',
        ) );

        // 11. Audit log
        AROPay_Wallet::write_audit( $user_id, 'withdrawal_init', $amount, array(
            'withdrawal_id' => $withdrawal_id,
            'phone_id'      => $phone_id,
            'fee'           => $fee,
            'internal_ref'  => $internal_ref,
        ) );

        return array(
            'internal_ref' => $internal_ref,
            'message'      => __( 'Withdrawal initiated. Funds will arrive shortly.', 'aropay' ),
        );
    }

    /**
     * Handle IPN callback from Yo for a withdrawal.
     *
     * @param string $internal_ref
     * @param string $status        'completed' | 'failed'
     * @param string $provider_ref
     * @return bool
     */
    public static function process_callback( $internal_ref, $status, $provider_ref = '' ) {
        global $wpdb;

        $withdrawal = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_withdrawals WHERE internal_ref = %s LIMIT 1",
            sanitize_text_field( $internal_ref )
        ) );

        if ( ! $withdrawal || 'processing' !== $withdrawal->status ) {
            return false;
        }

        $success = ( 'completed' === $status );
        $total   = (float) $withdrawal->amount + (float) $withdrawal->fee;

        // Release hold
        AROPay_Wallet::release_hold( $withdrawal->user_id, $total, $success );

        // Update withdrawal record
        $update_data = array(
            'status'       => $success ? 'completed' : 'failed',
            'provider_ref' => sanitize_text_field( $provider_ref ),
        );
        if ( ! $success ) {
            $update_data['failure_reason'] = 'Provider reported failure';
        }

        $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_withdrawals',
            $update_data,
            array( 'id' => $withdrawal->id ),
            array_fill( 0, count( $update_data ), '%s' ),
            array( '%d' )
        );

        $action = $success ? 'withdrawal_complete' : 'withdrawal_fail';
        AROPay_Wallet::write_audit( $withdrawal->user_id, $action, $withdrawal->amount, array(
            'withdrawal_id' => $withdrawal->id,
            'internal_ref'  => $internal_ref,
            'provider_ref'  => $provider_ref,
        ) );

        return true;
    }

    // ── Fee Calculation ─────────────────────────────────────────────────────

    /**
     * Calculate the withdrawal fee for a given amount.
     *
     * @param float $amount
     * @return float
     */
    public static function get_fee( $amount ) {
        $percent = (float) get_option( 'aropay_withdrawal_fee_percent', 1.50 );
        $fee     = ( $amount * $percent ) / 100;
        $min_fee = 200; // UGX 200 minimum withdrawal fee
        return round( max( $fee, $min_fee ), 2 );
    }

    // ── History ─────────────────────────────────────────────────────────────

    /**
     * Get paginated withdrawal history for a user.
     *
     * @param int $user_id
     * @param int $page
     * @param int $per_page
     * @return array { withdrawals, total }
     */
    public static function get_list( $user_id, $page = 1, $per_page = 10 ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;

        $withdrawals = $wpdb->get_results( $wpdb->prepare(
            "SELECT w.*, p.phone, p.network, p.label
             FROM {$wpdb->prefix}aropay_wallet_withdrawals w
             LEFT JOIN {$wpdb->prefix}aropay_wallet_phones p ON p.id = w.phone_id
             WHERE w.user_id = %d
             ORDER BY w.created_at DESC
             LIMIT %d OFFSET %d",
            $user_id, $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_wallet_withdrawals WHERE user_id = %d",
            $user_id
        ) );

        return compact( 'withdrawals', 'total' );
    }

    /**
     * Get all withdrawals for admin panel with user details.
     *
     * @param array $filters { user_id, status, date_from, date_to }
     * @param int   $page
     * @param int   $per_page
     * @return array { withdrawals, total }
     */
    public static function get_all_list( $filters = array(), $page = 1, $per_page = 20 ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;
        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $filters['user_id'] ) ) {
            $where[]  = 'w.user_id = %d';
            $values[] = absint( $filters['user_id'] );
        }
        if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
            $where[]  = 'w.status = %s';
            $values[] = sanitize_text_field( $filters['status'] );
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 'w.created_at >= %s';
            $values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 'w.created_at <= %s';
            $values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );
        $base      = "FROM {$wpdb->prefix}aropay_wallet_withdrawals w
                      LEFT JOIN {$wpdb->prefix}aropay_wallet_phones p ON p.id = w.phone_id
                      INNER JOIN {$wpdb->users} u ON u.ID = w.user_id
                      WHERE $where_sql";

        $select = "SELECT w.*, p.phone AS dest_phone, p.network, u.user_login, u.user_email $base ORDER BY w.created_at DESC LIMIT %d OFFSET %d";
        $vals   = array_merge( $values, array( $per_page, $offset ) );

        $withdrawals = $wpdb->get_results( $wpdb->prepare( $select, $vals ) );
        $total       = empty( $values )
            ? (int) $wpdb->get_var( "SELECT COUNT(*) $base" )
            : (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $base", $values ) );

        return compact( 'withdrawals', 'total' );
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * Insert a withdrawal record into the DB.
     *
     * @param array $data
     * @return int|false Inserted ID or false
     */
    private static function create_record( $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'aropay_wallet_withdrawals',
            array(
                'wallet_id'    => absint( $data['wallet_id'] ),
                'user_id'      => absint( $data['user_id'] ),
                'phone_id'     => absint( $data['phone_id'] ),
                'amount'       => (float) $data['amount'],
                'fee'          => (float) $data['fee'],
                'net_amount'   => (float) $data['net_amount'],
                'provider_ref' => sanitize_text_field( $data['provider_ref'] ?? '' ),
                'internal_ref' => sanitize_text_field( $data['internal_ref'] ),
                'status'       => sanitize_text_field( $data['status'] ),
            ),
            array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }
}

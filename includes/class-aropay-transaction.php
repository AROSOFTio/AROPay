<?php
/**
 * AROPay Transaction — CRUD for transaction records.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Transaction {

    /**
     * Create a new transaction record.
     *
     * @param array $data
     * @return int|false
     */
    public static function create( $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'aropay_transactions',
            array(
                'merchant_id'    => absint( $data['merchant_id'] ?? 0 ),
                'order_id'       => sanitize_text_field( $data['order_id'] ?? '' ),
                'wc_order_id'    => absint( $data['wc_order_id'] ?? 0 ) ?: null,
                'payment_method' => sanitize_text_field( $data['payment_method'] ),
                'provider'       => in_array( $data['provider'] ?? '', array( 'yo', 'pesapal' ), true ) ? $data['provider'] : 'yo',
                'amount'         => (float) $data['amount'],
                'currency'       => sanitize_text_field( $data['currency'] ?? 'UGX' ),
                'fee_amount'     => (float) ( $data['fee_amount'] ?? 0 ),
                'net_amount'     => (float) ( $data['net_amount'] ?? 0 ),
                'customer_phone' => sanitize_text_field( $data['customer_phone'] ?? '' ),
                'customer_email' => sanitize_email( $data['customer_email'] ?? '' ),
                'customer_name'  => sanitize_text_field( $data['customer_name'] ?? '' ),
                'provider_ref'   => sanitize_text_field( $data['provider_ref'] ?? '' ),
                'internal_ref'   => sanitize_text_field( $data['internal_ref'] ),
                'status'         => 'pending',
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a transaction by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_transactions WHERE id = %d LIMIT 1",
            $id
        ) );
    }

    /**
     * Get a transaction by internal reference.
     *
     * @param string $ref
     * @return object|null
     */
    public static function get_by_ref( $ref ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_transactions WHERE internal_ref = %s LIMIT 1",
            sanitize_text_field( $ref )
        ) );
    }

    /**
     * Get transaction by WooCommerce order ID.
     *
     * @param int $order_id
     * @return object|null
     */
    public static function get_by_order( $order_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_transactions WHERE wc_order_id = %d ORDER BY created_at DESC LIMIT 1",
            $order_id
        ) );
    }

    /**
     * Update transaction status.
     *
     * @param int    $id
     * @param string $status
     * @param string $provider_status
     * @param string $provider_ref
     * @return bool
     */
    public static function update_status( $id, $status, $provider_status = '', $provider_ref = '' ) {
        global $wpdb;

        $data   = array( 'status' => sanitize_text_field( $status ) );
        $format = array( '%s' );

        if ( $provider_status ) {
            $data['provider_status'] = sanitize_text_field( $provider_status );
            $format[] = '%s';
        }
        if ( $provider_ref ) {
            $data['provider_ref'] = sanitize_text_field( $provider_ref );
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'aropay_transactions',
            $data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        // Sync WooCommerce order status
        if ( $result !== false ) {
            $transaction = self::get( $id );
            if ( $transaction && $transaction->wc_order_id ) {
                self::sync_wc_order( $transaction );
            }
        }

        return $result !== false;
    }

    /**
     * Save IPN data payload.
     *
     * @param int   $id
     * @param array $ipn_data
     */
    public static function save_ipn_data( $id, $ipn_data ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aropay_transactions',
            array( 'ipn_data' => wp_json_encode( $ipn_data ) ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * Sync WooCommerce order status based on transaction status.
     *
     * @param object $transaction
     */
    private static function sync_wc_order( $transaction ) {
        $order = wc_get_order( $transaction->wc_order_id );
        if ( ! $order ) {
            return;
        }

        switch ( $transaction->status ) {
            case 'completed':
                if ( ! $order->is_paid() ) {
                    $order->payment_complete( $transaction->provider_ref );
                    $order->add_order_note( sprintf(
                        __( 'AROPay: Payment completed via %s. Provider ref: %s', 'aropay' ),
                        strtoupper( $transaction->provider ),
                        $transaction->provider_ref
                    ) );
                }
                break;
            case 'failed':
                $order->update_status( 'failed', __( 'AROPay: Payment failed.', 'aropay' ) );
                break;
            case 'refunded':
                $order->update_status( 'refunded', __( 'AROPay: Payment refunded.', 'aropay' ) );
                break;
        }
        $order->save();
    }

    /**
     * Get paginated transactions.
     *
     * @param array $filters { merchant_id, status, provider, date_from, date_to, search }
     * @param int   $page
     * @param int   $per_page
     * @return array { transactions, total }
     */
    public static function get_list( $filters = array(), $page = 1, $per_page = 20 ) {
        global $wpdb;

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $filters['merchant_id'] ) ) {
            $where[]  = 'merchant_id = %d';
            $values[] = absint( $filters['merchant_id'] );
        }
        if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
            $where[]  = 'status = %s';
            $values[] = sanitize_text_field( $filters['status'] );
        }
        if ( ! empty( $filters['provider'] ) && 'all' !== $filters['provider'] ) {
            $where[]  = 'provider = %s';
            $values[] = sanitize_text_field( $filters['provider'] );
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = ( $page - 1 ) * $per_page;

        $query = "SELECT * FROM {$wpdb->prefix}aropay_transactions WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $transactions = empty( $values )
            ? $wpdb->get_results( $query )
            : $wpdb->get_results( $wpdb->prepare( $query, $values ) );

        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_transactions WHERE $where_sql";
        $total = empty( array_slice( $values, 0, -2 ) )
            ? (int) $wpdb->get_var( $count_query )
            : (int) $wpdb->get_var( $wpdb->prepare( $count_query, array_slice( $values, 0, -2 ) ) );

        return compact( 'transactions', 'total' );
    }

    /**
     * Get today's summary stats (for admin dashboard).
     *
     * @return object
     */
    public static function get_today_stats() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) as count,
                SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) as volume,
                SUM(CASE WHEN status='completed' THEN fee_amount ELSE 0 END) as fees,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
            FROM {$wpdb->prefix}aropay_transactions
            WHERE DATE(created_at) = %s",
            $today
        ) );
    }
}

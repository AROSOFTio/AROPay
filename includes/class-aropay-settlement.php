<?php
/**
 * AROPay Settlement — calculates and triggers merchant settlements.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Settlement {

    /**
     * Process all pending settlements for all active merchants.
     */
    public function process_all_pending() {
        global $wpdb;

        $merchants = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}aropay_merchants WHERE status = 'active'"
        );

        foreach ( $merchants as $merchant ) {
            $this->process_for_merchant( $merchant->id );
        }
    }

    /**
     * Process settlement for a specific merchant.
     *
     * @param int $merchant_id
     * @return int|false Settlement ID or false.
     */
    public function process_for_merchant( $merchant_id ) {
        global $wpdb;

        // Find completed, unsettled transactions
        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_transactions
             WHERE merchant_id = %d
               AND status = 'completed'
               AND id NOT IN (
                   SELECT DISTINCT transaction_id
                   FROM {$wpdb->prefix}aropay_settlements
                   WHERE merchant_id = %d AND status IN ('pending','processing','settled')
               )
             ORDER BY created_at ASC",
            $merchant_id,
            $merchant_id
        ) );

        if ( empty( $transactions ) ) {
            return false;
        }

        $total_amount = array_sum( array_column( $transactions, 'amount' ) );
        $fee_amount   = array_sum( array_column( $transactions, 'fee_amount' ) );
        $net_amount   = $total_amount - $fee_amount;

        $period_start = $transactions[0]->created_at;
        $period_end   = end( $transactions )->created_at;

        $settlement_id = $this->create_settlement( array(
            'merchant_id'       => $merchant_id,
            'period_start'      => $period_start,
            'period_end'        => $period_end,
            'total_amount'      => $total_amount,
            'fee_amount'        => $fee_amount,
            'net_amount'        => $net_amount,
            'transaction_count' => count( $transactions ),
        ) );

        if ( $settlement_id ) {
            AROPay_Helpers::log(
                "Settlement #{$settlement_id} created for merchant #{$merchant_id}: " .
                AROPay_Helpers::format_ugx( $net_amount ),
                'info'
            );
        }

        return $settlement_id;
    }

    /**
     * Create a settlement record.
     *
     * @param array $data
     * @return int|false
     */
    public function create_settlement( $data ) {
        global $wpdb;

        $merchant = AROPay_Merchant::get( $data['merchant_id'] );
        $result   = $wpdb->insert(
            $wpdb->prefix . 'aropay_settlements',
            array(
                'merchant_id'       => absint( $data['merchant_id'] ),
                'period_start'      => $data['period_start'],
                'period_end'        => $data['period_end'],
                'total_amount'      => (float) $data['total_amount'],
                'fee_amount'        => (float) $data['fee_amount'],
                'net_amount'        => (float) $data['net_amount'],
                'transaction_count' => absint( $data['transaction_count'] ),
                'status'            => 'pending',
                'settlement_method' => $merchant ? $merchant->settlement_type : 'mtn',
            ),
            array( '%d', '%s', '%s', '%f', '%f', '%f', '%d', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Mark a settlement as settled.
     *
     * @param int    $settlement_id
     * @param string $ref Payout reference.
     * @return bool
     */
    public function mark_settled( $settlement_id, $ref = '' ) {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'aropay_settlements',
            array(
                'status'         => 'settled',
                'settlement_ref' => sanitize_text_field( $ref ),
                'settled_at'     => current_time( 'mysql' ),
            ),
            array( 'id' => $settlement_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Get paginated settlements list.
     *
     * @param int $page
     * @param int $per_page
     * @return array { settlements, total }
     */
    public function get_list( $page = 1, $per_page = 20 ) {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;

        $settlements = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, m.business_name, m.settlement_account, m.settlement_type
             FROM {$wpdb->prefix}aropay_settlements s
             LEFT JOIN {$wpdb->prefix}aropay_merchants m ON s.merchant_id = m.id
             ORDER BY s.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_settlements" );

        return compact( 'settlements', 'total' );
    }
}

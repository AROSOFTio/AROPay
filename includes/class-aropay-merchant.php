<?php
/**
 * AROPay Merchant — CRUD and helpers for merchant records.
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Merchant {

    /**
     * Create a new merchant record.
     *
     * @param array $data
     * @return int|false Inserted ID or false.
     */
    public static function create( $data ) {
        global $wpdb;

        $keypair = AROPay_Helpers::generate_api_keypair();

        $insert = array(
            'business_name'          => sanitize_text_field( $data['business_name'] ),
            'business_type'          => sanitize_text_field( $data['business_type'] ?? '' ),
            'phone'                  => sanitize_text_field( $data['phone'] ),
            'email'                  => sanitize_email( $data['email'] ),
            'tin_number'             => sanitize_text_field( $data['tin_number'] ?? '' ),
            'status'                 => 'pending',
            'api_key'                => $keypair['key'],
            'api_secret'             => AROPay_Encryption::encrypt( $keypair['secret'] ),
            'settlement_account'     => sanitize_text_field( $data['settlement_account'] ?? '' ),
            'settlement_type'        => in_array( $data['settlement_type'] ?? 'mtn', array( 'mtn', 'airtel', 'bank' ), true )
                                        ? $data['settlement_type'] : 'mtn',
            'transaction_fee_percent'=> (float) ( $data['transaction_fee_percent'] ?? get_option( 'aropay_default_fee_percent', 1.50 ) ),
            'user_id'                => absint( $data['user_id'] ?? 0 ) ?: null,
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'aropay_merchants',
            $insert,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d' )
        );

        if ( $result ) {
            AROPay_Helpers::log( "Merchant created: {$insert['business_name']} (ID {$wpdb->insert_id})", 'info' );
            return $wpdb->insert_id;
        }
        return false;
    }

    /**
     * Get a merchant by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_merchants WHERE id = %d LIMIT 1",
            $id
        ) );
    }

    /**
     * Get merchant by API key.
     *
     * @param string $api_key
     * @return object|null
     */
    public static function get_by_api_key( $api_key ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_merchants WHERE api_key = %s LIMIT 1",
            sanitize_text_field( $api_key )
        ) );
    }

    /**
     * Validate an API key + secret pair.
     *
     * @param string $api_key
     * @param string $api_secret
     * @return object|false Merchant object or false.
     */
    public static function validate_credentials( $api_key, $api_secret ) {
        $merchant = self::get_by_api_key( $api_key );
        if ( ! $merchant ) {
            return false;
        }
        $decrypted = AROPay_Encryption::decrypt( $merchant->api_secret );
        if ( ! hash_equals( $decrypted, $api_secret ) ) {
            return false;
        }
        if ( 'active' !== $merchant->status ) {
            return false;
        }
        return $merchant;
    }

    /**
     * Get the merchant ID configured for the current WP site.
     *
     * @return int
     */
    public static function get_merchant_id_for_site() {
        return (int) get_option( 'aropay_merchant_id', 0 );
    }

    /**
     * Update merchant status.
     *
     * @param int    $id
     * @param string $status pending|active|suspended
     * @return bool
     */
    public static function update_status( $id, $status ) {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'aropay_merchants',
            array( 'status' => $status ),
            array( 'id'     => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * Get paginated merchant list.
     *
     * @param int    $page
     * @param int    $per_page
     * @param string $status   all|pending|active|suspended
     * @return array { merchants, total }
     */
    public static function get_list( $page = 1, $per_page = 20, $status = 'all' ) {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $where  = 'all' !== $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';

        $merchants = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_merchants $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_merchants $where" );

        return compact( 'merchants', 'total' );
    }

    /**
     * Get merchant transaction summary stats.
     *
     * @param int $merchant_id
     * @return object
     */
    public static function get_stats( $merchant_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_volume,
                SUM(CASE WHEN status = 'completed' THEN fee_amount ELSE 0 END) as total_fees
            FROM {$wpdb->prefix}aropay_transactions
            WHERE merchant_id = %d",
            $merchant_id
        ) );
    }
}

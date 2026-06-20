<?php
/**
 * AROPay Wallet Phone — registration, approval, deactivation, and number change requests.
 *
 * Business rules enforced here:
 *  - Max 1 MTN phone + 1 Airtel phone per user (2 total)
 *  - Only 1 pending change request per network slot at a time
 *  - Phone is never deleted — only deactivated/reactivated to preserve history
 *
 * @package AROPay
 * @author  AROSOFT
 */

defined( 'ABSPATH' ) || exit;

class AROPay_Wallet_Phone {

    // ── User Actions ────────────────────────────────────────────────────────

    /**
     * Register a new phone number for withdrawals.
     *
     * @param int    $user_id
     * @param string $phone   Raw phone number (will be formatted)
     * @param string $label   Optional friendly label
     * @return int|WP_Error  Inserted phone ID or WP_Error
     */
    public static function register( $user_id, $phone, $label = '' ) {
        global $wpdb;

        $formatted = AROPay_Helpers::format_phone( $phone );

        if ( ! AROPay_Helpers::is_valid_ug_phone( $formatted ) ) {
            return new WP_Error( 'invalid_phone', __( 'Please enter a valid Ugandan phone number.', 'aropay' ) );
        }

        $network = AROPay_Helpers::detect_network( $formatted );
        if ( 'unknown' === $network ) {
            return new WP_Error( 'unknown_network', __( 'Could not detect MTN or Airtel from this number. Please check and try again.', 'aropay' ) );
        }

        // Enforce 1-per-network rule: check for any non-rejected phone on this network
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status, is_active FROM {$wpdb->prefix}aropay_wallet_phones
             WHERE user_id = %d AND network = %s AND status != 'rejected'
             LIMIT 1",
            $user_id, $network
        ) );

        if ( $existing ) {
            $net_label = strtoupper( $network );
            return new WP_Error(
                'slot_occupied',
                sprintf(
                    __( 'You already have a %s number registered. Deregister or request a number change instead.', 'aropay' ),
                    $net_label
                )
            );
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'aropay_wallet_phones',
            array(
                'user_id'   => absint( $user_id ),
                'phone'     => $formatted,
                'network'   => $network,
                'label'     => sanitize_text_field( $label ),
                'status'    => 'pending',
                'is_active' => 1,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Could not save phone number. Please try again.', 'aropay' ) );
        }

        $phone_id = $wpdb->insert_id;

        AROPay_Wallet::write_audit( $user_id, 'phone_register', null, array(
            'phone_id' => $phone_id,
            'phone'    => AROPay_Helpers::mask_phone( $formatted ),
            'network'  => $network,
        ) );

        return $phone_id;
    }

    /**
     * Get all phones for a user (all statuses).
     *
     * @param int $user_id
     * @return array
     */
    public static function get_all( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_phones WHERE user_id = %d ORDER BY network ASC, created_at DESC",
            $user_id
        ) );
    }

    /**
     * Get phones that are both approved AND active (used for withdrawal dropdown).
     *
     * @param int $user_id
     * @return array
     */
    public static function get_approved_active( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_phones
             WHERE user_id = %d AND status = 'approved' AND is_active = 1
             ORDER BY network ASC",
            $user_id
        ) );
    }

    /**
     * Deactivate a user's own phone (soft-delete — preserves history).
     *
     * @param int $phone_id
     * @param int $user_id  Must match owner
     * @return bool|WP_Error
     */
    public static function deactivate( $phone_id, $user_id ) {
        global $wpdb;

        $phone = self::get_phone( $phone_id, $user_id );
        if ( ! $phone ) {
            return new WP_Error( 'not_found', __( 'Phone number not found.', 'aropay' ) );
        }
        if ( ! $phone->is_active ) {
            return new WP_Error( 'already_inactive', __( 'This phone is already inactive.', 'aropay' ) );
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_phones',
            array( 'is_active' => 0 ),
            array( 'id' => $phone_id, 'user_id' => $user_id ),
            array( '%d' ),
            array( '%d', '%d' )
        );

        if ( false !== $updated ) {
            AROPay_Wallet::write_audit( $user_id, 'phone_deactivate', null, array(
                'phone_id' => $phone_id,
                'network'  => $phone->network,
            ) );
        }

        return $updated !== false;
    }

    /**
     * Reactivate a previously deactivated phone.
     *
     * @param int $phone_id
     * @param int $user_id
     * @return bool|WP_Error
     */
    public static function reactivate( $phone_id, $user_id ) {
        global $wpdb;

        $phone = self::get_phone( $phone_id, $user_id );
        if ( ! $phone ) {
            return new WP_Error( 'not_found', __( 'Phone number not found.', 'aropay' ) );
        }
        if ( 'approved' !== $phone->status ) {
            return new WP_Error( 'not_approved', __( 'Only approved phones can be reactivated.', 'aropay' ) );
        }
        if ( $phone->is_active ) {
            return new WP_Error( 'already_active', __( 'This phone is already active.', 'aropay' ) );
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_phones',
            array( 'is_active' => 1 ),
            array( 'id' => $phone_id, 'user_id' => $user_id ),
            array( '%d' ),
            array( '%d', '%d' )
        );

        if ( false !== $updated ) {
            AROPay_Wallet::write_audit( $user_id, 'phone_reactivate', null, array(
                'phone_id' => $phone_id,
                'network'  => $phone->network,
            ) );
        }

        return $updated !== false;
    }

    /**
     * Check if a phone is usable for withdrawals (approved, active, owned by user).
     *
     * @param int $phone_id
     * @param int $user_id
     * @return bool
     */
    public static function is_usable( $phone_id, $user_id ) {
        global $wpdb;
        $phone = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aropay_wallet_phones
             WHERE id = %d AND user_id = %d AND status = 'approved' AND is_active = 1 LIMIT 1",
            $phone_id, $user_id
        ) );
        return null !== $phone;
    }

    // ── Phone Change Requests ───────────────────────────────────────────────

    /**
     * Submit a number change request for an existing network slot.
     *
     * @param int    $phone_id   The current phone record's ID
     * @param int    $user_id
     * @param string $new_phone
     * @param string $new_label
     * @return int|WP_Error  Change request ID or error
     */
    public static function request_change( $phone_id, $user_id, $new_phone, $new_label = '' ) {
        global $wpdb;

        $old_phone = self::get_phone( $phone_id, $user_id );
        if ( ! $old_phone ) {
            return new WP_Error( 'not_found', __( 'Phone number not found.', 'aropay' ) );
        }

        $formatted = AROPay_Helpers::format_phone( $new_phone );
        if ( ! AROPay_Helpers::is_valid_ug_phone( $formatted ) ) {
            return new WP_Error( 'invalid_phone', __( 'Please enter a valid Ugandan phone number.', 'aropay' ) );
        }

        // New number must match same network
        $network = AROPay_Helpers::detect_network( $formatted );
        if ( $network !== $old_phone->network ) {
            return new WP_Error(
                'wrong_network',
                sprintf(
                    __( 'The new number must be on the same network (%s).', 'aropay' ),
                    strtoupper( $old_phone->network )
                )
            );
        }

        // Only 1 pending change request per phone slot at a time
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aropay_wallet_phone_changes
             WHERE phone_id = %d AND status = 'pending' LIMIT 1",
            $phone_id
        ) );

        if ( $pending ) {
            return new WP_Error( 'change_pending', __( 'You already have a pending change request for this number. Please wait for admin review.', 'aropay' ) );
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'aropay_wallet_phone_changes',
            array(
                'user_id'   => absint( $user_id ),
                'phone_id'  => absint( $phone_id ),
                'new_phone' => $formatted,
                'new_label' => sanitize_text_field( $new_label ),
                'network'   => $network,
                'status'    => 'pending',
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Could not submit change request. Please try again.', 'aropay' ) );
        }

        $change_id = $wpdb->insert_id;

        AROPay_Wallet::write_audit( $user_id, 'phone_change_request', null, array(
            'change_id'  => $change_id,
            'phone_id'   => $phone_id,
            'new_phone'  => AROPay_Helpers::mask_phone( $formatted ),
            'network'    => $network,
        ) );

        return $change_id;
    }

    /**
     * Get all pending change requests (for admin dashboard).
     *
     * @param int $page
     * @param int $per_page
     * @return array { changes, total }
     */
    public static function get_pending_changes( $page = 1, $per_page = 20 ) {
        global $wpdb;

        $offset  = ( $page - 1 ) * $per_page;
        $changes = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, p.phone AS old_phone, u.user_login, u.user_email
             FROM {$wpdb->prefix}aropay_wallet_phone_changes c
             INNER JOIN {$wpdb->prefix}aropay_wallet_phones p ON p.id = c.phone_id
             INNER JOIN {$wpdb->users} u ON u.ID = c.user_id
             WHERE c.status = 'pending'
             ORDER BY c.created_at ASC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aropay_wallet_phone_changes WHERE status = 'pending'"
        );

        return compact( 'changes', 'total' );
    }

    /**
     * Get all phone change requests (filterable by status) for admin.
     *
     * @param array $filters { status }
     * @param int   $page
     * @param int   $per_page
     * @return array { changes, total }
     */
    public static function get_all_changes( $filters = array(), $page = 1, $per_page = 20 ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;
        $where  = '1=1';
        $values = array();

        if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
            $where   .= ' AND c.status = %s';
            $values[] = sanitize_text_field( $filters['status'] );
        }

        $base    = "FROM {$wpdb->prefix}aropay_wallet_phone_changes c
                    INNER JOIN {$wpdb->prefix}aropay_wallet_phones p ON p.id = c.phone_id
                    INNER JOIN {$wpdb->users} u ON u.ID = c.user_id
                    WHERE $where";

        $select  = "SELECT c.*, p.phone AS old_phone, u.user_login, u.user_email $base ORDER BY c.created_at DESC LIMIT %d OFFSET %d";
        $vals    = array_merge( $values, array( $per_page, $offset ) );

        $changes = $wpdb->get_results( $wpdb->prepare( $select, $vals ) );
        $total   = empty( $values )
            ? (int) $wpdb->get_var( "SELECT COUNT(*) $base" )
            : (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $base", $values ) );

        return compact( 'changes', 'total' );
    }

    // ── Admin Actions ───────────────────────────────────────────────────────

    /**
     * Admin: approve a registered phone.
     *
     * @param int $phone_id
     * @param int $admin_id
     * @return bool
     */
    public static function approve( $phone_id, $admin_id ) {
        global $wpdb;

        $phone = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_phones WHERE id = %d LIMIT 1",
            $phone_id
        ) );

        if ( ! $phone ) {
            return false;
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_phones',
            array(
                'status'      => 'approved',
                'approved_by' => absint( $admin_id ),
                'approved_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $phone_id ),
            array( '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( false !== $updated ) {
            AROPay_Wallet::write_audit( $phone->user_id, 'phone_approved', null, array(
                'phone_id'   => $phone_id,
                'network'    => $phone->network,
                'admin_id'   => $admin_id,
            ) );
        }

        return $updated !== false;
    }

    /**
     * Admin: reject a registered phone.
     *
     * @param int    $phone_id
     * @param int    $admin_id
     * @param string $note
     * @return bool
     */
    public static function reject( $phone_id, $admin_id, $note = '' ) {
        global $wpdb;

        $phone = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_phones WHERE id = %d LIMIT 1",
            $phone_id
        ) );

        if ( ! $phone ) {
            return false;
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_phones',
            array(
                'status'         => 'rejected',
                'rejection_note' => sanitize_textarea_field( $note ),
            ),
            array( 'id' => $phone_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false !== $updated ) {
            AROPay_Wallet::write_audit( $phone->user_id, 'phone_rejected', null, array(
                'phone_id' => $phone_id,
                'network'  => $phone->network,
                'admin_id' => $admin_id,
                'note'     => $note,
            ) );
        }

        return $updated !== false;
    }

    /**
     * Admin: approve a phone change request.
     * Deactivates old phone; activates new phone in its place.
     *
     * @param int $change_id
     * @param int $admin_id
     * @return bool|WP_Error
     */
    public static function approve_change( $change_id, $admin_id ) {
        global $wpdb;

        $change = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_phone_changes WHERE id = %d LIMIT 1",
            $change_id
        ) );

        if ( ! $change || 'pending' !== $change->status ) {
            return new WP_Error( 'not_found', __( 'Change request not found or already reviewed.', 'aropay' ) );
        }

        // 1. Deactivate old phone
        $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_phones',
            array( 'is_active' => 0, 'status' => 'inactive' ),
            array( 'id' => $change->phone_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        // 2. Insert new phone as approved+active
        $wpdb->insert(
            $wpdb->prefix . 'aropay_wallet_phones',
            array(
                'user_id'     => $change->user_id,
                'phone'       => $change->new_phone,
                'network'     => $change->network,
                'label'       => $change->new_label,
                'status'      => 'approved',
                'is_active'   => 1,
                'approved_by' => absint( $admin_id ),
                'approved_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
        );

        // 3. Mark change request as approved
        $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_phone_changes',
            array(
                'status'      => 'approved',
                'reviewed_by' => absint( $admin_id ),
                'reviewed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $change_id ),
            array( '%s', '%d', '%s' ),
            array( '%d' )
        );

        AROPay_Wallet::write_audit( $change->user_id, 'phone_change_approved', null, array(
            'change_id' => $change_id,
            'old_phone_id' => $change->phone_id,
            'new_phone' => AROPay_Helpers::mask_phone( $change->new_phone ),
            'network'   => $change->network,
            'admin_id'  => $admin_id,
        ) );

        return true;
    }

    /**
     * Admin: reject a phone change request.
     *
     * @param int    $change_id
     * @param int    $admin_id
     * @param string $notes
     * @return bool|WP_Error
     */
    public static function reject_change( $change_id, $admin_id, $notes = '' ) {
        global $wpdb;

        $change = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_phone_changes WHERE id = %d LIMIT 1",
            $change_id
        ) );

        if ( ! $change || 'pending' !== $change->status ) {
            return new WP_Error( 'not_found', __( 'Change request not found or already reviewed.', 'aropay' ) );
        }

        $wpdb->update(
            $wpdb->prefix . 'aropay_wallet_phone_changes',
            array(
                'status'      => 'rejected',
                'reviewed_by' => absint( $admin_id ),
                'reviewed_at' => current_time( 'mysql' ),
                'notes'       => sanitize_textarea_field( $notes ),
            ),
            array( 'id' => $change_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        AROPay_Wallet::write_audit( $change->user_id, 'phone_change_rejected', null, array(
            'change_id' => $change_id,
            'phone_id'  => $change->phone_id,
            'admin_id'  => $admin_id,
            'notes'     => $notes,
        ) );

        return true;
    }

    // ── Admin List ──────────────────────────────────────────────────────────

    /**
     * Get paginated list of all registered phones (for admin panel).
     *
     * @param array $filters { status, network }
     * @param int   $page
     * @param int   $per_page
     * @return array { phones, total }
     */
    public static function get_all_phones( $filters = array(), $page = 1, $per_page = 20 ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;
        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
            $where[]  = 'p.status = %s';
            $values[] = sanitize_text_field( $filters['status'] );
        }
        if ( ! empty( $filters['network'] ) && 'all' !== $filters['network'] ) {
            $where[]  = 'p.network = %s';
            $values[] = sanitize_text_field( $filters['network'] );
        }

        $where_sql = implode( ' AND ', $where );
        $base      = "FROM {$wpdb->prefix}aropay_wallet_phones p
                      INNER JOIN {$wpdb->users} u ON u.ID = p.user_id
                      WHERE $where_sql";
        $select    = "SELECT p.*, u.user_login, u.user_email $base ORDER BY p.created_at DESC LIMIT %d OFFSET %d";
        $vals      = array_merge( $values, array( $per_page, $offset ) );

        $phones = $wpdb->get_results( $wpdb->prepare( $select, $vals ) );
        $total  = empty( $values )
            ? (int) $wpdb->get_var( "SELECT COUNT(*) $base" )
            : (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $base", $values ) );

        return compact( 'phones', 'total' );
    }

    /**
     * Count phones by status (for admin badge notifications).
     *
     * @return object { pending, approved, rejected }
     */
    public static function get_counts_by_status() {
        global $wpdb;
        return $wpdb->get_row(
            "SELECT
                SUM(status = 'pending')  AS pending,
                SUM(status = 'approved') AS approved,
                SUM(status = 'rejected') AS rejected
             FROM {$wpdb->prefix}aropay_wallet_phones"
        );
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    /**
     * Fetch a single phone by ID + user_id (ownership guard).
     *
     * @param int $phone_id
     * @param int $user_id
     * @return object|null
     */
    private static function get_phone( $phone_id, $user_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aropay_wallet_phones WHERE id = %d AND user_id = %d LIMIT 1",
            $phone_id, $user_id
        ) );
    }
}

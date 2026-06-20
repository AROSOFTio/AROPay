<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aropay-admin-wrap">
    <h1><?php esc_html_e( 'Transactions', 'aropay' ); ?></h1>

    <form method="get" class="aropay-filter-form">
        <input type="hidden" name="page" value="aropay-transactions">
        <select name="status">
            <option value="all"><?php esc_html_e( 'All Statuses', 'aropay' ); ?></option>
            <?php foreach ( array( 'pending', 'completed', 'failed', 'refunded' ) as $s ) : ?>
                <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filters['status'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="provider">
            <option value="all"><?php esc_html_e( 'All Providers', 'aropay' ); ?></option>
            <option value="yo" <?php selected( $filters['provider'], 'yo' ); ?>>Yo Uganda</option>
            <option value="pesapal" <?php selected( $filters['provider'], 'pesapal' ); ?>>Pesapal</option>
        </select>
        <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" placeholder="From">
        <input type="date" name="date_to"   value="<?php echo esc_attr( $filters['date_to'] ); ?>"   placeholder="To">
        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'aropay' ); ?></button>
    </form>

    <table class="wp-list-table widefat fixed striped aropay-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Order', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Customer', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Method', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Fee', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Status', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Provider', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Date', 'aropay' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $transactions ) ) : ?>
                <tr><td colspan="9" style="text-align:center;"><?php esc_html_e( 'No transactions found.', 'aropay' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $transactions as $txn ) : ?>
                <tr>
                    <td><?php echo esc_html( $txn->id ); ?></td>
                    <td>
                        <?php if ( $txn->wc_order_id ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $txn->wc_order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $txn->order_id ); ?></a>
                        <?php else : echo esc_html( $txn->order_id ); endif; ?>
                    </td>
                    <td><?php echo esc_html( $txn->customer_name ); ?><br><small><?php echo esc_html( $txn->customer_phone ); ?></small></td>
                    <td><?php echo esc_html( strtoupper( $txn->payment_method ) ); ?></td>
                    <td><?php echo esc_html( AROPay_Helpers::format_ugx( $txn->amount ) ); ?></td>
                    <td><?php echo esc_html( AROPay_Helpers::format_ugx( $txn->fee_amount ) ); ?></td>
                    <td><span class="aropay-badge aropay-badge-<?php echo esc_attr( $txn->status ); ?>"><?php echo esc_html( ucfirst( $txn->status ) ); ?></span></td>
                    <td><?php echo esc_html( strtoupper( $txn->provider ) ); ?></td>
                    <td><?php echo esc_html( wp_date( 'd M Y H:i', strtotime( $txn->created_at ) ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="aropay-pagination">
        <?php echo paginate_links( array(
            'base'      => add_query_arg( 'paged', '%#%' ),
            'format'    => '',
            'current'   => $page,
            'total'     => $total_pages,
            'prev_text' => '← ' . __( 'Previous', 'aropay' ),
            'next_text' => __( 'Next', 'aropay' ) . ' →',
        ) ); ?>
    </div>
    <?php endif; ?>
</div>

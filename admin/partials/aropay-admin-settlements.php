<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aropay-admin-wrap">
    <h1><?php esc_html_e( 'Settlements', 'aropay' ); ?></h1>

    <?php if ( ! empty( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( ucfirst( sanitize_text_field( $_GET['msg'] ) ) ); ?></p></div>
    <?php endif; ?>

    <div class="aropay-action-bar">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
            <?php wp_nonce_field( 'aropay_settlement_action' ); ?>
            <input type="hidden" name="action" value="aropay_trigger_settlement">
            <input type="hidden" name="merchant_id" value="0">
            <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Process settlements for all active merchants?', 'aropay' ); ?>')">
                🏦 <?php esc_html_e( 'Process All Settlements Now', 'aropay' ); ?>
            </button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped aropay-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Merchant', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Period', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Transactions', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Total', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Fee', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Net (Pay Out)', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Method', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Status', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'aropay' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $settlements ) ) : ?>
                <tr><td colspan="10" style="text-align:center;"><?php esc_html_e( 'No settlements yet.', 'aropay' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $settlements as $s ) : ?>
                <tr>
                    <td><?php echo esc_html( $s->id ); ?></td>
                    <td><?php echo esc_html( $s->business_name ); ?><br><small><?php echo esc_html( $s->settlement_account ); ?></small></td>
                    <td><?php echo esc_html( wp_date( 'd M', strtotime( $s->period_start ) ) ); ?> – <?php echo esc_html( wp_date( 'd M Y', strtotime( $s->period_end ) ) ); ?></td>
                    <td><?php echo esc_html( number_format( $s->transaction_count ) ); ?></td>
                    <td><?php echo esc_html( AROPay_Helpers::format_ugx( $s->total_amount ) ); ?></td>
                    <td><?php echo esc_html( AROPay_Helpers::format_ugx( $s->fee_amount ) ); ?></td>
                    <td><strong><?php echo esc_html( AROPay_Helpers::format_ugx( $s->net_amount ) ); ?></strong></td>
                    <td><?php echo esc_html( strtoupper( $s->settlement_method ) ); ?></td>
                    <td><span class="aropay-badge aropay-badge-<?php echo esc_attr( $s->status ); ?>"><?php echo esc_html( ucfirst( $s->status ) ); ?></span></td>
                    <td>
                        <?php if ( 'pending' === $s->status || 'processing' === $s->status ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'aropay_settlement_action' ); ?>
                            <input type="hidden" name="action" value="aropay_mark_settled">
                            <input type="hidden" name="settlement_id" value="<?php echo esc_attr( $s->id ); ?>">
                            <input type="text" name="settlement_ref" placeholder="<?php esc_attr_e( 'Payout Ref', 'aropay' ); ?>" class="small-text">
                            <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Mark Settled', 'aropay' ); ?></button>
                        </form>
                        <?php elseif ( 'settled' === $s->status ) : ?>
                            <small><?php echo esc_html( $s->settlement_ref ); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

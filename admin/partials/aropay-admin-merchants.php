<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aropay-admin-wrap">
    <h1><?php esc_html_e( 'Merchants', 'aropay' ); ?></h1>

    <?php if ( ! empty( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( ucfirst( sanitize_text_field( $_GET['msg'] ) ) ); ?></p></div>
    <?php endif; ?>

    <div class="aropay-tab-filter">
        <?php foreach ( array( 'all' => __( 'All', 'aropay' ), 'pending' => __( 'Pending', 'aropay' ), 'active' => __( 'Active', 'aropay' ), 'suspended' => __( 'Suspended', 'aropay' ) ) as $key => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-merchants&status=' . $key ) ); ?>"
               class="button <?php echo $status === $key ? 'button-primary' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <table class="wp-list-table widefat fixed striped aropay-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Business', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Contact', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'TIN', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Settlement', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Fee %', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Status', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Joined', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'aropay' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $merchants ) ) : ?>
                <tr><td colspan="8" style="text-align:center;"><?php esc_html_e( 'No merchants found.', 'aropay' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $merchants as $m ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $m->business_name ); ?></strong><br><small><?php echo esc_html( $m->business_type ); ?></small></td>
                    <td><?php echo esc_html( $m->email ); ?><br><?php echo esc_html( $m->phone ); ?></td>
                    <td><?php echo esc_html( $m->tin_number ); ?></td>
                    <td><?php echo esc_html( $m->settlement_account ); ?><br><small><?php echo esc_html( strtoupper( $m->settlement_type ) ); ?></small></td>
                    <td><?php echo esc_html( $m->transaction_fee_percent ); ?>%</td>
                    <td><span class="aropay-badge aropay-badge-<?php echo esc_attr( $m->status ); ?>"><?php echo esc_html( ucfirst( $m->status ) ); ?></span></td>
                    <td><?php echo esc_html( wp_date( 'd M Y', strtotime( $m->created_at ) ) ); ?></td>
                    <td>
                        <?php if ( 'pending' === $m->status || 'suspended' === $m->status ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                            <?php wp_nonce_field( 'aropay_merchant_action' ); ?>
                            <input type="hidden" name="action" value="aropay_approve_merchant">
                            <input type="hidden" name="merchant_id" value="<?php echo esc_attr( $m->id ); ?>">
                            <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Approve', 'aropay' ); ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if ( 'active' === $m->status ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                            <?php wp_nonce_field( 'aropay_merchant_action' ); ?>
                            <input type="hidden" name="action" value="aropay_suspend_merchant">
                            <input type="hidden" name="merchant_id" value="<?php echo esc_attr( $m->id ); ?>">
                            <button type="submit" class="button button-small"><?php esc_html_e( 'Suspend', 'aropay' ); ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="aropay-pagination">
        <?php echo paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $page, 'total' => $total_pages ) ); ?>
    </div>
    <?php endif; ?>
</div>

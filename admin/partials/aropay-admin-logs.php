<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aropay-admin-wrap">
    <h1><?php esc_html_e( 'API Logs', 'aropay' ); ?></h1>
    <form method="get" class="aropay-filter-form">
        <input type="hidden" name="page" value="aropay-logs">
        <select name="provider">
            <option value="all"><?php esc_html_e( 'All Providers', 'aropay' ); ?></option>
            <option value="yo"      <?php selected( $provider, 'yo' ); ?>>Yo Uganda</option>
            <option value="pesapal" <?php selected( $provider, 'pesapal' ); ?>>Pesapal</option>
        </select>
        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'aropay' ); ?></button>
    </form>
    <table class="wp-list-table widefat fixed striped aropay-table aropay-logs-table">
        <thead>
            <tr>
                <th style="width:60px"><?php esc_html_e( 'ID', 'aropay' ); ?></th>
                <th style="width:80px"><?php esc_html_e( 'Provider', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Request Type', 'aropay' ); ?></th>
                <th style="width:60px"><?php esc_html_e( 'HTTP', 'aropay' ); ?></th>
                <th style="width:70px"><?php esc_html_e( 'ms', 'aropay' ); ?></th>
                <th><?php esc_html_e( 'Response (preview)', 'aropay' ); ?></th>
                <th style="width:130px"><?php esc_html_e( 'Date', 'aropay' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr><td colspan="7" style="text-align:center"><?php esc_html_e( 'No logs found.', 'aropay' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                <tr>
                    <td><?php echo esc_html( $log->id ); ?></td>
                    <td><span class="aropay-badge aropay-badge-<?php echo esc_attr( $log->provider ); ?>"><?php echo esc_html( strtoupper( $log->provider ) ); ?></span></td>
                    <td><code><?php echo esc_html( $log->request_type ); ?></code></td>
                    <td><?php echo esc_html( $log->status_code ); ?></td>
                    <td><?php echo esc_html( $log->duration_ms ); ?></td>
                    <td><small class="aropay-log-preview"><?php echo esc_html( substr( $log->response_body, 0, 120 ) ); ?>…</small></td>
                    <td><?php echo esc_html( wp_date( 'd M Y H:i:s', strtotime( $log->created_at ) ) ); ?></td>
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
    <p class="description"><?php esc_html_e( 'Logs older than 90 days are automatically deleted.', 'aropay' ); ?></p>
</div>

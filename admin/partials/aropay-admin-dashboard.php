<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aropay-admin-wrap">

    <div class="aropay-admin-header">
        <h1 class="aropay-logo-title">
            <span class="aropay-logo-badge">ARO</span>Pay
        </h1>
        <p class="aropay-tagline"><?php esc_html_e( 'Uganda\'s Payment Gateway · Powered by AROSOFT', 'aropay' ); ?></p>
    </div>

    <div class="aropay-stats-grid">

        <div class="aropay-stat-card aropay-stat-volume">
            <div class="aropay-stat-icon">💰</div>
            <div class="aropay-stat-body">
                <span class="aropay-stat-number"><?php echo esc_html( AROPay_Helpers::format_ugx( $today_stats->volume ?? 0 ) ); ?></span>
                <span class="aropay-stat-label"><?php esc_html_e( "Today's Volume", 'aropay' ); ?></span>
            </div>
        </div>

        <div class="aropay-stat-card aropay-stat-fees">
            <div class="aropay-stat-icon">📊</div>
            <div class="aropay-stat-body">
                <span class="aropay-stat-number"><?php echo esc_html( AROPay_Helpers::format_ugx( $today_stats->fees ?? 0 ) ); ?></span>
                <span class="aropay-stat-label"><?php esc_html_e( "Today's Revenue", 'aropay' ); ?></span>
            </div>
        </div>

        <div class="aropay-stat-card aropay-stat-txns">
            <div class="aropay-stat-icon">🔄</div>
            <div class="aropay-stat-body">
                <span class="aropay-stat-number"><?php echo esc_html( number_format( $today_stats->count ?? 0 ) ); ?></span>
                <span class="aropay-stat-label"><?php esc_html_e( 'Transactions Today', 'aropay' ); ?></span>
            </div>
        </div>

        <div class="aropay-stat-card aropay-stat-merchants">
            <div class="aropay-stat-icon">🏪</div>
            <div class="aropay-stat-body">
                <span class="aropay-stat-number"><?php echo esc_html( $active_merchants ); ?></span>
                <span class="aropay-stat-label"><?php esc_html_e( 'Active Merchants', 'aropay' ); ?></span>
            </div>
        </div>

        <div class="aropay-stat-card aropay-stat-pending">
            <div class="aropay-stat-icon">⏳</div>
            <div class="aropay-stat-body">
                <span class="aropay-stat-number"><?php echo esc_html( $today_stats->pending ?? 0 ); ?></span>
                <span class="aropay-stat-label"><?php esc_html_e( 'Pending Payments', 'aropay' ); ?></span>
            </div>
        </div>

        <div class="aropay-stat-card aropay-stat-settlements">
            <div class="aropay-stat-icon">🏦</div>
            <div class="aropay-stat-body">
                <span class="aropay-stat-number"><?php echo esc_html( $pending_settlements ); ?></span>
                <span class="aropay-stat-label"><?php esc_html_e( 'Pending Settlements', 'aropay' ); ?></span>
            </div>
        </div>

    </div>

    <?php if ( $pending_merchants > 0 ) : ?>
    <div class="aropay-notice aropay-notice-warning">
        <strong><?php esc_html_e( 'Action Required:', 'aropay' ); ?></strong>
        <?php echo esc_html( sprintf(
            _n( '%d merchant is awaiting approval.', '%d merchants are awaiting approval.', $pending_merchants, 'aropay' ),
            $pending_merchants
        ) ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-merchants&status=pending' ) ); ?>">
            <?php esc_html_e( 'Review Now →', 'aropay' ); ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="aropay-quick-links">
        <h2><?php esc_html_e( 'Quick Actions', 'aropay' ); ?></h2>
        <div class="aropay-quick-grid">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-transactions' ) ); ?>" class="aropay-quick-btn">
                📋 <?php esc_html_e( 'View Transactions', 'aropay' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-merchants' ) ); ?>" class="aropay-quick-btn">
                🏪 <?php esc_html_e( 'Manage Merchants', 'aropay' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settlements' ) ); ?>" class="aropay-quick-btn">
                🏦 <?php esc_html_e( 'Settlements', 'aropay' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings' ) ); ?>" class="aropay-quick-btn">
                ⚙️ <?php esc_html_e( 'Settings', 'aropay' ); ?>
            </a>
        </div>
    </div>

    <div class="aropay-footer-note">
        AROPay v<?php echo esc_html( AROPAY_VERSION ); ?> · 
        <a href="https://arosoftlabs.com/market/plugins/aropay" target="_blank">Documentation</a> · 
        <a href="https://arosoftlabs.com" target="_blank">AROSOFT</a>
    </div>

</div>

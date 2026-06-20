<?php
/**
 * AROPay — Pending payment screen shown after MoMo STK push.
 * Rendered on the WooCommerce receipt/pay page.
 *
 * Variables available: $order_id, $internal_ref, $network
 *
 * @package AROPay
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="aropay-pending-wrap" id="aropay-payment-pending"
     data-ref="<?php echo esc_attr( $internal_ref ); ?>"
     data-order="<?php echo esc_attr( $order_id ); ?>"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'aropay_nonce' ) ); ?>">

    <div class="aropay-pending-icon" aria-hidden="true">
        <?php if ( 'airtel' === $network ) : ?>
            <span class="aropay-network-pulse aropay-airtel-pulse"></span>
        <?php else : ?>
            <span class="aropay-network-pulse aropay-mtn-pulse"></span>
        <?php endif; ?>
        📱
    </div>

    <h2 class="aropay-pending-title">
        <?php esc_html_e( 'Complete Your Payment', 'aropay' ); ?>
    </h2>

    <p class="aropay-pending-instruction">
        <?php echo esc_html( sprintf(
            __( 'A payment prompt has been sent to your %s phone. Please check your phone and enter your Mobile Money PIN to complete the payment.', 'aropay' ),
            'airtel' === $network ? 'Airtel' : 'MTN'
        ) ); ?>
    </p>

    <div class="aropay-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="aropay-progress-fill" id="aropay-progress-fill"></div>
    </div>

    <p class="aropay-pending-timer" id="aropay-pending-timer" aria-live="polite">
        <?php esc_html_e( 'Waiting for confirmation…', 'aropay' ); ?>
    </p>

    <div class="aropay-pending-actions">
        <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="aropay-btn-secondary">
            ← <?php esc_html_e( 'Go back and try again', 'aropay' ); ?>
        </a>
    </div>

    <p class="aropay-support-note">
        <?php esc_html_e( 'Need help?', 'aropay' ); ?>
        <?php $support = AROPay_Helpers::support_info(); ?>
        <?php if ( $support ) : echo esc_html( $support ); endif; ?>
    </p>

</div>

<script type="text/javascript">
(function($){
    var ref      = '<?php echo esc_js( $internal_ref ); ?>';
    var nonce    = '<?php echo esc_js( wp_create_nonce( 'aropay_nonce' ) ); ?>';
    var restUrl  = '<?php echo esc_js( rest_url( 'aropay/v1/transaction-status/' ) ); ?>';
    var interval = 5000;
    var elapsed  = 0;
    var timeout  = 120000;
    var timer;

    function updateTimer() {
        var remaining = Math.max(0, Math.floor((timeout - elapsed) / 1000));
        var pct = Math.min(100, (elapsed / timeout) * 100);
        $('#aropay-progress-fill').css('width', pct + '%');
        if (remaining > 0) {
            $('#aropay-pending-timer').text('<?php echo esc_js( __( 'Waiting…', 'aropay' ) ); ?> (' + remaining + 's)');
        }
    }

    function poll() {
        $.get(restUrl + ref, function(data) {
            if (!data) return;
            if (data.status === 'completed' && data.redirect) {
                clearInterval(timer);
                $('#aropay-pending-timer').text('<?php echo esc_js( __( '✓ Payment confirmed! Redirecting…', 'aropay' ) ); ?>');
                window.location.href = data.redirect;
            } else if (data.status === 'failed') {
                clearInterval(timer);
                $('#aropay-pending-timer').text('<?php echo esc_js( __( '✗ Payment failed. Please try again.', 'aropay' ) ); ?>');
                $('#aropay-pending-timer').addClass('aropay-error');
            }
        });

        elapsed += interval;
        updateTimer();

        if (elapsed >= timeout) {
            clearInterval(timer);
            $('#aropay-pending-timer').text('<?php echo esc_js( __( 'Payment timed out. Please go back and try again.', 'aropay' ) ); ?>');
        }
    }

    $(document).ready(function() {
        timer = setInterval(poll, interval);
        updateTimer();
        poll();
    });
}(jQuery));
</script>

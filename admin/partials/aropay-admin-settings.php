<?php defined( 'ABSPATH' ) || exit;

$current_mode   = AROPay_License::get_mode();
$license_check  = AROPay_License::check();
$own_api_active = AROPay_License::own_api_active();
$has_own_api    = ( 'own_api' === $current_mode );
?>
<div class="wrap aropay-admin-wrap">

    <div class="aropay-admin-header">
        <h1><span class="aropay-logo-badge">ARO</span>Pay <small>Settings</small></h1>
    </div>

    <?php if ( ! empty( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✅ <?php esc_html_e( 'Settings saved.', 'aropay' ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! empty( $_GET['license_msg'] ) ) :
        $lmsg = sanitize_text_field( $_GET['license_msg'] );
        echo '<div class="notice notice-' . ( 'activated' === $lmsg ? 'success' : 'error' ) . ' is-dismissible"><p>'
            . ( 'activated' === $lmsg
                ? esc_html__( '✅ License activated! Own API mode is now unlocked.', 'aropay' )
                : esc_html__( '❌ License check failed. Please verify your key and try again.', 'aropay' ) )
            . '</p></div>';
    endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════
         FEE TRANSPARENCY BANNER  —  always visible
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="aropay-fee-banner">
        <div class="aropay-fee-banner-icon">💰</div>
        <div class="aropay-fee-banner-body">
            <strong><?php esc_html_e( 'Transaction Fee: 7% per transaction', 'aropay' ); ?></strong>
            <span class="aropay-fee-split">
                <span class="aropay-fee-pill aropay-fee-gateway">3% → Payment Gateway (Pesapal / Yo Uganda)</span>
                <span class="aropay-fee-plus">+</span>
                <span class="aropay-fee-pill aropay-fee-arosoft">4% → AROSOFT for system maintenance &amp; security</span>
            </span>
            <p class="aropay-fee-note">
                <?php esc_html_e( 'This fee is deducted automatically from each completed transaction before funds reach your wallet. There are no hidden charges.', 'aropay' ); ?>
            </p>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         API MODE QUESTION  —  simple yes / no
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="aropay-api-question-card">
        <h2 class="aropay-aq-title">
            🔌 <?php esc_html_e( 'Do you have your own payment API credentials?', 'aropay' ); ?>
        </h2>
        <p class="aropay-aq-subtitle">
            <?php esc_html_e( 'AROPay works out of the box using AROSOFT Innovations Ltd\'s licensed accounts — you don\'t need anything. If you already have your own Yo Uganda or Pesapal API credentials, you can enter them below instead.', 'aropay' ); ?>
        </p>

        <div class="aropay-aq-choices">

            <!-- NO → use AROSOFT managed accounts -->
            <div class="aropay-aq-choice <?php echo ! $has_own_api ? 'aropay-aq-selected' : ''; ?>" id="aq-choice-no">
                <div class="aropay-aq-choice-icon">🏦</div>
                <div class="aropay-aq-choice-label">
                    <strong><?php esc_html_e( 'No — use AROSOFT\'s accounts', 'aropay' ); ?></strong>
                    <span><?php esc_html_e( 'Recommended. Zero setup. Everything just works.', 'aropay' ); ?></span>
                </div>
                <?php if ( ! $has_own_api ) : ?>
                    <span class="aropay-aq-tick">✅</span>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                        <?php wp_nonce_field( 'aropay_switch_mode' ); ?>
                        <input type="hidden" name="action" value="aropay_switch_mode">
                        <input type="hidden" name="mode"   value="managed">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Switch to this', 'aropay' ); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- YES → own API -->
            <div class="aropay-aq-choice <?php echo $has_own_api ? 'aropay-aq-selected' : ''; ?>" id="aq-choice-yes">
                <div class="aropay-aq-choice-icon">🔑</div>
                <div class="aropay-aq-choice-label">
                    <strong><?php esc_html_e( 'Yes — I have my own API credentials', 'aropay' ); ?></strong>
                    <span><?php esc_html_e( 'Requires a $2/month AROPay subscription. API fields will appear below.', 'aropay' ); ?></span>
                </div>
                <?php if ( $has_own_api ) : ?>
                    <span class="aropay-aq-tick">✅</span>
                <?php else : ?>
                    <?php if ( $license_check['valid'] ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                            <?php wp_nonce_field( 'aropay_switch_mode' ); ?>
                            <input type="hidden" name="action" value="aropay_switch_mode">
                            <input type="hidden" name="mode"   value="own_api">
                            <button type="submit" class="button button-secondary">
                                <?php esc_html_e( 'Use my own API', 'aropay' ); ?>
                            </button>
                        </form>
                    <?php else : ?>
                        <button class="button button-secondary" onclick="document.getElementById('aropay-license-section').scrollIntoView({behavior:'smooth'});return false;">
                            <?php esc_html_e( 'Enter license key ↓', 'aropay' ); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- .aropay-aq-choices -->

        <!-- Where to get APIs — a brief note, not a wall of requirements -->
        <div class="aropay-api-note">
            <strong>💡 <?php esc_html_e( 'Don\'t have API credentials yet?', 'aropay' ); ?></strong>
            <?php esc_html_e( 'You can apply directly with the payment providers:', 'aropay' ); ?>
            <a href="https://yo.co.ug/contact" target="_blank" rel="noopener">Yo Uganda ↗</a>
            &nbsp;|&nbsp;
            <a href="https://www.pesapal.com/merchant/register" target="_blank" rel="noopener">Pesapal ↗</a>
            &nbsp;—&nbsp;
            <?php esc_html_e( 'or simply leave this as "No" and AROSOFT handles everything for you.', 'aropay' ); ?>
        </div>

    </div><!-- .aropay-api-question-card -->

    <!-- ═══════════════════════════════════════════════════════════════════
         LICENSE KEY (only relevant for Own API, but always visible so
         user can enter key before switching)
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="aropay-section-card" id="aropay-license-section" <?php echo ! $has_own_api ? 'style="display:none;"' : ''; ?>>
        <h3>🔑 <?php esc_html_e( 'Own API Subscription License', 'aropay' ); ?></h3>
        <p><?php printf(
            esc_html__( 'Using your own API credentials requires an active %s subscription from AROSOFT. After purchase you\'ll receive a license key — enter it here.', 'aropay' ),
            '<strong>$2.00/month</strong>'
        ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aropay-inline-form">
            <?php wp_nonce_field( 'aropay_save_settings' ); ?>
            <input type="hidden" name="action"     value="aropay_save_settings">
            <input type="hidden" name="aropay_tab" value="license">
            <input type="text" name="license_key" class="regular-text"
                   value="<?php echo esc_attr( AROPay_License::get_key() ); ?>"
                   placeholder="AROP-XXXX-XXXX-XXXX" autocomplete="off" style="width:280px;">
            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Activate Key', 'aropay' ); ?>
            </button>
            <a href="<?php echo esc_url( AROPay_License::purchase_url() ); ?>" target="_blank" class="button button-secondary">
                🛒 <?php esc_html_e( 'Buy Subscription', 'aropay' ); ?>
            </a>
            <?php if ( $license_check['valid'] ) : ?>
                <span class="aropay-license-badge-active">✅ <?php esc_html_e( 'Active', 'aropay' ); ?></span>
            <?php elseif ( ! empty( AROPay_License::get_key() ) ) : ?>
                <span class="aropay-license-badge-inactive">❌ <?php echo esc_html( $license_check['message'] ); ?></span>
            <?php endif; ?>
        </form>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         SETTINGS TABS
    ═══════════════════════════════════════════════════════════════════ -->
    <nav class="nav-tab-wrapper aropay-tabs" style="margin-top:28px;">
        <?php if ( $has_own_api ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=yo' ) ); ?>" class="nav-tab <?php echo 'yo' === $active_tab ? 'nav-tab-active' : ''; ?>">
            📱 Yo Uganda API
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=pesapal' ) ); ?>" class="nav-tab <?php echo 'pesapal' === $active_tab ? 'nav-tab-active' : ''; ?>">
            💳 Pesapal API
        </a>
        <?php endif; ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=fees' ) ); ?>" class="nav-tab <?php echo 'fees' === $active_tab ? 'nav-tab-active' : ''; ?>">
            💰 <?php esc_html_e( 'Fees & Settlement', 'aropay' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=branding' ) ); ?>" class="nav-tab <?php echo 'branding' === $active_tab ? 'nav-tab-active' : ''; ?>">
            🎨 <?php esc_html_e( 'Branding', 'aropay' ); ?>
        </a>
    </nav>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aropay-settings-form">
        <?php wp_nonce_field( 'aropay_save_settings' ); ?>
        <input type="hidden" name="action"     value="aropay_save_settings">
        <input type="hidden" name="aropay_tab" value="<?php echo esc_attr( $active_tab ); ?>">

        <table class="form-table aropay-form-table">

        <?php if ( 'yo' === $active_tab && $has_own_api ) : ?>

            <tr>
                <th colspan="2">
                    <h2><?php esc_html_e( 'Your Yo Uganda API Credentials', 'aropay' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Enter the API Username and Password from your Yo Uganda merchant portal.', 'aropay' ); ?>
                    <a href="https://yo.co.ug" target="_blank" rel="noopener">yo.co.ug ↗</a></p>
                </th>
            </tr>
            <tr>
                <th><label for="yo_test_mode"><?php esc_html_e( 'Mode', 'aropay' ); ?></label></th>
                <td>
                    <select name="yo_test_mode" id="yo_test_mode">
                        <option value="yes" <?php selected( get_option( 'aropay_yo_test_mode' ), 'yes' ); ?>><?php esc_html_e( 'Sandbox / Test', 'aropay' ); ?></option>
                        <option value="no"  <?php selected( get_option( 'aropay_yo_test_mode' ), 'no' );  ?>><?php esc_html_e( 'Live / Production', 'aropay' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="yo_username"><?php esc_html_e( 'API Username', 'aropay' ); ?></label></th>
                <td>
                    <input type="text" name="yo_username" id="yo_username" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_yo_username' ) ); ?>"
                           autocomplete="off">
                </td>
            </tr>
            <tr>
                <th><label for="yo_password"><?php esc_html_e( 'API Password', 'aropay' ); ?></label></th>
                <td>
                    <input type="password" name="yo_password" id="yo_password" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_yo_password' ) ); ?>"
                           autocomplete="new-password">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Callback URL', 'aropay' ); ?></th>
                <td>
                    <code><?php echo esc_html( AROPay_Yo_API::get_callback_url() ); ?></code>
                    <p class="description"><?php esc_html_e( 'Set this as your Payment Notification URL in the Yo Uganda portal.', 'aropay' ); ?></p>
                </td>
            </tr>

        <?php elseif ( 'pesapal' === $active_tab && $has_own_api ) : ?>

            <tr>
                <th colspan="2">
                    <h2><?php esc_html_e( 'Your Pesapal OpenFloat API Credentials', 'aropay' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Enter the Consumer Key and Secret from your Pesapal merchant dashboard.', 'aropay' ); ?>
                    <a href="https://pesapal.com" target="_blank" rel="noopener">pesapal.com ↗</a></p>
                    <div class="aropay-info-note">
                        ℹ️ <?php esc_html_e( 'Pesapal independently charges 3% on all transactions processed through your account. This is separate from the AROPay 4% fee.', 'aropay' ); ?>
                    </div>
                </th>
            </tr>
            <tr>
                <th><label for="pesapal_test_mode"><?php esc_html_e( 'Mode', 'aropay' ); ?></label></th>
                <td>
                    <select name="pesapal_test_mode" id="pesapal_test_mode">
                        <option value="yes" <?php selected( get_option( 'aropay_pesapal_test_mode' ), 'yes' ); ?>><?php esc_html_e( 'Sandbox / Test', 'aropay' ); ?></option>
                        <option value="no"  <?php selected( get_option( 'aropay_pesapal_test_mode' ), 'no' );  ?>><?php esc_html_e( 'Live / Production', 'aropay' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="pesapal_consumer_key"><?php esc_html_e( 'Consumer Key', 'aropay' ); ?></label></th>
                <td>
                    <input type="text" name="pesapal_consumer_key" id="pesapal_consumer_key" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_pesapal_consumer_key' ) ); ?>"
                           autocomplete="off">
                </td>
            </tr>
            <tr>
                <th><label for="pesapal_consumer_secret"><?php esc_html_e( 'Consumer Secret', 'aropay' ); ?></label></th>
                <td>
                    <input type="password" name="pesapal_consumer_secret" id="pesapal_consumer_secret" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_pesapal_consumer_secret' ) ); ?>"
                           autocomplete="new-password">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'IPN URL', 'aropay' ); ?></th>
                <td>
                    <code><?php echo esc_html( AROPay_Pesapal_API::get_ipn_url() ); ?></code>
                    <p class="description"><?php esc_html_e( 'Auto-registered with Pesapal on first payment.', 'aropay' ); ?></p>
                </td>
            </tr>

        <?php elseif ( 'fees' === $active_tab ) : ?>

            <tr>
                <th colspan="2"><h2><?php esc_html_e( 'Fee & Settlement Configuration', 'aropay' ); ?></h2></th>
            </tr>
            <tr>
                <th><label for="default_fee_percent"><?php esc_html_e( 'Total Transaction Fee (%)', 'aropay' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" min="0" max="100"
                           name="default_fee_percent" id="default_fee_percent"
                           value="<?php echo esc_attr( get_option( 'aropay_default_fee_percent', '7.00' ) ); ?>"
                           class="small-text"> %
                    <p class="description">
                        <?php esc_html_e( 'Default: 7% (3% gateway + 4% AROSOFT). Minimum recommended is 7% to cover payment gateway costs.', 'aropay' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="min_fee_ugx"><?php esc_html_e( 'Minimum Fee (UGX)', 'aropay' ); ?></label></th>
                <td>
                    <input type="number" step="100" min="0"
                           name="min_fee_ugx" id="min_fee_ugx"
                           value="<?php echo esc_attr( get_option( 'aropay_min_fee_ugx', 500 ) ); ?>"
                           class="small-text"> UGX
                </td>
            </tr>
            <tr>
                <th><label for="withdrawal_fee_percent"><?php esc_html_e( 'Withdrawal Fee (%)', 'aropay' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" min="0" max="100"
                           name="withdrawal_fee_percent" id="withdrawal_fee_percent"
                           value="<?php echo esc_attr( get_option( 'aropay_withdrawal_fee_percent', '1.50' ) ); ?>"
                           class="small-text"> %
                    <p class="description"><?php esc_html_e( 'Charged when a user withdraws from their AROPay wallet to mobile money.', 'aropay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="settlement_schedule"><?php esc_html_e( 'Settlement Schedule', 'aropay' ); ?></label></th>
                <td>
                    <select name="settlement_schedule" id="settlement_schedule">
                        <option value="daily"  <?php selected( get_option( 'aropay_settlement_schedule' ), 'daily' ); ?>><?php esc_html_e( 'Daily (2:00 AM EAT)', 'aropay' ); ?></option>
                        <option value="weekly" <?php selected( get_option( 'aropay_settlement_schedule' ), 'weekly' ); ?>><?php esc_html_e( 'Weekly (Monday 2:00 AM EAT)', 'aropay' ); ?></option>
                        <option value="manual" <?php selected( get_option( 'aropay_settlement_schedule' ), 'manual' ); ?>><?php esc_html_e( 'Manual Only', 'aropay' ); ?></option>
                    </select>
                </td>
            </tr>

        <?php elseif ( 'branding' === $active_tab ) : ?>

            <tr><th colspan="2"><h2><?php esc_html_e( 'Branding & Support', 'aropay' ); ?></h2></th></tr>
            <tr>
                <th><label for="display_name"><?php esc_html_e( 'Plugin Display Name', 'aropay' ); ?></label></th>
                <td>
                    <input type="text" name="display_name" id="display_name" class="regular-text"
                           value="<?php echo esc_attr( get_option( 'aropay_plugin_display_name', 'AROPay' ) ); ?>">
                    <p class="description"><?php esc_html_e( 'Shown to customers on payment pages.', 'aropay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="support_email"><?php esc_html_e( 'Support Email', 'aropay' ); ?></label></th>
                <td>
                    <input type="email" name="support_email" id="support_email" class="regular-text"
                           value="<?php echo esc_attr( get_option( 'aropay_support_email' ) ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="support_phone"><?php esc_html_e( 'Support Phone', 'aropay' ); ?></label></th>
                <td>
                    <input type="text" name="support_phone" id="support_phone" class="regular-text"
                           value="<?php echo esc_attr( get_option( 'aropay_support_phone' ) ); ?>">
                </td>
            </tr>

        <?php endif; ?>

        </table>

        <?php if ( in_array( $active_tab, array( 'yo', 'pesapal', 'fees', 'branding' ), true ) ) : ?>
        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                💾 <?php esc_html_e( 'Save Settings', 'aropay' ); ?>
            </button>
        </p>
        <?php endif; ?>

    </form>

</div><!-- .wrap -->

<!-- ── JavaScript: show license box when "Yes" choice is clicked ─── -->
<script>
(function(){
    var yesCard = document.getElementById('aq-choice-yes');
    var licSec  = document.getElementById('aropay-license-section');
    if ( yesCard && licSec ) {
        yesCard.style.cursor = 'pointer';
        yesCard.addEventListener('click', function(e){
            if ( e.target.tagName === 'BUTTON' || e.target.tagName === 'FORM' ) return;
            licSec.style.display = '';
            licSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
})();
</script>

<style>
/* ── Layout ─────────────────────────────────────────────────────────── */
.aropay-admin-wrap{max-width:900px;}
.aropay-admin-header h1{display:flex;align-items:center;gap:10px;font-size:22px;}
.aropay-logo-badge{background:#2271b1;color:#fff;border-radius:6px;padding:2px 8px;font-weight:800;font-size:18px;}
.aropay-admin-header h1 small{font-size:13px;font-weight:400;opacity:.6;}

/* ── Fee Transparency Banner ────────────────────────────────────────── */
.aropay-fee-banner{display:flex;align-items:flex-start;gap:16px;background:#fffbeb;border:2px solid #f0b429;border-radius:12px;padding:20px 24px;margin:20px 0 24px;box-shadow:0 2px 8px rgba(240,180,41,.12);}
.aropay-fee-banner-icon{font-size:32px;flex-shrink:0;line-height:1;}
.aropay-fee-banner-body strong{display:block;font-size:15px;margin-bottom:10px;color:#7a4f00;}
.aropay-fee-split{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;}
.aropay-fee-pill{padding:5px 14px;border-radius:20px;font-size:13px;font-weight:600;}
.aropay-fee-gateway{background:#dbeafe;color:#1d4ed8;}
.aropay-fee-arosoft{background:#d1fae5;color:#065f46;}
.aropay-fee-plus{font-size:18px;font-weight:700;color:#555;}
.aropay-fee-note{margin:0;font-size:12.5px;color:#7a6600;}

/* ── API Question Card ──────────────────────────────────────────────── */
.aropay-api-question-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:28px;margin-bottom:24px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.aropay-aq-title{font-size:17px;margin:0 0 8px;}
.aropay-aq-subtitle{color:#555;margin:0 0 20px;font-size:13.5px;line-height:1.6;}
.aropay-aq-choices{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.aropay-aq-choice{display:flex;align-items:center;gap:14px;border:2px solid #e0e0e0;border-radius:10px;padding:16px 20px;transition:border-color .2s,box-shadow .2s;}
.aropay-aq-selected{border-color:#2271b1;background:#f0f7ff;box-shadow:0 0 0 3px rgba(34,113,177,.1);}
.aropay-aq-choice-icon{font-size:28px;flex-shrink:0;}
.aropay-aq-choice-label{flex:1;}
.aropay-aq-choice-label strong{display:block;font-size:14px;margin-bottom:3px;}
.aropay-aq-choice-label span{font-size:12.5px;color:#666;}
.aropay-aq-tick{font-size:22px;flex-shrink:0;}
.aropay-api-note{background:#f9f9f9;border-left:4px solid #c3d9f7;padding:10px 16px;border-radius:6px;font-size:13px;color:#444;}
.aropay-api-note a{font-weight:600;}

/* ── License Section ────────────────────────────────────────────────── */
.aropay-section-card{background:#fff;border:1px solid #c3d9f7;border-radius:12px;padding:24px;margin-bottom:24px;}
.aropay-section-card h3{margin:0 0 8px;font-size:15px;}
.aropay-section-card p{font-size:13px;color:#555;margin-bottom:16px;}
.aropay-inline-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.aropay-license-badge-active{background:#d1fae5;color:#065f46;font-weight:700;padding:4px 12px;border-radius:20px;font-size:13px;}
.aropay-license-badge-inactive{background:#fee2e2;color:#991b1b;font-weight:700;padding:4px 12px;border-radius:20px;font-size:13px;}

/* ── Info note inside tab ───────────────────────────────────────────── */
.aropay-info-note{background:#eff6ff;border-left:4px solid #3b82f6;padding:10px 14px;border-radius:6px;font-size:13px;margin-top:10px;}

@media(max-width:700px){.aropay-aq-choices{grid-template-columns:1fr;}.aropay-fee-split{flex-direction:column;}}
</style>

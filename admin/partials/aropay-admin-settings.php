<?php defined( 'ABSPATH' ) || exit;

$current_mode    = AROPay_License::get_mode();
$license_check   = AROPay_License::check();
$own_api_active  = AROPay_License::own_api_active();
?>
<div class="wrap aropay-admin-wrap">

    <div class="aropay-admin-header">
        <h1 class="aropay-logo-title"><span class="aropay-logo-badge">ARO</span>Pay <span style="font-size:14px;font-weight:400;opacity:.7;">Settings</span></h1>
    </div>

    <?php if ( ! empty( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'aropay' ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $_GET['license_msg'] ) ) :
        $lmsg = sanitize_text_field( $_GET['license_msg'] );
        $type = ( 'activated' === $lmsg ) ? 'success' : 'error';
        $text = ( 'activated' === $lmsg )
            ? __( 'License activated! Own API mode is now unlocked.', 'aropay' )
            : __( 'License activation failed. Please check your key and try again.', 'aropay' );
    ?>
        <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $text ); ?></p></div>
    <?php endif; ?>

    <!-- ── Mode Selection Cards ───────────────────────────────────────────── -->
    <div class="aropay-mode-cards">

        <div class="aropay-mode-card <?php echo 'managed' === $current_mode ? 'aropay-mode-active' : ''; ?>">
            <div class="aropay-mode-badge">⭐ <?php esc_html_e( 'Recommended', 'aropay' ); ?></div>
            <div class="aropay-mode-icon">🏦</div>
            <h2><?php esc_html_e( 'Managed by AROSOFT', 'aropay' ); ?></h2>
            <p class="aropay-mode-desc">
                <?php esc_html_e( 'AROSOFT Innovations Ltd collects all payments on your behalf using its licensed Yo Uganda and Pesapal accounts. Your customers\' funds land directly in your AROPay wallet. Withdraw to mobile money anytime.', 'aropay' ); ?>
            </p>
            <ul class="aropay-mode-checklist">
                <li>✅ <?php esc_html_e( 'No API accounts needed — zero setup', 'aropay' ); ?></li>
                <li>✅ <?php esc_html_e( 'Funds go to your AROPay wallet instantly', 'aropay' ); ?></li>
                <li>✅ <?php esc_html_e( 'Withdraw to MTN or Airtel anytime', 'aropay' ); ?></li>
                <li>✅ <?php esc_html_e( 'No monthly subscription fee', 'aropay' ); ?></li>
                <li>💰 <?php printf( esc_html__( '%s%% platform fee per transaction (includes Pesapal\'s 3%%)', 'aropay' ), esc_html( get_option( 'aropay_default_fee_percent', '7.00' ) ) ); ?></li>
            </ul>
            <?php if ( 'managed' !== $current_mode ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'aropay_switch_mode' ); ?>
                    <input type="hidden" name="action" value="aropay_switch_mode">
                    <input type="hidden" name="mode" value="managed">
                    <button type="submit" class="button button-primary aropay-mode-btn">
                        <?php esc_html_e( 'Switch to Managed Mode', 'aropay' ); ?>
                    </button>
                </form>
            <?php else : ?>
                <span class="aropay-mode-current-badge">✅ <?php esc_html_e( 'Currently Active', 'aropay' ); ?></span>
            <?php endif; ?>
        </div>

        <div class="aropay-mode-card <?php echo 'own_api' === $current_mode ? 'aropay-mode-active' : ''; ?>">
            <div class="aropay-mode-icon">🔑</div>
            <h2><?php esc_html_e( 'Own API Credentials', 'aropay' ); ?></h2>
            <p class="aropay-mode-desc">
                <?php esc_html_e( 'Use your own licensed Yo Uganda and Pesapal accounts. Payments go directly to your provider accounts — full control of your float and settlement.', 'aropay' ); ?>
            </p>
            <ul class="aropay-mode-checklist">
                <li>🔑 <?php esc_html_e( 'Requires your own Yo Uganda licence', 'aropay' ); ?></li>
                <li>🔑 <?php esc_html_e( 'Requires your own Pesapal OpenFloat account', 'aropay' ); ?></li>
                <li>💳 <?php printf( esc_html__( '%s/month plugin subscription (billed by AROSOFT)', 'aropay' ), esc_html( AROPay_License::subscription_price() ) ); ?></li>
                <li>💰 <?php esc_html_e( 'Pesapal charges 3% independently — you set your own merchant fee', 'aropay' ); ?></li>
            </ul>

            <?php if ( 'own_api' !== $current_mode ) : ?>
                <?php if ( ! empty( AROPay_License::get_key() ) && $license_check['valid'] ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'aropay_switch_mode' ); ?>
                        <input type="hidden" name="action" value="aropay_switch_mode">
                        <input type="hidden" name="mode" value="own_api">
                        <button type="submit" class="button button-secondary aropay-mode-btn">
                            <?php esc_html_e( 'Switch to Own API Mode', 'aropay' ); ?>
                        </button>
                    </form>
                <?php else : ?>
                    <a href="<?php echo esc_url( AROPay_License::purchase_url() ); ?>" target="_blank" class="button button-secondary aropay-mode-btn">
                        🛒 <?php esc_html_e( 'Purchase Subscription', 'aropay' ); ?>
                    </a>
                    <p class="description" style="margin-top:8px;">
                        <?php esc_html_e( 'Enter your license key below in the License tab after purchase.', 'aropay' ); ?>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <span class="aropay-mode-current-badge aropay-mode-current-own">⚙️ <?php esc_html_e( 'Currently Active', 'aropay' ); ?></span>
            <?php endif; ?>
        </div>

    </div><!-- .aropay-mode-cards -->

    <!-- ── API Registration Guide (shown only if Own API mode selected) ───── -->
    <?php if ( 'own_api' === $current_mode || ! empty( $_GET['show_guide'] ) ) : ?>
    <div class="aropay-registration-guide">
        <h2>📋 <?php esc_html_e( 'How to Get Your Own API Accounts', 'aropay' ); ?></h2>
        <p><?php esc_html_e( 'You need to independently register with Yo Uganda and Pesapal as a licensed payment service user. Below are the requirements and official registration links for each provider.', 'aropay' ); ?></p>

        <div class="aropay-reg-grid">

            <div class="aropay-reg-card">
                <h3>📱 Yo Uganda (Yo! Payments)</h3>
                <p><?php esc_html_e( 'Required for MTN Mobile Money and Airtel Money STK push integration.', 'aropay' ); ?></p>
                <h4><?php esc_html_e( 'Requirements', 'aropay' ); ?></h4>
                <ul>
                    <li>🏢 <?php esc_html_e( 'Certificate of Incorporation (certified copy)', 'aropay' ); ?></li>
                    <li>📄 <?php esc_html_e( 'Memorandum & Articles of Association (certified)', 'aropay' ); ?></li>
                    <li>🪪 <?php esc_html_e( 'National IDs or Passports of all Directors', 'aropay' ); ?></li>
                    <li>📋 <?php esc_html_e( 'Board Resolution / Letter of Authority', 'aropay' ); ?></li>
                    <li>🧾 <?php esc_html_e( 'TIN Certificate from URA', 'aropay' ); ?></li>
                    <li>🏦 <?php esc_html_e( 'Bank Account details or Mobile Money float account', 'aropay' ); ?></li>
                    <li>📍 <?php esc_html_e( 'Physical business address & contact details', 'aropay' ); ?></li>
                    <li>💼 <?php esc_html_e( 'Business Plan or description of use case', 'aropay' ); ?></li>
                    <li>🔗 <?php esc_html_e( 'Your active website or mobile app URL', 'aropay' ); ?></li>
                </ul>
                <div class="aropay-reg-actions">
                    <a href="https://yo.co.ug/contact" target="_blank" class="button button-primary">
                        🚀 <?php esc_html_e( 'Apply at Yo Uganda', 'aropay' ); ?>
                    </a>
                    <a href="https://yo.co.ug" target="_blank" class="button button-secondary">
                        🌐 <?php esc_html_e( 'Visit yo.co.ug', 'aropay' ); ?>
                    </a>
                </div>
                <p class="description"><?php esc_html_e( 'Processing time: typically 2–4 weeks. Yo Uganda will provide you with an API Username and Password.', 'aropay' ); ?></p>
            </div>

            <div class="aropay-reg-card">
                <h3>💳 Pesapal (OpenFloat)</h3>
                <p><?php esc_html_e( 'Required for Visa, Mastercard, and Pesapal-hosted Mobile Money payments.', 'aropay' ); ?></p>
                <h4><?php esc_html_e( 'Requirements', 'aropay' ); ?></h4>
                <ul>
                    <li>🏢 <?php esc_html_e( 'Certificate of Incorporation (certified copy)', 'aropay' ); ?></li>
                    <li>📄 <?php esc_html_e( 'Memorandum & Articles of Association', 'aropay' ); ?></li>
                    <li>🪪 <?php esc_html_e( 'National IDs / Passports of Directors & Signatories', 'aropay' ); ?></li>
                    <li>🧾 <?php esc_html_e( 'TIN Certificate from URA', 'aropay' ); ?></li>
                    <li>🏦 <?php esc_html_e( 'Bank statement (last 3 months)', 'aropay' ); ?></li>
                    <li>📋 <?php esc_html_e( 'Completed Pesapal Merchant Application Form', 'aropay' ); ?></li>
                    <li>🔗 <?php esc_html_e( 'Active website with clear product/service description', 'aropay' ); ?></li>
                    <li>📍 <?php esc_html_e( 'Physical business address and contact', 'aropay' ); ?></li>
                    <li>📊 <?php esc_html_e( 'Projected monthly transaction volumes', 'aropay' ); ?></li>
                </ul>
                <div class="aropay-reg-actions">
                    <a href="https://www.pesapal.com/merchant/register" target="_blank" class="button button-primary">
                        🚀 <?php esc_html_e( 'Apply at Pesapal', 'aropay' ); ?>
                    </a>
                    <a href="https://pesapal.com" target="_blank" class="button button-secondary">
                        🌐 <?php esc_html_e( 'Visit pesapal.com', 'aropay' ); ?>
                    </a>
                </div>
                <p class="description"><?php esc_html_e( 'Processing time: typically 1–2 weeks. Pesapal will provide a Consumer Key and Consumer Secret for the OpenFloat API. Note: Pesapal charges a 3% transaction fee independently.', 'aropay' ); ?></p>
            </div>

        </div><!-- .aropay-reg-grid -->

        <div class="aropay-notice aropay-notice-info">
            <strong>💡 <?php esc_html_e( 'Tip:', 'aropay' ); ?></strong>
            <?php esc_html_e( 'Not ready to register your own accounts yet? Switch to Managed Mode above — AROSOFT handles everything and you still receive funds directly to your AROPay wallet.', 'aropay' ); ?>
        </div>

    </div><!-- .aropay-registration-guide -->
    <?php endif; ?>

    <!-- ── Settings Tabs ─────────────────────────────────────────────────── -->
    <nav class="nav-tab-wrapper aropay-tabs" style="margin-top:24px;">
        <?php if ( 'own_api' === $current_mode ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=license' ) ); ?>" class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
            🔑 <?php esc_html_e( 'License', 'aropay' ); ?>
            <?php if ( $own_api_active ) : ?>
                <span class="aropay-badge-green">Active</span>
            <?php else : ?>
                <span class="aropay-badge-red">!</span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=yo' ) ); ?>" class="nav-tab <?php echo 'yo' === $active_tab ? 'nav-tab-active' : ''; ?>">
            📱 <?php esc_html_e( 'Yo Uganda API', 'aropay' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=pesapal' ) ); ?>" class="nav-tab <?php echo 'pesapal' === $active_tab ? 'nav-tab-active' : ''; ?>">
            💳 <?php esc_html_e( 'Pesapal API', 'aropay' ); ?>
        </a>
        <?php else : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=yo' ) ); ?>" class="nav-tab <?php echo 'yo' === $active_tab ? 'nav-tab-active' : ''; ?>">
            📱 <?php esc_html_e( 'Yo Uganda', 'aropay' ); ?> <span class="aropay-managed-tag"><?php esc_html_e( 'Managed', 'aropay' ); ?></span>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=pesapal' ) ); ?>" class="nav-tab <?php echo 'pesapal' === $active_tab ? 'nav-tab-active' : ''; ?>">
            💳 <?php esc_html_e( 'Pesapal', 'aropay' ); ?> <span class="aropay-managed-tag"><?php esc_html_e( 'Managed', 'aropay' ); ?></span>
        </a>
        <?php endif; ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=fees' ) ); ?>" class="nav-tab <?php echo 'fees' === $active_tab ? 'nav-tab-active' : ''; ?>">
            💰 <?php esc_html_e( 'Fees & Settlement', 'aropay' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=branding' ) ); ?>" class="nav-tab <?php echo 'branding' === $active_tab ? 'nav-tab-active' : ''; ?>">
            🎨 <?php esc_html_e( 'Branding', 'aropay' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&show_guide=1' ) ); ?>" class="nav-tab <?php echo ! empty( $_GET['show_guide'] ) ? 'nav-tab-active' : ''; ?>">
            📋 <?php esc_html_e( 'API Registration Guide', 'aropay' ); ?>
        </a>
    </nav>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aropay-settings-form">
        <?php wp_nonce_field( 'aropay_save_settings' ); ?>
        <input type="hidden" name="action" value="aropay_save_settings">
        <input type="hidden" name="aropay_tab" value="<?php echo esc_attr( $active_tab ); ?>">

        <table class="form-table aropay-form-table">

        <?php if ( 'license' === $active_tab && 'own_api' === $current_mode ) : ?>

            <tr>
                <th colspan="2">
                    <h2><?php esc_html_e( 'AROPay Own API Subscription', 'aropay' ); ?></h2>
                    <p><?php printf(
                        esc_html__( 'Own API mode requires an active %s subscription from AROSOFT Innovations Ltd.', 'aropay' ),
                        '<strong>' . esc_html( AROPay_License::subscription_price() ) . '</strong>'
                    ); ?></p>
                </th>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Subscription Status', 'aropay' ); ?></th>
                <td>
                    <?php if ( $license_check['valid'] ) : ?>
                        <span class="aropay-license-status aropay-license-active">
                            ✅ <?php esc_html_e( 'Active', 'aropay' ); ?>
                        </span>
                        <?php if ( ! empty( $license_check['expires'] ) ) : ?>
                            <span class="description">&nbsp;&mdash;&nbsp;<?php echo esc_html( sprintf( __( 'Renews %s', 'aropay' ), $license_check['expires'] ) ); ?></span>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="aropay-license-status aropay-license-inactive">
                            ❌ <?php echo esc_html( $license_check['message'] ); ?>
                        </span>
                        <br><br>
                        <a href="<?php echo esc_url( AROPay_License::purchase_url() ); ?>" target="_blank" class="button button-primary">
                            🛒 <?php esc_html_e( 'Purchase Subscription', 'aropay' ); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label for="license_key"><?php esc_html_e( 'License Key', 'aropay' ); ?></label></th>
                <td>
                    <input type="text" name="license_key" id="license_key" class="regular-text"
                           value="<?php echo esc_attr( AROPay_License::get_key() ); ?>"
                           placeholder="AROP-XXXX-XXXX-XXXX" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'Enter the license key from your AROSOFT account dashboard.', 'aropay' ); ?></p>
                </td>
            </tr>

            <tr>
                <th></th>
                <td>
                    <a href="<?php echo esc_url( AROPay_License::purchase_url() ); ?>" target="_blank" class="button button-secondary" style="margin-right:8px;">
                        🛒 <?php esc_html_e( 'Buy / Manage Subscription', 'aropay' ); ?>
                    </a>
                    <span class="description"><?php printf( esc_html__( 'Cost: %s — Cancel anytime.', 'aropay' ), esc_html( AROPay_License::subscription_price() ) ); ?></span>
                </td>
            </tr>

        <?php elseif ( 'yo' === $active_tab ) : ?>

            <tr>
                <th colspan="2">
                    <h2><?php esc_html_e( 'Yo Uganda API', 'aropay' ); ?></h2>
                    <?php if ( AROPay_License::is_managed() ) : ?>
                    <div class="aropay-managed-notice">
                        🏦 <?php esc_html_e( 'Managed Mode: AROPay uses AROSOFT\'s licensed Yo Uganda account. These credentials are pre-configured. You do not need to enter anything here.', 'aropay' ); ?>
                    </div>
                    <?php else : ?>
                    <p><?php esc_html_e( 'Enter your own Yo Uganda API credentials. Must be a licensed Yo Uganda merchant account.', 'aropay' ); ?></p>
                    <?php endif; ?>
                </th>
            </tr>
            <tr>
                <th><label for="yo_test_mode"><?php esc_html_e( 'Mode', 'aropay' ); ?></label></th>
                <td>
                    <select name="yo_test_mode" id="yo_test_mode" <?php echo AROPay_License::is_managed() ? 'disabled' : ''; ?>>
                        <option value="yes" <?php selected( get_option( 'aropay_yo_test_mode' ), 'yes' ); ?>><?php esc_html_e( 'Sandbox / Test', 'aropay' ); ?></option>
                        <option value="no"  <?php selected( get_option( 'aropay_yo_test_mode' ), 'no' ); ?>><?php esc_html_e( 'Live / Production', 'aropay' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="yo_username"><?php esc_html_e( 'API Username', 'aropay' ); ?></label></th>
                <td>
                    <input type="text" name="yo_username" id="yo_username" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_yo_username' ) ); ?>"
                           autocomplete="off" <?php echo AROPay_License::is_managed() ? 'disabled placeholder="Managed by AROSOFT"' : ''; ?>>
                </td>
            </tr>
            <tr>
                <th><label for="yo_password"><?php esc_html_e( 'API Password', 'aropay' ); ?></label></th>
                <td>
                    <input type="password" name="yo_password" id="yo_password" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_yo_password' ) ); ?>"
                           autocomplete="new-password" <?php echo AROPay_License::is_managed() ? 'disabled placeholder="Managed by AROSOFT"' : ''; ?>>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Callback URL', 'aropay' ); ?></th>
                <td>
                    <code><?php echo esc_html( AROPay_Yo_API::get_callback_url() ); ?></code>
                    <?php if ( AROPay_License::is_own_api() ) : ?>
                    <p class="description"><?php esc_html_e( 'Configure this URL in your Yo Uganda merchant portal as the Payment Notification URL.', 'aropay' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ( AROPay_License::is_managed() ) : ?>
            <tr>
                <td colspan="2">
                    <div class="aropay-managed-footer">
                        <?php esc_html_e( 'Want to use your own Yo Uganda account?', 'aropay' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&show_guide=1' ) ); ?>"><?php esc_html_e( 'View API Registration Guide →', 'aropay' ); ?></a>
                    </div>
                </td>
            </tr>
            <?php endif; ?>

        <?php elseif ( 'pesapal' === $active_tab ) : ?>

            <tr>
                <th colspan="2">
                    <h2><?php esc_html_e( 'Pesapal OpenFloat API', 'aropay' ); ?></h2>
                    <?php if ( AROPay_License::is_managed() ) : ?>
                    <div class="aropay-managed-notice">
                        🏦 <?php esc_html_e( 'Managed Mode: AROPay uses AROSOFT\'s licensed Pesapal OpenFloat account. You do not need to enter credentials here.', 'aropay' ); ?>
                    </div>
                    <?php else : ?>
                    <p><?php esc_html_e( 'Enter your own Pesapal OpenFloat credentials. Pesapal charges a 3% fee on all transactions independently of AROPay.', 'aropay' ); ?></p>
                    <?php endif; ?>
                </th>
            </tr>
            <tr>
                <th><label for="pesapal_test_mode"><?php esc_html_e( 'Mode', 'aropay' ); ?></label></th>
                <td>
                    <select name="pesapal_test_mode" id="pesapal_test_mode" <?php echo AROPay_License::is_managed() ? 'disabled' : ''; ?>>
                        <option value="yes" <?php selected( get_option( 'aropay_pesapal_test_mode' ), 'yes' ); ?>><?php esc_html_e( 'Sandbox / Test', 'aropay' ); ?></option>
                        <option value="no"  <?php selected( get_option( 'aropay_pesapal_test_mode' ), 'no' ); ?>><?php esc_html_e( 'Live / Production', 'aropay' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="pesapal_consumer_key"><?php esc_html_e( 'Consumer Key', 'aropay' ); ?></label></th>
                <td>
                    <input type="text" name="pesapal_consumer_key" id="pesapal_consumer_key" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_pesapal_consumer_key' ) ); ?>"
                           autocomplete="off" <?php echo AROPay_License::is_managed() ? 'disabled placeholder="Managed by AROSOFT"' : ''; ?>>
                </td>
            </tr>
            <tr>
                <th><label for="pesapal_consumer_secret"><?php esc_html_e( 'Consumer Secret', 'aropay' ); ?></label></th>
                <td>
                    <input type="password" name="pesapal_consumer_secret" id="pesapal_consumer_secret" class="regular-text"
                           value="<?php echo esc_attr( AROPay_Encryption::get_option( 'aropay_pesapal_consumer_secret' ) ); ?>"
                           autocomplete="new-password" <?php echo AROPay_License::is_managed() ? 'disabled placeholder="Managed by AROSOFT"' : ''; ?>>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'IPN URL', 'aropay' ); ?></th>
                <td>
                    <code><?php echo esc_html( AROPay_Pesapal_API::get_ipn_url() ); ?></code>
                    <p class="description"><?php esc_html_e( 'Auto-registered with Pesapal on first payment.', 'aropay' ); ?></p>
                </td>
            </tr>
            <?php if ( AROPay_License::is_managed() ) : ?>
            <tr>
                <td colspan="2">
                    <div class="aropay-managed-footer">
                        <?php esc_html_e( 'Want to use your own Pesapal account?', 'aropay' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&show_guide=1' ) ); ?>"><?php esc_html_e( 'View API Registration Guide →', 'aropay' ); ?></a>
                    </div>
                </td>
            </tr>
            <?php endif; ?>

        <?php elseif ( 'fees' === $active_tab ) : ?>

            <tr>
                <th colspan="2">
                    <h2><?php esc_html_e( 'Fee & Settlement Configuration', 'aropay' ); ?></h2>
                </th>
            </tr>

            <?php if ( AROPay_License::is_managed() ) : ?>
            <tr>
                <td colspan="2">
                    <div class="aropay-fee-breakdown">
                        <h3>💰 <?php esc_html_e( 'Transaction Fee Breakdown (Managed Mode)', 'aropay' ); ?></h3>
                        <table class="widefat" style="max-width:500px;">
                            <tr>
                                <td><?php esc_html_e( 'Pesapal base fee', 'aropay' ); ?></td>
                                <td><strong>3.00%</strong></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'AROSOFT platform fee', 'aropay' ); ?></td>
                                <td><strong><?php echo esc_html( number_format( (float) get_option( 'aropay_default_fee_percent', 7.00 ) - 3.00, 2 ) ); ?>%</strong></td>
                            </tr>
                            <tr style="background:#f5f5f5;">
                                <td><strong><?php esc_html_e( 'Total charged per transaction', 'aropay' ); ?></strong></td>
                                <td><strong><?php echo esc_html( number_format( (float) get_option( 'aropay_default_fee_percent', 7.00 ), 2 ) ); ?>%</strong></td>
                            </tr>
                        </table>
                        <p class="description"><?php esc_html_e( 'Merchants receive the net amount in their AROPay wallet. Adjust the total percentage below.', 'aropay' ); ?></p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <th><label for="default_fee_percent"><?php esc_html_e( 'Total Transaction Fee (%)', 'aropay' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" min="0" max="100" name="default_fee_percent" id="default_fee_percent"
                           value="<?php echo esc_attr( get_option( 'aropay_default_fee_percent', '7.00' ) ); ?>" class="small-text">
                    <span> %</span>
                    <?php if ( AROPay_License::is_managed() ) : ?>
                    <p class="description">
                        <?php esc_html_e( 'Includes Pesapal\'s fixed 3%. Minimum recommended: 7%. Merchants\' net is: Amount × (1 - Fee%).', 'aropay' ); ?>
                    </p>
                    <?php else : ?>
                    <p class="description"><?php esc_html_e( 'Your merchant-side fee. Note: Pesapal separately charges 3% directly.', 'aropay' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="min_fee_ugx"><?php esc_html_e( 'Minimum Fee (UGX)', 'aropay' ); ?></label></th>
                <td>
                    <input type="number" step="100" min="0" name="min_fee_ugx" id="min_fee_ugx"
                           value="<?php echo esc_attr( get_option( 'aropay_min_fee_ugx', 500 ) ); ?>" class="small-text">
                    <span> UGX</span>
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

        <?php if ( ! empty( $_GET['show_guide'] ) ) : ?>
            <!-- No form submit on guide tab -->
        <?php elseif ( 'license' === $active_tab ) : ?>
        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                🔑 <?php esc_html_e( 'Activate License Key', 'aropay' ); ?>
            </button>
        </p>
        <?php elseif ( ! in_array( $active_tab, array( 'yo', 'pesapal' ), true ) || AROPay_License::is_own_api() ) : ?>
        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                💾 <?php esc_html_e( 'Save Settings', 'aropay' ); ?>
            </button>
        </p>
        <?php endif; ?>

    </form>

</div>
<style>
.aropay-mode-cards{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:24px 0;}
.aropay-mode-card{background:#fff;border:2px solid #e0e0e0;border-radius:12px;padding:28px;position:relative;transition:border-color .2s;}
.aropay-mode-active{border-color:#2271b1;box-shadow:0 0 0 3px rgba(34,113,177,.12);}
.aropay-mode-badge{position:absolute;top:14px;right:14px;background:#f0b429;color:#5a3c00;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;}
.aropay-mode-icon{font-size:40px;margin-bottom:12px;}
.aropay-mode-card h2{font-size:18px;margin:0 0 8px;}
.aropay-mode-desc{color:#555;font-size:13.5px;line-height:1.6;margin-bottom:16px;}
.aropay-mode-checklist{list-style:none;margin:0 0 20px;padding:0;}
.aropay-mode-checklist li{font-size:13px;margin-bottom:6px;padding-left:4px;}
.aropay-mode-btn{width:100%;text-align:center;margin-top:4px;}
.aropay-mode-current-badge{display:inline-block;background:#d1fae5;color:#065f46;padding:6px 18px;border-radius:20px;font-weight:600;font-size:13px;}
.aropay-mode-current-own{background:#dbeafe;color:#1e40af;}
.aropay-managed-notice{background:#fef9c3;border-left:4px solid #f0b429;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px;}
.aropay-managed-footer{background:#f0f7ff;border:1px solid #c3d9f7;padding:10px 16px;border-radius:6px;font-size:13px;}
.aropay-managed-tag{background:#e0efff;color:#1e5fa8;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:4px;vertical-align:middle;}
.aropay-license-status{font-weight:700;font-size:14px;padding:4px 12px;border-radius:6px;display:inline-block;}
.aropay-license-active{background:#d1fae5;color:#065f46;}
.aropay-license-inactive{background:#fee2e2;color:#991b1b;}
.aropay-badge-green{background:#d1fae5;color:#065f46;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:4px;}
.aropay-badge-red{background:#fee2e2;color:#991b1b;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:4px;}
.aropay-fee-breakdown{background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:24px;}
.aropay-fee-breakdown h3{margin:0 0 14px;}
.aropay-registration-guide{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:28px;margin:24px 0;}
.aropay-registration-guide h2{margin:0 0 10px;}
.aropay-reg-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:20px 0;}
.aropay-reg-card{background:#f9f9f9;border:1px solid #e0e0e0;border-radius:10px;padding:22px;}
.aropay-reg-card h3{margin:0 0 8px;font-size:16px;}
.aropay-reg-card h4{margin:14px 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:#555;}
.aropay-reg-card ul{margin:0 0 16px;padding-left:0;list-style:none;}
.aropay-reg-card ul li{font-size:13px;margin-bottom:5px;}
.aropay-reg-actions{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;}
@media(max-width:900px){.aropay-mode-cards,.aropay-reg-grid{grid-template-columns:1fr;}}
</style>

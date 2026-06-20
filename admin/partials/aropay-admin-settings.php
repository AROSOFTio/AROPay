<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aropay-admin-wrap">

    <h1><?php esc_html_e( 'AROPay Settings', 'aropay' ); ?></h1>

    <?php if ( ! empty( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'aropay' ); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper aropay-tabs">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=yo' ) ); ?>" class="nav-tab <?php echo 'yo' === $active_tab ? 'nav-tab-active' : ''; ?>">
            📱 <?php esc_html_e( 'Yo Uganda', 'aropay' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=pesapal' ) ); ?>" class="nav-tab <?php echo 'pesapal' === $active_tab ? 'nav-tab-active' : ''; ?>">
            💳 <?php esc_html_e( 'Pesapal', 'aropay' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=fees' ) ); ?>" class="nav-tab <?php echo 'fees' === $active_tab ? 'nav-tab-active' : ''; ?>">
            💰 <?php esc_html_e( 'Fees & Settlement', 'aropay' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aropay-settings&tab=branding' ) ); ?>" class="nav-tab <?php echo 'branding' === $active_tab ? 'nav-tab-active' : ''; ?>">
            🎨 <?php esc_html_e( 'Branding', 'aropay' ); ?>
        </a>
    </nav>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aropay-settings-form">
        <?php wp_nonce_field( 'aropay_save_settings' ); ?>
        <input type="hidden" name="action" value="aropay_save_settings">
        <input type="hidden" name="aropay_tab" value="<?php echo esc_attr( $active_tab ); ?>">

        <table class="form-table aropay-form-table">

            <?php if ( 'yo' === $active_tab ) : ?>

                <tr>
                    <th colspan="2"><h2><?php esc_html_e( 'Yo Uganda API Credentials', 'aropay' ); ?></h2>
                    <p><?php esc_html_e( 'These are YOUR Yo Uganda master credentials (the account licensed under AROSOFT/your company).', 'aropay' ); ?></p></th>
                </tr>
                <tr>
                    <th><label for="yo_test_mode"><?php esc_html_e( 'Mode', 'aropay' ); ?></label></th>
                    <td>
                        <select name="yo_test_mode" id="yo_test_mode">
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
                        <p class="description"><?php esc_html_e( 'Copy this URL into your Yo Uganda account as the callback/notification URL.', 'aropay' ); ?></p>
                    </td>
                </tr>

            <?php elseif ( 'pesapal' === $active_tab ) : ?>

                <tr>
                    <th colspan="2"><h2><?php esc_html_e( 'Pesapal OpenFloat Credentials', 'aropay' ); ?></h2>
                    <p><?php esc_html_e( 'Your Pesapal OpenFloat Consumer Key and Secret. All merchant payments route through your float.', 'aropay' ); ?></p></th>
                </tr>
                <tr>
                    <th><label for="pesapal_test_mode"><?php esc_html_e( 'Mode', 'aropay' ); ?></label></th>
                    <td>
                        <select name="pesapal_test_mode" id="pesapal_test_mode">
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
                        <p class="description"><?php esc_html_e( 'This is auto-registered with Pesapal on first payment.', 'aropay' ); ?></p>
                    </td>
                </tr>

            <?php elseif ( 'fees' === $active_tab ) : ?>

                <tr>
                    <th colspan="2"><h2><?php esc_html_e( 'Fee & Settlement Configuration', 'aropay' ); ?></h2></th>
                </tr>
                <tr>
                    <th><label for="default_fee_percent"><?php esc_html_e( 'Default Transaction Fee (%)', 'aropay' ); ?></label></th>
                    <td>
                        <input type="number" step="0.01" min="0" max="100" name="default_fee_percent" id="default_fee_percent"
                               value="<?php echo esc_attr( get_option( 'aropay_default_fee_percent', 1.50 ) ); ?>" class="small-text">
                        <span> %</span>
                        <p class="description"><?php esc_html_e( 'Deducted from each completed transaction before settlement.', 'aropay' ); ?></p>
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

                <tr>
                    <th colspan="2"><h2><?php esc_html_e( 'Branding & Support', 'aropay' ); ?></h2></th>
                </tr>
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

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                💾 <?php esc_html_e( 'Save Settings', 'aropay' ); ?>
            </button>
        </p>

    </form>
</div>

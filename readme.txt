=== AROPay — Uganda Payment Gateway ===
Contributors:       arosoft
Tags:               woocommerce, payment, mtn, airtel, mobile money, uganda, pesapal, yo uganda
Requires at least:  5.0
Tested up to:       6.7
Requires PHP:       7.4
Stable tag:         1.1.0
License:            GPLv2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

Uganda's premier WooCommerce payment gateway — MTN MoMo, Airtel Money & Cards via Yo Uganda and Pesapal OpenFloat. Includes a built-in user wallet with instant mobile money withdrawals. Powered by AROSOFT.

== Description ==

**AROPay** is a fully managed payment gateway plugin for WooCommerce stores operating in Uganda. Powered by AROSOFT and available at [arosoftlabs.com](https://arosoftlabs.com/market/plugins/aropay).

= Payment Methods =

* **MTN Mobile Money** — Real-time STK push via Yo Uganda
* **Airtel Money** — Real-time STK push via Yo Uganda
* **Visa / Mastercard** — Cards via Pesapal OpenFloat
* **MTN & Airtel via Pesapal** — Hosted payment page

= Built-in Wallet & Withdrawals =

AROPay includes a full **wallet system** for your site's users:

* Users accumulate a balance from WooCommerce transactions
* Each user can register up to **2 withdrawal phones** (1 MTN + 1 Airtel)
* Registered phones are **reviewed and approved by the site admin** before use
* Users set a private **withdrawal secret PIN** to authorise every payout
* Instant withdrawal to approved mobile money numbers via Yo Uganda B2C
* Full withdrawal history visible to users on the My Account page
* Users can **deactivate, reactivate, or request a number change** at any time
* Number change requests require admin approval before taking effect
* All wallet actions are captured in an **immutable audit log**

= Admin & Developer Dashboard =

* Full user wallet overview — balances, lifetime credited and withdrawn
* Payout phone approval queue — approve or reject with optional admin note
* Phone change request management — approve/reject number swaps
* Immutable audit log — filterable by user, action, and date; exportable to CSV
* Transaction history, merchant management, and settlement tracking

= How It Works =

AROPay uses a **fully managed float model**:

1. Merchants install AROPay and enter their AROSOFT Merchant API Key
2. All payments route through AROSOFT's licensed Yo Uganda + Pesapal accounts
3. AROSOFT collects a small transaction fee and settles the net amount to the merchant
4. Merchants never need their own Yo Uganda or Pesapal accounts

= Features =

* ✅ Works with ALL WooCommerce-compatible themes (Astra, Divi, Avada, Storefront, Flatsome, OceanWP, GeneratePress, Blocksy, Kadence, Hello Elementor, and more)
* ✅ Supports classic shortcode checkout AND WooCommerce Blocks checkout
* ✅ Real-time MoMo STK push with live polling — no page refresh needed
* ✅ Auto-detects MTN vs Airtel from phone number
* ✅ Built-in user wallet with instant mobile money withdrawals
* ✅ Admin dashboard with transaction history, merchant management, and settlements
* ✅ Sandbox/test mode for both providers
* ✅ AES-256 encrypted credentials storage
* ✅ HPOS (High-Performance Order Storage) compatible
* ✅ Immutable audit log for all wallet actions
* ✅ GPL-2.0+ licensed — 100% open source

= Requirements =

* WordPress 5.0+
* WooCommerce 4.0+
* PHP 7.4+
* An active AROPay Merchant Account from [AROSOFT](https://arosoftlabs.com/market/plugins/aropay)
* WooCommerce currency must be set to **UGX (Uganda Shillings)**

== Installation ==

1. In your WordPress dashboard go to **Plugins → Add New → Upload Plugin**
2. Upload the plugin zip file and click **Activate Plugin**
3. Go to **WooCommerce → Settings → Payments**
4. Enable **"AROPay — MTN & Airtel MoMo"** and/or **"AROPay — Cards & MoMo (Pesapal)"**
5. Click **Set Up** and enter your AROPay Merchant API Key and Secret
6. Save settings — payments are now live

**To enable the Wallet feature:**

7. Go to **AROPay → Settings** to configure withdrawal fees and minimum amounts
8. Add the `[aropay_wallet]` shortcode to any page, or instruct users to visit **My Account → My Wallet**
9. Users register their payout phones; approve them from **AROPay → Payout Phones**

For detailed setup instructions, see the [SETUP.md](https://github.com/AROSOFTio/AROPay) guide included with the plugin.

== Frequently Asked Questions ==

= Do I need my own Yo Uganda or Pesapal account? =

No. AROPay is fully managed — all payments flow through AROSOFT's licensed accounts. You only need an AROPay Merchant API Key from AROSOFT.

= What currency does AROPay support? =

AROPay only supports **UGX (Uganda Shillings)**. Ensure your WooCommerce store currency is set to UGX before enabling the plugin.

= Is there a transaction fee? =

Yes. AROSOFT deducts a small percentage fee per transaction (configured in the admin dashboard). The net amount is settled to your nominated Mobile Money or bank account.

= Can I use it in test/sandbox mode? =

Yes. Both Yo Uganda and Pesapal have sandbox environments. Toggle test mode per provider under **AROPay → Settings**.

= Is it compatible with my theme? =

Yes. AROPay uses only WooCommerce-standard hooks and CSS classes — it works with every WooCommerce-compatible theme.

= Does it support WooCommerce Blocks? =

Yes. AROPay registers payment methods for both the classic shortcode checkout and the modern WooCommerce Blocks (Gutenberg) checkout.

= How does the wallet work? =

Users accumulate a balance when payments are credited to their account. They can register up to two withdrawal phones (one MTN, one Airtel). Phones must be approved by the site admin. When withdrawing, the user must enter their private secret PIN. Funds are sent instantly via Yo Uganda's B2C API.

= What happens if a withdrawal fails? =

If the Yo Uganda API returns a failure or the payout times out, the held amount is automatically refunded back to the user's available wallet balance. A failure record is written to the audit log.

= How is the withdrawal PIN stored? =

The PIN is hashed with `wp_hash_password()` (phpass) and stored in WordPress user meta. It is **never stored in plain text** and cannot be recovered — only reset.

= Can a user have two phones on the same network? =

No. The system enforces a maximum of one MTN number and one Airtel number per user. Attempting to register a second number on the same network is blocked server-side.

= What is the audit log? =

An immutable, append-only log stored in the database that records every sensitive wallet action: credits, debits, withdrawals, PIN changes, phone registrations, deactivations, and change requests — including IP address and timestamp. Only administrators can view it.

= Is AROPay GPL-compatible? =

Yes. AROPay is licensed under the **GNU General Public License v2 or later (GPLv2+)**, the same license as WordPress itself.

== Screenshots ==

1. Checkout page — MTN MoMo phone input with network auto-detection
2. Pending payment screen with live countdown
3. Admin dashboard — stats overview
4. Admin transactions list
5. Wallet dashboard — balance card, withdrawal form, and phone management
6. Admin payout phones — approval queue
7. Admin audit log — filterable by user, action, and date

== Changelog ==

= 1.1.0 =
* NEW: Built-in user wallet system with balance tracking
* NEW: Instant mobile money withdrawal via Yo Uganda B2C API
* NEW: Withdrawal phone registration (max 1 MTN + 1 Airtel per user)
* NEW: Admin phone approval / rejection workflow with notes
* NEW: Phone number change request flow with admin approval
* NEW: Phone deactivation and reactivation by user
* NEW: Secret withdrawal PIN (phpass-hashed, never stored in plaintext)
* NEW: Immutable wallet audit log with CSV export
* NEW: WooCommerce My Account "My Wallet" tab
* NEW: `[aropay_wallet]` shortcode
* NEW: Admin Wallets, Payout Phones, and Audit Log pages

= 1.0.0 =
* Initial release
* Yo Uganda gateway (MTN & Airtel MoMo via STK push)
* Pesapal OpenFloat gateway (Cards + MoMo)
* Admin dashboard with transactions, merchants, settlements
* WooCommerce Blocks support
* HPOS compatibility
* AES-256 encrypted credential storage

== Upgrade Notice ==

= 1.1.0 =
New wallet system added. Deactivate and reactivate the plugin once after upgrading to create the new database tables automatically.

= 1.0.0 =
Initial release. No upgrade needed.

== External Services ==

This plugin connects to external third-party services to process payments and wallet payouts.
By installing and activating this plugin you agree to their respective terms of service and privacy policies.

= Yo Uganda (Yo! Payments) =
Used to process MTN Mobile Money and Airtel Money payments at checkout, and to send instant wallet withdrawal payouts to users (B2C transfers).
Data transmitted: phone number, amount, currency (UGX), and transaction reference.

* Website: https://yo.co.ug
* Terms of Service: https://yo.co.ug/terms
* Privacy Policy: https://yo.co.ug/privacy

= Pesapal =
Used to process Visa, Mastercard, and Mobile Money payments via the Pesapal OpenFloat hosted payment page.
Data transmitted: customer billing name, email address, phone number, order amount, and order reference.

* Website: https://pesapal.com
* Terms of Service: https://www.pesapal.com/terms-of-service
* Privacy Policy: https://www.pesapal.com/privacy-policy

No data is transmitted to these services until a user actively initiates a payment or withdrawal on your WooCommerce store.

== Privacy Policy ==

AROPay transmits payment and payout data to Yo Uganda and Pesapal solely for the purpose of processing transactions initiated by your site's users.

**Data sent to Yo Uganda:**
Phone number, transaction amount, currency, and a unique internal reference.

**Data sent to Pesapal:**
Customer billing name, email address, phone number, order amount, and order reference.

**Data stored locally:**
* Transaction records (amount, status, masked phone number, provider reference)
* Wallet balances and withdrawal history
* Payout phone numbers (stored in your WordPress database)
* Withdrawal secret PINs (hashed with phpass — never stored in plain text)
* Audit log entries (action, amount, IP address, timestamp)

Phone numbers are masked in local API logs (e.g. `25677****123`).

Please review the privacy policies of [Yo Uganda](https://yo.co.ug/privacy) and [Pesapal](https://www.pesapal.com/privacy-policy) for details on how they handle personal data.

=== AROPay — Uganda Payment Gateway ===
Contributors:       arosoft
Tags:               woocommerce, payment, mtn, airtel, mobile money, uganda, pesapal, yo uganda, momo
Requires at least:  5.0
Tested up to:       6.5
Requires PHP:       7.4
Stable tag:         1.0.0
License:            GPLv2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

Uganda's premier WooCommerce payment gateway — MTN MoMo, Airtel Money & Cards via Yo Uganda and Pesapal OpenFloat. Powered by AROSOFT.

== Description ==

**AROPay** is a fully managed payment gateway plugin for WooCommerce stores operating in Uganda. Powered by AROSOFT and available at [arosoftlabs.com](https://arosoftlabs.com/market/plugins/aropay).

= Payment Methods =

* **MTN Mobile Money** — Real-time STK push via Yo Uganda
* **Airtel Money** — Real-time STK push via Yo Uganda
* **Visa / Mastercard** — Cards via Pesapal OpenFloat
* **MTN & Airtel via Pesapal** — Hosted payment page

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
* ✅ Admin dashboard with transaction history, merchant management, and settlements
* ✅ Sandbox/test mode for both providers
* ✅ AES-256 encrypted credentials storage
* ✅ HPOS (High-Performance Order Storage) compatible
* ✅ WP-CLI test commands included

= Requirements =

* WordPress 5.0+
* WooCommerce 4.0+
* PHP 7.4+
* An active AROPay Merchant Account from [AROSOFT](https://arosoftlabs.com/market/plugins/aropay)
* Currency must be set to **UGX (Uganda Shillings)**

== Installation ==

1. Download the plugin zip from [arosoftlabs.com/market/plugins/aropay](https://arosoftlabs.com/market/plugins/aropay)
2. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin**
3. Upload and activate the plugin
4. Go to **WooCommerce → Settings → Payments**
5. Enable "AROPay — MTN & Airtel MoMo" and/or "AROPay — Cards & MoMo (Pesapal)"
6. Click "Set Up" and enter your AROPay Merchant API Key and Secret
7. Save settings — payments are now live!

For detailed setup instructions, see the [SETUP.md](https://arosoftlabs.com/market/plugins/aropay) guide.

== Frequently Asked Questions ==

= Do I need my own Yo Uganda or Pesapal account? =

No. AROPay is fully managed — all payments flow through AROSOFT's licensed accounts. You only need an AROPay Merchant API Key from AROSOFT.

= What currency does AROPay support? =

AROPay only supports **UGX (Uganda Shillings)**. Ensure your WooCommerce store currency is set to UGX.

= Is there a transaction fee? =

Yes. AROSOFT deducts a small percentage fee per transaction (configured in the admin dashboard). The net amount is settled to your nominated Mobile Money or bank account.

= Can I use it in test/sandbox mode? =

Yes. Both Yo Uganda and Pesapal have sandbox environments. Toggle test mode in **AROPay → Settings**.

= Is it compatible with my theme? =

Yes. AROPay uses only WooCommerce-standard hooks and CSS classes — it works with every WooCommerce-compatible theme.

= Does it support WooCommerce Blocks? =

Yes. AROPay registers payment methods for both the classic shortcode checkout and the modern WooCommerce Blocks checkout.

== Screenshots ==

1. Checkout page — MTN MoMo phone input with network auto-detection
2. Pending payment screen — "Check your phone" with live countdown
3. Admin dashboard — stats overview
4. Admin transactions list
5. Settings — Yo Uganda tab

== Changelog ==

= 1.0.0 — 2024 =
* Initial release
* Yo Uganda gateway (MTN & Airtel MoMo via STK push)
* Pesapal OpenFloat gateway (Cards + MoMo)
* Admin dashboard with transactions, merchants, settlements
* WooCommerce Blocks support
* HPOS compatibility
* AES-256 encrypted credential storage

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade needed.

== Privacy Policy ==

AROPay transmits payment data (phone numbers, amounts, order references) to Yo Uganda and Pesapal to process transactions. Phone numbers are masked in local logs. Please review the privacy policies of [Yo Uganda](https://yo.co.ug) and [Pesapal](https://pesapal.com) for details on their data handling.

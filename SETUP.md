# AROPay Setup Guide

**Plugin:** AROPay — Uganda Payment Gateway  
**Author:** AROSOFT  
**Website:** https://arosoftlabs.com/market/plugins/aropay  
**Version:** 1.0.0

---

## Requirements

| Requirement      | Minimum Version |
|-----------------|----------------|
| WordPress        | 5.0+           |
| WooCommerce      | 4.0+           |
| PHP              | 7.4+           |
| WC Currency      | **UGX**        |

---

## Step 1 — Installation

1. Download **aropay.zip** from [arosoftlabs.com/market/plugins/aropay](https://arosoftlabs.com/market/plugins/aropay)
2. In WordPress admin → **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Activate Plugin**
4. The plugin creates 4 database tables automatically on activation

---

## Step 2 — Global Settings (AROSOFT admin only)

Go to **AROPay → Settings** in the WordPress admin sidebar.

### Tab: Yo Uganda
| Field | Description |
|-------|-------------|
| Mode | `Sandbox` for testing, `Live` for real payments |
| API Username | Your Yo Uganda account username |
| API Password | Your Yo Uganda account password |
| Callback URL | **Copy this URL** into your Yo Uganda account as the notification/callback URL |

### Tab: Pesapal
| Field | Description |
|-------|-------------|
| Mode | `Sandbox` or `Live` |
| Consumer Key | Your Pesapal OpenFloat Consumer Key |
| Consumer Secret | Your Pesapal OpenFloat Consumer Secret |
| IPN URL | Auto-registered with Pesapal on first payment |

### Tab: Fees & Settlement
| Field | Description |
|-------|-------------|
| Default Transaction Fee % | Percentage deducted per completed transaction (e.g. `1.5`) |
| Minimum Fee (UGX) | Minimum fee per transaction (e.g. `500`) |
| Settlement Schedule | `Daily`, `Weekly`, or `Manual` |

### Tab: Branding
- **Plugin Display Name** — shown to customers on payment pages
- **Support Email / Phone** — shown on the pending payment screen

---

## Step 3 — Enable Payment Gateways

1. Go to **WooCommerce → Settings → Payments**
2. You will see two AROPay gateways:
   - **AROPay — MTN & Airtel MoMo** (Yo Uganda)
   - **AROPay — Cards & MoMo (Pesapal)**
3. Click **Manage** on each gateway you want to enable
4. Enter the **Merchant API Key** and **Merchant API Secret** for the merchant
5. Toggle **Enable** and click **Save changes**

> **Note for hosted model:** If you are the platform operator (AROSOFT), the credentials entered here are your master API credentials. Merchants enter their own unique API Key/Secret issued by you.

---

## Step 4 — Set Currency to UGX

1. Go to **WooCommerce → Settings → General**
2. Set **Currency** to `Uganda shilling (UGX)`
3. Save changes

---

## Step 5 — Test the Integration

### Test Mode
In **AROPay → Settings → Yo Uganda**, set Mode to `Sandbox`.  
Use Yo Uganda's sandbox endpoint: `https://paymentsapi1.yo.co.ug/ybs/task.php`

In **AROPay → Settings → Pesapal**, set Mode to `Sandbox`.  
Pesapal sandbox: `https://cybqa.pesapal.com/pesapalv3/`

### WP-CLI Tests
```bash
# Test Yo Uganda connection
wp aropay test-connection --provider=yo

# Test Pesapal connection
wp aropay test-connection --provider=pesapal
```

### Manual Test Flow (MTN MoMo)
1. Add a product to cart and go to checkout
2. Select "Mobile Money (MTN / Airtel)"
3. Enter a valid Ugandan phone number (e.g. `0771234567`)
4. Place order — you will see the "Check your phone" pending screen
5. In sandbox mode, the transaction will auto-resolve

---

## Merchant Management

Go to **AROPay → Merchants** to:
- View all merchants and their status (Pending / Active / Suspended)
- **Approve** a pending merchant to activate their access
- **Suspend** a merchant to block their payments
- View per-merchant transaction stats

### Creating a Merchant
Merchants are created programmatically via:
```php
$id = AROPay_Merchant::create([
    'business_name' => 'Acme Store',
    'phone'         => '0701234567',
    'email'         => 'acme@example.com',
    'settlement_account' => '0701234567',
    'settlement_type'    => 'mtn',
]);
```
Or via your own merchant onboarding portal using the `AROPay_Merchant::create()` method.

---

## Settlement Management

Go to **AROPay → Settlements** to:
- Click **Process All Settlements Now** to compute pending settlements
- Enter the payout reference and click **Mark Settled** after manually transferring funds
- View settlement history

Settlements run automatically at **2:00 AM EAT** daily (configurable).

---

## REST API Reference

Base URL: `/wp-json/aropay/v1/`

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/validate-merchant` | POST | None | Validate merchant API key+secret |
| `/transaction-status/{ref}` | GET | None | Get transaction status (polled by checkout JS) |
| `/ipn/yo` | POST | None | Yo Uganda payment callback |
| `/ipn/pesapal` | GET | None | Pesapal IPN |
| `/merchant/stats` | GET | API Key header | Merchant transaction stats |
| `/refund` | POST | WP Admin | Mark transaction as refunded |

### Auth Header (merchant endpoints)
```
X-AROPay-Key: ak_your_api_key_here
```

---

## Yo Uganda Callback URL

Copy from **AROPay → Settings → Yo Uganda → Callback URL** and register it in your Yo Uganda merchant portal as the **Notification URL** for your payment account.

Format: `https://yoursite.com/wp-json/aropay/v1/ipn/yo`

---

## Pesapal IPN URL

The IPN URL is automatically registered with Pesapal on the first payment request. You can find it at:  
`https://yoursite.com/wp-json/aropay/v1/ipn/pesapal`

---

## Troubleshooting

### "Invalid credentials" on gateway setup
- Double-check the API Key and Secret from your AROPay merchant account
- Ensure the merchant status is **Active** in the admin dashboard

### Payments not completing
- Check **AROPay → API Logs** for error details
- Verify Yo Uganda callback URL is correctly registered
- Check WooCommerce logs at `WooCommerce → Status → Logs` (source: `aropay`)

### Phone number not accepted
- AROPay accepts Ugandan numbers only: 07X or 256X format
- MTN: 077, 078, 076, 039, 031
- Airtel: 070, 075, 074, 020

### Pesapal redirect not working
- Ensure the site uses HTTPS (required by Pesapal)
- Verify Consumer Key/Secret in settings
- Try switching to sandbox mode to isolate the issue

---

## Support

- **Website:** https://arosoftlabs.com
- **Plugin page:** https://arosoftlabs.com/market/plugins/aropay
- **Author:** AROSOFT

---

*AROPay v1.0.0 · © AROSOFT · Licensed under GPL-2.0+*

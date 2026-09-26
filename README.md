# RAR Woo Payment Gateway

Advance / full payment gateway for WooCommerce stores in Bangladesh — **Bangla QR, bKash, Nagad, Rocket, Upay, Bank Transfer (NPSB) and a custom channel** — with a verification dashboard, audit trail, customer self-correction, private payment-screenshot upload, Checkout Blocks support, REST API, signed webhooks and hourly automation.

Collect the **delivery fee, a fixed advance, a percentage, shipping + percentage, or the full order total** before fulfilment, keep a fail-safe COD fallback, and verify every transfer against the official merchant/bank account.

## Download

⬇️ **[Download Latest Installable ZIP](https://raw.githubusercontent.com/ruhulaminrevens/RAR-Woo-Payment-Gateway/main/releases/RAR-Woo-Payment-Gateway-v2.1.0.zip)**

Current stable version: **v2.1.0**

### Install / Update

`WordPress → Plugins → Add New → Upload Plugin → choose ZIP → Install Now → Replace current with uploaded`

Updating from v1.x or v2.0 keeps the same plugin folder (`rar-woo-advance-payment`), gateway ID (`rar_advance_payment`), settings and all order metadata. **Take a full backup first** (files + database) and test a low-value order while **Safe Test Mode** is ON.

After activation:

- `WooCommerce → Advance Payments` — verification dashboard and queue
- `WooCommerce → Settings → Payments → RAR Advance Payment` — gateway settings
- `Plugins → RAR Woo Advance Payment Gateway → Settings / Dashboard`

## What's new in v2.1.0 — complete UI redesign

### Customer checkout (classic + Checkout Blocks)
- Mobile-first **3-step flow**: ① choose method (large logo tiles) → ② send the money (one card with the number, the exact amount and **Copy** buttons, or the QR / bank details) → ③ enter your number + TrxID.
- Big "Pay now" amount header with the on-delivery balance; far less text; 15–16 px type and 50 px inputs (no iOS zoom).
- Field labels adapt to the method ("Your bKash number"), inline validation, Bangla-digit support, TrxID auto upper-case.
- Bilingual mode now shows Bangla only under key headings, so the box stays short. English-only and বাংলা-only modes available.
- **Accent colour** setting to match your brand; container-query layout adapts to narrow and wide checkout columns.

### Customer order page
- Status card with icon, 3-step tracker (Sent → Checking → Confirmed), advance / on-delivery / method / TrxID tiles.
- Rejected payments show the reason and a simple correction form; optional screenshot upload.
- Guest customers can open the correction link from e-mail without re-typing their e-mail (signed, expiring link).

### Admin dashboard (WooCommerce → Advance Payments)
- Live header, KPI cards (awaiting ৳, overdue, verified ৳, typical check time, verification rate).
- Queue with status tabs + counts, search, channel and period presets; rows show customer, **call / WhatsApp** shortcuts, one-tap copy of TrxID and payer number, amount, collect-on-delivery and age (overdue highlighted).
- **Verify / Reject / Reopen in a modal** (bottom sheet on phones) — no page reload; the queue refreshes itself.
- Auto-checks for new payments every 45 s, toast notification, pending count in the tab title and menu badge.
- Side cards: 14-day trend chart, channel mix, team (who verified, typical time), recent activity feed.
- Fully responsive — rows become cards on phones.

### Settings
- Hero with gateway status and a **setup checklist**; tabs (General, Payment rules, Verification, Notifications, Channels); toggle switches.
- Channel **cards** with on/off switch, logo preview and "shown at checkout" status.
- **Live amount preview** calculator for the selected rule; sticky Save bar; phone-friendly layout.

### Order screen
- Verification panel restyled with copy chips, Call / WhatsApp buttons and accent-coloured actions.

### Fixed
- Customer page showed the courier "collect" amount (full total) as "On delivery" while a payment was pending; it now shows the planned balance.
- Transaction IDs are stored in canonical form (upper-case, no spaces, ASCII digits) at checkout and on correction.

## What's new in v2.0.0

### Fixed (bugs present in v1.2.0)
- Verification e-mails never used the WooCommerce e-mail template (`WC_Emails::wrap_message()` was called statically, so it always fell back to plain HTML). All plugin e-mails now go through the WooCommerce mailer with inline styles and your SMTP plugin.
- Customers lost the typed payer number and Transaction ID every time checkout refreshed (address or shipping change). Values are now preserved server-side and client-side.
- SVG channel icons lost their `viewBox` (the attribute was stripped by `wp_kses`), so fallback icons rendered at the wrong size.
- Gateway was unavailable on the **order-pay** page (it only looked at the cart). Admin-created or phone orders can now be paid via the payment link.
- Duplicate-reference check blocked references from **cancelled or failed** orders and, on HPOS, ignored order state. It now uses the authoritative order store and ignores cancelled, failed and trashed orders.
- An unverified advance was treated as money received for COD. "Collect on delivery" now counts only the verified amount.

### Added
- **Verification dashboard** (WooCommerce → Advance Payments): KPI cards (awaiting, overdue, verified, submitted, rejected, verification rate), pending queue with one-click Verify/Reject, filters (period, status, channel, search by order #, TrxID or phone), channel reconciliation table and **CSV export** (UTF-8 BOM for Excel, formula-injection safe). The menu badge shows the pending count.
- **Verify with the actual received amount** — if the customer sent a different amount, record what arrived; the balance to collect updates automatically.
- **Reject with a reason** (not found, amount mismatch, wrong reference, duplicate, other) plus a message to the customer, and **Undo** for verify and reject.
- **Audit trail** per order (who, when, what), shown in the order panel and the REST API.
- **Customer self-correction**: after a rejection, customers fix their Transaction ID from the order page (guest-safe via the order key). Admin is alerted.
- **Payment screenshot upload** (optional) from the order page — stored privately (random names, deny-all `.htaccess`, staff-only nonce-protected viewer).
- **Checkout Blocks** support (declared compatible) with server-calculated amounts via the Store API.
- **Upay**, a **bank account copy button**, a bank logo, and a **custom channel** (Cellfin, SureCash, etc.).
- New amount rule **shipping + % of products**, a **free-shipping fallback** to a fixed advance, **rounding** (whole taka or next 10), **min/max order total**, and **"require only from order total X"** for Required mode.
- **Bangladesh mobile validation** (accepts Bangla digits `০১৭…`, `+880`, 12-digit Rocket accounts) and stricter Transaction ID validation with inline hints.
- **Bilingual customer text** — English, বাংলা, or both.
- **Duplicate policy**: block (default) or allow and flag.
- On verification: saves the reference as the WooCommerce **transaction ID**, and sets the **paid date** when the full total is covered.
- **Automation (hourly, Action Scheduler)**: an overdue-verification digest e-mail to admin, and optional auto-cancel of rejected payments not corrected within N hours (WooCommerce restores stock).
- **Signed webhooks** for every event (SMS gateway, Zapier, ERP, Google Sheets) with an HMAC-SHA256 signature and a test button.
- **REST API** `rar-wap/v1` for the staff app and other RAR plugins.
- A **bulk "Verify advance payment"** action and an **Advance status filter** on the WooCommerce Orders list (HPOS and legacy).
- **Privacy**: personal-data exporter/eraser (erasure respects WooCommerce's order-data retention setting) and privacy-policy text.
- Multiple admin notification recipients (comma-separated).

## Payment rules

| Rule | Pay now |
|---|---|
| Shipping fee | shipping + shipping tax (optional fixed fallback when shipping is free) |
| Fixed | fixed amount (capped at the order total) |
| Percentage | % of order total |
| Shipping + % | shipping + % of (total − shipping) |
| Full | order total |

Optional rounding: whole amount, or next 10. **Required** mode hides standard COD only when this gateway is enabled, visible to the customer, configured, and the pay-now amount is above zero (fail-safe).

## Verification workflow

1. The customer selects a channel, pays the exact amount, and submits the paying number and Transaction ID.
2. The order goes **On hold** (default). Admin gets an e-mail with an "Open order" button.
3. Staff check the transfer in the official merchant or bank account.
4. **Verify** (optionally with the actual received amount) → the order moves to Processing (configurable), the customer is e-mailed, and the transaction ID is saved.
5. Or **Mark unverified** with a reason → the customer is e-mailed a correction link; they can fix the reference and/or upload a screenshot, which puts it back in the queue.
6. Hourly checks remind admin about payments waiting longer than the configured hours.

## Integration for other plugins

**Order meta (stable, v1-compatible)**

| Key | Meaning |
|---|---|
| `_rar_wap_status` | `submitted`, `verified`, `unverified` |
| `_rar_wap_required_amount` | advance amount (after verification = verified amount) |
| `_rar_wap_requested_amount` | amount the customer was asked to pay (v2) |
| `_rar_wap_received_amount` | amount staff confirmed (v2) |
| `_rar_wap_balance_due` | balance after the advance |
| `_rar_wap_channel`, `_rar_wap_channel_label`, `_rar_wap_payer`, `_rar_wap_reference` | submission details |

**PHP helpers**

```php
rar_wap_get_collectable_amount( $order ); // amount the rider should collect
rar_wap_get_payment( $order );            // full snapshot array or null
```

**Actions / filters**

```php
do_action( 'rar_wap_payment_event', $event, $order, $context ); // submitted|verified|rejected|reset|resubmitted|proof_uploaded|auto_cancelled
do_action( 'rar_wap_payment_verified', $order, $context );      // and rar_wap_payment_{event}
apply_filters( 'rar_wap_amount_due', $amount, $total, $shipping, $rule, $gateway );
apply_filters( 'rar_wap_collectable_amount', $amount, $order );
apply_filters( 'rar_wap_channels', $channels, $gateway );
```

**REST API** (`/wp-json/rar-wap/v1`, requires order-management permission — cookie + nonce or an Application Password)

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/summary?from=YYYY-MM-DD&to=YYYY-MM-DD` | totals by status and channel |
| GET | `/payments?status=submitted&channel=&search=&page=&per_page=` | snapshots; `X-WP-Total` header |
| GET | `/payments/{order_id}` | snapshot + audit trail |
| POST | `/payments/{order_id}/verify` | `amount`, `note`, `notify` |
| POST | `/payments/{order_id}/reject` | `reason`, `note`, `notify` |
| POST | `/payments/{order_id}/reset` | `note` |

**Webhook** — JSON POST `{event, delivery_id, occurred_at, site, payment}` with headers `X-RAR-WAP-Event`, `X-RAR-WAP-Delivery` and `X-RAR-WAP-Signature: sha256=<HMAC of body with your secret>`. Deliveries run through Action Scheduler; failures appear under `Tools → Scheduled Actions` (group `rar-wap`).

## Security model

This is a **manual transfer verification gateway**. It does not call bKash/Nagad/Rocket/bank APIs and never treats a submitted Transaction ID as proof of payment. It never asks for a PIN, password, OTP, CVV or card security code (and blocks those words in the fields). Payment screenshots are never publicly accessible. True instant verification requires official merchant API credentials/webhooks from the provider or a licensed aggregator.

## Compatibility

- WordPress 6.5+, PHP 8.0+, WooCommerce 8.5+ (target 11.1.x)
- HPOS and legacy order storage
- Classic checkout **and** Checkout Blocks
- Works on shared hosting (Hostinger/LiteSpeed) — no Node.js or build step needed
- Coexists with RAR Woo Order Workflow & Notify, RAR Woo Smart Courier, RAR Woo Cart & Checkout and SMTP plugins

## Repository structure

```text
assets/
  css/admin.css, checkout.css
  js/admin.js (settings), admin-orders.js (order panel + dashboard), checkout.js (classic), blocks.js (Checkout Blocks)
includes/
  class-rar-wap-plugin.php         bootstrap, assets, COD enforcement, helpers
  class-rar-wap-gateway.php        gateway settings, checkout UI, validation, processing
  class-rar-wap-order.php          state machine, meta, audit trail, events
  class-rar-wap-query.php          HPOS/legacy-aware SQL (queue, summaries, duplicates)
  class-rar-wap-admin.php          order panel (AJAX), list column/filter, bulk verify
  class-rar-wap-dashboard.php      KPI dashboard, queue, reconciliation, CSV, tools
  class-rar-wap-display.php        customer order card, correction form, totals, e-mail summary
  class-rar-wap-proofs.php         private screenshot storage
  class-rar-wap-emails.php         WooCommerce-templated e-mails
  class-rar-wap-automation.php     hourly checks, webhooks
  class-rar-wap-rest.php           REST API
  class-rar-wap-privacy.php        exporter / eraser / policy text
  class-rar-wap-blocks*.php        Checkout Blocks + Store API data
  class-rar-wap-i18n.php           English / বাংলা customer strings
rar-woo-advance-payment.php
uninstall.php
releases/
```

## Uninstall

Deleting the plugin removes its settings and scheduled jobs only. Order payment metadata, the audit trail and stored payment screenshots (`wp-content/uploads/rar-wap-proofs/`) are kept for accounting.

## License

GPLv2 or later.

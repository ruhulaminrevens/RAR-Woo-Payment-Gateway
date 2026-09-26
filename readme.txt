=== RAR Woo Advance Payment Gateway ===
Contributors: ruhulaminrevens
Tags: woocommerce, bkash, nagad, rocket, bangla qr, npsb, upay, advance payment, cod, bangladesh
Requires at least: 6.5
Requires PHP: 8.0
Tested up to: 6.8
Stable tag: 2.1.0
WC requires at least: 8.5
WC tested up to: 11.1
License: GPLv2 or later

Advance/full payment gateway for WooCommerce (Bangladesh): Bangla QR, bKash, Nagad, Rocket, Upay, NPSB bank transfer and custom channels, with a verification dashboard, audit trail, REST API, webhooks and Checkout Blocks support.

== Features ==
* Verification dashboard (WooCommerce → Advance Payments): KPIs, pending queue, one-click verify/reject, channel reconciliation, CSV export, menu badge.
* Verify with the actual received amount; reject with reason; undo; full audit trail.
* Customer self-correction of a rejected Transaction ID and private payment-screenshot upload from the order page.
* Classic checkout and Checkout Blocks.
* Channels: Bangla QR, bKash, Nagad, Rocket, Upay, Bank Transfer (NPSB), custom channel, with logos and one-tap copy.
* Pay-now rules: shipping fee (with free-shipping fallback), fixed, percentage, shipping + percentage, full; rounding; min/max order total.
* Optional or required advance (with "require only from order total"); fail-safe COD hiding.
* Bangladesh mobile validation (Bangla digits, +880, Rocket 12-digit) and Transaction ID validation.
* Duplicate Transaction ID protection (block or flag), ignoring cancelled/failed orders.
* English / বাংলা / bilingual customer text.
* WooCommerce-templated e-mails: admin alert, customer receipt (optional), verified, rejected with correction link, overdue digest.
* Hourly automation: overdue reminders, optional auto-cancel of uncorrected rejected payments.
* Signed webhooks and REST API (rar-wap/v1) for staff apps, ERP and other plugins.
* Order-pay page support, bulk verify, orders-list filter, transaction ID + paid-date on verification.
* HPOS compatible; privacy exporter/eraser; Safe Test Mode for rollout.

== Important ==
This is a manual transfer verification gateway. It does not call bKash/Nagad/Rocket/bank APIs and cannot cryptographically verify a transfer before order creation. True instant verification requires official merchant API credentials/webhooks from the provider or a licensed payment aggregator.

Never collect PIN, password, OTP, CVV or card security codes.

== Changelog ==
= 2.1.0 =
Complete UI redesign: mobile-first 3-step checkout (method tiles, one "send the money" card with copy buttons, two fields), redesigned customer order page with progress tracker, live admin dashboard (trend chart, channel mix, team speed, activity feed, modal verify/reject, auto-refresh), tabbed settings with channel cards, setup checklist and live amount preview. Adds accent colour, WhatsApp/call shortcuts, guest-safe correction links. See CHANGELOG.md.

= 2.0.0 =
Major upgrade: verification dashboard, Checkout Blocks, REST API, webhooks, automation, customer correction and screenshot upload, Upay/custom channels, new amount rules, bilingual text, and multiple bug fixes. See CHANGELOG.md.

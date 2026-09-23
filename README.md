# RAR Woo Payment Gateway

Production-focused manual advance/full payment gateway for WooCommerce with **Bangla QR, bKash, Nagad, Rocket and Bank Transfer / NPSB** support.

Designed for stores that need to collect **delivery fee, fixed advance, percentage advance, or full payment** before fulfilment while keeping a safe COD fallback and a clear manual-verification workflow.

## Download

⬇️ **[Download Latest Installable ZIP](https://raw.githubusercontent.com/ruhulaminrevens/RAR-Woo-Payment-Gateway/main/releases/RAR-Woo-Payment-Gateway-v1.2.0.zip)**

Current stable version: **v1.2.0**

### Install / Update

`WordPress → Plugins → Add New → Upload Plugin → choose ZIP → Install Now`

If an earlier version is already installed, WordPress can replace it with v1.2.0. Existing gateway settings and order metadata are preserved. Existing gateway settings and order metadata are preserved.

After activation, use either:

- `Plugins → RAR Woo Advance Payment Gateway → Settings`
- `WooCommerce → Settings → Payments → RAR Advance Payment`

## v1.2.0 UI update

- Larger, scan-friendly Bangla QR checkout preview with full-size view.
- Media Library logo fields for bKash, Nagad, Rocket and Bangla QR.
- Removed single-letter channel badges; neutral vector fallbacks are used until logos are configured.
- Improved mobile channel spacing and admin logo/QR previews.
- For live Bangla QR payments, use the merchant/bank-issued payable QR image; a brand logo alone is not a payment QR.

## v1.1.1 hotfix

- Fixed a false-positive duplicate Transaction ID / Reference warning on sites using legacy WooCommerce order storage.
- Duplicate detection now requires an exact RAR payment-reference + channel match.
- Supports both legacy CPT order storage and HPOS.
- Unrelated WooCommerce orders can no longer trigger the duplicate-payment warning.

## What changed in v1.1.0

- Added direct **Settings** action link on the Plugins page.
- Redesigned gateway settings into a clearer production control center.
- Added configuration-health indicator and safe-rollout guidance.
- Added dynamic settings: only fields relevant to the selected payment rule/channel remain visible.
- Added Media Library picker + preview for Bangla QR.
- Improved checkout payment cards, trust messaging, payment steps and mobile layout.
- Added one-click **Copy** for bKash/Nagad/Rocket destination numbers.
- Added stronger sensitive-data warnings.
- Added duplicate Transaction ID / Reference protection per payment channel.
- Added normalized payment-reference metadata for safer duplicate matching.
- Added idempotent verification/unverified actions to prevent duplicate customer emails.
- Added professional order verification panel and clearer order-list status badges.
- Added optional WooCommerce diagnostic logging.
- Added **async custom notification emails** through WooCommerce Action Scheduler to reduce Place Order waiting when SMTP is slow.
- Removed redundant manual stock-reduction call; WooCommerce order-status stock handling remains authoritative.
- Improved admin/customer notification wording and WooCommerce-styled verification emails.
- HPOS compatibility retained.

## Payment channels

- Bangla QR
- bKash
- Nagad
- Rocket
- Bank Transfer / NPSB

Only enabled channels with usable destination details are shown at checkout.

## Payment rules

The administrator can choose:

- **Optional** — keep normal COD/payment choices available
- **Required** — require advance submission before order placement

Pay-now amount can be:

- Full shipping / delivery fee
- Fixed advance amount
- Percentage of order total
- Full order total

When Required mode is used, normal COD can be hidden. This is fail-safe: COD is not removed unless this gateway is enabled, configured, visible to the customer and has a positive pay-now amount.

## Safe Test Mode

Safe Test Mode is **ON by default**. While enabled, only Administrators and Shop Managers can see the gateway.

Recommended rollout:

1. Install/update the plugin.
2. Open the gateway **Settings**.
3. Keep **Safe Test Mode ON**.
4. Configure at least one channel.
5. Place a low-value test order.
6. Check checkout UI, order metadata, admin alert and customer verification email.
7. Verify the payment manually from the order screen.
8. If required, enable **Required** + **Hide standard COD**.
9. Turn **Safe Test Mode OFF** only after successful testing.

## Checkout experience

Customers see:

- Pay now amount
- Due on delivery amount
- 3-step payment guide
- Enabled payment-channel cards
- Clear payment destination/instructions
- One-click copy for MFS numbers
- Payer/account reference field
- Transaction ID / Reference field
- Security notice: **never share PIN, password, OTP, CVV or security code**
- Notice that submitting a Transaction ID does not mean the transfer is automatically verified

## Verification workflow

1. Customer chooses the gateway.
2. Customer transfers the required amount externally.
3. Customer submits payer/account reference + Transaction ID.
4. Order is placed in **On hold** by default.
5. Admin independently verifies the transfer in the official merchant/bank account.
6. Admin clicks **Verify Payment**.
7. Plugin stores verifier/time audit metadata, updates order status and notifies the customer.
8. If the reference cannot be verified, admin can **Mark Unverified** and notify the customer.

Repeated Verify/Unverified actions are guarded so duplicate emails are not sent accidentally.

## Checkout performance

By default, the plugin queues its **custom payment-submission emails** through WooCommerce Action Scheduler.

This means slow external SMTP delivery is less likely to keep the customer waiting on the Place Order spinner. WooCommerce's normal order emails continue to be managed by WooCommerce / your SMTP plugin.

You can disable async custom emails from Settings if immediate synchronous sending is preferred.

## Duplicate reference protection

v1.1.0 prevents the same Transaction ID / Reference from being submitted again for the same payment channel.

New orders store a normalized reference value for reliable matching. The plugin also performs a backward-compatible check against v1.0.0 order metadata.

## Admin tools

The order screen includes a compact **Advance Payment Verification** panel showing:

- Verification state
- Channel
- Pay-now amount
- Due-on-delivery amount
- Payer reference
- Transaction ID
- Submission time
- Verification user/time
- Verify Payment / Mark Unverified actions

The WooCommerce Orders list also includes a compact Advance status badge.

## Logging

Optional diagnostic logging can be enabled from gateway Settings.

Logs use WooCommerce's logger with source:

`rar-woo-advance-payment`

Keep logging OFF during normal operation unless troubleshooting.

## Important security model

This is a **manual transfer verification gateway**.

It does **not** call bKash/Nagad/Rocket/bank APIs and does not claim a payment is successful merely because a customer entered a Transaction ID.

True instant/cryptographic verification requires official merchant API credentials/webhooks from the relevant provider or a licensed payment aggregator.

The plugin never asks customers for PIN, password, OTP, CVV or card security code.

## Compatibility

- WordPress 6.5+
- PHP 8.0+
- WooCommerce 8.5+
- Tested target: WooCommerce 11.1.x
- HPOS compatible
- Classic checkout supported
- Checkout Blocks are **not declared compatible** in v1.1.0
- Designed to coexist with WooCommerce shipping, order-workflow and SMTP plugins

## Repository structure

```text
assets/
  css/
    admin.css
    checkout.css
  js/
    admin.js
    checkout.js
includes/
  class-rar-wap-admin.php
  class-rar-wap-display.php
  class-rar-wap-gateway.php
rar-woo-advance-payment.php
readme.txt
README.md
CHANGELOG.md
uninstall.php
releases/
  RAR-Woo-Payment-Gateway-v1.2.0.zip
```

## Production notes

- Keep **On hold** as the submission status for manual verification.
- Use an authenticated SMTP provider for reliable email delivery.
- Keep WooCommerce New Order email enabled so store staff still receive the standard order alert.
- Test Required + Hide COD carefully before going live.
- Do not treat customer-entered payment references as proof of payment.

=== RAR Woo Advance Payment Gateway ===
Contributors: ruhulaminrevens
Tags: woocommerce, bkash, nagad, rocket, bangla qr, npsb, advance payment, cod
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later

Professional manual advance/full payment gateway for WooCommerce with Bangla QR, bKash, Nagad, Rocket and NPSB bank transfer.

== Features ==
* Direct Settings link on the Plugins page.
* Safe Test Mode for admin/shop-manager rollout.
* Optional or required advance payment.
* Pay-now amount: shipping fee, fixed amount, percentage, or full order total.
* Fail-safe COD hiding only when the gateway is usable.
* Bangla QR, bKash, Nagad, Rocket and Bank Transfer / NPSB.
* Media Library picker for Bangla QR.
* Professional responsive checkout cards and payment instructions.
* One-click copy for MFS destination numbers.
* Transaction/reference and payer/account reference capture.
* Duplicate Transaction ID/reference protection per channel.
* Admin manual verification controls with audit metadata.
* Idempotent verification/unverified actions.
* Customer + admin notification workflow.
* Async custom submission emails via WooCommerce Action Scheduler.
* Optional WooCommerce diagnostic logging.
* Pay-now and due-on-delivery order metadata.
* HPOS compatible.
* Fails safe: incomplete gateway configuration never removes COD.

== Important ==
This is a manual transfer verification gateway. It does not call bKash/Nagad/Rocket/bank APIs and therefore cannot cryptographically verify a transfer before order creation. True instant verification requires official merchant API credentials/webhooks from the payment provider or a licensed payment aggregator.

Never collect PIN, password, OTP, CVV or card security codes.

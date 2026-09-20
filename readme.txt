=== RAR Woo Advance Payment Gateway ===
Contributors: ruhulaminrevens
Tags: woocommerce, bkash, nagad, rocket, bangla qr, npsb, advance payment, cod
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later

Safe manual advance/full payment gateway for WooCommerce with Bangla QR, bKash, Nagad, Rocket and NPSB bank transfer.

== Features ==
* Safe Test Mode (admins/shop managers only) enabled by default.
* Optional or required advance payment.
* Advance amount: shipping fee, fixed amount, percentage, or full order total.
* Keep COD available or hide COD only when required and gateway is fully configured.
* Bangla QR, bKash, Nagad, Rocket and Bank Transfer (NPSB).
* Transaction/reference and payer/account reference capture.
* Admin manual verification controls on the order screen.
* Customer + admin email notifications.
* Pay-now and due-on-delivery order metadata.
* HPOS compatible.
* Fails safe: incomplete gateway configuration never removes COD.

== Important ==
This is a manual transfer verification gateway. It does not call bKash/Nagad/Rocket/bank APIs and therefore cannot cryptographically verify a transfer before order creation. True instant verification requires official merchant API credentials/webhooks from the payment provider or a licensed payment aggregator.

Never collect PIN, password or OTP.

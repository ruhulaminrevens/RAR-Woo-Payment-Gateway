# Changelog

## v1.1.1 — 2026-09-23

Hotfix release.

### Fixed
- Fixed false-positive duplicate Transaction ID / Reference warnings on legacy WooCommerce order storage.
- Duplicate detection now requires an exact plugin reference + payment-channel match.
- Added storage-safe duplicate detection for both legacy CPT orders and HPOS.
- Unrelated WooCommerce orders no longer cause checkout to reject a valid payment reference.


## v1.1.0 — 2026-09-23

Production UX, safety and performance release.

### Added
- Direct **Settings** action link on the WordPress Plugins page.
- Gateway configuration-health panel and safe-rollout guidance.
- Dynamic admin fields based on payment rule and enabled payment channels.
- Bangla QR Media Library picker and image preview.
- Professional checkout trust strip, payment steps and responsive channel cards.
- One-click copy for bKash, Nagad and Rocket destination numbers.
- Dynamic payer/reference placeholders by selected channel.
- Duplicate Transaction ID / Reference protection per channel.
- Normalized transaction-reference metadata for new orders.
- Backward-compatible duplicate check for v1.0.0 order metadata.
- Idempotent Verify / Mark Unverified actions.
- Verification audit details: verified/rejected by and timestamp.
- Enhanced Advance status badge on order list.
- Optional WooCommerce diagnostic logging.
- Async custom payment-submission emails through WooCommerce Action Scheduler.
- Admin settings CSS/JS and improved mobile layout.

### Improved
- Payment and verification wording is more explicit and trustworthy.
- Security notices now also cover CVV/security codes.
- Customer verification emails can use WooCommerce email styling when available.
- Admin payment alert clearly states that the reference must be verified externally.
- Safer defensive validation in payment processing.
- Plugin URI and documentation links point to the project repository.

### Optimized
- Removed redundant explicit stock-reduction call; WooCommerce order-status stock handling remains authoritative.
- Custom payment-submission email delivery no longer needs to block checkout when async mode is enabled.
- Admin assets load only on relevant WooCommerce payment/order screens.

### Compatibility
- HPOS compatibility retained.
- Classic Checkout supported.
- Checkout Blocks remain undeclared/incompatible in v1.1.0.

## v1.0.0 — 2026-09-20

Initial stable release.

### Added
- Bangla QR, bKash, Nagad, Rocket and Bank Transfer / NPSB payment channels.
- Full shipping fee, fixed amount, percentage and full-order advance rules.
- Optional or required advance-payment mode.
- Safe Test Mode enabled by default.
- COD fallback protection when configuration is incomplete.
- Customer payer/reference and Transaction ID capture.
- On-hold payment verification workflow.
- Admin verification controls and order metadata.
- Customer/admin notification hooks.
- HPOS compatibility declaration.
- Classic WooCommerce checkout support.

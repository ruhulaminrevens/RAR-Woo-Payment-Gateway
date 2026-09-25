# Changelog

## v2.0.0 — 2026-09-26

Major feature and reliability release. Backward compatible with v1.x settings, gateway ID and order metadata.

### Fixed
- E-mails now use the WooCommerce mailer/template (v1 called `WC_Emails::wrap_message()` statically, which always fell back to plain HTML).
- Payer number and Transaction ID are no longer wiped when checkout refreshes.
- SVG fallback icons kept their `viewBox` (was stripped by `wp_kses`).
- Gateway now works on the order-pay page (amount calculated from the order, not the cart).
- Duplicate-reference detection uses the authoritative order store (HPOS or legacy) and ignores cancelled/failed/trashed orders; excludes the order being paid.
- Collect-on-delivery amount counts only a verified advance.
- `SHOW TABLES` queries removed from the checkout path.

### Added
- Verification dashboard with KPIs, pending queue, AJAX verify/reject, filters, channel reconciliation, CSV export and menu badge.
- Verify with actual received amount; reject with reason + customer message; undo; per-order audit trail.
- Customer self-correction form and private payment-screenshot upload on the order page.
- Checkout Blocks integration and Store API cart data.
- Upay, bank account copy, bank logo, custom channel.
- Amount rule "shipping + % of products", free-shipping fixed fallback, rounding, min/max order total, required-mode threshold.
- Bangladesh mobile and Transaction ID validation (Bangla digits supported).
- English / বাংলা / bilingual customer text.
- Duplicate policy (block or flag).
- Transaction ID and paid date set on verification.
- Hourly automation: overdue digest, optional auto-cancel of uncorrected rejected payments.
- Signed webhooks, REST API `rar-wap/v1`, PHP helpers and action/filter hooks.
- Orders list: advance-status filter, bulk verify, collect amount in column.
- Privacy exporter/eraser and privacy-policy text.
- Multiple admin e-mail recipients; customer verified/rejected e-mail toggles; custom Place Order button text; title logos.

### Changed
- Code split into focused classes (order domain, query, e-mails, dashboard, REST, automation, proofs, privacy, blocks).
- Uninstall also clears scheduled actions (order data and screenshots are preserved).


## v1.2.0 — 2026-09-23

Customer-facing payment UI refinement.

### Added
- Configurable Media Library logo fields for bKash, Nagad, Rocket and Bangla QR.
- Clean vector fallback icons when a brand logo has not been configured.
- Click/tap-to-open full-size Bangla QR image.

### Improved
- Bangla QR checkout preview is substantially larger and scan-friendly.
- Mobile QR sizing, spacing and payment-channel layout refined.
- Admin QR preview enlarged and brand-logo previews added.

### Changed
- Removed single-letter B/N/R/B channel badges from checkout.


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

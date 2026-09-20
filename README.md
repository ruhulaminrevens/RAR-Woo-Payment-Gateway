# RAR Woo Payment Gateway

Production-focused manual advance/full payment gateway for WooCommerce with **Bangla QR, bKash, Nagad, Rocket and Bank Transfer / NPSB** support.

Designed for stores that need to collect **delivery fee, partial advance, percentage advance, or full payment** before order fulfilment while keeping a safe COD fallback.

## Download

⬇️ **[Download Latest Installable ZIP](https://github.com/ruhulaminrevens/RAR-Woo-Payment-Gateway/raw/main/releases/RAR-Woo-Payment-Gateway-v1.0.0.zip)**

Current stable version: **v1.0.0**

### Install

`WordPress → Plugins → Add New → Upload Plugin → choose ZIP → Install Now → Activate`

## Payment channels

- Bangla QR
- bKash
- Nagad
- Rocket
- Bank Transfer / NPSB

## Payment rules

The store administrator can choose how much the customer must pay now:

- Full shipping / delivery fee
- Fixed advance amount
- Percentage of order total
- Full order total

The gateway can be **optional** or **required**. When required, standard Cash on Delivery can be hidden only when this gateway is correctly configured and the required advance is greater than zero.

## Safe Test Mode

Safe Test Mode is **ON by default**. While enabled, only Administrators and Shop Managers can see the gateway at checkout. Normal customers continue using the existing live checkout.

Recommended rollout:

1. Install and activate the plugin.
2. Open `WooCommerce → Settings → Payments → RAR Advance Payment`.
3. Keep **Safe Test Mode ON**.
4. Configure one or more payment channels.
5. Test a low-value order as an administrator.
6. Verify payment reference capture, order notes, admin verification controls and emails.
7. If required, enable **Required** + **Hide standard COD**.
8. Turn **Safe Test Mode OFF** only after successful testing.

## Payment verification workflow

This release uses a **manual verification model**:

1. Customer selects the gateway.
2. Customer sends money externally through the configured channel.
3. Customer submits payer/account reference and Transaction ID / Reference ID.
4. Order is placed in **On hold** by default.
5. Admin verifies the payment from the order screen.
6. The order can move to **Processing** and the customer can be notified.

The plugin does **not** claim that a transaction is paid merely because a Transaction ID was entered. Real-time payment confirmation requires an official merchant API/webhook integration.

## Safety behaviour

- Required prepayment never removes COD when the advance gateway is not usable.
- Configuration is validated before enforcing payment restrictions.
- Safe Test Mode prevents accidental live rollout.
- Existing WooCommerce checkout remains the fallback during incomplete setup.

## Compatibility

- WooCommerce 11.1.x
- PHP 8.0+
- HPOS compatible
- Classic checkout supported
- Checkout Blocks are not declared compatible in v1.0.0
- Designed to coexist with other WooCommerce shipping/workflow plugins

## Repository structure

```text
assets/
  css/
  js/
includes/
rar-woo-advance-payment.php
readme.txt
README.md
CHANGELOG.md
uninstall.php
releases/
  RAR-Woo-Payment-Gateway-v1.0.0.zip
```

## Notes

This is a manual payment-submission and verification gateway. For automatic payment verification, future versions can integrate official bKash/Nagad or supported payment-processor APIs and signed webhooks.

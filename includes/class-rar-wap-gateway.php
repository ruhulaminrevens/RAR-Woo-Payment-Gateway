<?php
/**
 * RAR Advance Payment gateway.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Gateway extends WC_Payment_Gateway {

	/** Channel definitions: key => [ type, default label ]. */
	const CHANNELS = array(
		'bkash'    => array( 'mfs', 'bKash' ),
		'nagad'    => array( 'mfs', 'Nagad' ),
		'rocket'   => array( 'mfs', 'Rocket' ),
		'upay'     => array( 'mfs', 'Upay' ),
		'banglaqr' => array( 'qr', 'Bangla QR' ),
		'bank'     => array( 'bank', 'Bank Transfer (NPSB)' ),
		'custom'   => array( 'custom', 'Other payment method' ),
	);

	/** @var array|null Cached per-request channel config. */
	private $channel_cache = null;

	public function __construct() {
		$this->id                 = RAR_WAP_Order::GATEWAY_ID;
		$this->method_title       = __( 'RAR Advance Payment', 'rar-woo-advance-payment' );
		$this->method_description = __( 'Manual advance/full payment with Bangla QR, bKash, Nagad, Rocket, Upay, bank transfer (NPSB) or a custom channel — with verification queue, audit trail, REST API and automation.', 'rar-woo-advance-payment' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Advance Payment / Online Transfer', 'rar-woo-advance-payment' ) );
		$this->description = $this->get_option( 'description', '' );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		$button = trim( (string) $this->get_option( 'order_button_text', '' ) );
		if ( '' !== $button ) {
			$this->order_button_text = $button;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( 'RAR_WAP_I18n', 'reset' ), 20 );
	}

	/* ------------------------------------------------------------------ *
	 * Settings
	 * ------------------------------------------------------------------ */

	public function init_form_fields() {
		$fields = array(
			'rollout_heading'           => array(
				'title'       => __( 'Gateway & Rollout', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Start in Safe Test Mode, complete the payment-channel setup, place a test order, then make the gateway available to customers.', 'rar-woo-advance-payment' ),
			),
			'enabled'                   => array(
				'title'   => __( 'Enable / Disable', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable RAR Advance Payment Gateway', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'safe_test_mode'            => array(
				'title'       => __( 'Safe Test Mode', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Only Administrators and Shop Managers can see this gateway', 'rar-woo-advance-payment' ),
				'description' => __( 'Recommended while configuring or testing. Regular customers keep using the existing payment methods.', 'rar-woo-advance-payment' ),
				'default'     => 'yes',
			),
			'title'                     => array(
				'title'       => __( 'Checkout title', 'rar-woo-advance-payment' ),
				'type'        => 'text',
				'default'     => __( 'Advance Payment / Online Transfer', 'rar-woo-advance-payment' ),
				'description' => __( 'Shown to customers beside the payment option.', 'rar-woo-advance-payment' ),
			),
			'description'               => array(
				'title'       => __( 'Checkout description', 'rar-woo-advance-payment' ),
				'type'        => 'textarea',
				'default'     => __( 'Pay the required amount now using Bangla QR, bKash, Nagad, Rocket or Bank Transfer (NPSB). We verify your payment before fulfilment. Never share your PIN or OTP.', 'rar-woo-advance-payment' ),
				'description' => __( 'Keep this short and reassuring. Payment instructions are shown below automatically.', 'rar-woo-advance-payment' ),
			),
			'customer_language'         => array(
				'title'       => __( 'Customer text language', 'rar-woo-advance-payment' ),
				'type'        => 'select',
				'default'     => 'both',
				'options'     => array(
					'both' => __( 'English + বাংলা (bilingual)', 'rar-woo-advance-payment' ),
					'en'   => __( 'English only', 'rar-woo-advance-payment' ),
					'bn'   => __( 'বাংলা only', 'rar-woo-advance-payment' ),
				),
				'description' => __( 'Language for checkout labels, validation messages and the customer order page.', 'rar-woo-advance-payment' ),
			),
			'show_logos_in_title'       => array(
				'title'   => __( 'Title logos', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show configured channel logos beside the checkout title', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'order_button_text'         => array(
				'title'       => __( 'Place-order button text', 'rar-woo-advance-payment' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => __( 'Leave blank to use the WooCommerce default', 'rar-woo-advance-payment' ),
			),

			'rules_heading'             => array(
				'title'       => __( 'Payment Rules', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Choose whether advance payment is optional or mandatory, how the amount due now is calculated, and when the gateway is offered.', 'rar-woo-advance-payment' ),
			),
			'enforcement'               => array(
				'title'       => __( 'Payment requirement', 'rar-woo-advance-payment' ),
				'type'        => 'select',
				'default'     => 'optional',
				'options'     => array(
					'optional' => __( 'Optional — keep COD available', 'rar-woo-advance-payment' ),
					'required' => __( 'Required — customer must submit advance before placing order', 'rar-woo-advance-payment' ),
				),
				'description' => __( 'Required mode hides standard COD only when this gateway is enabled, visible and has at least one usable channel.', 'rar-woo-advance-payment' ),
			),
			'hide_cod_when_required'    => array(
				'title'       => __( 'COD control', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Hide standard Cash on Delivery when advance payment is required', 'rar-woo-advance-payment' ),
				'description' => __( 'Fails safe: COD stays available if this gateway is not correctly configured.', 'rar-woo-advance-payment' ),
				'default'     => 'yes',
			),
			'required_min_total'        => array(
				'title'             => __( 'Require only from order total', 'rar-woo-advance-payment' ),
				'type'              => 'price',
				'default'           => '0',
				'description'       => __( 'Required mode applies only when the order total is at least this amount (e.g. high-value orders). 0 = always.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			'amount_rule'               => array(
				'title'   => __( 'Amount to pay now', 'rar-woo-advance-payment' ),
				'type'    => 'select',
				'default' => 'shipping',
				'options' => array(
					'shipping'         => __( 'Full delivery / shipping fee', 'rar-woo-advance-payment' ),
					'fixed'            => __( 'Fixed advance amount', 'rar-woo-advance-payment' ),
					'percent'          => __( 'Percentage of order total', 'rar-woo-advance-payment' ),
					'shipping_percent' => __( 'Shipping fee + percentage of products', 'rar-woo-advance-payment' ),
					'full'             => __( 'Full order total', 'rar-woo-advance-payment' ),
				),
			),
			'fixed_amount'              => array(
				'title'             => __( 'Fixed advance amount', 'rar-woo-advance-payment' ),
				'type'              => 'price',
				'default'           => '200',
				'description'       => __( 'Used by the fixed rule, and as the fallback when shipping is free (if enabled below).', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			'percentage'                => array(
				'title'             => __( 'Advance percentage', 'rar-woo-advance-payment' ),
				'type'              => 'number',
				'default'           => '20',
				'description'       => __( 'Used by the percentage rules.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '1', 'max' => '100', 'step' => '1' ),
			),
			'shipping_zero_behaviour'   => array(
				'title'       => __( 'When shipping is free', 'rar-woo-advance-payment' ),
				'type'        => 'select',
				'default'     => 'hide',
				'options'     => array(
					'hide'  => __( 'Hide this gateway (nothing to pay in advance)', 'rar-woo-advance-payment' ),
					'fixed' => __( 'Ask for the fixed advance amount instead', 'rar-woo-advance-payment' ),
				),
				'description' => __( 'Applies to the shipping-fee rule.', 'rar-woo-advance-payment' ),
			),
			'round_amount'              => array(
				'title'   => __( 'Rounding', 'rar-woo-advance-payment' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none' => __( 'No rounding', 'rar-woo-advance-payment' ),
					'up'   => __( 'Round up to a whole amount (e.g. ৳152.40 → ৳153)', 'rar-woo-advance-payment' ),
					'ten'  => __( 'Round up to the next 10 (e.g. ৳152 → ৳160)', 'rar-woo-advance-payment' ),
				),
			),
			'min_order_total'           => array(
				'title'             => __( 'Minimum order total', 'rar-woo-advance-payment' ),
				'type'              => 'price',
				'default'           => '0',
				'description'       => __( 'Offer the gateway only at or above this total. 0 = no minimum.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			'max_order_total'           => array(
				'title'             => __( 'Maximum order total', 'rar-woo-advance-payment' ),
				'type'              => 'price',
				'default'           => '0',
				'description'       => __( 'Hide the gateway above this total. 0 = no maximum.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),

			'verification_heading'      => array(
				'title'       => __( 'Verification Workflow', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Controls order status, validation and what happens after staff verify or reject a payment.', 'rar-woo-advance-payment' ),
			),
			'after_submit_status'       => array(
				'title'       => __( 'Status after submission', 'rar-woo-advance-payment' ),
				'type'        => 'select',
				'default'     => 'on-hold',
				'options'     => array(
					'on-hold'    => __( 'On hold — recommended for manual verification', 'rar-woo-advance-payment' ),
					'processing' => __( 'Processing', 'rar-woo-advance-payment' ),
				),
				'description' => __( 'On hold is safest because entering a Transaction ID does not prove money was received.', 'rar-woo-advance-payment' ),
			),
			'after_verify_status'       => array(
				'title'   => __( 'Status after verification', 'rar-woo-advance-payment' ),
				'type'    => 'select',
				'default' => 'processing',
				'options' => array(
					'processing' => __( 'Processing', 'rar-woo-advance-payment' ),
					'on-hold'    => __( 'Keep On hold', 'rar-woo-advance-payment' ),
					'keep'       => __( 'Do not change the order status', 'rar-woo-advance-payment' ),
				),
			),
			'validate_mobile'           => array(
				'title'   => __( 'Mobile number check', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Require a valid Bangladeshi mobile number for MFS channels (Bangla digits accepted)', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'duplicate_policy'          => array(
				'title'       => __( 'Duplicate Transaction ID', 'rar-woo-advance-payment' ),
				'type'        => 'select',
				'default'     => 'block',
				'options'     => array(
					'block' => __( 'Block checkout (recommended)', 'rar-woo-advance-payment' ),
					'flag'  => __( 'Allow, but flag the order for review', 'rar-woo-advance-payment' ),
				),
				'description' => __( 'References on cancelled, failed or trashed orders can be reused.', 'rar-woo-advance-payment' ),
			),
			'set_transaction_id'        => array(
				'title'   => __( 'Transaction ID', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Save the verified reference as the WooCommerce order transaction ID', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'mark_paid_when_full'       => array(
				'title'   => __( 'Paid date', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Set the order "paid" date when the verified amount covers the full total', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'allow_resubmit'            => array(
				'title'   => __( 'Customer correction', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Let customers correct a rejected Transaction ID from their order page', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'proof_upload'              => array(
				'title'   => __( 'Payment screenshot', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Let customers upload a payment screenshot from the order page (stored privately)', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'proof_max_mb'              => array(
				'title'             => __( 'Screenshot size limit (MB)', 'rar-woo-advance-payment' ),
				'type'              => 'number',
				'default'           => '4',
				'custom_attributes' => array( 'min' => '1', 'max' => '10', 'step' => '1' ),
			),

			'notifications_heading'     => array(
				'title'       => __( 'Notifications & Automation', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'E-mails use your WooCommerce e-mail template and SMTP. Automation runs hourly through WooCommerce Action Scheduler.', 'rar-woo-advance-payment' ),
			),
			'admin_email'               => array(
				'title'       => __( 'Admin notification e-mail(s)', 'rar-woo-advance-payment' ),
				'type'        => 'text',
				'default'     => get_option( 'admin_email' ),
				'description' => __( 'Comma-separated. Leave blank to use the WordPress admin e-mail.', 'rar-woo-advance-payment' ),
			),
			'admin_submission_email'    => array(
				'title'   => __( 'Admin payment alert', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'E-mail admin when a customer submits, corrects or uploads payment proof', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'customer_submission_email' => array(
				'title'       => __( 'Customer submission e-mail', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Send an extra "payment submission received" e-mail', 'rar-woo-advance-payment' ),
				'description' => __( 'Default OFF — WooCommerce already sends an On-hold / Order received e-mail.', 'rar-woo-advance-payment' ),
				'default'     => 'no',
			),
			'customer_verified_email'   => array(
				'title'   => __( 'Customer verified e-mail', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'E-mail the customer when the payment is verified', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'customer_rejected_email'   => array(
				'title'   => __( 'Customer rejected e-mail', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'E-mail the customer (with a correction link) when a payment is rejected', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'async_emails'              => array(
				'title'       => __( 'Checkout speed', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Queue payment-notification e-mails after checkout', 'rar-woo-advance-payment' ),
				'description' => __( 'Recommended. Slow SMTP will not keep the Place Order spinner running.', 'rar-woo-advance-payment' ),
				'default'     => 'yes',
			),
			'overdue_hours'             => array(
				'title'             => __( 'Overdue reminder (hours)', 'rar-woo-advance-payment' ),
				'type'              => 'number',
				'default'           => '6',
				'description'       => __( 'E-mail admin a digest of payments still unverified after this many hours. 0 = off.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '0', 'max' => '720', 'step' => '1' ),
			),
			'auto_cancel_hours'         => array(
				'title'             => __( 'Auto-cancel rejected (hours)', 'rar-woo-advance-payment' ),
				'type'              => 'number',
				'default'           => '0',
				'description'       => __( 'Cancel on-hold/pending orders whose payment was rejected and not corrected within this many hours (stock is restored by WooCommerce). 0 = off.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '0', 'max' => '720', 'step' => '1' ),
			),
			'webhook_url'               => array(
				'title'       => __( 'Webhook URL', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Receives a signed JSON POST for every payment event (SMS gateway, Zapier, ERP, Google Sheets…).', 'rar-woo-advance-payment' ),
			),
			'webhook_secret'            => array(
				'title'       => __( 'Webhook secret', 'rar-woo-advance-payment' ),
				'type'        => 'password',
				'default'     => '',
				'description' => __( 'Used to sign requests (header X-RAR-WAP-Signature = HMAC-SHA256 of the body).', 'rar-woo-advance-payment' ),
			),
			'debug_logging'             => array(
				'title'       => __( 'Diagnostic logging', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Write payment-workflow diagnostics to WooCommerce logs', 'rar-woo-advance-payment' ),
				'description' => __( 'Leave OFF during normal operation. Logs never include PIN, password or OTP because this plugin never collects them.', 'rar-woo-advance-payment' ),
				'default'     => 'no',
			),

			'channel_heading'           => array(
				'title'       => __( 'Payment Channels', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Enable one or more channels. Customers only see channels that are enabled and have the required destination details.', 'rar-woo-advance-payment' ),
			),
		);

		$instructions = array(
			'bkash'  => __( 'Send Money / Payment', 'rar-woo-advance-payment' ),
			'nagad'  => __( 'Send Money / Payment', 'rar-woo-advance-payment' ),
			'rocket' => __( 'Send Money', 'rar-woo-advance-payment' ),
			'upay'   => __( 'Send Money', 'rar-woo-advance-payment' ),
		);

		foreach ( $instructions as $key => $instruction ) {
			$label = self::CHANNELS[ $key ][1];

			$fields[ $key . '_enabled' ] = array(
				'title'   => $label,
				'type'    => 'checkbox',
				/* translators: %s: channel */
				'label'   => sprintf( __( 'Enable %s', 'rar-woo-advance-payment' ), $label ),
				'default' => 'no',
			);
			$fields[ $key . '_number' ]  = array(
				/* translators: %s: channel */
				'title'       => sprintf( __( '%s number', 'rar-woo-advance-payment' ), $label ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'The number customers should pay. Never enter a PIN or secret.', 'rar-woo-advance-payment' ),
			);
			$fields[ $key . '_type' ]    = array(
				/* translators: %s: channel */
				'title'   => sprintf( __( '%s instruction', 'rar-woo-advance-payment' ), $label ),
				'type'    => 'text',
				'default' => $instruction,
			);
			$fields[ $key . '_logo' ]    = array(
				/* translators: %s: channel */
				'title'       => sprintf( __( '%s logo', 'rar-woo-advance-payment' ), $label ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Choose the official logo from the Media Library. A neutral icon is used when blank.', 'rar-woo-advance-payment' ),
			);
		}

		$fields += array(
			'banglaqr_enabled' => array(
				'title'   => 'Bangla QR',
				'type'    => 'checkbox',
				'label'   => __( 'Enable Bangla QR', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'banglaqr_image'   => array(
				'title'       => __( 'Bangla QR image', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'The merchant/bank-issued payable QR. Use the Media Library button beside this field.', 'rar-woo-advance-payment' ),
			),
			'banglaqr_note'    => array(
				'title'   => __( 'Bangla QR instruction', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => __( 'Scan the QR with a supported banking/MFS app and pay the exact amount.', 'rar-woo-advance-payment' ),
			),
			'banglaqr_logo'    => array(
				'title'       => __( 'Bangla QR channel logo', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional brand logo beside the channel name. Keep the payable QR in the field above.', 'rar-woo-advance-payment' ),
			),
			'bank_enabled'     => array(
				'title'   => __( 'Bank Transfer (NPSB)', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Bank Transfer / NPSB', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'bank_details'     => array(
				'title'       => __( 'Bank / NPSB details', 'rar-woo-advance-payment' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Bank name, account name, account number, branch and routing number. Never put passwords here.', 'rar-woo-advance-payment' ),
			),
			'bank_account'     => array(
				'title'       => __( 'Bank account number (copy button)', 'rar-woo-advance-payment' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Optional. Shown with a one-tap Copy button.', 'rar-woo-advance-payment' ),
			),
			'bank_logo'        => array(
				'title'   => __( 'Bank logo', 'rar-woo-advance-payment' ),
				'type'    => 'url',
				'default' => '',
			),
			'custom_enabled'   => array(
				'title'   => __( 'Custom channel', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable a custom channel (e.g. Cellfin, SureCash, card POS link)', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'custom_label'     => array(
				'title'   => __( 'Custom channel name', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => '',
			),
			'custom_number'    => array(
				'title'   => __( 'Custom channel number / account', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => '',
			),
			'custom_details'   => array(
				'title'   => __( 'Custom channel instructions', 'rar-woo-advance-payment' ),
				'type'    => 'textarea',
				'default' => '',
			),
			'custom_logo'      => array(
				'title'   => __( 'Custom channel logo', 'rar-woo-advance-payment' ),
				'type'    => 'url',
				'default' => '',
			),
		);

		$this->form_fields = $fields;
	}

	public function admin_options() {
		$health   = $this->get_configuration_health();
		$channels = $this->get_enabled_channels();
		$pending  = class_exists( 'RAR_WAP_Query' ) ? RAR_WAP_Query::pending_count() : 0;

		echo '<div class="rar-wap-admin-hero">';
		echo '<div><span class="rar-wap-kicker">' . esc_html__( 'RAR Woo Payment Gateway', 'rar-woo-advance-payment' ) . ' · v' . esc_html( RAR_WAP_VERSION ) . '</span>';
		echo '<h2>' . esc_html__( 'Advance Payment Control Center', 'rar-woo-advance-payment' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure a safe manual payment workflow, test it privately, and verify every submitted transfer before fulfilment.', 'rar-woo-advance-payment' ) . '</p>';
		echo '<p class="rar-wap-hero-links"><a class="button button-primary" href="' . esc_url( RAR_WAP_Dashboard::url() ) . '">' . esc_html__( 'Open verification dashboard', 'rar-woo-advance-payment' ) . '</a></p></div>';
		echo '<div class="rar-wap-health ' . esc_attr( $health['class'] ) . '"><strong>' . esc_html( $health['label'] ) . '</strong><span>' . esc_html( $health['message'] ) . '</span></div>';
		echo '</div>';

		echo '<div class="rar-wap-admin-stats">';
		echo '<div><span>' . esc_html__( 'Gateway', 'rar-woo-advance-payment' ) . '</span><strong>' . ( 'yes' === $this->enabled ? esc_html__( 'Enabled', 'rar-woo-advance-payment' ) : esc_html__( 'Disabled', 'rar-woo-advance-payment' ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Visibility', 'rar-woo-advance-payment' ) . '</span><strong>' . ( $this->is_safe_test_mode() ? esc_html__( 'Safe Test', 'rar-woo-advance-payment' ) : esc_html__( 'Live', 'rar-woo-advance-payment' ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Usable channels', 'rar-woo-advance-payment' ) . '</span><strong>' . esc_html( $channels ? implode( ', ', $channels ) : '0' ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Requirement', 'rar-woo-advance-payment' ) . '</span><strong>' . ( $this->is_force_required() ? esc_html__( 'Required', 'rar-woo-advance-payment' ) : esc_html__( 'Optional', 'rar-woo-advance-payment' ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Awaiting verification', 'rar-woo-advance-payment' ) . '</span><strong>' . esc_html( (string) $pending ) . '</strong></div>';
		echo '</div>';

		echo '<div class="rar-wap-admin-guidance"><strong>' . esc_html__( 'Safe rollout:', 'rar-woo-advance-payment' ) . '</strong> ';
		echo esc_html__( 'Keep Safe Test Mode ON → configure a channel → place a low-value test order → verify it from the dashboard → then turn Safe Test Mode OFF.', 'rar-woo-advance-payment' );
		echo '</div>';

		parent::admin_options();
	}

	public function get_configuration_health() {
		$channels = $this->get_enabled_channels();

		if ( 'yes' !== $this->enabled ) {
			return array(
				'class'   => 'is-neutral',
				'label'   => __( 'Not live', 'rar-woo-advance-payment' ),
				'message' => __( 'Gateway is currently disabled.', 'rar-woo-advance-payment' ),
			);
		}

		if ( empty( $channels ) ) {
			return array(
				'class'   => 'is-warning',
				'label'   => __( 'Needs setup', 'rar-woo-advance-payment' ),
				'message' => __( 'Enable at least one payment channel and enter its destination details.', 'rar-woo-advance-payment' ),
			);
		}

		$rule = $this->get_option( 'amount_rule', 'shipping' );
		$uses_fixed = 'fixed' === $rule || ( 'shipping' === $rule && 'fixed' === $this->get_option( 'shipping_zero_behaviour', 'hide' ) );
		if ( $uses_fixed && (float) wc_format_decimal( $this->get_option( 'fixed_amount', '0' ) ) <= 0 ) {
			return array(
				'class'   => 'is-warning',
				'label'   => __( 'Needs setup', 'rar-woo-advance-payment' ),
				'message' => __( 'Fixed advance amount must be greater than zero.', 'rar-woo-advance-payment' ),
			);
		}

		if ( $this->is_safe_test_mode() ) {
			return array(
				'class'   => 'is-test',
				'label'   => __( 'Ready for testing', 'rar-woo-advance-payment' ),
				'message' => __( 'Configured, but visible only to Administrators and Shop Managers.', 'rar-woo-advance-payment' ),
			);
		}

		return array(
			'class'   => 'is-ready',
			'label'   => __( 'Live & configured', 'rar-woo-advance-payment' ),
			'message' => __( 'Gateway is available to eligible customers.', 'rar-woo-advance-payment' ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Channels
	 * ------------------------------------------------------------------ */

	/**
	 * Detailed configuration of usable channels.
	 *
	 * @return array<string,array{key:string,type:string,label:string,instruction:string,number:string,details:string,qr:string,logo:string,destination:string}>
	 */
	public function get_channel_config() {
		if ( null !== $this->channel_cache ) {
			return $this->channel_cache;
		}

		$out = array();
		foreach ( self::CHANNELS as $key => $def ) {
			if ( 'yes' !== $this->get_option( $key . '_enabled', 'no' ) ) {
				continue;
			}

			$type  = $def[0];
			$label = $def[1];
			$item  = array(
				'key'         => $key,
				'type'        => $type,
				'label'       => $label,
				'instruction' => '',
				'number'      => '',
				'details'     => '',
				'qr'          => '',
				'logo'        => esc_url_raw( (string) $this->get_option( $key . '_logo', '' ) ),
				'destination' => '',
			);

			switch ( $type ) {
				case 'mfs':
					$item['number']      = trim( (string) $this->get_option( $key . '_number', '' ) );
					$item['instruction'] = trim( (string) $this->get_option( $key . '_type', '' ) );
					$item['destination'] = $item['number'];
					if ( '' === $item['number'] ) {
						continue 2;
					}
					break;
				case 'qr':
					$item['qr']          = esc_url_raw( (string) $this->get_option( 'banglaqr_image', '' ) );
					$item['instruction'] = trim( (string) $this->get_option( 'banglaqr_note', '' ) );
					$item['destination'] = 'Bangla QR';
					if ( '' === $item['qr'] ) {
						continue 2;
					}
					break;
				case 'bank':
					$item['label']       = __( 'Bank Transfer (NPSB)', 'rar-woo-advance-payment' );
					$item['details']     = trim( (string) $this->get_option( 'bank_details', '' ) );
					$item['number']      = trim( (string) $this->get_option( 'bank_account', '' ) );
					$first               = strtok( $item['details'], "\n" );
					$item['destination'] = $item['number'] ? $item['number'] : trim( (string) $first );
					if ( '' === $item['details'] ) {
						continue 2;
					}
					break;
				case 'custom':
					$item['label']       = trim( (string) $this->get_option( 'custom_label', '' ) );
					$item['number']      = trim( (string) $this->get_option( 'custom_number', '' ) );
					$item['details']     = trim( (string) $this->get_option( 'custom_details', '' ) );
					$item['destination'] = $item['number'];
					if ( '' === $item['label'] || ( '' === $item['number'] && '' === $item['details'] ) ) {
						continue 2;
					}
					break;
			}

			$out[ $key ] = $item;
		}

		$this->channel_cache = apply_filters( 'rar_wap_channels', $out, $this );
		return $this->channel_cache;
	}

	/**
	 * Back-compat: key => label of usable channels.
	 *
	 * @return array<string,string>
	 */
	public function get_enabled_channels() {
		return wp_list_pluck( $this->get_channel_config(), 'label' );
	}

	/* ------------------------------------------------------------------ *
	 * Availability and amount
	 * ------------------------------------------------------------------ */

	public function is_safe_test_mode() {
		return 'yes' === $this->get_option( 'safe_test_mode', 'yes' );
	}

	public function is_force_required() {
		return 'required' === $this->get_option( 'enforcement', 'optional' );
	}

	/**
	 * Required mode for a given total (respects the "require only from" threshold).
	 */
	public function is_required_for_total( $total ) {
		if ( ! $this->is_force_required() ) {
			return false;
		}
		$threshold = (float) wc_format_decimal( $this->get_option( 'required_min_total', '0' ) );
		return $threshold <= 0 || (float) $total >= $threshold;
	}

	public function is_visible_to_current_user() {
		return ! $this->is_safe_test_mode() || current_user_can( 'manage_woocommerce' );
	}

	public function is_configured_for_checkout() {
		return 'yes' === $this->enabled && ! empty( $this->get_channel_config() );
	}

	/**
	 * The order being paid on the order-pay endpoint, if any.
	 *
	 * @return WC_Order|null
	 */
	public function get_context_order() {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}
		return null;
	}

	/**
	 * @return array{total:float,shipping:float}
	 */
	private function context_totals() {
		$order = $this->get_context_order();
		if ( $order ) {
			return array(
				'total'    => max( 0.0, (float) $order->get_total() ),
				'shipping' => (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
			);
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array(
				'total'    => 0.0,
				'shipping' => 0.0,
			);
		}

		return array(
			'total'    => max( 0.0, (float) WC()->cart->get_total( 'edit' ) ),
			'shipping' => (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax(),
		);
	}

	/**
	 * Core amount rule — one implementation for cart, order-pay, blocks and orders.
	 */
	public function compute_amount( $total, $shipping ) {
		$total    = max( 0.0, (float) $total );
		$shipping = max( 0.0, (float) $shipping );
		$rule     = $this->get_option( 'amount_rule', 'shipping' );
		$percent  = min( 100, max( 1, (float) $this->get_option( 'percentage', '20' ) ) );
		$fixed    = max( 0.0, (float) wc_format_decimal( $this->get_option( 'fixed_amount', '0' ) ) );

		switch ( $rule ) {
			case 'fixed':
				$amount = $fixed;
				break;
			case 'percent':
				$amount = $total * ( $percent / 100 );
				break;
			case 'shipping_percent':
				$amount = $shipping + max( 0.0, $total - $shipping ) * ( $percent / 100 );
				break;
			case 'full':
				$amount = $total;
				break;
			case 'shipping':
			default:
				$amount = $shipping;
				if ( $amount <= 0 && 'fixed' === $this->get_option( 'shipping_zero_behaviour', 'hide' ) ) {
					$amount = $fixed;
				}
				break;
		}

		switch ( $this->get_option( 'round_amount', 'none' ) ) {
			case 'up':
				$amount = ceil( $amount - 0.0001 );
				break;
			case 'ten':
				$amount = ceil( ( $amount - 0.0001 ) / 10 ) * 10;
				break;
		}

		$amount = max( 0.0, min( $amount, $total ) );
		$amount = (float) apply_filters( 'rar_wap_amount_due', $amount, $total, $shipping, $rule, $this );

		return (float) wc_format_decimal( max( 0.0, min( $amount, $total ) ), wc_get_price_decimals() );
	}

	public function get_cart_total() {
		$totals = $this->context_totals();
		return $totals['total'];
	}

	public function get_amount_due_now() {
		$totals = $this->context_totals();
		return $this->compute_amount( $totals['total'], $totals['shipping'] );
	}

	public function get_balance_due() {
		return max( 0.0, $this->get_cart_total() - $this->get_amount_due_now() );
	}

	public function calculate_amount_for_order( WC_Order $order ) {
		return $this->compute_amount( (float) $order->get_total(), (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() );
	}

	private function total_within_limits( $total ) {
		$min = (float) wc_format_decimal( $this->get_option( 'min_order_total', '0' ) );
		$max = (float) wc_format_decimal( $this->get_option( 'max_order_total', '0' ) );
		if ( $min > 0 && $total < $min ) {
			return false;
		}
		if ( $max > 0 && $total > $max ) {
			return false;
		}
		return true;
	}

	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}
		if ( ! $this->is_visible_to_current_user() ) {
			return false;
		}
		if ( empty( $this->get_channel_config() ) ) {
			return false;
		}

		// Admin screens (e.g. payment settings list) have no cart.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return true;
		}

		$totals = $this->context_totals();
		if ( ! $this->total_within_limits( $totals['total'] ) ) {
			return false;
		}

		return $this->compute_amount( $totals['total'], $totals['shipping'] ) > 0;
	}

	public function get_icon() {
		if ( 'yes' !== $this->get_option( 'show_logos_in_title', 'yes' ) ) {
			return apply_filters( 'woocommerce_gateway_icon', '', $this->id );
		}

		$html = '';
		foreach ( $this->get_channel_config() as $channel ) {
			if ( $channel['logo'] ) {
				$html .= '<img class="rar-wap-title-logo" src="' . esc_url( $channel['logo'] ) . '" alt="' . esc_attr( $channel['label'] ) . '" loading="lazy" decoding="async">';
			}
		}
		if ( $html ) {
			$html = '<span class="rar-wap-title-logos">' . $html . '</span>';
		}

		return apply_filters( 'woocommerce_gateway_icon', $html, $this->id );
	}

	/* ------------------------------------------------------------------ *
	 * Checkout UI
	 * ------------------------------------------------------------------ */

	/**
	 * Read a posted field, including from the serialized post_data sent by
	 * update_order_review, so values survive checkout refreshes.
	 */
	private function posted( $key ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
		if ( isset( $_POST[ $key ] ) ) {
			return wc_clean( wp_unslash( $_POST[ $key ] ) );
		}
		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			$data = array();
			wp_parse_str( wp_unslash( $_POST['post_data'] ), $data );
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				return wc_clean( $data[ $key ] );
			}
		}
		// phpcs:enable
		return '';
	}

	private function copy_button( $value, $label ) {
		if ( '' === trim( (string) $value ) ) {
			return '';
		}

		return sprintf(
			'<button type="button" class="rar-wap-copy" data-copy="%1$s" aria-label="%2$s">%3$s</button>',
			esc_attr( $value ),
			esc_attr( $label ),
			esc_html( RAR_WAP_I18n::t( 'copy' ) )
		);
	}

	public function channel_icon_html( array $channel ) {
		$key = sanitize_key( $channel['key'] );

		if ( $channel['logo'] ) {
			return sprintf(
				'<span class="rar-wap-channel-icon has-logo is-%1$s" aria-hidden="true"><img src="%2$s" alt="" loading="eager" decoding="async"></span>',
				esc_attr( $key ),
				esc_url( $channel['logo'] )
			);
		}

		if ( 'qr' === $channel['type'] ) {
			$svg = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M3 3h7v7H3V3zm2 2v3h3V5H5zm9-2h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5zm9-2h3v3h-3v-3zm4 0h3v7h-3v-3h-2v-2h2v-2zm-4 5h2v2h-2v-2z"/></svg>';
		} elseif ( 'bank' === $channel['type'] ) {
			$svg = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 3 3 7v2h18V7l-9-4zM5 11h2v7H5v-7zm4 0h2v7H9v-7zm4 0h2v7h-2v-7zm4 0h2v7h-2v-7zM3 20h18v2H3v-2z"/></svg>';
		} else {
			$svg = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 5h13a3 3 0 0 1 3 3v1h-5a4 4 0 0 0 0 8h5v1a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3zm11 6h7v4h-7a2 2 0 1 1 0-4z"/></svg>';
		}

		return '<span class="rar-wap-channel-icon is-' . esc_attr( $key ) . '" aria-hidden="true">' . $svg . '</span>';
	}

	public function channel_details_html( array $channel ) {
		$html = '';

		if ( 'qr' === $channel['type'] ) {
			$html .= sprintf(
				'<span class="rar-wap-qr"><a class="rar-wap-qr-link" href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" loading="eager" decoding="async"></a><small class="rar-wap-qr-enlarge">%3$s</small></span>',
				esc_url( $channel['qr'] ),
				esc_attr__( 'Bangla QR payment code', 'rar-woo-advance-payment' ),
				esc_html( RAR_WAP_I18n::t( 'qr_enlarge' ) )
			);
			if ( $channel['instruction'] ) {
				$html .= '<span class="rar-wap-channel-note">' . esc_html( $channel['instruction'] ) . '</span>';
			}
			return $html;
		}

		if ( '' !== $channel['number'] ) {
			$html .= sprintf(
				'<span class="rar-wap-destination"><span><small>%1$s</small><strong>%2$s</strong><code>%3$s</code></span>%4$s</span>',
				esc_html( 'mfs' === $channel['type'] ? RAR_WAP_I18n::t( 'instruction' ) : RAR_WAP_I18n::t( 'account_type' ) ),
				esc_html( $channel['instruction'] ? $channel['instruction'] : $channel['label'] ),
				esc_html( $channel['number'] ),
				/* translators: %s: channel */
				$this->copy_button( $channel['number'], sprintf( __( 'Copy %s number', 'rar-woo-advance-payment' ), $channel['label'] ) )
			);
		}

		if ( '' !== $channel['details'] ) {
			$html .= '<span class="rar-wap-bank-details">' . nl2br( esc_html( $channel['details'] ) ) . '</span>';
		}

		return $html;
	}

	public function payment_fields() {
		$totals   = $this->context_totals();
		$amount   = $this->compute_amount( $totals['total'], $totals['shipping'] );
		$balance  = max( 0.0, $totals['total'] - $amount );
		$channels = $this->get_channel_config();
		$selected = sanitize_key( $this->posted( 'rar_wap_channel' ) );
		$payer    = $this->posted( 'rar_wap_payer' );
		$ref      = $this->posted( 'rar_wap_reference' );

		if ( ! isset( $channels[ $selected ] ) ) {
			$selected = 1 === count( $channels ) ? (string) key( $channels ) : '';
		}

		if ( $this->description ) {
			echo '<div class="rar-wap-intro">' . wp_kses_post( wpautop( $this->description ) ) . '</div>';
		}

		$allowed_icon = array(
			'span' => array( 'class' => true, 'aria-hidden' => true ),
			'img'  => array( 'src' => true, 'alt' => true, 'loading' => true, 'decoding' => true ),
			'svg'  => array( 'viewbox' => true, 'focusable' => true, 'aria-hidden' => true ),
			'path' => array( 'd' => true ),
		);
		$allowed_details = array(
			'span'   => array( 'class' => true ),
			'small'  => array( 'class' => true ),
			'strong' => array(),
			'code'   => array(),
			'br'     => array(),
			'a'      => array( 'class' => true, 'href' => true, 'target' => true, 'rel' => true ),
			'img'    => array( 'src' => true, 'alt' => true, 'loading' => true, 'decoding' => true ),
			'button' => array( 'type' => true, 'class' => true, 'data-copy' => true, 'aria-label' => true ),
		);
		?>
		<div class="rar-wap-box" data-amount="<?php echo esc_attr( (string) $amount ); ?>">
			<div class="rar-wap-trust-strip" role="note">
				<span>✓ <?php RAR_WAP_I18n::e( 'trust_manual' ); ?></span>
				<span>🔒 <?php RAR_WAP_I18n::e( 'trust_nopin' ); ?></span>
				<span>✓ <?php RAR_WAP_I18n::e( 'trust_linked' ); ?></span>
			</div>

			<div class="rar-wap-summary">
				<div class="is-primary">
					<span><?php RAR_WAP_I18n::e( 'pay_now' ); ?></span>
					<strong><?php echo wp_kses_post( wc_price( $amount ) ); ?></strong>
					<small><?php RAR_WAP_I18n::e( 'transfer_exact' ); ?></small>
				</div>
				<div>
					<span><?php RAR_WAP_I18n::e( 'due_on_delivery' ); ?></span>
					<strong><?php echo wp_kses_post( wc_price( $balance ) ); ?></strong>
					<small><?php RAR_WAP_I18n::e( $balance > 0 ? 'remaining_balance' : 'fully_paid_now' ); ?></small>
				</div>
			</div>

			<ol class="rar-wap-steps" aria-label="<?php esc_attr_e( 'Payment steps', 'rar-woo-advance-payment' ); ?>">
				<li><span>1</span><strong><?php RAR_WAP_I18n::e( 'step_channel' ); ?></strong></li>
				<li><span>2</span><strong><?php RAR_WAP_I18n::e( 'step_pay' ); ?></strong></li>
				<li><span>3</span><strong><?php RAR_WAP_I18n::e( 'step_submit' ); ?></strong></li>
			</ol>

			<p class="rar-wap-help"><?php RAR_WAP_I18n::e( 'help' ); ?></p>

			<div class="rar-wap-channels" role="radiogroup" aria-label="<?php esc_attr_e( 'Payment channel', 'rar-woo-advance-payment' ); ?>">
				<?php foreach ( $channels as $key => $channel ) : ?>
					<label class="rar-wap-channel<?php echo $selected === $key ? ' is-active' : ''; ?>" data-channel="<?php echo esc_attr( $key ); ?>" data-type="<?php echo esc_attr( $channel['type'] ); ?>">
						<span class="rar-wap-channel-head">
							<input type="radio" name="rar_wap_channel" value="<?php echo esc_attr( $key ); ?>" <?php checked( $selected, $key ); ?>>
							<?php echo wp_kses( $this->channel_icon_html( $channel ), $allowed_icon ); ?>
							<span>
								<strong><?php echo esc_html( $channel['label'] ); ?></strong>
								<small><?php RAR_WAP_I18n::e( 'tap_view' ); ?></small>
							</span>
						</span>
						<span class="rar-wap-channel-details" data-rar-channel="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses( $this->channel_details_html( $channel ), $allowed_details ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="rar-wap-reference-fields">
				<p class="form-row form-row-wide validate-required">
					<label for="rar_wap_payer"><?php RAR_WAP_I18n::e( 'payer_label' ); ?> <span class="required">*</span></label>
					<input type="text" class="input-text" name="rar_wap_payer" id="rar_wap_payer" autocomplete="off" inputmode="tel" maxlength="80" placeholder="<?php echo esc_attr( RAR_WAP_I18n::t( 'ph_generic_payer' ) ); ?>" value="<?php echo esc_attr( $payer ); ?>">
				</p>

				<p class="form-row form-row-wide validate-required">
					<label for="rar_wap_reference"><?php RAR_WAP_I18n::e( 'trx_label' ); ?> <span class="required">*</span></label>
					<input type="text" class="input-text" name="rar_wap_reference" id="rar_wap_reference" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="60" placeholder="<?php echo esc_attr( RAR_WAP_I18n::t( 'ph_trx' ) ); ?>" value="<?php echo esc_attr( $ref ); ?>">
					<small class="rar-wap-field-note"><?php RAR_WAP_I18n::e( 'trx_note' ); ?></small>
				</p>
			</div>

			<p class="rar-wap-safety"><strong>🔒 <?php RAR_WAP_I18n::e( 'security_title' ); ?></strong> <?php RAR_WAP_I18n::e( 'security' ); ?></p>
			<p class="rar-wap-verification-note"><?php RAR_WAP_I18n::e( 'verification_note' ); ?></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Validation and processing
	 * ------------------------------------------------------------------ */

	/**
	 * Validate and normalise the submitted payment fields.
	 *
	 * @return array{channel:string,payer:string,reference:string,normalized:string,duplicate_of:int,error:string}
	 */
	public function read_submission( $exclude_order_id = 0 ) {
		$channels  = $this->get_channel_config();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
		$channel   = isset( $_POST['rar_wap_channel'] ) ? sanitize_key( wp_unslash( $_POST['rar_wap_channel'] ) ) : '';
		$payer     = isset( $_POST['rar_wap_payer'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_payer'] ) ) : '';
		$reference = isset( $_POST['rar_wap_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_reference'] ) ) : '';
		// phpcs:enable

		return $this->check_submission( $channel, $payer, $reference, $exclude_order_id, $channels );
	}

	/**
	 * Shared validation (checkout, blocks, customer correction, REST).
	 *
	 * @return array{channel:string,payer:string,reference:string,normalized:string,duplicate_of:int,error:string}
	 */
	public function check_submission( $channel, $payer, $reference, $exclude_order_id = 0, $channels = null ) {
		$channels = null === $channels ? $this->get_channel_config() : $channels;
		$result   = array(
			'channel'      => sanitize_key( $channel ),
			'payer'        => trim( RAR_WAP_Order::normalize_digits( (string) $payer ) ),
			'reference'    => trim( RAR_WAP_Order::normalize_digits( (string) $reference ) ),
			'normalized'   => RAR_WAP_Order::normalize_reference( $reference ),
			'duplicate_of' => 0,
			'error'        => '',
		);

		if ( '' === $result['channel'] || ! isset( $channels[ $result['channel'] ] ) ) {
			$result['error'] = RAR_WAP_I18n::t( 'err_channel' );
			return $result;
		}

		if ( preg_match( '/\b(?:otp|pin|password|passcode|cvv|cvc)\b/i', $result['payer'] . ' ' . $result['reference'] ) ) {
			$result['error'] = RAR_WAP_I18n::t( 'err_sensitive' );
			return $result;
		}

		if ( 'mfs' === $channels[ $result['channel'] ]['type'] && 'no' !== $this->get_option( 'validate_mobile', 'yes' ) ) {
			$mobile = RAR_WAP_Order::normalize_mobile( $result['payer'] );
			if ( '' === $mobile ) {
				$result['error'] = RAR_WAP_I18n::t( 'err_mobile' );
				return $result;
			}
			$result['payer'] = $mobile;
		} elseif ( strlen( $result['payer'] ) < 4 ) {
			$result['error'] = RAR_WAP_I18n::t( 'err_payer' );
			return $result;
		}

		if ( ! RAR_WAP_Order::is_valid_reference( $result['normalized'] ) ) {
			$result['error'] = RAR_WAP_I18n::t( 'err_trx' );
			return $result;
		}

		$duplicate = RAR_WAP_Query::find_reference( $result['channel'], $result['reference'], $result['normalized'], $exclude_order_id );
		if ( $duplicate ) {
			if ( 'flag' === $this->get_option( 'duplicate_policy', 'block' ) ) {
				$result['duplicate_of'] = $duplicate;
			} else {
				$result['error'] = RAR_WAP_I18n::t( 'err_duplicate' );
			}
		}

		return $result;
	}

	public function validate_fields() {
		$context = $this->get_context_order();
		$result  = $this->read_submission( $context ? $context->get_id() : 0 );

		if ( $result['error'] ) {
			wc_add_notice( $result['error'], 'error' );
			return false;
		}
		return true;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Unable to create the order. Please try again.', 'rar-woo-advance-payment' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$result = $this->read_submission( $order->get_id() );
		if ( $result['error'] ) {
			RAR_WAP_Plugin::log( 'Payment processing stopped: invalid submission.', 'warning', array( 'order_id' => $order_id ) );
			wc_add_notice( $result['error'], 'error' );
			return array( 'result' => 'failure' );
		}

		$amount = $this->calculate_amount_for_order( $order );
		if ( $amount <= 0 ) {
			RAR_WAP_Plugin::log( 'Payment processing stopped: pay-now amount was zero.', 'warning', array( 'order_id' => $order_id ) );
			wc_add_notice( RAR_WAP_I18n::t( 'err_amount' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$channels = $this->get_channel_config();
		$channel  = $channels[ $result['channel'] ];

		RAR_WAP_Order::submit(
			$order,
			array(
				'channel'       => $result['channel'],
				'channel_label' => $channel['label'],
				'payer'         => $result['payer'],
				'reference'     => $result['reference'],
				'amount'        => $amount,
				'balance'       => max( 0.0, (float) $order->get_total() - $amount ),
				'rule'          => $this->get_option( 'amount_rule', 'shipping' ),
				'destination'   => $channel['destination'],
				'duplicate_of'  => $result['duplicate_of'],
			)
		);

		$note = sprintf(
			/* translators: 1: channel, 2: amount, 3: reference */
			__( 'Advance payment submitted via %1$s. Claimed amount: %2$s. Reference: %3$s. Awaiting manual verification.', 'rar-woo-advance-payment' ),
			$channel['label'],
			html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
			$result['reference']
		);
		if ( $result['duplicate_of'] ) {
			/* translators: %d: order ID */
			$note .= ' ' . sprintf( __( 'WARNING: the same reference was also submitted on order #%d.', 'rar-woo-advance-payment' ), $result['duplicate_of'] );
		}

		$status = $this->get_option( 'after_submit_status', 'on-hold' );
		$order->update_status( in_array( $status, array( 'on-hold', 'processing' ), true ) ? $status : 'on-hold', $note );

		$this->queue_submission_emails( $order );

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		RAR_WAP_Plugin::log(
			'Advance payment order submitted.',
			'info',
			array(
				'order_id' => $order_id,
				'channel'  => $result['channel'],
				'amount'   => $amount,
			)
		);

		RAR_WAP_Order::fire( 'submitted', $order, array( 'amount' => $amount ) );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Notifications (back-compat wrappers)
	 * ------------------------------------------------------------------ */

	private function queue_submission_emails( WC_Order $order ) {
		if ( 'yes' === $this->get_option( 'async_emails', 'yes' ) && function_exists( 'as_enqueue_async_action' ) ) {
			$order_id = $order->get_id();
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'rar_wap_send_submission_emails', array( $order_id ), 'rar-wap' ) ) {
				return;
			}
			as_enqueue_async_action( 'rar_wap_send_submission_emails', array( $order_id ), 'rar-wap' );
			return;
		}

		RAR_WAP_Emails::submission( $order );
	}

	public function send_submission_emails( WC_Order $order ) {
		RAR_WAP_Emails::submission( $order );
	}

	public function send_verification_email( WC_Order $order, $verified = true ) {
		return $verified ? RAR_WAP_Emails::customer_verified( $order ) : RAR_WAP_Emails::customer_rejected( $order );
	}

	public function log( $message, $level = 'info', $context = array() ) {
		RAR_WAP_Plugin::log( $message, $level, $context );
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'rar_advance_payment';
		$this->method_title       = __( 'RAR Advance Payment', 'rar-woo-advance-payment' );
		$this->method_description = __( 'Professional manual advance/full payment with Bangla QR, bKash, Nagad, Rocket or NPSB bank transfer. Includes safe rollout controls and manual verification.', 'rar-woo-advance-payment' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', 'Advance Payment / Online Transfer' );
		$this->description = $this->get_option( 'description', 'Pay the required amount now using your preferred local payment method. Your order is confirmed only after manual verification. Never share your PIN or OTP.' );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'rollout_heading' => array(
				'title'       => __( 'Gateway & Rollout', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Start in Safe Test Mode, complete the payment-channel setup, place test orders, then make the gateway available to customers.', 'rar-woo-advance-payment' ),
			),
			'enabled' => array(
				'title'   => __( 'Enable / Disable', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable RAR Advance Payment Gateway', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'safe_test_mode' => array(
				'title'       => __( 'Safe Test Mode', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Only Administrators and Shop Managers can see this gateway', 'rar-woo-advance-payment' ),
				'description' => __( 'Recommended while configuring or testing. Regular customers continue using the existing live payment methods.', 'rar-woo-advance-payment' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'title' => array(
				'title'       => __( 'Checkout title', 'rar-woo-advance-payment' ),
				'type'        => 'text',
				'default'     => __( 'Advance Payment / Online Transfer', 'rar-woo-advance-payment' ),
				'description' => __( 'Shown to customers beside the payment option.', 'rar-woo-advance-payment' ),
			),
			'description' => array(
				'title'       => __( 'Checkout description', 'rar-woo-advance-payment' ),
				'type'        => 'textarea',
				'default'     => __( 'Pay the required amount now using Bangla QR, bKash, Nagad, Rocket or Bank Transfer (NPSB). We manually verify your payment before fulfilment. Never share your PIN or OTP.', 'rar-woo-advance-payment' ),
				'description' => __( 'Keep this short and reassuring. Payment instructions are shown below automatically.', 'rar-woo-advance-payment' ),
			),

			'rules_heading' => array(
				'title'       => __( 'Payment Rules', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Choose whether advance payment is optional or mandatory and how the amount due now is calculated.', 'rar-woo-advance-payment' ),
			),
			'enforcement' => array(
				'title'       => __( 'Payment requirement', 'rar-woo-advance-payment' ),
				'type'        => 'select',
				'default'     => 'optional',
				'options'     => array(
					'optional' => __( 'Optional — keep COD available', 'rar-woo-advance-payment' ),
					'required' => __( 'Required — customer must submit advance before placing order', 'rar-woo-advance-payment' ),
				),
				'description' => __( 'Required mode can hide standard COD only when this gateway is enabled and has at least one usable payment channel.', 'rar-woo-advance-payment' ),
			),
			'hide_cod_when_required' => array(
				'title'       => __( 'COD control', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Hide standard Cash on Delivery when advance payment is required', 'rar-woo-advance-payment' ),
				'description' => __( 'Fails safe: COD stays available if this gateway is not correctly configured.', 'rar-woo-advance-payment' ),
				'default'     => 'yes',
			),
			'amount_rule' => array(
				'title'   => __( 'Amount to pay now', 'rar-woo-advance-payment' ),
				'type'    => 'select',
				'default' => 'shipping',
				'options' => array(
					'shipping' => __( 'Full delivery / shipping fee', 'rar-woo-advance-payment' ),
					'fixed'    => __( 'Fixed advance amount', 'rar-woo-advance-payment' ),
					'percent'  => __( 'Percentage of order total', 'rar-woo-advance-payment' ),
					'full'     => __( 'Full order total', 'rar-woo-advance-payment' ),
				),
			),
			'fixed_amount' => array(
				'title'             => __( 'Fixed advance amount', 'rar-woo-advance-payment' ),
				'type'              => 'price',
				'default'           => '200',
				'description'       => __( 'Used only when “Fixed advance amount” is selected.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			),
			'percentage' => array(
				'title'             => __( 'Advance percentage', 'rar-woo-advance-payment' ),
				'type'              => 'number',
				'default'           => '20',
				'description'       => __( 'Used only when “Percentage of order total” is selected.', 'rar-woo-advance-payment' ),
				'custom_attributes' => array( 'min' => '1', 'max' => '100', 'step' => '1' ),
			),
			'after_submit_status' => array(
				'title'       => __( 'Status after submission', 'rar-woo-advance-payment' ),
				'type'        => 'select',
				'default'     => 'on-hold',
				'options'     => array(
					'on-hold'    => __( 'On hold — recommended for manual verification', 'rar-woo-advance-payment' ),
					'processing' => __( 'Processing', 'rar-woo-advance-payment' ),
				),
				'description' => __( 'On hold is safest because entering a Transaction ID does not prove that money was received.', 'rar-woo-advance-payment' ),
			),
			'after_verify_status' => array(
				'title'   => __( 'Status after admin verification', 'rar-woo-advance-payment' ),
				'type'    => 'select',
				'default' => 'processing',
				'options' => array(
					'processing' => __( 'Processing', 'rar-woo-advance-payment' ),
					'on-hold'    => __( 'Keep On hold', 'rar-woo-advance-payment' ),
				),
			),

			'notifications_heading' => array(
				'title'       => __( 'Notifications & Performance', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Admin alerts are independent from WooCommerce New Order emails. Async mode reduces checkout waiting when SMTP is slow.', 'rar-woo-advance-payment' ),
			),
			'admin_email' => array(
				'title'       => __( 'Admin notification email', 'rar-woo-advance-payment' ),
				'type'        => 'email',
				'default'     => get_option( 'admin_email' ),
				'description' => __( 'Receives advance-payment submission alerts. Leave blank to use the WordPress admin email.', 'rar-woo-advance-payment' ),
			),
			'admin_submission_email' => array(
				'title'   => __( 'Admin payment alert', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Email admin when a customer submits payment details', 'rar-woo-advance-payment' ),
				'default' => 'yes',
			),
			'customer_submission_email' => array(
				'title'       => __( 'Customer submission email', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Send an extra “payment submission received” email', 'rar-woo-advance-payment' ),
				'description' => __( 'Default OFF to avoid duplicate customer emails when WooCommerce already sends an On-hold / Order received email.', 'rar-woo-advance-payment' ),
				'default'     => 'no',
			),
			'async_emails' => array(
				'title'       => __( 'Checkout speed', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Queue custom payment-notification emails after checkout', 'rar-woo-advance-payment' ),
				'description' => __( 'Recommended. Uses WooCommerce Action Scheduler when available so remote SMTP latency does not keep the Place Order spinner running.', 'rar-woo-advance-payment' ),
				'default'     => 'yes',
			),
			'debug_logging' => array(
				'title'       => __( 'Diagnostic logging', 'rar-woo-advance-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Write payment-workflow diagnostics to WooCommerce logs', 'rar-woo-advance-payment' ),
				'description' => __( 'Leave OFF during normal operation. Logs never include PIN, password or OTP because this plugin never collects them.', 'rar-woo-advance-payment' ),
				'default'     => 'no',
			),

			'channel_heading' => array(
				'title'       => __( 'Payment Channels', 'rar-woo-advance-payment' ),
				'type'        => 'title',
				'description' => __( 'Enable one or more channels. Customers see only channels that are enabled and have the required destination details.', 'rar-woo-advance-payment' ),
			),

			'bkash_enabled' => array(
				'title'   => 'bKash',
				'type'    => 'checkbox',
				'label'   => __( 'Enable bKash', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'bkash_number' => array(
				'title'       => __( 'bKash number', 'rar-woo-advance-payment' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Use the number customers should pay. Do not enter any PIN or secret.', 'rar-woo-advance-payment' ),
			),
			'bkash_type' => array(
				'title'   => __( 'bKash instruction', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => __( 'Send Money / Payment', 'rar-woo-advance-payment' ),
			),
			'bkash_logo' => array(
				'title'       => __( 'bKash logo', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Choose an official bKash logo from the Media Library. A neutral payment icon is used when left blank.', 'rar-woo-advance-payment' ),
			),

			'nagad_enabled' => array(
				'title'   => 'Nagad',
				'type'    => 'checkbox',
				'label'   => __( 'Enable Nagad', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'nagad_number' => array(
				'title'   => __( 'Nagad number', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => '',
			),
			'nagad_type' => array(
				'title'   => __( 'Nagad instruction', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => __( 'Send Money / Payment', 'rar-woo-advance-payment' ),
			),
			'nagad_logo' => array(
				'title'       => __( 'Nagad logo', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Choose an official Nagad logo from the Media Library. A neutral payment icon is used when left blank.', 'rar-woo-advance-payment' ),
			),

			'rocket_enabled' => array(
				'title'   => 'Rocket',
				'type'    => 'checkbox',
				'label'   => __( 'Enable Rocket', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'rocket_number' => array(
				'title'   => __( 'Rocket number', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => '',
			),
			'rocket_type' => array(
				'title'   => __( 'Rocket instruction', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => __( 'Send Money', 'rar-woo-advance-payment' ),
			),
			'rocket_logo' => array(
				'title'       => __( 'Rocket logo', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Choose an official Rocket logo from the Media Library. A neutral payment icon is used when left blank.', 'rar-woo-advance-payment' ),
			),

			'banglaqr_enabled' => array(
				'title'   => 'Bangla QR',
				'type'    => 'checkbox',
				'label'   => __( 'Enable Bangla QR', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'banglaqr_image' => array(
				'title'       => __( 'Bangla QR image', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Paste an image URL or use the Media Library button added beside this field.', 'rar-woo-advance-payment' ),
			),
			'banglaqr_note' => array(
				'title'   => __( 'Bangla QR instruction', 'rar-woo-advance-payment' ),
				'type'    => 'text',
				'default' => __( 'Scan the QR with a supported banking/MFS app and pay the exact amount.', 'rar-woo-advance-payment' ),
			),
			'banglaqr_logo' => array(
				'title'       => __( 'Bangla QR channel logo', 'rar-woo-advance-payment' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional brand logo shown beside the Bangla QR channel. Keep the payable merchant QR in the separate Bangla QR image field above.', 'rar-woo-advance-payment' ),
			),

			'bank_enabled' => array(
				'title'   => __( 'Bank Transfer (NPSB)', 'rar-woo-advance-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Bank Transfer / NPSB', 'rar-woo-advance-payment' ),
				'default' => 'no',
			),
			'bank_details' => array(
				'title'       => __( 'Bank / NPSB details', 'rar-woo-advance-payment' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Example: bank name, account name, account number and branch/routing information. Never put passwords or OTPs here.', 'rar-woo-advance-payment' ),
			),
		);
	}

	public function admin_options() {
		$health   = $this->get_configuration_health();
		$channels = $this->get_enabled_channels();

		echo '<div class="rar-wap-admin-hero">';
		echo '<div><span class="rar-wap-kicker">' . esc_html__( 'RAR Woo Payment Gateway', 'rar-woo-advance-payment' ) . '</span>';
		echo '<h2>' . esc_html__( 'Advance Payment Control Center', 'rar-woo-advance-payment' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure a safe manual payment workflow, test it privately, and verify every submitted transfer before fulfilment.', 'rar-woo-advance-payment' ) . '</p></div>';
		echo '<div class="rar-wap-health ' . esc_attr( $health['class'] ) . '"><strong>' . esc_html( $health['label'] ) . '</strong><span>' . esc_html( $health['message'] ) . '</span></div>';
		echo '</div>';

		echo '<div class="rar-wap-admin-stats">';
		echo '<div><span>' . esc_html__( 'Gateway', 'rar-woo-advance-payment' ) . '</span><strong>' . ( 'yes' === $this->enabled ? esc_html__( 'Enabled', 'rar-woo-advance-payment' ) : esc_html__( 'Disabled', 'rar-woo-advance-payment' ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Visibility', 'rar-woo-advance-payment' ) . '</span><strong>' . ( $this->is_safe_test_mode() ? esc_html__( 'Safe Test', 'rar-woo-advance-payment' ) : esc_html__( 'Live', 'rar-woo-advance-payment' ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Usable channels', 'rar-woo-advance-payment' ) . '</span><strong>' . esc_html( (string) count( $channels ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Requirement', 'rar-woo-advance-payment' ) . '</span><strong>' . ( $this->is_force_required() ? esc_html__( 'Required', 'rar-woo-advance-payment' ) : esc_html__( 'Optional', 'rar-woo-advance-payment' ) ) . '</strong></div>';
		echo '</div>';

		echo '<div class="rar-wap-admin-guidance"><strong>' . esc_html__( 'Safe rollout:', 'rar-woo-advance-payment' ) . '</strong> ';
		echo esc_html__( 'Keep Safe Test Mode ON → configure a channel → place a low-value test order → verify emails/order metadata → then turn Safe Test Mode OFF.', 'rar-woo-advance-payment' );
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
		if ( 'fixed' === $rule && (float) wc_format_decimal( $this->get_option( 'fixed_amount', '0' ) ) <= 0 ) {
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

	public function is_safe_test_mode() {
		return 'yes' === $this->get_option( 'safe_test_mode', 'yes' );
	}

	public function is_force_required() {
		return 'required' === $this->get_option( 'enforcement', 'optional' );
	}

	public function get_enabled_channels() {
		$channels = array();

		if ( 'yes' === $this->get_option( 'bkash_enabled', 'no' ) && '' !== trim( (string) $this->get_option( 'bkash_number', '' ) ) ) {
			$channels['bkash'] = 'bKash';
		}
		if ( 'yes' === $this->get_option( 'nagad_enabled', 'no' ) && '' !== trim( (string) $this->get_option( 'nagad_number', '' ) ) ) {
			$channels['nagad'] = 'Nagad';
		}
		if ( 'yes' === $this->get_option( 'rocket_enabled', 'no' ) && '' !== trim( (string) $this->get_option( 'rocket_number', '' ) ) ) {
			$channels['rocket'] = 'Rocket';
		}
		if ( 'yes' === $this->get_option( 'banglaqr_enabled', 'no' ) && esc_url_raw( $this->get_option( 'banglaqr_image', '' ) ) ) {
			$channels['banglaqr'] = 'Bangla QR';
		}
		if ( 'yes' === $this->get_option( 'bank_enabled', 'no' ) && '' !== trim( (string) $this->get_option( 'bank_details', '' ) ) ) {
			$channels['bank'] = __( 'Bank Transfer (NPSB)', 'rar-woo-advance-payment' );
		}

		return $channels;
	}

	public function is_configured_for_checkout() {
		return 'yes' === $this->enabled && ! empty( $this->get_enabled_channels() );
	}

	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		if ( $this->is_safe_test_mode() && ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( empty( $this->get_enabled_channels() ) ) {
			return false;
		}

		return $this->get_amount_due_now() > 0;
	}

	public function get_cart_total() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		return max( 0.0, (float) WC()->cart->get_total( 'edit' ) );
	}

	public function get_amount_due_now() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		$total = $this->get_cart_total();
		$rule  = $this->get_option( 'amount_rule', 'shipping' );

		switch ( $rule ) {
			case 'fixed':
				$amount = (float) wc_format_decimal( $this->get_option( 'fixed_amount', '0' ) );
				break;
			case 'percent':
				$percent = min( 100, max( 1, (float) $this->get_option( 'percentage', '20' ) ) );
				$amount  = $total * ( $percent / 100 );
				break;
			case 'full':
				$amount = $total;
				break;
			case 'shipping':
			default:
				$amount = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
				break;
		}

		$amount = max( 0.0, min( $amount, $total ) );
		return (float) wc_format_decimal( $amount, wc_get_price_decimals() );
	}

	public function get_balance_due() {
		return max( 0.0, $this->get_cart_total() - $this->get_amount_due_now() );
	}

	private function copy_button( $value, $label = '' ) {
		if ( '' === trim( (string) $value ) ) {
			return '';
		}

		return sprintf(
			'<button type="button" class="rar-wap-copy" data-copy="%1$s" aria-label="%2$s">%3$s</button>',
			esc_attr( $value ),
			esc_attr( $label ?: __( 'Copy payment destination', 'rar-woo-advance-payment' ) ),
			esc_html__( 'Copy', 'rar-woo-advance-payment' )
		);
	}

	private function channel_icon_html( $key, $label ) {
		$key      = sanitize_key( $key );
		$logo_key = $key . '_logo';
		$logo_url = esc_url( $this->get_option( $logo_key, '' ) );

		if ( $logo_url ) {
			return sprintf(
				'<span class="rar-wap-channel-icon has-logo is-%1$s" aria-hidden="true"><img src="%2$s" alt="" loading="eager" decoding="async"></span>',
				esc_attr( $key ),
				$logo_url
			);
		}

		if ( 'banglaqr' === $key ) {
			$svg = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M3 3h7v7H3V3zm2 2v3h3V5H5zm9-2h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5zm9-2h3v3h-3v-3zm4 0h3v7h-3v-3h-2v-2h2v-2zm-4 5h2v2h-2v-2z"/></svg>';
		} elseif ( 'bank' === $key ) {
			$svg = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 3 3 7v2h18V7l-9-4zM5 11h2v7H5v-7zm4 0h2v7H9v-7zm4 0h2v7h-2v-7zm4 0h2v7h-2v-7zM3 20h18v2H3v-2z"/></svg>';
		} else {
			$svg = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 5h13a3 3 0 0 1 3 3v1h-5a4 4 0 0 0 0 8h5v1a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3zm11 6h7v4h-7a2 2 0 1 1 0-4z"/></svg>';
		}

		return '<span class="rar-wap-channel-icon is-' . esc_attr( $key ) . '" aria-hidden="true">' . $svg . '</span>';
	}

	private function channel_details_html( $key ) {
		switch ( $key ) {
			case 'bkash':
				$number = trim( (string) $this->get_option( 'bkash_number', '' ) );
				return sprintf(
					'<span class="rar-wap-destination"><span><small>%1$s</small><strong>%2$s</strong><code>%3$s</code></span>%4$s</span>',
					esc_html__( 'Instruction', 'rar-woo-advance-payment' ),
					esc_html( $this->get_option( 'bkash_type', 'Send Money / Payment' ) ),
					esc_html( $number ),
					$this->copy_button( $number, __( 'Copy bKash number', 'rar-woo-advance-payment' ) )
				);
			case 'nagad':
				$number = trim( (string) $this->get_option( 'nagad_number', '' ) );
				return sprintf(
					'<span class="rar-wap-destination"><span><small>%1$s</small><strong>%2$s</strong><code>%3$s</code></span>%4$s</span>',
					esc_html__( 'Instruction', 'rar-woo-advance-payment' ),
					esc_html( $this->get_option( 'nagad_type', 'Send Money / Payment' ) ),
					esc_html( $number ),
					$this->copy_button( $number, __( 'Copy Nagad number', 'rar-woo-advance-payment' ) )
				);
			case 'rocket':
				$number = trim( (string) $this->get_option( 'rocket_number', '' ) );
				return sprintf(
					'<span class="rar-wap-destination"><span><small>%1$s</small><strong>%2$s</strong><code>%3$s</code></span>%4$s</span>',
					esc_html__( 'Instruction', 'rar-woo-advance-payment' ),
					esc_html( $this->get_option( 'rocket_type', 'Send Money' ) ),
					esc_html( $number ),
					$this->copy_button( $number, __( 'Copy Rocket number', 'rar-woo-advance-payment' ) )
				);
			case 'banglaqr':
				$url = esc_url( $this->get_option( 'banglaqr_image', '' ) );
				return sprintf(
					'<div class="rar-wap-qr"><a class="rar-wap-qr-link" href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" loading="eager" decoding="async"></a><small class="rar-wap-qr-enlarge">%3$s</small></div><p class="rar-wap-channel-note">%4$s</p>',
					$url,
					esc_attr__( 'Bangla QR payment code', 'rar-woo-advance-payment' ),
					esc_html__( 'Tap / click the QR to view full size', 'rar-woo-advance-payment' ),
					esc_html( $this->get_option( 'banglaqr_note', '' ) )
				);
			case 'bank':
				return '<div class="rar-wap-bank-details">' . nl2br( esc_html( $this->get_option( 'bank_details', '' ) ) ) . '</div>';
		}

		return '';
	}

	public function payment_fields() {
		$amount   = $this->get_amount_due_now();
		$balance  = $this->get_balance_due();
		$channels = $this->get_enabled_channels();

		if ( $this->description ) {
			echo '<div class="rar-wap-intro">' . wpautop( wp_kses_post( $this->description ) ) . '</div>';
		}
		?>
		<div class="rar-wap-box">
			<div class="rar-wap-trust-strip" role="note">
				<span>✓ <?php esc_html_e( 'Manual verification', 'rar-woo-advance-payment' ); ?></span>
				<span>🔒 <?php esc_html_e( 'No PIN / OTP', 'rar-woo-advance-payment' ); ?></span>
				<span>✓ <?php esc_html_e( 'Order-linked reference', 'rar-woo-advance-payment' ); ?></span>
			</div>

			<div class="rar-wap-summary">
				<div class="is-primary">
					<span><?php esc_html_e( 'Pay now', 'rar-woo-advance-payment' ); ?></span>
					<strong><?php echo wp_kses_post( wc_price( $amount ) ); ?></strong>
					<small><?php esc_html_e( 'Transfer this exact amount', 'rar-woo-advance-payment' ); ?></small>
				</div>
				<div>
					<span><?php esc_html_e( 'Due on delivery', 'rar-woo-advance-payment' ); ?></span>
					<strong><?php echo wp_kses_post( wc_price( $balance ) ); ?></strong>
					<small><?php esc_html_e( 'Remaining order balance', 'rar-woo-advance-payment' ); ?></small>
				</div>
			</div>

			<ol class="rar-wap-steps" aria-label="<?php esc_attr_e( 'Payment steps', 'rar-woo-advance-payment' ); ?>">
				<li><span>1</span><strong><?php esc_html_e( 'Choose channel', 'rar-woo-advance-payment' ); ?></strong></li>
				<li><span>2</span><strong><?php esc_html_e( 'Pay exact amount', 'rar-woo-advance-payment' ); ?></strong></li>
				<li><span>3</span><strong><?php esc_html_e( 'Submit reference', 'rar-woo-advance-payment' ); ?></strong></li>
			</ol>

			<p class="rar-wap-help"><?php esc_html_e( 'Select a channel below, complete the transfer in your banking/MFS app, then enter the payer reference and Transaction ID. আপনার PIN, password বা OTP কখনো এখানে লিখবেন না।', 'rar-woo-advance-payment' ); ?></p>

			<div class="rar-wap-channels">
				<?php foreach ( $channels as $key => $label ) : ?>
					<label class="rar-wap-channel" data-channel="<?php echo esc_attr( $key ); ?>">
						<span class="rar-wap-channel-head">
							<input type="radio" name="rar_wap_channel" value="<?php echo esc_attr( $key ); ?>" <?php checked( isset( $_POST['rar_wap_channel'] ) ? wc_clean( wp_unslash( $_POST['rar_wap_channel'] ) ) : '', $key ); ?>>
							<?php echo wp_kses_post( $this->channel_icon_html( $key, $label ) ); ?>
							<span>
								<strong><?php echo esc_html( $label ); ?></strong>
								<small><?php esc_html_e( 'Tap to view payment instructions', 'rar-woo-advance-payment' ); ?></small>
							</span>
						</span>
						<span class="rar-wap-channel-details" data-rar-channel="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $this->channel_details_html( $key ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="rar-wap-reference-fields">
				<p class="form-row form-row-wide">
					<label for="rar_wap_payer"><?php esc_html_e( 'Payer mobile / bank account reference', 'rar-woo-advance-payment' ); ?> <span class="required">*</span></label>
					<input type="text" class="input-text" name="rar_wap_payer" id="rar_wap_payer" autocomplete="off" inputmode="text" maxlength="80" placeholder="<?php esc_attr_e( 'e.g. 01XXXXXXXXX or account reference', 'rar-woo-advance-payment' ); ?>" value="<?php echo isset( $_POST['rar_wap_payer'] ) ? esc_attr( wc_clean( wp_unslash( $_POST['rar_wap_payer'] ) ) ) : ''; ?>">
				</p>

				<p class="form-row form-row-wide">
					<label for="rar_wap_reference"><?php esc_html_e( 'Transaction ID / Reference', 'rar-woo-advance-payment' ); ?> <span class="required">*</span></label>
					<input type="text" class="input-text" name="rar_wap_reference" id="rar_wap_reference" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="120" placeholder="<?php esc_attr_e( 'Enter the transaction/reference ID exactly', 'rar-woo-advance-payment' ); ?>" value="<?php echo isset( $_POST['rar_wap_reference'] ) ? esc_attr( wc_clean( wp_unslash( $_POST['rar_wap_reference'] ) ) ) : ''; ?>">
					<small class="rar-wap-field-note"><?php esc_html_e( 'We use this only to match your transfer with this order.', 'rar-woo-advance-payment' ); ?></small>
				</p>
			</div>

			<p class="rar-wap-safety"><strong>🔒 <?php esc_html_e( 'Security notice:', 'rar-woo-advance-payment' ); ?></strong> <?php esc_html_e( 'We will never ask for your PIN, password, OTP or card security code.', 'rar-woo-advance-payment' ); ?></p>
			<p class="rar-wap-verification-note"><?php esc_html_e( 'Submitting a reference does not automatically mark the payment as verified. An admin will confirm the transfer before fulfilment.', 'rar-woo-advance-payment' ); ?></p>
		</div>
		<?php
	}

	public function validate_fields() {
		$channels  = $this->get_enabled_channels();
		$channel   = isset( $_POST['rar_wap_channel'] ) ? sanitize_key( wp_unslash( $_POST['rar_wap_channel'] ) ) : '';
		$payer     = isset( $_POST['rar_wap_payer'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_payer'] ) ) : '';
		$reference = isset( $_POST['rar_wap_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_reference'] ) ) : '';

		if ( ! $channel || ! isset( $channels[ $channel ] ) ) {
			wc_add_notice( __( 'Please choose a valid payment channel.', 'rar-woo-advance-payment' ), 'error' );
			return false;
		}

		if ( '' === trim( $payer ) || strlen( trim( $payer ) ) < 4 ) {
			wc_add_notice( __( 'Please enter the payer mobile number or bank account reference.', 'rar-woo-advance-payment' ), 'error' );
			return false;
		}

		if ( '' === trim( $reference ) || strlen( trim( $reference ) ) < 4 ) {
			wc_add_notice( __( 'Please enter a valid Transaction ID / Reference.', 'rar-woo-advance-payment' ), 'error' );
			return false;
		}

		if ( preg_match( '/\b(?:otp|pin|password|passcode|cvv|cvc)\b/i', $payer . ' ' . $reference ) ) {
			wc_add_notice( __( 'Never enter a PIN, password, OTP or security code. Enter only the payer/account reference and Transaction ID.', 'rar-woo-advance-payment' ), 'error' );
			return false;
		}

		if ( $this->reference_exists( $channel, $reference ) ) {
			wc_add_notice( __( 'This Transaction ID / Reference has already been submitted. Please check the reference or contact support instead of placing a duplicate payment claim.', 'rar-woo-advance-payment' ), 'error' );
			return false;
		}

		return true;
	}

	private function normalize_reference( $reference ) {
		$reference = strtoupper( trim( sanitize_text_field( (string) $reference ) ) );
		return preg_replace( '/\s+/', '', $reference );
	}

	private function reference_exists( $channel, $reference ) {
		global $wpdb;

		$channel    = sanitize_key( $channel );
		$raw        = trim( sanitize_text_field( (string) $reference ) );
		$normalized = $this->normalize_reference( $reference );

		if ( '' === $channel || '' === $raw || '' === $normalized ) {
			return false;
		}

		/*
		 * Do not rely on wc_get_orders( meta_query ) here.
		 * Some legacy-order-storage combinations can ignore unsupported query args
		 * and return unrelated orders, causing a false duplicate warning.
		 *
		 * Instead, require an exact matching RAR meta row and channel in the
		 * underlying WooCommerce storage. This supports both legacy CPT orders
		 * and HPOS without treating unrelated orders as duplicates.
		 */
		$legacy_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ref.post_id
				FROM {$wpdb->postmeta} ref
				INNER JOIN {$wpdb->postmeta} channel_meta
					ON channel_meta.post_id = ref.post_id
					AND channel_meta.meta_key = '_rar_wap_channel'
				INNER JOIN {$wpdb->posts} orders
					ON orders.ID = ref.post_id
				WHERE orders.post_type = 'shop_order'
					AND orders.post_status <> 'trash'
					AND channel_meta.meta_value = %s
					AND (
						(ref.meta_key = '_rar_wap_reference_normalized' AND ref.meta_value = %s)
						OR
						(ref.meta_key = '_rar_wap_reference' AND ref.meta_value = %s)
					)
				LIMIT 1",
				$channel,
				$normalized,
				$raw
			)
		);

		if ( $legacy_id ) {
			return true;
		}

		$orders_table = $wpdb->prefix . 'wc_orders';
		$meta_table   = $wpdb->prefix . 'wc_orders_meta';

		$orders_table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $orders_table ) )
		);
		$meta_table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $meta_table ) )
		);

		if ( $orders_table_exists !== $orders_table || $meta_table_exists !== $meta_table ) {
			return false;
		}

		$hpos_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ref.order_id
				FROM {$meta_table} ref
				INNER JOIN {$meta_table} channel_meta
					ON channel_meta.order_id = ref.order_id
					AND channel_meta.meta_key = '_rar_wap_channel'
				INNER JOIN {$orders_table} orders
					ON orders.id = ref.order_id
				WHERE channel_meta.meta_value = %s
					AND (
						(ref.meta_key = '_rar_wap_reference_normalized' AND ref.meta_value = %s)
						OR
						(ref.meta_key = '_rar_wap_reference' AND ref.meta_value = %s)
					)
				LIMIT 1",
				$channel,
				$normalized,
				$raw
			)
		);

		return (bool) $hpos_id;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Unable to create the order. Please try again.', 'rar-woo-advance-payment' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$channels  = $this->get_enabled_channels();
		$channel   = isset( $_POST['rar_wap_channel'] ) ? sanitize_key( wp_unslash( $_POST['rar_wap_channel'] ) ) : '';
		$payer     = isset( $_POST['rar_wap_payer'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_payer'] ) ) : '';
		$reference = isset( $_POST['rar_wap_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_reference'] ) ) : '';

		if ( ! isset( $channels[ $channel ] ) || '' === trim( $payer ) || '' === trim( $reference ) ) {
			$this->log( 'Payment processing stopped because submitted gateway fields were incomplete.', 'warning', array( 'order_id' => $order_id ) );
			wc_add_notice( __( 'Payment details are incomplete. Please review the payment section and try again.', 'rar-woo-advance-payment' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$amount  = $this->calculate_amount_for_order( $order );
		$balance = max( 0.0, (float) $order->get_total() - $amount );

		if ( $amount <= 0 ) {
			$this->log( 'Payment processing stopped because calculated pay-now amount was zero.', 'warning', array( 'order_id' => $order_id ) );
			wc_add_notice( __( 'The advance amount could not be calculated. Please refresh checkout or choose another payment method.', 'rar-woo-advance-payment' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_rar_wap_status', 'submitted' );
		$order->update_meta_data( '_rar_wap_channel', $channel );
		$order->update_meta_data( '_rar_wap_channel_label', $channels[ $channel ] );
		$order->update_meta_data( '_rar_wap_payer', trim( $payer ) );
		$order->update_meta_data( '_rar_wap_reference', trim( $reference ) );
		$order->update_meta_data( '_rar_wap_reference_normalized', $this->normalize_reference( $reference ) );
		$order->update_meta_data( '_rar_wap_required_amount', wc_format_decimal( $amount ) );
		$order->update_meta_data( '_rar_wap_balance_due', wc_format_decimal( $balance ) );
		$order->update_meta_data( '_rar_wap_rule', $this->get_option( 'amount_rule', 'shipping' ) );
		$order->update_meta_data( '_rar_wap_submitted_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rar_wap_submission_id', wp_generate_uuid4() );
		$order->save();

		$status = $this->get_option( 'after_submit_status', 'on-hold' );
		$order->update_status(
			$status,
			sprintf(
				'Advance payment submitted via %1$s. Claimed amount: %2$s. Reference: %3$s. Awaiting manual verification.',
				$channels[ $channel ],
				wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
				$reference
			)
		);

		$this->queue_submission_emails( $order );

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		$this->log(
			'Advance payment order submitted.',
			'info',
			array(
				'order_id' => $order_id,
				'channel'  => $channel,
				'amount'   => $amount,
			)
		);

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function calculate_amount_for_order( WC_Order $order ) {
		$total = max( 0.0, (float) $order->get_total() );
		$rule  = $this->get_option( 'amount_rule', 'shipping' );

		switch ( $rule ) {
			case 'fixed':
				$amount = (float) wc_format_decimal( $this->get_option( 'fixed_amount', '0' ) );
				break;
			case 'percent':
				$percent = min( 100, max( 1, (float) $this->get_option( 'percentage', '20' ) ) );
				$amount  = $total * ( $percent / 100 );
				break;
			case 'full':
				$amount = $total;
				break;
			case 'shipping':
			default:
				$amount = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
				break;
		}

		return (float) wc_format_decimal( max( 0.0, min( $amount, $total ) ), wc_get_price_decimals() );
	}

	private function queue_submission_emails( WC_Order $order ) {
		if ( 'yes' === $this->get_option( 'async_emails', 'yes' ) && function_exists( 'as_enqueue_async_action' ) ) {
			$order_id = $order->get_id();

			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'rar_wap_send_submission_emails', array( $order_id ), 'rar-wap' ) ) {
				return;
			}

			as_enqueue_async_action( 'rar_wap_send_submission_emails', array( $order_id ), 'rar-wap' );
			return;
		}

		$this->send_submission_emails( $order );
	}

	public function send_submission_emails( WC_Order $order ) {
		if ( 'yes' === $order->get_meta( '_rar_wap_submission_email_sent' ) ) {
			return;
		}

		$amount    = (float) $order->get_meta( '_rar_wap_required_amount' );
		$balance   = (float) $order->get_meta( '_rar_wap_balance_due' );
		$channel   = $order->get_meta( '_rar_wap_channel_label' );
		$reference = $order->get_meta( '_rar_wap_reference' );
		$payer     = $order->get_meta( '_rar_wap_payer' );
		$order_no  = $order->get_order_number();

		$admin_email = sanitize_email( $this->get_option( 'admin_email', '' ) );
		if ( ! $admin_email ) {
			$admin_email = sanitize_email( get_option( 'admin_email' ) );
		}

		$sent_any = false;

		if ( 'yes' === $this->get_option( 'admin_submission_email', 'yes' ) && $admin_email ) {
			$subject = sprintf( '[%s] Payment verification required — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
			$body    = $this->email_wrap(
				'Advance Payment Submitted',
				sprintf(
					'<p><strong>Order:</strong> #%1$s</p><p><strong>Customer:</strong> %2$s</p><p><strong>Channel:</strong> %3$s</p><p><strong>Claimed amount:</strong> %4$s</p><p><strong>Due on delivery:</strong> %5$s</p><p><strong>Payer reference:</strong> %6$s</p><p><strong>Transaction/reference:</strong> %7$s</p><p style="padding:12px;background:#fff8e6;border-left:4px solid #d97706"><strong>Action required:</strong> Verify the transfer from the official merchant/bank account before fulfilment. A submitted reference is not proof of payment.</p>',
					esc_html( $order_no ),
					esc_html( $order->get_formatted_billing_full_name() ),
					esc_html( $channel ),
					wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
					wp_kses_post( wc_price( $balance, array( 'currency' => $order->get_currency() ) ) ),
					esc_html( $payer ),
					esc_html( $reference )
				)
			);
			$sent_any = wp_mail( $admin_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) ) || $sent_any;
		}

		$customer_email = sanitize_email( $order->get_billing_email() );
		if ( 'yes' === $this->get_option( 'customer_submission_email', 'no' ) && $customer_email ) {
			$subject = sprintf( '[%s] Payment submission received — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
			$body    = $this->email_wrap(
				'Payment Submission Received',
				sprintf(
					'<p>Dear %1$s,</p><p>আপনার payment information আমরা পেয়েছি। আমাদের team transfer টি verify করবে। Verification complete হলে order status update জানানো হবে।</p><p><strong>Pay now:</strong> %2$s<br><strong>Due on delivery:</strong> %3$s<br><strong>Method:</strong> %4$s<br><strong>Reference:</strong> %5$s</p><p><strong>Security:</strong> Never share your PIN, password or OTP with anyone.</p>',
					esc_html( $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name() ),
					wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
					wp_kses_post( wc_price( $balance, array( 'currency' => $order->get_currency() ) ) ),
					esc_html( $channel ),
					esc_html( $reference )
				)
			);
			$sent_any = wp_mail( $customer_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) ) || $sent_any;
		}

		$order->update_meta_data( '_rar_wap_submission_email_sent', 'yes' );
		$order->update_meta_data( '_rar_wap_submission_email_sent_at', current_time( 'mysql' ) );
		$order->save();

		$this->log(
			'Submission notification workflow completed.',
			$sent_any ? 'info' : 'notice',
			array( 'order_id' => $order->get_id() )
		);
	}

	public function send_verification_email( WC_Order $order, $verified = true ) {
		$email = sanitize_email( $order->get_billing_email() );
		if ( ! $email ) {
			return false;
		}

		$order_no = $order->get_order_number();

		if ( $verified ) {
			$subject = sprintf( '[%s] Advance payment verified — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
			$body    = $this->email_wrap(
				'Payment Verified ✅',
				sprintf(
					'<p>Dear %1$s,</p><p>আপনার advance payment successfully verify হয়েছে। Thank you! আপনার order এখন পরবর্তী fulfilment step-এ যাবে।</p><p><strong>Verified amount:</strong> %2$s<br><strong>Due on delivery:</strong> %3$s</p>',
					esc_html( $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name() ),
					wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_required_amount' ), array( 'currency' => $order->get_currency() ) ) ),
					wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_balance_due' ), array( 'currency' => $order->get_currency() ) ) )
				)
			);
		} else {
			$subject = sprintf( '[%s] Payment verification needs attention — Order #%s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order_no );
			$body    = $this->email_wrap(
				'Payment Verification Needs Attention',
				sprintf(
					'<p>Dear %1$s,</p><p>দুঃখিত, আপনার দেওয়া payment reference এখনো verify করা যায়নি। Please check the transaction details and contact our support team with the correct reference.</p><p>For your security, never send a PIN, password or OTP.</p>',
					esc_html( $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name() )
				)
			);
		}

		return wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	private function email_wrap( $heading, $content ) {
		$site = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

		if ( class_exists( 'WC_Emails' ) && is_callable( array( 'WC_Emails', 'wrap_message' ) ) ) {
			return WC_Emails::wrap_message( esc_html( $heading ), $content );
		}

		return '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#222;line-height:1.6">'
			. '<div style="background:#0f8a6b;color:#fff;padding:20px 24px;border-radius:10px 10px 0 0"><h2 style="margin:0">' . esc_html( $heading ) . '</h2></div>'
			. '<div style="border:1px solid #e5e7eb;border-top:0;padding:24px">' . $content
			. '<hr style="border:0;border-top:1px solid #eee;margin:24px 0"><p style="font-size:12px;color:#666">' . $site . ' · Secure payment reference notice</p></div></div>';
	}

	public function log( $message, $level = 'info', $context = array() ) {
		if ( 'yes' !== $this->get_option( 'debug_logging', 'no' ) || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$context['source'] = 'rar-woo-advance-payment';

		if ( is_callable( array( $logger, $level ) ) ) {
			$logger->{$level}( $message, $context );
		} else {
			$logger->info( $message, $context );
		}
	}
}

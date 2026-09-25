<?php
/**
 * Plugin bootstrap: shared helpers, assets and cross-cutting hooks.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Plugin {

	const OPTION = 'woocommerce_rar_advance_payment_settings';

	/** @var array|null */
	private static $settings = null;

	public static function init() {
		RAR_WAP_Admin::init();
		RAR_WAP_Dashboard::init();
		RAR_WAP_Display::init();
		RAR_WAP_Proofs::init();
		RAR_WAP_Automation::init();
		RAR_WAP_REST::init();
		RAR_WAP_Privacy::init();
		RAR_WAP_Blocks_Loader::init();

		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register_gateway' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'enforce_required_mode' ), 90 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'rar_wap_send_submission_emails', array( __CLASS__, 'async_submission_emails' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_settings' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RAR_WAP_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ *
	 * Shared helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Raw saved settings (fast, safe in cron/REST where gateways are not loaded).
	 */
	public static function settings() {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, array() );
			self::$settings = is_array( $saved ) ? $saved : array();
		}
		return self::$settings;
	}

	public static function flush_settings() {
		self::$settings = null;
		RAR_WAP_I18n::reset();
	}

	/**
	 * @return RAR_WAP_Gateway|null
	 */
	public static function gateway() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		return ( isset( $gateways[ RAR_WAP_Order::GATEWAY_ID ] ) && $gateways[ RAR_WAP_Order::GATEWAY_ID ] instanceof RAR_WAP_Gateway )
			? $gateways[ RAR_WAP_Order::GATEWAY_ID ]
			: null;
	}

	public static function settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . RAR_WAP_Order::GATEWAY_ID );
	}

	public static function can_manage() {
		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function log( $message, $level = 'info', $context = array() ) {
		$settings = self::settings();
		if ( 'yes' !== ( $settings['debug_logging'] ?? 'no' ) || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger            = wc_get_logger();
		$context['source'] = 'rar-woo-advance-payment';
		$level             = in_array( $level, array( 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' ), true ) ? $level : 'info';
		$logger->log( $level, $message, $context );
	}

	/* ------------------------------------------------------------------ *
	 * Hooks
	 * ------------------------------------------------------------------ */

	public static function register_gateway( $methods ) {
		$methods[] = 'RAR_WAP_Gateway';
		return $methods;
	}

	/**
	 * Required mode: hide standard COD only when this gateway is genuinely usable.
	 * Fails safe: incomplete configuration always leaves COD untouched.
	 */
	public static function enforce_required_mode( $gateways ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! is_array( $gateways ) || ! isset( $gateways[ RAR_WAP_Order::GATEWAY_ID ], $gateways['cod'] ) ) {
			return $gateways;
		}

		$gateway = $gateways[ RAR_WAP_Order::GATEWAY_ID ];
		if ( ! $gateway instanceof RAR_WAP_Gateway || ! $gateway->is_visible_to_current_user() || ! $gateway->is_configured_for_checkout() ) {
			return $gateways;
		}

		if ( 'yes' !== $gateway->get_option( 'hide_cod_when_required', 'yes' ) ) {
			return $gateways;
		}

		if ( $gateway->is_required_for_total( $gateway->get_cart_total() ) && $gateway->get_amount_due_now() > 0 ) {
			unset( $gateways['cod'] );
		}

		return $gateways;
	}

	public static function async_submission_emails( $order_id ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( RAR_WAP_Order::is_rar_order( $order ) ) {
			RAR_WAP_Emails::submission( $order );
		}
	}

	public static function frontend_assets() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}

		$is_checkout = is_checkout() && ! is_order_received_page();
		$is_order    = is_order_received_page() || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) );

		if ( ! $is_checkout && ! $is_order ) {
			return;
		}

		wp_enqueue_style( 'rar-wap-checkout', RAR_WAP_URL . 'assets/css/checkout.css', array(), RAR_WAP_VERSION );

		if ( $is_checkout ) {
			wp_enqueue_script( 'rar-wap-checkout', RAR_WAP_URL . 'assets/js/checkout.js', array( 'jquery' ), RAR_WAP_VERSION, true );
			wp_localize_script( 'rar-wap-checkout', 'rarWapCheckout', self::checkout_js_strings() );
		}
	}

	public static function checkout_js_strings() {
		return array(
			'copied'       => RAR_WAP_I18n::t( 'copied' ),
			'phMobile'     => RAR_WAP_I18n::t( 'ph_mobile' ),
			'phAccount'    => RAR_WAP_I18n::t( 'ph_account' ),
			'phGeneric'    => RAR_WAP_I18n::t( 'ph_generic_payer' ),
			'phTrx'        => RAR_WAP_I18n::t( 'ph_trx' ),
			'phBankTrx'    => RAR_WAP_I18n::t( 'ph_bank_trx' ),
			'errMobile'    => RAR_WAP_I18n::t( 'err_mobile' ),
			'errTrx'       => RAR_WAP_I18n::t( 'err_trx' ),
			'validateMfs'  => 'no' !== ( self::settings()['validate_mobile'] ?? 'yes' ),
		);
	}

	public static function admin_assets( $hook ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable

		$screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id       = $screen ? (string) $screen->id : '';
		$is_payment_page = 'wc-settings' === $page && 'checkout' === $tab;
		$is_order_page   = false !== strpos( $screen_id, 'shop_order' ) || false !== strpos( $screen_id, 'wc-orders' );
		$is_dashboard    = RAR_WAP_Dashboard::SLUG === $page;

		if ( ! $is_payment_page && ! $is_order_page && ! $is_dashboard ) {
			return;
		}

		wp_enqueue_style( 'rar-wap-admin', RAR_WAP_URL . 'assets/css/admin.css', array(), RAR_WAP_VERSION );

		if ( $is_payment_page ) {
			wp_enqueue_media();
			wp_enqueue_script( 'rar-wap-admin', RAR_WAP_URL . 'assets/js/admin.js', array( 'jquery' ), RAR_WAP_VERSION, true );
		}

		if ( $is_order_page || $is_dashboard ) {
			wp_enqueue_script( 'rar-wap-orders', RAR_WAP_URL . 'assets/js/admin-orders.js', array( 'jquery' ), RAR_WAP_VERSION, true );
			wp_localize_script(
				'rar-wap-orders',
				'rarWapAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'rar_wap_admin' ),
					'i18n'    => array(
						'confirmVerify' => __( 'Confirm you have checked this transfer in the official merchant/bank account?', 'rar-woo-advance-payment' ),
						'confirmReject' => __( 'Mark this payment as unverified and notify the customer?', 'rar-woo-advance-payment' ),
						'confirmReset'  => __( 'Undo this decision and move the payment back to "Awaiting verification"?', 'rar-woo-advance-payment' ),
						'confirmBulk'   => __( 'Verify ALL selected advance payments? Only do this after checking every transfer.', 'rar-woo-advance-payment' ),
						'amountPrompt'  => __( 'Received amount (leave as-is if it matches):', 'rar-woo-advance-payment' ),
						'reasonPrompt'  => __( 'Reason / note for the customer (optional):', 'rar-woo-advance-payment' ),
						'working'       => __( 'Working…', 'rar-woo-advance-payment' ),
						'failed'        => __( 'Action failed. Please reload and try again.', 'rar-woo-advance-payment' ),
						'webhookOk'     => __( 'Test webhook sent.', 'rar-woo-advance-payment' ),
					),
				)
			);
		}
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Settings', 'rar-woo-advance-payment' ) . '</a>',
			'<a href="' . esc_url( RAR_WAP_Dashboard::url() ) . '">' . esc_html__( 'Dashboard', 'rar-woo-advance-payment' ) . '</a>'
		);
		return $links;
	}

	public static function row_meta( $links, $file ) {
		if ( plugin_basename( RAR_WAP_FILE ) === $file ) {
			$links[] = '<a href="https://github.com/ruhulaminrevens/RAR-Woo-Payment-Gateway" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'rar-woo-advance-payment' ) . '</a>';
		}
		return $links;
	}

	/* ------------------------------------------------------------------ *
	 * Lifecycle
	 * ------------------------------------------------------------------ */

	public static function activate() {
		update_option( 'rar_wap_version', RAR_WAP_VERSION, false );
		if ( class_exists( 'RAR_WAP_Proofs' ) ) {
			RAR_WAP_Proofs::protect_directory();
		}
	}

	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'rar_wap_maintenance', array(), 'rar-wap' );
		}
		delete_transient( 'rar_wap_pending_count' );
	}
}

/**
 * Public helper: amount the courier/rider should collect for an order.
 *
 * @param WC_Order|int $order
 */
function rar_wap_get_collectable_amount( $order ) {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
	return $order instanceof WC_Order ? RAR_WAP_Order::collectable_amount( $order ) : 0.0;
}

/**
 * Public helper: portable advance-payment snapshot (or null for other gateways).
 *
 * @param WC_Order|int $order
 * @return array|null
 */
function rar_wap_get_payment( $order ) {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
	return RAR_WAP_Order::is_rar_order( $order ) ? RAR_WAP_Order::snapshot( $order ) : null;
}

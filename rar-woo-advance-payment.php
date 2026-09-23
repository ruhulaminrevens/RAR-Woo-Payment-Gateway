<?php
/**
 * Plugin Name: RAR Woo Advance Payment Gateway
 * Plugin URI: https://github.com/ruhulaminrevens/RAR-Woo-Payment-Gateway
 * Description: Production-focused manual advance/full payment gateway for WooCommerce with Bangla QR, bKash, Nagad, Rocket and NPSB bank transfer, safe COD enforcement, manual verification, and optimized notifications.
 * Version: 1.1.1
 * Author: Ruhul Amin Revens
 * Text Domain: rar-woo-advance-payment
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.5
 * WC tested up to: 11.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAR_WAP_VERSION', '1.1.1' );
define( 'RAR_WAP_FILE', __FILE__ );
define( 'RAR_WAP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAR_WAP_URL', plugin_dir_url( __FILE__ ) );

function rar_wap_settings_url() {
	return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rar_advance_payment' );
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'rar-woo-advance-payment', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p><strong>RAR Woo Advance Payment Gateway</strong> requires WooCommerce to be active.</p></div>';
					}
				}
			);
			return;
		}

		require_once RAR_WAP_DIR . 'includes/class-rar-wap-gateway.php';
		require_once RAR_WAP_DIR . 'includes/class-rar-wap-admin.php';
		require_once RAR_WAP_DIR . 'includes/class-rar-wap-display.php';

		RAR_WAP_Admin::init();
		RAR_WAP_Display::init();

		add_filter(
			'woocommerce_payment_gateways',
			static function ( $methods ) {
				$methods[] = 'RAR_WAP_Gateway';
				return $methods;
			}
		);
	},
	20
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( rar_wap_settings_url() ) . '">' . esc_html__( 'Settings', 'rar-woo-advance-payment' ) . '</a>'
		);
		return $links;
	}
);

add_filter(
	'plugin_row_meta',
	static function ( $links, $file ) {
		if ( plugin_basename( RAR_WAP_FILE ) !== $file ) {
			return $links;
		}

		$links[] = '<a href="https://github.com/ruhulaminrevens/RAR-Woo-Payment-Gateway" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'rar-woo-advance-payment' ) . '</a>';
		return $links;
	},
	10,
	2
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			wp_enqueue_style(
				'rar-wap-checkout',
				RAR_WAP_URL . 'assets/css/checkout.css',
				array(),
				RAR_WAP_VERSION
			);
			wp_enqueue_script(
				'rar-wap-checkout',
				RAR_WAP_URL . 'assets/js/checkout.js',
				array( 'jquery' ),
				RAR_WAP_VERSION,
				true
			);
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		$screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id       = $screen && isset( $screen->id ) ? (string) $screen->id : '';
		$is_payment_page = 'wc-settings' === $page && 'checkout' === $tab;
		$is_order_page   = false !== strpos( $screen_id, 'shop_order' ) || false !== strpos( $screen_id, 'wc-orders' );

		if ( ! $is_payment_page && ! $is_order_page ) {
			return;
		}

		wp_enqueue_style(
			'rar-wap-admin',
			RAR_WAP_URL . 'assets/css/admin.css',
			array(),
			RAR_WAP_VERSION
		);

		if ( $is_payment_page ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'rar-wap-admin',
				RAR_WAP_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				RAR_WAP_VERSION,
				true
			);
		}
	}
);

/**
 * Async email handler.
 *
 * WooCommerce includes Action Scheduler. When available, payment-submission emails
 * are queued after checkout so Place Order is not held up by remote SMTP latency.
 */
add_action(
	'rar_wap_send_submission_emails',
	static function ( $order_id ) {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order || 'rar_advance_payment' !== $order->get_payment_method() ) {
			return;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $gateways['rar_advance_payment'] ) || ! $gateways['rar_advance_payment'] instanceof RAR_WAP_Gateway ) {
			return;
		}

		$gateways['rar_advance_payment']->send_submission_emails( $order );
	}
);

/**
 * Required mode: hide standard COD only when this gateway is genuinely usable.
 * Fails safe: incomplete configuration always leaves COD untouched.
 */
add_filter(
	'woocommerce_available_payment_gateways',
	static function ( $gateways ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}

		if ( ! isset( $gateways['rar_advance_payment'] ) ) {
			return $gateways;
		}

		$gateway = $gateways['rar_advance_payment'];
		if ( ! $gateway instanceof RAR_WAP_Gateway ) {
			return $gateways;
		}

		if ( $gateway->is_safe_test_mode() && ! current_user_can( 'manage_woocommerce' ) ) {
			return $gateways;
		}

		if ( $gateway->is_force_required() && $gateway->is_configured_for_checkout() && $gateway->get_amount_due_now() > 0 ) {
			if ( 'yes' === $gateway->get_option( 'hide_cod_when_required', 'yes' ) ) {
				unset( $gateways['cod'] );
			}
		}

		return $gateways;
	},
	90
);

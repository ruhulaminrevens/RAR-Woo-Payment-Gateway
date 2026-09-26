<?php
/**
 * Plugin Name: RAR Woo Advance Payment Gateway
 * Plugin URI: https://github.com/ruhulaminrevens/RAR-Woo-Payment-Gateway
 * Description: Advance/full payment gateway for WooCommerce (Bangladesh) — Bangla QR, bKash, Nagad, Rocket, Upay, NPSB bank transfer and custom channels, with a verification dashboard, audit trail, customer correction and screenshot upload, Checkout Blocks support, REST API, webhooks and automation.
 * Version: 2.1.0
 * Author: Ruhul Amin Revens
 * Text Domain: rar-woo-advance-payment
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 8.5
 * WC tested up to: 11.1
 * License: GPLv2 or later
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAR_WAP_VERSION', '2.1.0' );
define( 'RAR_WAP_FILE', __FILE__ );
define( 'RAR_WAP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAR_WAP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Back-compat helper (v1.x).
 */
function rar_wap_settings_url() {
	return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rar_advance_payment' );
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
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

		$files = array(
			'class-rar-wap-i18n.php',
			'class-rar-wap-order.php',
			'class-rar-wap-query.php',
			'class-rar-wap-emails.php',
			'class-rar-wap-gateway.php',
			'class-rar-wap-plugin.php',
			'class-rar-wap-admin.php',
			'class-rar-wap-dashboard.php',
			'class-rar-wap-display.php',
			'class-rar-wap-proofs.php',
			'class-rar-wap-automation.php',
			'class-rar-wap-rest.php',
			'class-rar-wap-privacy.php',
			'class-rar-wap-blocks-loader.php',
		);
		foreach ( $files as $file ) {
			require_once RAR_WAP_DIR . 'includes/' . $file;
		}

		RAR_WAP_Plugin::init();

		if ( get_option( 'rar_wap_version' ) !== RAR_WAP_VERSION ) {
			RAR_WAP_Plugin::activate();
		}
	},
	20
);

register_activation_hook(
	__FILE__,
	static function () {
		update_option( 'rar_wap_version', '0', false ); // Triggers upgrade routine on next load.
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'rar_wap_maintenance', array(), 'rar-wap' );
		}
		delete_transient( 'rar_wap_pending_count' );
	}
);

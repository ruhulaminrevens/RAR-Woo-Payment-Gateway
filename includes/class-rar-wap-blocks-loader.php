<?php
/**
 * Registers Checkout Blocks support and Store API cart data (safe no-op on
 * sites without WooCommerce Blocks).
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Blocks_Loader {

	public static function init() {
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::loaded();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'loaded' ) );
		}
	}

	public static function loaded() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
			require_once RAR_WAP_DIR . 'includes/class-rar-wap-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				static function ( $registry ) {
					$registry->register( new RAR_WAP_Blocks() );
				}
			);
		}

		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			$endpoint = class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartSchema' )
				? \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER
				: 'cart';

			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => $endpoint,
					'namespace'       => 'rar-wap',
					'data_callback'   => array( __CLASS__, 'cart_data' ),
					'schema_callback' => array( __CLASS__, 'cart_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	private static function text( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Server-calculated advance for the current cart (single source of truth).
	 */
	public static function cart_data() {
		$gateway = RAR_WAP_Plugin::gateway();
		if ( ! $gateway || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array(
				'amount_due'       => '0',
				'balance_due'      => '0',
				'amount_due_text'  => '',
				'balance_due_text' => '',
			);
		}

		$amount  = $gateway->get_amount_due_now();
		$balance = $gateway->get_balance_due();

		return array(
			'amount_due'       => wc_format_decimal( $amount ),
			'balance_due'      => wc_format_decimal( $balance ),
			'amount_due_text'  => self::text( $amount ),
			'balance_due_text' => self::text( $balance ),
		);
	}

	public static function cart_schema() {
		$string = array(
			'type'     => 'string',
			'context'  => array( 'view', 'edit' ),
			'readonly' => true,
		);
		return array(
			'amount_due'       => $string + array( 'description' => __( 'Advance amount due now.', 'rar-woo-advance-payment' ) ),
			'balance_due'      => $string + array( 'description' => __( 'Balance due on delivery.', 'rar-woo-advance-payment' ) ),
			'amount_due_text'  => $string + array( 'description' => __( 'Formatted advance amount.', 'rar-woo-advance-payment' ) ),
			'balance_due_text' => $string + array( 'description' => __( 'Formatted balance.', 'rar-woo-advance-payment' ) ),
		);
	}
}

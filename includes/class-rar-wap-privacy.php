<?php
/**
 * WordPress personal-data exporter / eraser for advance-payment details.
 *
 * Erasure follows the WooCommerce "Remove personal data from orders" setting,
 * because payment references may be needed for accounting records.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['rar-wap'] = array(
			'exporter_friendly_name' => __( 'Advance payment details', 'rar-woo-advance-payment' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['rar-wap'] = array(
			'eraser_friendly_name' => __( 'Advance payment details', 'rar-woo-advance-payment' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	private static function orders( $email, $page ) {
		return wc_get_orders(
			array(
				'billing_email'  => $email,
				'payment_method' => RAR_WAP_Order::GATEWAY_ID,
				'limit'          => 20,
				'page'           => max( 1, (int) $page ),
				'type'           => 'shop_order',
			)
		);
	}

	public static function export( $email, $page = 1 ) {
		$orders = self::orders( $email, $page );
		$data   = array();

		foreach ( $orders as $order ) {
			if ( ! RAR_WAP_Order::has_submission( $order ) ) {
				continue;
			}
			$data[] = array(
				'group_id'    => 'rar-wap',
				'group_label' => __( 'Advance payments', 'rar-woo-advance-payment' ),
				'item_id'     => 'rar-wap-' . $order->get_id(),
				'data'        => array(
					array( 'name' => __( 'Order', 'rar-woo-advance-payment' ), 'value' => $order->get_order_number() ),
					array( 'name' => __( 'Channel', 'rar-woo-advance-payment' ), 'value' => (string) $order->get_meta( '_rar_wap_channel_label' ) ),
					array( 'name' => __( 'Paid from', 'rar-woo-advance-payment' ), 'value' => (string) $order->get_meta( '_rar_wap_payer' ) ),
					array( 'name' => __( 'Transaction ID', 'rar-woo-advance-payment' ), 'value' => (string) $order->get_meta( '_rar_wap_reference' ) ),
					array( 'name' => __( 'Status', 'rar-woo-advance-payment' ), 'value' => RAR_WAP_Order::status_label( RAR_WAP_Order::get_status( $order ) ) ),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $orders ) < 20,
		);
	}

	public static function erase( $email, $page = 1 ) {
		$orders   = self::orders( $email, $page );
		$allowed  = 'yes' === get_option( 'woocommerce_erasure_request_removes_order_data', 'no' );
		$removed  = false;
		$retained = false;
		$messages = array();

		foreach ( $orders as $order ) {
			if ( ! RAR_WAP_Order::has_submission( $order ) ) {
				continue;
			}
			if ( ! $allowed ) {
				$retained = true;
				continue;
			}
			$order->update_meta_data( '_rar_wap_payer', wp_privacy_anonymize_data( 'text' ) );
			RAR_WAP_Proofs::delete_for_order( $order->get_id() );
			$order->delete_meta_data( '_rar_wap_proof_file' );
			$order->save();
			$removed = true;
		}

		if ( $retained ) {
			$messages[] = __( 'Advance-payment details were retained because WooCommerce is set to keep personal data in orders (accounting records).', 'rar-woo-advance-payment' );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => count( $orders ) < 20,
		);
	}

	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'RAR Woo Advance Payment Gateway',
			'<p>' . esc_html__( 'When you pay an advance by bKash, Nagad, Rocket, Upay, Bangla QR or bank transfer, we store the number/account you paid from, the Transaction ID and (if you upload one) a payment screenshot with your order, so our staff can match your transfer. We never ask for or store your PIN, password or OTP.', 'rar-woo-advance-payment' ) . '</p>'
		);
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_post_rar_wap_verify', array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_rar_wap_reject', array( __CLASS__, 'handle_reject' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_legacy_column' ), 25 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_legacy_column' ), 25, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_hpos_column' ), 25 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_column' ), 25, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	public static function add_meta_box() {
		$screen = 'shop_order';

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			try {
				$screen = wc_get_page_screen_id( 'shop-order' );
			} catch ( Throwable $e ) {
				$screen = 'shop_order';
			}
		}

		add_meta_box(
			'rar-wap-payment-box',
			__( 'Advance Payment Verification', 'rar-woo-advance-payment' ),
			array( __CLASS__, 'render_meta_box' ),
			$screen,
			'side',
			'high'
		);

		if ( 'shop_order' !== $screen ) {
			add_meta_box(
				'rar-wap-payment-box',
				__( 'Advance Payment Verification', 'rar-woo-advance-payment' ),
				array( __CLASS__, 'render_meta_box' ),
				'shop_order',
				'side',
				'high'
			);
		}
	}

	private static function get_order_from_screen( $object ) {
		if ( $object instanceof WC_Order ) {
			return $object;
		}
		if ( $object instanceof WP_Post ) {
			return wc_get_order( $object->ID );
		}
		if ( is_numeric( $object ) ) {
			return wc_get_order( absint( $object ) );
		}
		return false;
	}

	private static function status_label( $status ) {
		$labels = array(
			'submitted'  => __( 'Awaiting verification', 'rar-woo-advance-payment' ),
			'verified'   => __( 'Verified', 'rar-woo-advance-payment' ),
			'unverified' => __( 'Needs attention', 'rar-woo-advance-payment' ),
		);
		return $labels[ $status ] ?? ucfirst( (string) $status );
	}

	private static function status_class( $status ) {
		$allowed = array( 'submitted', 'verified', 'unverified' );
		return in_array( $status, $allowed, true ) ? $status : 'submitted';
	}

	public static function render_meta_box( $object ) {
		$order = self::get_order_from_screen( $object );

		if ( ! $order || 'rar_advance_payment' !== $order->get_payment_method() ) {
			echo '<p class="rar-wap-admin-muted">' . esc_html__( 'This order does not use RAR Advance Payment.', 'rar-woo-advance-payment' ) . '</p>';
			return;
		}

		$status       = $order->get_meta( '_rar_wap_status' ) ?: 'submitted';
		$amount       = (float) $order->get_meta( '_rar_wap_required_amount' );
		$balance      = (float) $order->get_meta( '_rar_wap_balance_due' );
		$channel      = $order->get_meta( '_rar_wap_channel_label' );
		$payer        = $order->get_meta( '_rar_wap_payer' );
		$reference    = $order->get_meta( '_rar_wap_reference' );
		$submitted_at = $order->get_meta( '_rar_wap_submitted_at' );
		$verified_at  = $order->get_meta( '_rar_wap_verified_at' );
		$verified_by  = absint( $order->get_meta( '_rar_wap_verified_by' ) );

		$verify_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=rar_wap_verify&order_id=' . $order->get_id() ),
			'rar_wap_verify_' . $order->get_id()
		);
		$reject_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=rar_wap_reject&order_id=' . $order->get_id() ),
			'rar_wap_reject_' . $order->get_id()
		);

		echo '<div class="rar-wap-admin-card">';
		echo '<div class="rar-wap-admin-status ' . esc_attr( self::status_class( $status ) ) . '"><span></span><strong>' . esc_html( self::status_label( $status ) ) . '</strong></div>';

		echo '<dl class="rar-wap-admin-details">';
		echo '<div><dt>' . esc_html__( 'Channel', 'rar-woo-advance-payment' ) . '</dt><dd>' . esc_html( $channel ?: '—' ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Pay now', 'rar-woo-advance-payment' ) . '</dt><dd><strong>' . wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ) . '</strong></dd></div>';
		echo '<div><dt>' . esc_html__( 'Due on delivery', 'rar-woo-advance-payment' ) . '</dt><dd>' . wp_kses_post( wc_price( $balance, array( 'currency' => $order->get_currency() ) ) ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Payer reference', 'rar-woo-advance-payment' ) . '</dt><dd><code>' . esc_html( $payer ?: '—' ) . '</code></dd></div>';
		echo '<div><dt>' . esc_html__( 'Transaction ID', 'rar-woo-advance-payment' ) . '</dt><dd><code>' . esc_html( $reference ?: '—' ) . '</code></dd></div>';
		if ( $submitted_at ) {
			echo '<div><dt>' . esc_html__( 'Submitted', 'rar-woo-advance-payment' ) . '</dt><dd>' . esc_html( $submitted_at ) . '</dd></div>';
		}
		echo '</dl>';

		if ( 'verified' === $status ) {
			$user = $verified_by ? get_user_by( 'id', $verified_by ) : false;
			echo '<div class="rar-wap-admin-confirmed">✓ <strong>' . esc_html__( 'Payment verified', 'rar-woo-advance-payment' ) . '</strong>';
			if ( $verified_at ) {
				echo '<br><small>' . esc_html( $verified_at );
				if ( $user ) {
					echo ' · ' . esc_html( $user->display_name );
				}
				echo '</small>';
			}
			echo '</div>';
		} else {
			echo '<div class="rar-wap-admin-warning"><strong>' . esc_html__( 'Verify externally first', 'rar-woo-advance-payment' ) . '</strong><br>';
			echo esc_html__( 'A submitted Transaction ID is not proof of payment. Confirm it in the official merchant/bank account before clicking Verify.', 'rar-woo-advance-payment' );
			echo '</div>';

			echo '<div class="rar-wap-admin-actions">';
			echo '<a class="button button-primary" href="' . esc_url( $verify_url ) . '" onclick="return confirm('' . esc_js( __( 'Confirm that you have verified this transfer in the official payment account?', 'rar-woo-advance-payment' ) ) . '');">' . esc_html__( 'Verify Payment', 'rar-woo-advance-payment' ) . '</a>';
			echo '<a class="button" href="' . esc_url( $reject_url ) . '" onclick="return confirm('' . esc_js( __( 'Mark this payment reference as unverified and notify the customer?', 'rar-woo-advance-payment' ) ) . '');">' . esc_html__( 'Mark Unverified', 'rar-woo-advance-payment' ) . '</a>';
			echo '</div>';
		}

		echo '</div>';
	}

	public static function handle_verify() {
		self::assert_admin_request();

		$order_id = absint( $_GET['order_id'] ?? 0 );
		check_admin_referer( 'rar_wap_verify_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order || 'rar_advance_payment' !== $order->get_payment_method() ) {
			wp_die( esc_html__( 'Advance-payment order not found.', 'rar-woo-advance-payment' ) );
		}

		if ( 'verified' === $order->get_meta( '_rar_wap_status' ) ) {
			self::redirect_with_notice( $order, 'already_verified' );
		}

		$user = wp_get_current_user();

		$order->update_meta_data( '_rar_wap_status', 'verified' );
		$order->update_meta_data( '_rar_wap_verified_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rar_wap_verified_by', get_current_user_id() );
		$order->save();

		$gateway       = self::gateway_instance();
		$target_status = $gateway ? $gateway->get_option( 'after_verify_status', 'processing' ) : 'processing';

		$note = sprintf(
			'Advance payment manually verified by %s.',
			$user && $user->exists() ? $user->display_name : 'administrator'
		);

		if ( $target_status && ! $order->has_status( $target_status ) ) {
			$order->update_status( $target_status, $note );
		} else {
			$order->add_order_note( $note );
		}

		if ( $gateway ) {
			$sent = $gateway->send_verification_email( $order, true );
			$gateway->log(
				'Admin verified advance payment.',
				'info',
				array(
					'order_id'   => $order_id,
					'email_sent' => (bool) $sent,
				)
			);
		}

		self::redirect_with_notice( $order, 'verified' );
	}

	public static function handle_reject() {
		self::assert_admin_request();

		$order_id = absint( $_GET['order_id'] ?? 0 );
		check_admin_referer( 'rar_wap_reject_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order || 'rar_advance_payment' !== $order->get_payment_method() ) {
			wp_die( esc_html__( 'Advance-payment order not found.', 'rar-woo-advance-payment' ) );
		}

		if ( 'unverified' === $order->get_meta( '_rar_wap_status' ) ) {
			self::redirect_with_notice( $order, 'already_unverified' );
		}

		$user = wp_get_current_user();

		$order->update_meta_data( '_rar_wap_status', 'unverified' );
		$order->update_meta_data( '_rar_wap_rejected_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rar_wap_rejected_by', get_current_user_id() );
		$order->save();

		$order->add_order_note(
			sprintf(
				'Advance payment marked unverified by %s. Customer notification requested.',
				$user && $user->exists() ? $user->display_name : 'administrator'
			)
		);

		$gateway = self::gateway_instance();
		if ( $gateway ) {
			$sent = $gateway->send_verification_email( $order, false );
			$gateway->log(
				'Admin marked advance payment unverified.',
				'notice',
				array(
					'order_id'   => $order_id,
					'email_sent' => (bool) $sent,
				)
			);
		}

		self::redirect_with_notice( $order, 'unverified' );
	}

	private static function assert_admin_request() {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this order.', 'rar-woo-advance-payment' ) );
		}
	}

	private static function redirect_with_notice( WC_Order $order, $notice ) {
		$url = add_query_arg( 'rar_wap_notice', sanitize_key( $notice ), $order->get_edit_order_url() );
		wp_safe_redirect( $url );
		exit;
	}

	public static function admin_notices() {
		if ( empty( $_GET['rar_wap_notice'] ) ) {
			return;
		}

		$key = sanitize_key( wp_unslash( $_GET['rar_wap_notice'] ) );
		$messages = array(
			'verified'           => array( 'success', __( 'Advance payment verified. Order status and customer notification were processed.', 'rar-woo-advance-payment' ) ),
			'unverified'         => array( 'warning', __( 'Payment marked unverified. The customer notification was processed.', 'rar-woo-advance-payment' ) ),
			'already_verified'   => array( 'info', __( 'This payment was already verified. No duplicate verification email was sent.', 'rar-woo-advance-payment' ) ),
			'already_unverified' => array( 'info', __( 'This payment was already marked unverified. No duplicate notification was sent.', 'rar-woo-advance-payment' ) ),
		);

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $key ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private static function gateway_instance() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return false;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		return ( isset( $gateways['rar_advance_payment'] ) && $gateways['rar_advance_payment'] instanceof RAR_WAP_Gateway )
			? $gateways['rar_advance_payment']
			: false;
	}

	public static function add_legacy_column( $columns ) {
		$columns['rar_wap_payment'] = __( 'Advance', 'rar-woo-advance-payment' );
		return $columns;
	}

	public static function render_legacy_column( $column, $post_id ) {
		if ( 'rar_wap_payment' === $column ) {
			self::render_column_value( wc_get_order( $post_id ) );
		}
	}

	public static function add_hpos_column( $columns ) {
		$columns['rar_wap_payment'] = __( 'Advance', 'rar-woo-advance-payment' );
		return $columns;
	}

	public static function render_hpos_column( $column, $order ) {
		if ( 'rar_wap_payment' === $column ) {
			self::render_column_value( self::get_order_from_screen( $order ) );
		}
	}

	private static function render_column_value( $order ) {
		if ( ! $order || 'rar_advance_payment' !== $order->get_payment_method() ) {
			echo '—';
			return;
		}

		$status  = $order->get_meta( '_rar_wap_status' ) ?: 'submitted';
		$amount  = (float) $order->get_meta( '_rar_wap_required_amount' );
		$balance = (float) $order->get_meta( '_rar_wap_balance_due' );

		echo '<span class="rar-wap-column-status ' . esc_attr( self::status_class( $status ) ) . '">' . esc_html( self::status_label( $status ) ) . '</span>';
		echo '<small class="rar-wap-column-money">' . wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ) . ' · ' . esc_html__( 'due', 'rar-woo-advance-payment' ) . ' ' . wp_kses_post( wc_price( $balance, array( 'currency' => $order->get_currency() ) ) ) . '</small>';
	}
}

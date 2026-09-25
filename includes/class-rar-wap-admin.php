<?php
/**
 * Order-screen tools: verification panel, AJAX actions, list column, filter
 * and bulk verification.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'wp_ajax_rar_wap_order_action', array( __CLASS__, 'ajax_order_action' ) );

		// v1.x link-based actions (kept for bookmarks/e-mail links).
		add_action( 'admin_post_rar_wap_verify', array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_rar_wap_reject', array( __CLASS__, 'handle_reject' ) );

		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ), 25 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_legacy_column' ), 25, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_column' ), 25 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_column' ), 25, 2 );

		add_action( 'restrict_manage_posts', array( __CLASS__, 'legacy_filter_dropdown' ), 25 );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( __CLASS__, 'filter_dropdown' ), 25 );
		add_filter( 'request', array( __CLASS__, 'legacy_filter_query' ) );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( __CLASS__, 'hpos_filter_query' ) );

		add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'handle_bulk' ), 10, 3 );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Meta box
	 * ------------------------------------------------------------------ */

	public static function add_meta_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			try {
				$screens[] = wc_get_page_screen_id( 'shop-order' );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}

		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box(
				'rar-wap-payment-box',
				__( 'Advance Payment Verification', 'rar-woo-advance-payment' ),
				array( __CLASS__, 'render_meta_box' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	private static function order_from( $object ) {
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

	public static function render_meta_box( $object ) {
		$order = self::order_from( $object );

		if ( ! RAR_WAP_Order::is_rar_order( $order ) ) {
			echo '<p class="rar-wap-admin-muted">' . esc_html__( 'This order does not use RAR Advance Payment.', 'rar-woo-advance-payment' ) . '</p>';
			return;
		}

		echo '<div class="rar-wap-panel" data-order-id="' . esc_attr( (string) $order->get_id() ) . '">';
		echo self::panel_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		echo '</div>';
	}

	private static function money( WC_Order $order, $amount ) {
		return wp_kses_post( wc_price( (float) $amount, array( 'currency' => $order->get_currency() ) ) );
	}

	private static function row( $label, $value_html ) {
		return '<div><dt>' . esc_html( $label ) . '</dt><dd>' . $value_html . '</dd></div>';
	}

	private static function meta_or_dash( WC_Order $order, $key ) {
		$value = (string) $order->get_meta( $key );
		return '' === $value ? '—' : $value;
	}

	/**
	 * Full panel markup (also returned by AJAX after each action).
	 */
	public static function panel_html( WC_Order $order ) {
		if ( ! RAR_WAP_Order::has_submission( $order ) ) {
			return '<p class="rar-wap-admin-muted">' . esc_html__( 'No payment details submitted yet (e.g. order awaiting payment on the order-pay page).', 'rar-woo-advance-payment' ) . '</p>';
		}

		$status    = RAR_WAP_Order::get_status( $order );
		$requested = RAR_WAP_Order::requested_amount( $order );

		$html  = '<div class="rar-wap-admin-card">';
		$html .= '<div class="rar-wap-admin-status ' . esc_attr( $status ) . '"><span></span><strong>' . esc_html( RAR_WAP_Order::status_label( $status ) ) . '</strong></div>';

		$duplicate = absint( $order->get_meta( '_rar_wap_duplicate_of' ) );
		if ( $duplicate ) {
			$dup_order = wc_get_order( $duplicate );
			$link      = $dup_order ? '<a href="' . esc_url( $dup_order->get_edit_order_url() ) . '">#' . esc_html( $dup_order->get_order_number() ) . '</a>' : '#' . $duplicate;
			/* translators: %s: order link */
			$html .= '<div class="rar-wap-admin-danger">⚠ ' . sprintf( esc_html__( 'Same Transaction ID was also submitted on order %s. Check carefully.', 'rar-woo-advance-payment' ), $link ) . '</div>';
		}

		$html .= '<dl class="rar-wap-admin-details">';
		$html .= self::row( __( 'Channel', 'rar-woo-advance-payment' ), esc_html( self::meta_or_dash( $order, '_rar_wap_channel_label' ) ) );
		$dest  = (string) $order->get_meta( '_rar_wap_destination' );
		if ( '' !== $dest ) {
			$html .= self::row( __( 'Paid to', 'rar-woo-advance-payment' ), '<code>' . esc_html( $dest ) . '</code>' );
		}
		$html .= self::row( __( 'Requested', 'rar-woo-advance-payment' ), '<strong>' . self::money( $order, $requested ) . '</strong>' );
		if ( 'verified' === $status ) {
			$html .= self::row( __( 'Received', 'rar-woo-advance-payment' ), '<strong>' . self::money( $order, $order->get_meta( '_rar_wap_required_amount' ) ) . '</strong>' );
		}
		$html .= self::row( __( 'Collect on delivery', 'rar-woo-advance-payment' ), self::money( $order, RAR_WAP_Order::collectable_amount( $order ) ) );
		$html .= self::row( __( 'Paid from', 'rar-woo-advance-payment' ), '<code>' . esc_html( self::meta_or_dash( $order, '_rar_wap_payer' ) ) . '</code>' );
		$html .= self::row( __( 'Transaction ID', 'rar-woo-advance-payment' ), '<code>' . esc_html( self::meta_or_dash( $order, '_rar_wap_reference' ) ) . '</code>' );
		$html .= self::row( __( 'Submitted', 'rar-woo-advance-payment' ), esc_html( self::meta_or_dash( $order, '_rar_wap_submitted_at' ) ) );
		$resubs = absint( $order->get_meta( '_rar_wap_resubmission_count' ) );
		if ( $resubs ) {
			$html .= self::row( __( 'Corrections', 'rar-woo-advance-payment' ), esc_html( (string) $resubs ) );
		}
		$html .= '</dl>';

		$html .= RAR_WAP_Proofs::admin_preview_html( $order );

		if ( 'unverified' === $status && $order->get_meta( '_rar_wap_reject_reason' ) ) {
			$html .= '<div class="rar-wap-admin-warning"><strong>' . esc_html__( 'Rejected:', 'rar-woo-advance-payment' ) . '</strong> ' . esc_html( (string) $order->get_meta( '_rar_wap_reject_reason' ) ) . '</div>';
		}

		if ( 'verified' === $status ) {
			$user  = get_user_by( 'id', absint( $order->get_meta( '_rar_wap_verified_by' ) ) );
			$html .= '<div class="rar-wap-admin-confirmed">✓ <strong>' . esc_html__( 'Payment verified', 'rar-woo-advance-payment' ) . '</strong><br><small>'
				. esc_html( (string) $order->get_meta( '_rar_wap_verified_at' ) ) . ( $user ? ' · ' . esc_html( $user->display_name ) : '' ) . '</small></div>';
			$html .= '<div class="rar-wap-admin-actions"><button type="button" class="button rar-wap-act" data-op="reset">' . esc_html__( 'Undo verification', 'rar-woo-advance-payment' ) . '</button></div>';
		} else {
			$html .= '<div class="rar-wap-admin-warning"><strong>' . esc_html__( 'Verify externally first', 'rar-woo-advance-payment' ) . '</strong><br>'
				. esc_html__( 'A submitted Transaction ID is not proof of payment. Confirm it in the official merchant/bank account before verifying.', 'rar-woo-advance-payment' ) . '</div>';

			$html .= '<div class="rar-wap-admin-form">';
			$html .= '<label>' . esc_html__( 'Received amount', 'rar-woo-advance-payment' ) . '<input type="number" step="0.01" min="0" class="rar-wap-amount" value="' . esc_attr( (string) $requested ) . '"></label>';
			$html .= '<label>' . esc_html__( 'Internal note (optional)', 'rar-woo-advance-payment' ) . '<input type="text" class="rar-wap-note" maxlength="200"></label>';
			$html .= '<button type="button" class="button button-primary rar-wap-act" data-op="verify">' . esc_html__( 'Verify payment', 'rar-woo-advance-payment' ) . '</button>';
			$html .= '</div>';

			if ( 'submitted' === $status ) {
				$html .= '<details class="rar-wap-reject-box"><summary>' . esc_html__( 'Cannot find this transfer?', 'rar-woo-advance-payment' ) . '</summary>';
				$html .= '<label>' . esc_html__( 'Reason', 'rar-woo-advance-payment' ) . '<select class="rar-wap-reason">';
				foreach ( array_keys( RAR_WAP_Order::REJECT_REASONS ) as $key ) {
					$html .= '<option value="' . esc_attr( $key ) . '">' . esc_html( RAR_WAP_Order::reject_reason_label( $key ) ) . '</option>';
				}
				$html .= '</select></label>';
				$html .= '<label>' . esc_html__( 'Message to customer (optional)', 'rar-woo-advance-payment' ) . '<input type="text" class="rar-wap-reject-note" maxlength="200"></label>';
				$html .= '<button type="button" class="button rar-wap-act" data-op="reject">' . esc_html__( 'Mark unverified & notify', 'rar-woo-advance-payment' ) . '</button>';
				$html .= '</details>';
			} else {
				$html .= '<div class="rar-wap-admin-actions"><button type="button" class="button rar-wap-act" data-op="reset">' . esc_html__( 'Move back to awaiting verification', 'rar-woo-advance-payment' ) . '</button></div>';
			}
		}

		$html .= '<p class="rar-wap-panel-msg" role="status" aria-live="polite"></p>';
		$html .= self::history_html( $order );
		$html .= '</div>';

		return $html;
	}

	private static function history_html( WC_Order $order ) {
		$history = RAR_WAP_Order::get_history( $order );
		if ( ! $history ) {
			return '';
		}

		$labels = array(
			'submitted'      => __( 'Submitted', 'rar-woo-advance-payment' ),
			'verified'       => __( 'Verified', 'rar-woo-advance-payment' ),
			'rejected'       => __( 'Rejected', 'rar-woo-advance-payment' ),
			'reset'          => __( 'Reset', 'rar-woo-advance-payment' ),
			'resubmitted'    => __( 'Customer corrected', 'rar-woo-advance-payment' ),
			'proof_uploaded' => __( 'Screenshot uploaded', 'rar-woo-advance-payment' ),
		);

		$html = '<details class="rar-wap-history"><summary>' . esc_html__( 'Audit trail', 'rar-woo-advance-payment' ) . ' (' . count( $history ) . ')</summary><ol>';
		foreach ( array_reverse( $history ) as $item ) {
			$user  = ! empty( $item['u'] ) ? get_user_by( 'id', absint( $item['u'] ) ) : false;
			$when  = ! empty( $item['t'] ) ? wp_date( 'd M Y, h:i A', (int) $item['t'] ) : '';
			$html .= '<li><strong>' . esc_html( $labels[ $item['a'] ] ?? ucfirst( (string) $item['a'] ) ) . '</strong> <small>' . esc_html( (string) $when ) . ( $user ? ' · ' . esc_html( $user->display_name ) : '' ) . '</small>';
			if ( ! empty( $item['n'] ) ) {
				$html .= '<br><span>' . esc_html( (string) $item['n'] ) . '</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ol></details>';

		return $html;
	}

	/* ------------------------------------------------------------------ *
	 * AJAX
	 * ------------------------------------------------------------------ */

	public static function ajax_order_action() {
		check_ajax_referer( 'rar_wap_admin', 'nonce' );

		if ( ! RAR_WAP_Plugin::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage orders.', 'rar-woo-advance-payment' ) ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$op       = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$order    = wc_get_order( $order_id );

		if ( ! RAR_WAP_Order::is_rar_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Advance-payment order not found.', 'rar-woo-advance-payment' ) ), 404 );
		}

		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		switch ( $op ) {
			case 'verify':
				$raw    = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';
				$amount = '' === $raw ? null : wc_format_decimal( $raw );
				$result = RAR_WAP_Order::verify( $order, $amount, $note );
				$msg    = __( 'Payment verified. Order status and customer notification processed.', 'rar-woo-advance-payment' );
				break;
			case 'reject':
				$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : 'other';
				$result = RAR_WAP_Order::reject( $order, $reason, $note );
				$msg    = __( 'Payment marked unverified. Customer notified.', 'rar-woo-advance-payment' );
				break;
			case 'reset':
				$result = RAR_WAP_Order::reset( $order, $note );
				$msg    = __( 'Payment moved back to awaiting verification.', 'rar-woo-advance-payment' );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'rar-woo-advance-payment' ) ), 400 );
				return;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$order  = wc_get_order( $order_id );
		$status = RAR_WAP_Order::get_status( $order );
		wp_send_json_success(
			array(
				'message'      => $msg,
				'status'       => $status,
				'status_label' => RAR_WAP_Order::status_label( $status ),
				'order_status' => wc_get_order_status_name( $order->get_status() ),
				'panel'        => self::panel_html( $order ),
				'pending'      => RAR_WAP_Query::pending_count( true ),
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Legacy link actions (v1.x)
	 * ------------------------------------------------------------------ */

	public static function handle_verify() {
		self::handle_link( 'verify' );
	}

	public static function handle_reject() {
		self::handle_link( 'reject' );
	}

	private static function handle_link( $op ) {
		if ( ! RAR_WAP_Plugin::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage this order.', 'rar-woo-advance-payment' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'rar_wap_' . $op . '_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! RAR_WAP_Order::is_rar_order( $order ) ) {
			wp_die( esc_html__( 'Advance-payment order not found.', 'rar-woo-advance-payment' ) );
		}

		$result = 'verify' === $op ? RAR_WAP_Order::verify( $order ) : RAR_WAP_Order::reject( $order, 'other' );
		$notice = is_wp_error( $result ) ? 'already_' . ( 'verify' === $op ? 'verified' : 'unverified' ) : ( 'verify' === $op ? 'verified' : 'unverified' );

		wp_safe_redirect( add_query_arg( 'rar_wap_notice', $notice, $order->get_edit_order_url() ) );
		exit;
	}

	public static function admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['rar_wap_notice'] ) ) {
			$key      = sanitize_key( wp_unslash( $_GET['rar_wap_notice'] ) );
			$messages = array(
				'verified'           => array( 'success', __( 'Advance payment verified. Order status and customer notification were processed.', 'rar-woo-advance-payment' ) ),
				'unverified'         => array( 'warning', __( 'Payment marked unverified. The customer notification was processed.', 'rar-woo-advance-payment' ) ),
				'already_verified'   => array( 'info', __( 'This payment was already verified. No duplicate e-mail was sent.', 'rar-woo-advance-payment' ) ),
				'already_unverified' => array( 'info', __( 'This payment was already marked unverified. No duplicate notification was sent.', 'rar-woo-advance-payment' ) ),
			);
			if ( isset( $messages[ $key ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $messages[ $key ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ][1] ) . '</p></div>';
			}
		}

		if ( isset( $_GET['rar_wap_bulk_verified'] ) ) {
			$done    = absint( $_GET['rar_wap_bulk_verified'] );
			$skipped = isset( $_GET['rar_wap_bulk_skipped'] ) ? absint( $_GET['rar_wap_bulk_skipped'] ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: verified count, 2: skipped count */
					__( 'Advance payments verified: %1$d. Skipped (not awaiting verification / other gateway): %2$d.', 'rar-woo-advance-payment' ),
					$done,
					$skipped
				)
			) . '</p></div>';
		}
		// phpcs:enable
	}

	/* ------------------------------------------------------------------ *
	 * Orders list
	 * ------------------------------------------------------------------ */

	public static function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['rar_wap_payment'] = __( 'Advance', 'rar-woo-advance-payment' );
			}
		}
		if ( ! isset( $new['rar_wap_payment'] ) ) {
			$new['rar_wap_payment'] = __( 'Advance', 'rar-woo-advance-payment' );
		}
		return $new;
	}

	public static function render_legacy_column( $column, $post_id ) {
		if ( 'rar_wap_payment' === $column ) {
			self::render_column_value( wc_get_order( $post_id ) );
		}
	}

	public static function render_hpos_column( $column, $order ) {
		if ( 'rar_wap_payment' === $column ) {
			self::render_column_value( self::order_from( $order ) );
		}
	}

	private static function render_column_value( $order ) {
		if ( ! RAR_WAP_Order::is_rar_order( $order ) || ! RAR_WAP_Order::has_submission( $order ) ) {
			echo '<span class="rar-wap-admin-muted">—</span>';
			return;
		}

		$status = RAR_WAP_Order::get_status( $order );
		echo '<span class="rar-wap-column-status ' . esc_attr( $status ) . '">' . esc_html( RAR_WAP_Order::status_label( $status ) ) . '</span>';
		echo '<small class="rar-wap-column-money">' . esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) ) . ' · '
			. wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_required_amount' ), array( 'currency' => $order->get_currency() ) ) )
			. ' · ' . esc_html__( 'collect', 'rar-woo-advance-payment' ) . ' '
			. wp_kses_post( wc_price( RAR_WAP_Order::collectable_amount( $order ), array( 'currency' => $order->get_currency() ) ) ) . '</small>';
		if ( absint( $order->get_meta( '_rar_wap_duplicate_of' ) ) ) {
			echo '<small class="rar-wap-column-flag">⚠ ' . esc_html__( 'duplicate ref', 'rar-woo-advance-payment' ) . '</small>';
		}
	}

	private static function current_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$value = isset( $_GET['rar_wap_status'] ) ? sanitize_key( wp_unslash( $_GET['rar_wap_status'] ) ) : '';
		return in_array( $value, RAR_WAP_Order::STATUSES, true ) ? $value : '';
	}

	public static function filter_dropdown() {
		$current = self::current_filter();
		echo '<select name="rar_wap_status" id="rar-wap-status-filter"><option value="">' . esc_html__( 'Advance: all', 'rar-woo-advance-payment' ) . '</option>';
		foreach ( RAR_WAP_Order::STATUSES as $status ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $current, $status, false ) . '>' . esc_html( RAR_WAP_Order::status_label( $status ) ) . '</option>';
		}
		echo '</select>';
	}

	public static function legacy_filter_dropdown( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			self::filter_dropdown();
		}
	}

	public static function legacy_filter_query( $vars ) {
		global $typenow;
		$status = self::current_filter();
		if ( ! is_admin() || 'shop_order' !== $typenow || ! $status ) {
			return $vars;
		}

		$vars['meta_query']   = isset( $vars['meta_query'] ) && is_array( $vars['meta_query'] ) ? $vars['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$vars['meta_query'][] = array(
			'key'   => '_rar_wap_status',
			'value' => $status,
		);
		return $vars;
	}

	public static function hpos_filter_query( $args ) {
		$status = self::current_filter();
		if ( $status ) {
			$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'][] = array(
				'key'   => '_rar_wap_status',
				'value' => $status,
			);
		}
		return $args;
	}

	public static function bulk_actions( $actions ) {
		if ( RAR_WAP_Plugin::can_manage() ) {
			$actions['rar_wap_bulk_verify'] = __( 'Verify advance payment (RAR)', 'rar-woo-advance-payment' );
		}
		return $actions;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'rar_wap_bulk_verify' !== $action || ! RAR_WAP_Plugin::can_manage() ) {
			return $redirect;
		}

		$done    = 0;
		$skipped = 0;
		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( absint( $id ) );
			if ( ! RAR_WAP_Order::is_rar_order( $order ) || 'submitted' !== RAR_WAP_Order::get_status( $order ) || ! RAR_WAP_Order::has_submission( $order ) ) {
				++$skipped;
				continue;
			}
			$result = RAR_WAP_Order::verify( $order, null, __( 'Bulk verification.', 'rar-woo-advance-payment' ) );
			if ( is_wp_error( $result ) ) {
				++$skipped;
			} else {
				++$done;
			}
		}

		return add_query_arg(
			array(
				'rar_wap_bulk_verified' => $done,
				'rar_wap_bulk_skipped'  => $skipped,
			),
			$redirect
		);
	}
}

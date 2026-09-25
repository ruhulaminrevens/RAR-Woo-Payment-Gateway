<?php
/**
 * Advance-payment order domain: state machine, metadata and audit trail.
 *
 * Meta keys (backward compatible with v1.x — sibling RAR plugins read them):
 *  _rar_wap_status               submitted | verified | unverified
 *  _rar_wap_channel / _label     channel key and label
 *  _rar_wap_payer                paying number / account reference
 *  _rar_wap_reference(_normalized) Transaction ID
 *  _rar_wap_requested_amount     amount the customer was asked to pay (v2)
 *  _rar_wap_required_amount      advance amount; after verification = verified amount
 *  _rar_wap_received_amount      amount confirmed by staff (v2)
 *  _rar_wap_balance_due          amount still to collect (COD)
 *  _rar_wap_history              audit trail (v2)
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Order {

	const GATEWAY_ID = 'rar_advance_payment';
	const STATUSES   = array( 'submitted', 'verified', 'unverified' );

	const REJECT_REASONS = array(
		'not_found'       => 'Transfer not found in the merchant/bank account',
		'amount_mismatch' => 'Received amount does not match',
		'wrong_reference' => 'Transaction ID / reference is incorrect',
		'duplicate'       => 'Reference already used for another order',
		'other'           => 'Other',
	);

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	public static function is_rar_order( $order ) {
		return $order instanceof WC_Order && self::GATEWAY_ID === $order->get_payment_method();
	}

	public static function get_status( WC_Order $order ) {
		$status = sanitize_key( (string) $order->get_meta( '_rar_wap_status' ) );
		return in_array( $status, self::STATUSES, true ) ? $status : 'submitted';
	}

	public static function has_submission( WC_Order $order ) {
		return '' !== (string) $order->get_meta( '_rar_wap_reference' );
	}

	public static function status_label( $status ) {
		$labels = array(
			'submitted'  => __( 'Awaiting verification', 'rar-woo-advance-payment' ),
			'verified'   => __( 'Verified', 'rar-woo-advance-payment' ),
			'unverified' => __( 'Rejected / needs attention', 'rar-woo-advance-payment' ),
		);
		return $labels[ $status ] ?? ucfirst( (string) $status );
	}

	public static function reject_reason_label( $key ) {
		$labels = array(
			'not_found'       => __( 'Transfer not found in the merchant/bank account', 'rar-woo-advance-payment' ),
			'amount_mismatch' => __( 'Received amount does not match', 'rar-woo-advance-payment' ),
			'wrong_reference' => __( 'Transaction ID / reference is incorrect', 'rar-woo-advance-payment' ),
			'duplicate'       => __( 'Reference already used for another order', 'rar-woo-advance-payment' ),
			'other'           => __( 'Other', 'rar-woo-advance-payment' ),
		);
		return $labels[ $key ] ?? '';
	}

	/** Convert Bangla digits (০-৯) to ASCII. */
	public static function normalize_digits( $value ) {
		return strtr(
			(string) $value,
			array(
				'০' => '0',
				'১' => '1',
				'২' => '2',
				'৩' => '3',
				'৪' => '4',
				'৫' => '5',
				'৬' => '6',
				'৭' => '7',
				'৮' => '8',
				'৯' => '9',
			)
		);
	}

	public static function normalize_reference( $reference ) {
		$reference = strtoupper( trim( sanitize_text_field( self::normalize_digits( (string) $reference ) ) ) );
		return (string) preg_replace( '/\s+/', '', $reference );
	}

	public static function is_valid_reference( $normalized ) {
		return (bool) preg_match( '/^[A-Z0-9][A-Z0-9\-_\/.#]{3,39}$/', (string) $normalized );
	}

	/**
	 * Normalise a Bangladeshi mobile/MFS account number.
	 * Accepts 01XXXXXXXXX, +8801..., 8801..., and 12-digit Rocket accounts.
	 *
	 * @return string Normalised number or '' when invalid.
	 */
	public static function normalize_mobile( $value ) {
		$digits = (string) preg_replace( '/\D+/', '', self::normalize_digits( (string) $value ) );

		if ( 0 === strpos( $digits, '880' ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( preg_match( '/^01[3-9]\d{8,9}$/', $digits ) ) {
			return $digits;
		}

		return '';
	}

	public static function money( $amount ) {
		return (float) wc_format_decimal( (float) $amount, wc_get_price_decimals() );
	}

	public static function net_total( WC_Order $order ) {
		return max( 0.0, (float) $order->get_total() - (float) $order->get_total_refunded() );
	}

	public static function requested_amount( WC_Order $order ) {
		$requested = $order->get_meta( '_rar_wap_requested_amount' );
		if ( '' === $requested || null === $requested ) {
			$requested = $order->get_meta( '_rar_wap_required_amount' );
		}
		return (float) $requested;
	}

	/**
	 * Amount the courier/rider should collect on delivery.
	 */
	public static function collectable_amount( WC_Order $order ) {
		$total = self::net_total( $order );

		if ( ! self::is_rar_order( $order ) ) {
			$amount = $total;
		} elseif ( 'verified' === self::get_status( $order ) ) {
			$amount = max( 0.0, $total - (float) $order->get_meta( '_rar_wap_required_amount' ) );
		} else {
			// Unverified advance is not money received: collect the full total.
			$amount = $total;
		}

		return (float) apply_filters( 'rar_wap_collectable_amount', self::money( $amount ), $order );
	}

	/* ------------------------------------------------------------------ *
	 * Audit trail
	 * ------------------------------------------------------------------ */

	public static function add_history( WC_Order $order, $action, $note = '', $user_id = null ) {
		$history = $order->get_meta( '_rar_wap_history' );
		$history = is_array( $history ) ? $history : array();

		$history[] = array(
			't' => time(),
			'a' => sanitize_key( $action ),
			'u' => null === $user_id ? get_current_user_id() : absint( $user_id ),
			'n' => wp_strip_all_tags( (string) $note ),
		);

		if ( count( $history ) > 60 ) {
			$history = array_slice( $history, -60 );
		}

		$order->update_meta_data( '_rar_wap_history', $history );
	}

	/**
	 * History including synthesised entries for v1.x orders without a trail.
	 *
	 * @return array<int,array{t:int,a:string,u:int,n:string}>
	 */
	public static function get_history( WC_Order $order ) {
		$history = $order->get_meta( '_rar_wap_history' );
		if ( is_array( $history ) && $history ) {
			return $history;
		}

		$out = array();
		$map = array(
			'submitted'  => array( '_rar_wap_submitted_at', 0 ),
			'verified'   => array( '_rar_wap_verified_at', '_rar_wap_verified_by' ),
			'rejected'   => array( '_rar_wap_rejected_at', '_rar_wap_rejected_by' ),
		);
		foreach ( $map as $action => $keys ) {
			$when = (string) $order->get_meta( $keys[0] );
			if ( '' === $when ) {
				continue;
			}
			$ts    = strtotime( get_gmt_from_date( $when ) . ' UTC' );
			$out[] = array(
				't' => $ts ? $ts : 0,
				'a' => $action,
				'u' => $keys[1] ? absint( $order->get_meta( $keys[1] ) ) : 0,
				'n' => '',
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a['t'] <=> $b['t'];
			}
		);
		return $out;
	}

	private static function actor_name( $user_id = null ) {
		$user = get_user_by( 'id', null === $user_id ? get_current_user_id() : absint( $user_id ) );
		return $user ? $user->display_name : __( 'system', 'rar-woo-advance-payment' );
	}

	private static function price_text( WC_Order $order, $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
	}

	/* ------------------------------------------------------------------ *
	 * State transitions
	 * ------------------------------------------------------------------ */

	/**
	 * Record a new payment submission (checkout / order-pay).
	 *
	 * @param array $data channel, channel_label, payer, reference, amount, balance, rule, destination, duplicate_of.
	 */
	public static function submit( WC_Order $order, array $data ) {
		$amount = self::money( $data['amount'] );

		$order->update_meta_data( '_rar_wap_status', 'submitted' );
		$order->update_meta_data( '_rar_wap_channel', sanitize_key( $data['channel'] ) );
		$order->update_meta_data( '_rar_wap_channel_label', sanitize_text_field( $data['channel_label'] ) );
		$order->update_meta_data( '_rar_wap_payer', sanitize_text_field( $data['payer'] ) );
		$order->update_meta_data( '_rar_wap_reference', sanitize_text_field( $data['reference'] ) );
		$order->update_meta_data( '_rar_wap_reference_normalized', self::normalize_reference( $data['reference'] ) );
		$order->update_meta_data( '_rar_wap_requested_amount', wc_format_decimal( $amount ) );
		$order->update_meta_data( '_rar_wap_required_amount', wc_format_decimal( $amount ) );
		$order->update_meta_data( '_rar_wap_balance_due', wc_format_decimal( self::money( $data['balance'] ) ) );
		$order->update_meta_data( '_rar_wap_rule', sanitize_key( $data['rule'] ) );
		$order->update_meta_data( '_rar_wap_destination', sanitize_text_field( (string) ( $data['destination'] ?? '' ) ) );
		$order->update_meta_data( '_rar_wap_submitted_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rar_wap_submission_id', wp_generate_uuid4() );

		if ( ! empty( $data['duplicate_of'] ) ) {
			$order->update_meta_data( '_rar_wap_duplicate_of', (string) absint( $data['duplicate_of'] ) );
		}

		self::add_history(
			$order,
			'submitted',
			sprintf( '%1$s · %2$s · %3$s', $data['channel_label'], self::price_text( $order, $amount ), $data['reference'] ),
			$order->get_customer_id()
		);

		$order->save();
		RAR_WAP_Query::flush_counts();
	}

	/**
	 * Staff confirms the transfer.
	 *
	 * @param float|string|null $received Actually received amount (null = requested amount).
	 * @return true|WP_Error
	 */
	public static function verify( WC_Order $order, $received = null, $note = '', $args = array() ) {
		if ( ! self::is_rar_order( $order ) ) {
			return new WP_Error( 'rar_wap_not_rar', __( 'This order does not use RAR Advance Payment.', 'rar-woo-advance-payment' ) );
		}
		if ( 'verified' === self::get_status( $order ) ) {
			return new WP_Error( 'rar_wap_already_verified', __( 'This payment was already verified.', 'rar-woo-advance-payment' ) );
		}

		$args      = wp_parse_args( $args, array( 'notify' => true, 'user_id' => get_current_user_id() ) );
		$requested = self::requested_amount( $order );
		$received  = ( null === $received || '' === $received ) ? $requested : (float) wc_format_decimal( self::normalize_digits( (string) $received ) );
		$total     = self::net_total( $order );
		$received  = self::money( min( max( 0.0, $received ), $total > 0 ? $total : $received ) );

		if ( $received <= 0 ) {
			return new WP_Error( 'rar_wap_amount', __( 'Received amount must be greater than zero.', 'rar-woo-advance-payment' ) );
		}

		$balance  = self::money( max( 0.0, $total - $received ) );
		$settings = RAR_WAP_Plugin::settings();

		$order->update_meta_data( '_rar_wap_status', 'verified' );
		$order->update_meta_data( '_rar_wap_verified_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rar_wap_verified_by', (string) absint( $args['user_id'] ) );
		$order->update_meta_data( '_rar_wap_received_amount', wc_format_decimal( $received ) );
		$order->update_meta_data( '_rar_wap_required_amount', wc_format_decimal( $received ) );
		$order->update_meta_data( '_rar_wap_balance_due', wc_format_decimal( $balance ) );
		$order->delete_meta_data( '_rar_wap_reject_reason' );

		if ( 'no' !== ( $settings['set_transaction_id'] ?? 'yes' ) && ! $order->get_transaction_id() ) {
			$order->set_transaction_id( (string) $order->get_meta( '_rar_wap_reference' ) );
		}

		if ( $balance <= 0 && 'no' !== ( $settings['mark_paid_when_full'] ?? 'yes' ) && ! $order->get_date_paid() ) {
			$order->set_date_paid( time() );
			$order->update_meta_data( '_rar_wap_marked_paid', 'yes' );
		}

		$text = sprintf(
			/* translators: 1: amount, 2: staff name */
			__( 'Advance payment of %1$s verified by %2$s.', 'rar-woo-advance-payment' ),
			self::price_text( $order, $received ),
			self::actor_name( $args['user_id'] )
		);
		if ( abs( $received - $requested ) > 0.009 ) {
			$text .= ' ' . sprintf(
				/* translators: %s: requested amount */
				__( '(Requested: %s — received amount differs.)', 'rar-woo-advance-payment' ),
				self::price_text( $order, $requested )
			);
		}
		$text .= ' ' . sprintf(
			/* translators: %s: balance */
			__( 'Collect on delivery: %s.', 'rar-woo-advance-payment' ),
			self::price_text( $order, $balance )
		);
		if ( '' !== trim( (string) $note ) ) {
			$text .= ' ' . __( 'Note:', 'rar-woo-advance-payment' ) . ' ' . sanitize_textarea_field( $note );
		}

		self::add_history( $order, 'verified', $text, $args['user_id'] );
		$order->save();

		$target = sanitize_key( $settings['after_verify_status'] ?? 'processing' );
		if ( $target && 'keep' !== $target && ! $order->has_status( array( $target, 'completed', 'cancelled', 'refunded', 'failed' ) ) ) {
			$order->update_status( $target, $text );
		} else {
			$order->add_order_note( $text );
		}

		if ( $args['notify'] && 'no' !== ( $settings['customer_verified_email'] ?? 'yes' ) ) {
			RAR_WAP_Emails::customer_verified( $order );
		}

		RAR_WAP_Query::flush_counts();
		self::fire( 'verified', $order, array( 'received' => $received, 'note' => $note ) );
		return true;
	}

	/**
	 * Staff cannot match the transfer.
	 *
	 * @return true|WP_Error
	 */
	public static function reject( WC_Order $order, $reason = 'not_found', $note = '', $args = array() ) {
		if ( ! self::is_rar_order( $order ) ) {
			return new WP_Error( 'rar_wap_not_rar', __( 'This order does not use RAR Advance Payment.', 'rar-woo-advance-payment' ) );
		}
		if ( 'unverified' === self::get_status( $order ) ) {
			return new WP_Error( 'rar_wap_already_rejected', __( 'This payment was already marked unverified.', 'rar-woo-advance-payment' ) );
		}
		if ( 'verified' === self::get_status( $order ) ) {
			return new WP_Error( 'rar_wap_verified', __( 'Undo the verification first before rejecting this payment.', 'rar-woo-advance-payment' ) );
		}

		$args   = wp_parse_args( $args, array( 'notify' => true, 'user_id' => get_current_user_id() ) );
		$reason = array_key_exists( $reason, self::REJECT_REASONS ) ? $reason : 'other';
		$label  = self::reject_reason_label( $reason );
		$note   = sanitize_textarea_field( (string) $note );
		$full   = trim( $label . ( $note ? ' — ' . $note : '' ) );

		$order->update_meta_data( '_rar_wap_status', 'unverified' );
		$order->update_meta_data( '_rar_wap_rejected_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rar_wap_rejected_by', (string) absint( $args['user_id'] ) );
		$order->update_meta_data( '_rar_wap_reject_reason', $full );
		$order->update_meta_data( '_rar_wap_reject_reason_key', $reason );

		$text = sprintf(
			/* translators: 1: staff name, 2: reason */
			__( 'Advance payment marked unverified by %1$s. Reason: %2$s', 'rar-woo-advance-payment' ),
			self::actor_name( $args['user_id'] ),
			$full
		);
		self::add_history( $order, 'rejected', $full, $args['user_id'] );
		$order->save();
		$order->add_order_note( $text );

		$settings = RAR_WAP_Plugin::settings();
		if ( $args['notify'] && 'no' !== ( $settings['customer_rejected_email'] ?? 'yes' ) ) {
			RAR_WAP_Emails::customer_rejected( $order );
		}

		RAR_WAP_Query::flush_counts();
		self::fire( 'rejected', $order, array( 'reason' => $reason, 'note' => $note ) );
		return true;
	}

	/**
	 * Undo a verification or rejection (back to "awaiting verification").
	 *
	 * @return true|WP_Error
	 */
	public static function reset( WC_Order $order, $note = '', $args = array() ) {
		if ( ! self::is_rar_order( $order ) ) {
			return new WP_Error( 'rar_wap_not_rar', __( 'This order does not use RAR Advance Payment.', 'rar-woo-advance-payment' ) );
		}

		$previous = self::get_status( $order );
		if ( 'submitted' === $previous ) {
			return new WP_Error( 'rar_wap_already_pending', __( 'This payment is already awaiting verification.', 'rar-woo-advance-payment' ) );
		}

		$args      = wp_parse_args( $args, array( 'user_id' => get_current_user_id() ) );
		$requested = self::requested_amount( $order );

		$order->update_meta_data( '_rar_wap_status', 'submitted' );
		$order->update_meta_data( '_rar_wap_required_amount', wc_format_decimal( $requested ) );
		$order->update_meta_data( '_rar_wap_balance_due', wc_format_decimal( self::money( max( 0.0, self::net_total( $order ) - $requested ) ) ) );
		$order->delete_meta_data( '_rar_wap_received_amount' );
		$order->delete_meta_data( '_rar_wap_verified_at' );
		$order->delete_meta_data( '_rar_wap_verified_by' );
		$order->delete_meta_data( '_rar_wap_reject_reason' );
		$order->delete_meta_data( '_rar_wap_overdue_notified' );

		if ( 'yes' === $order->get_meta( '_rar_wap_marked_paid' ) ) {
			$order->set_date_paid( null );
			$order->delete_meta_data( '_rar_wap_marked_paid' );
		}

		$text = sprintf(
			/* translators: 1: previous state, 2: staff name */
			__( 'Advance payment state reset from "%1$s" to "Awaiting verification" by %2$s.', 'rar-woo-advance-payment' ),
			self::status_label( $previous ),
			self::actor_name( $args['user_id'] )
		);
		if ( '' !== trim( (string) $note ) ) {
			$text .= ' ' . sanitize_textarea_field( $note );
		}

		self::add_history( $order, 'reset', $text, $args['user_id'] );
		$order->save();

		if ( 'verified' === $previous && $order->has_status( 'processing' ) ) {
			$order->update_status( 'on-hold', $text );
		} else {
			$order->add_order_note( $text );
		}

		RAR_WAP_Query::flush_counts();
		self::fire( 'reset', $order, array( 'previous' => $previous ) );
		return true;
	}

	/**
	 * Customer corrects payer/reference after a rejection.
	 *
	 * @return true|WP_Error
	 */
	public static function resubmit( WC_Order $order, $payer, $reference ) {
		if ( ! self::is_rar_order( $order ) || 'unverified' !== self::get_status( $order ) ) {
			return new WP_Error( 'rar_wap_state', __( 'This payment cannot be updated right now.', 'rar-woo-advance-payment' ) );
		}
		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed', 'completed' ) ) ) {
			return new WP_Error( 'rar_wap_closed', __( 'This order is closed. Please contact support.', 'rar-woo-advance-payment' ) );
		}

		$old = (string) $order->get_meta( '_rar_wap_reference' );

		$order->update_meta_data( '_rar_wap_status', 'submitted' );
		$order->update_meta_data( '_rar_wap_payer', sanitize_text_field( $payer ) );
		$order->update_meta_data( '_rar_wap_reference', sanitize_text_field( $reference ) );
		$order->update_meta_data( '_rar_wap_reference_normalized', self::normalize_reference( $reference ) );
		$order->update_meta_data( '_rar_wap_resubmitted_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rar_wap_resubmission_count', (string) ( absint( $order->get_meta( '_rar_wap_resubmission_count' ) ) + 1 ) );
		$order->delete_meta_data( '_rar_wap_overdue_notified' );

		$text = sprintf(
			/* translators: 1: old reference, 2: new reference */
			__( 'Customer re-submitted advance payment details. Previous reference: %1$s → new reference: %2$s. Awaiting verification.', 'rar-woo-advance-payment' ),
			$old,
			$reference
		);
		self::add_history( $order, 'resubmitted', $text, $order->get_customer_id() );
		$order->save();
		$order->add_order_note( $text );

		RAR_WAP_Emails::admin_update( $order, 'resubmitted' );
		RAR_WAP_Query::flush_counts();
		self::fire( 'resubmitted', $order, array( 'previous_reference' => $old ) );
		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Integration
	 * ------------------------------------------------------------------ */

	/**
	 * Portable snapshot used by REST, webhooks, CSV and other plugins.
	 */
	public static function snapshot( WC_Order $order ) {
		$status = self::get_status( $order );
		$by     = absint( $order->get_meta( '_rar_wap_verified_by' ) );
		$user   = $by ? get_user_by( 'id', $by ) : false;

		return array(
			'order_id'           => $order->get_id(),
			'order_number'       => (string) $order->get_order_number(),
			'order_status'       => $order->get_status(),
			'order_total'        => (float) $order->get_total(),
			'currency'           => $order->get_currency(),
			'customer_name'      => trim( $order->get_formatted_billing_full_name() ),
			'customer_phone'     => $order->get_billing_phone(),
			'customer_email'     => $order->get_billing_email(),
			'payment_status'     => $status,
			'payment_status_label' => self::status_label( $status ),
			'channel'            => (string) $order->get_meta( '_rar_wap_channel' ),
			'channel_label'      => (string) $order->get_meta( '_rar_wap_channel_label' ),
			'destination'        => (string) $order->get_meta( '_rar_wap_destination' ),
			'payer'              => (string) $order->get_meta( '_rar_wap_payer' ),
			'reference'          => (string) $order->get_meta( '_rar_wap_reference' ),
			'rule'               => (string) $order->get_meta( '_rar_wap_rule' ),
			'requested_amount'   => self::requested_amount( $order ),
			'received_amount'    => (float) $order->get_meta( '_rar_wap_received_amount' ),
			'advance_amount'     => (float) $order->get_meta( '_rar_wap_required_amount' ),
			'balance_due'        => (float) $order->get_meta( '_rar_wap_balance_due' ),
			'collect_on_delivery' => self::collectable_amount( $order ),
			'submitted_at'       => (string) $order->get_meta( '_rar_wap_submitted_at' ),
			'verified_at'        => (string) $order->get_meta( '_rar_wap_verified_at' ),
			'verified_by'        => $user ? $user->display_name : '',
			'rejected_at'        => (string) $order->get_meta( '_rar_wap_rejected_at' ),
			'reject_reason'      => (string) $order->get_meta( '_rar_wap_reject_reason' ),
			'resubmissions'      => absint( $order->get_meta( '_rar_wap_resubmission_count' ) ),
			'duplicate_of'       => absint( $order->get_meta( '_rar_wap_duplicate_of' ) ),
			'has_proof'          => '' !== (string) $order->get_meta( '_rar_wap_proof_file' ),
			'edit_url'           => $order->get_edit_order_url(),
		);
	}

	/**
	 * Broadcast a payment event to other plugins and the optional webhook.
	 */
	public static function fire( $event, WC_Order $order, array $context = array() ) {
		/**
		 * Fires for every advance-payment event.
		 *
		 * @param string   $event   submitted|verified|rejected|reset|resubmitted|proof_uploaded
		 * @param WC_Order $order
		 * @param array    $context
		 */
		do_action( 'rar_wap_payment_event', $event, $order, $context );
		do_action( 'rar_wap_payment_' . $event, $order, $context );

		RAR_WAP_Automation::queue_webhook( $order->get_id(), $event );
	}
}

<?php
/**
 * Plugin e-mails, sent through the WooCommerce mailer so they use the store's
 * e-mail template, inline styles and the site's SMTP plugin.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Emails {

	private static function site_name() {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	public static function admin_recipient() {
		$settings = RAR_WAP_Plugin::settings();
		$emails   = array();
		foreach ( preg_split( '/[,;\s]+/', (string) ( $settings['admin_email'] ?? '' ) ) as $candidate ) {
			$candidate = sanitize_email( $candidate );
			if ( $candidate && is_email( $candidate ) ) {
				$emails[] = $candidate;
			}
		}
		if ( ! $emails ) {
			$emails[] = sanitize_email( get_option( 'admin_email' ) );
		}
		return implode( ',', array_unique( $emails ) );
	}

	/**
	 * Send an HTML mail using the WooCommerce template.
	 */
	public static function send( $to, $subject, $heading, $content ) {
		if ( ! $to ) {
			return false;
		}

		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$mailer  = WC()->mailer();
			$message = $mailer->wrap_message( $heading, $content );
			return (bool) $mailer->send( $to, $subject, $message, "Content-Type: text/html; charset=UTF-8\r\n" );
		}

		$message = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#222;line-height:1.6">'
			. '<div style="background:#0f8a6b;color:#fff;padding:20px 24px;border-radius:10px 10px 0 0"><h2 style="margin:0">' . esc_html( $heading ) . '</h2></div>'
			. '<div style="border:1px solid #e5e7eb;border-top:0;padding:24px">' . $content . '</div></div>';

		return (bool) wp_mail( $to, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	private static function price( WC_Order $order, $amount ) {
		return wp_kses_post( wc_price( (float) $amount, array( 'currency' => $order->get_currency() ) ) );
	}

	private static function first_name( WC_Order $order ) {
		$name = $order->get_billing_first_name();
		return esc_html( $name ? $name : $order->get_formatted_billing_full_name() );
	}

	private static function button( $url, $label ) {
		return '<p style="margin:18px 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#0f8a6b;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:bold">' . esc_html( $label ) . '</a></p>';
	}

	private static function details_table( WC_Order $order ) {
		$rows = array(
			__( 'Order', 'rar-woo-advance-payment' )           => '#' . esc_html( $order->get_order_number() ),
			__( 'Customer', 'rar-woo-advance-payment' )        => esc_html( $order->get_formatted_billing_full_name() ) . ' · ' . esc_html( $order->get_billing_phone() ),
			__( 'Channel', 'rar-woo-advance-payment' )         => esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) ),
			__( 'Paid to', 'rar-woo-advance-payment' )         => esc_html( (string) $order->get_meta( '_rar_wap_destination' ) ),
			__( 'Claimed amount', 'rar-woo-advance-payment' )  => self::price( $order, RAR_WAP_Order::requested_amount( $order ) ),
			__( 'Order total', 'rar-woo-advance-payment' )     => self::price( $order, $order->get_total() ),
			__( 'Paid from', 'rar-woo-advance-payment' )       => '<code>' . esc_html( (string) $order->get_meta( '_rar_wap_payer' ) ) . '</code>',
			__( 'Transaction ID', 'rar-woo-advance-payment' )  => '<code>' . esc_html( (string) $order->get_meta( '_rar_wap_reference' ) ) . '</code>',
		);

		$html = '<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e7eb;border-collapse:collapse">';
		foreach ( $rows as $label => $value ) {
			$html .= '<tr><th style="text-align:left;border:1px solid #e5e7eb;width:38%">' . esc_html( $label ) . '</th><td style="text-align:left;border:1px solid #e5e7eb">' . $value . '</td></tr>';
		}
		$html .= '</table>';

		if ( absint( $order->get_meta( '_rar_wap_duplicate_of' ) ) ) {
			$html .= '<p style="padding:10px;background:#fdecea;border-left:4px solid #c62828"><strong>' . esc_html__( 'Warning:', 'rar-woo-advance-payment' ) . '</strong> '
				. esc_html( sprintf( __( 'This Transaction ID was also submitted on order #%d.', 'rar-woo-advance-payment' ), absint( $order->get_meta( '_rar_wap_duplicate_of' ) ) ) ) . '</p>';
		}

		return $html;
	}

	/**
	 * Admin alert + optional customer receipt after checkout submission.
	 */
	public static function submission( WC_Order $order ) {
		if ( 'yes' === $order->get_meta( '_rar_wap_submission_email_sent' ) ) {
			return;
		}

		$settings = RAR_WAP_Plugin::settings();
		$sent_any = false;

		if ( 'no' !== ( $settings['admin_submission_email'] ?? 'yes' ) ) {
			$content  = self::details_table( $order );
			$content .= '<p style="padding:12px;background:#fff8e6;border-left:4px solid #d97706"><strong>' . esc_html__( 'Action required:', 'rar-woo-advance-payment' ) . '</strong> '
				. esc_html__( 'Verify the transfer in the official merchant/bank account before fulfilment. A submitted reference is not proof of payment.', 'rar-woo-advance-payment' ) . '</p>';
			$content .= self::button( $order->get_edit_order_url(), __( 'Open order to verify', 'rar-woo-advance-payment' ) );

			$sent_any = self::send(
				self::admin_recipient(),
				sprintf( '[%1$s] %2$s #%3$s', self::site_name(), __( 'Payment verification required — Order', 'rar-woo-advance-payment' ), $order->get_order_number() ),
				__( 'Advance payment submitted', 'rar-woo-advance-payment' ),
				$content
			) || $sent_any;
		}

		if ( 'yes' === ( $settings['customer_submission_email'] ?? 'no' ) ) {
			$content = '<p>' . sprintf( esc_html__( 'Dear %s,', 'rar-woo-advance-payment' ), self::first_name( $order ) ) . '</p>'
				. '<p>আপনার payment information আমরা পেয়েছি। আমাদের team transfer টি verify করবে। Verification সম্পন্ন হলে আপনাকে জানানো হবে।</p>'
				. '<p><strong>' . esc_html__( 'Pay now', 'rar-woo-advance-payment' ) . ':</strong> ' . self::price( $order, RAR_WAP_Order::requested_amount( $order ) )
				. '<br><strong>' . esc_html__( 'Due on delivery', 'rar-woo-advance-payment' ) . ':</strong> ' . self::price( $order, $order->get_meta( '_rar_wap_balance_due' ) )
				. '<br><strong>' . esc_html__( 'Method', 'rar-woo-advance-payment' ) . ':</strong> ' . esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) )
				. '<br><strong>' . esc_html__( 'Transaction ID', 'rar-woo-advance-payment' ) . ':</strong> ' . esc_html( (string) $order->get_meta( '_rar_wap_reference' ) ) . '</p>'
				. '<p><strong>' . esc_html__( 'Security:', 'rar-woo-advance-payment' ) . '</strong> ' . esc_html__( 'Never share your PIN, password or OTP with anyone.', 'rar-woo-advance-payment' ) . '</p>';

			$sent_any = self::send(
				$order->get_billing_email(),
				sprintf( '[%1$s] %2$s #%3$s', self::site_name(), __( 'Payment submission received — Order', 'rar-woo-advance-payment' ), $order->get_order_number() ),
				__( 'Payment submission received', 'rar-woo-advance-payment' ),
				$content
			) || $sent_any;
		}

		$order->update_meta_data( '_rar_wap_submission_email_sent', 'yes' );
		$order->update_meta_data( '_rar_wap_submission_email_sent_at', current_time( 'mysql' ) );
		$order->save();

		RAR_WAP_Plugin::log( 'Submission notification workflow completed.', $sent_any ? 'info' : 'notice', array( 'order_id' => $order->get_id() ) );
	}

	public static function customer_verified( WC_Order $order ) {
		$content = '<p>' . sprintf( esc_html__( 'Dear %s,', 'rar-woo-advance-payment' ), self::first_name( $order ) ) . '</p>'
			. '<p>আপনার advance payment সফলভাবে verify হয়েছে। ধন্যবাদ! আপনার order এখন পরবর্তী ধাপে যাবে।</p>'
			. '<p><strong>' . esc_html__( 'Verified amount', 'rar-woo-advance-payment' ) . ':</strong> ' . self::price( $order, $order->get_meta( '_rar_wap_required_amount' ) )
			. '<br><strong>' . esc_html__( 'Due on delivery', 'rar-woo-advance-payment' ) . ':</strong> ' . self::price( $order, $order->get_meta( '_rar_wap_balance_due' ) ) . '</p>'
			. self::button( RAR_WAP_Display::customer_order_url( $order ), __( 'View order', 'rar-woo-advance-payment' ) );

		return self::send(
			$order->get_billing_email(),
			sprintf( '[%1$s] %2$s #%3$s', self::site_name(), __( 'Advance payment verified — Order', 'rar-woo-advance-payment' ), $order->get_order_number() ),
			__( 'Payment verified ✅', 'rar-woo-advance-payment' ),
			$content
		);
	}

	public static function customer_rejected( WC_Order $order ) {
		$reason  = (string) $order->get_meta( '_rar_wap_reject_reason' );
		$content = '<p>' . sprintf( esc_html__( 'Dear %s,', 'rar-woo-advance-payment' ), self::first_name( $order ) ) . '</p>'
			. '<p>দুঃখিত, আপনার দেওয়া payment reference এখনো verify করা যায়নি। Please check the transaction details and update them using the button below, or contact our support team.</p>';
		if ( $reason ) {
			$content .= '<p><strong>' . esc_html__( 'Reason', 'rar-woo-advance-payment' ) . ':</strong> ' . esc_html( $reason ) . '</p>';
		}
		$content .= '<p><strong>' . esc_html__( 'Submitted Transaction ID', 'rar-woo-advance-payment' ) . ':</strong> <code>' . esc_html( (string) $order->get_meta( '_rar_wap_reference' ) ) . '</code></p>';

		if ( 'no' !== ( RAR_WAP_Plugin::settings()['allow_resubmit'] ?? 'yes' ) ) {
			$content .= self::button( RAR_WAP_Display::customer_order_url( $order ), __( 'Update payment details', 'rar-woo-advance-payment' ) );
		}
		$content .= '<p>' . esc_html__( 'For your security, never send a PIN, password or OTP.', 'rar-woo-advance-payment' ) . '</p>';

		return self::send(
			$order->get_billing_email(),
			sprintf( '[%1$s] %2$s #%3$s', self::site_name(), __( 'Payment verification needs attention — Order', 'rar-woo-advance-payment' ), $order->get_order_number() ),
			__( 'Payment verification needs attention', 'rar-woo-advance-payment' ),
			$content
		);
	}

	/**
	 * Admin alert when the customer re-submits details or uploads a proof.
	 */
	public static function admin_update( WC_Order $order, $type ) {
		if ( 'no' === ( RAR_WAP_Plugin::settings()['admin_submission_email'] ?? 'yes' ) ) {
			return false;
		}

		$headings = array(
			'resubmitted'    => __( 'Customer re-submitted payment details', 'rar-woo-advance-payment' ),
			'proof_uploaded' => __( 'Customer uploaded a payment screenshot', 'rar-woo-advance-payment' ),
		);
		$heading = $headings[ $type ] ?? __( 'Advance payment update', 'rar-woo-advance-payment' );

		$content  = self::details_table( $order );
		$content .= self::button( $order->get_edit_order_url(), __( 'Open order', 'rar-woo-advance-payment' ) );

		return self::send(
			self::admin_recipient(),
			sprintf( '[%1$s] %2$s — #%3$s', self::site_name(), $heading, $order->get_order_number() ),
			$heading,
			$content
		);
	}

	/**
	 * Digest of payments waiting too long for verification.
	 *
	 * @param WC_Order[] $orders
	 */
	public static function overdue_digest( array $orders, $hours ) {
		if ( ! $orders ) {
			return false;
		}

		$rows = '';
		foreach ( $orders as $order ) {
			$rows .= '<tr>'
				. '<td style="border:1px solid #e5e7eb"><a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>'
				. '<td style="border:1px solid #e5e7eb">' . esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) ) . '</td>'
				. '<td style="border:1px solid #e5e7eb">' . self::price( $order, RAR_WAP_Order::requested_amount( $order ) ) . '</td>'
				. '<td style="border:1px solid #e5e7eb"><code>' . esc_html( (string) $order->get_meta( '_rar_wap_reference' ) ) . '</code></td>'
				. '<td style="border:1px solid #e5e7eb">' . esc_html( (string) $order->get_meta( '_rar_wap_submitted_at' ) ) . '</td>'
				. '</tr>';
		}

		$content = '<p>' . esc_html( sprintf( _n( '%1$d advance payment has been waiting more than %2$d hours for verification.', '%1$d advance payments have been waiting more than %2$d hours for verification.', count( $orders ), 'rar-woo-advance-payment' ), count( $orders ), $hours ) ) . '</p>'
			. '<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse"><tr>'
			. '<th style="text-align:left;border:1px solid #e5e7eb">' . esc_html__( 'Order', 'rar-woo-advance-payment' ) . '</th>'
			. '<th style="text-align:left;border:1px solid #e5e7eb">' . esc_html__( 'Channel', 'rar-woo-advance-payment' ) . '</th>'
			. '<th style="text-align:left;border:1px solid #e5e7eb">' . esc_html__( 'Amount', 'rar-woo-advance-payment' ) . '</th>'
			. '<th style="text-align:left;border:1px solid #e5e7eb">' . esc_html__( 'Transaction ID', 'rar-woo-advance-payment' ) . '</th>'
			. '<th style="text-align:left;border:1px solid #e5e7eb">' . esc_html__( 'Submitted', 'rar-woo-advance-payment' ) . '</th>'
			. '</tr>' . $rows . '</table>'
			. self::button( RAR_WAP_Dashboard::url( array( 'status' => 'submitted' ) ), __( 'Open verification queue', 'rar-woo-advance-payment' ) );

		return self::send(
			self::admin_recipient(),
			sprintf( '[%1$s] %2$s', self::site_name(), __( 'Advance payments awaiting verification', 'rar-woo-advance-payment' ) ),
			__( 'Verification queue reminder', 'rar-woo-advance-payment' ),
			$content
		);
	}
}

<?php
/**
 * Customer-facing output: order page status card, correction form, proof
 * upload, order totals rows and e-mail summary.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Display {

	public static function init() {
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'order_card' ), 20 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_summary' ), 20, 4 );
		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'inject_totals' ), 20, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
		add_filter( 'woocommerce_order_email_verification_required', array( __CLASS__, 'skip_email_check' ), 20, 2 );
	}

	/**
	 * Signed, expiring access token for a guest's order page.
	 * Only ever sent to the order's own e-mail address or issued right after a
	 * nonce + order-key checked form post, so it is equivalent to WooCommerce's
	 * own "confirm your e-mail" step.
	 */
	public static function access_token( WC_Order $order, $ttl = WEEK_IN_SECONDS ) {
		$expires = time() + (int) $ttl;
		$sig     = substr( hash_hmac( 'sha256', $order->get_id() . '|' . $order->get_order_key() . '|' . $expires, wp_salt( 'auth' ) ), 0, 24 );
		return $expires . '.' . $sig;
	}

	public static function token_is_valid( WC_Order $order, $token ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || (int) $parts[0] < time() ) {
			return false;
		}
		$expected = substr( hash_hmac( 'sha256', $order->get_id() . '|' . $order->get_order_key() . '|' . $parts[0], wp_salt( 'auth' ) ), 0, 24 );
		return hash_equals( $expected, $parts[1] );
	}

	/**
	 * Let a guest holding a valid token skip WooCommerce's e-mail re-confirmation.
	 */
	public static function skip_email_check( $required, $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed token.
		$token = isset( $_GET['rar_wap_access'] ) ? sanitize_text_field( wp_unslash( $_GET['rar_wap_access'] ) ) : '';
		if ( $required && $token && RAR_WAP_Order::is_rar_order( $order ) && self::token_is_valid( $order, $token ) ) {
			return false;
		}
		return $required;
	}

	/**
	 * Best URL for the customer to see their order (guest-safe).
	 */
	public static function customer_order_url( WC_Order $order, $ttl = WEEK_IN_SECONDS ) {
		if ( $order->get_customer_id() ) {
			return $order->get_view_order_url();
		}
		return add_query_arg( 'rar_wap_access', self::access_token( $order, $ttl ), $order->get_checkout_order_received_url() );
	}

	/**
	 * Balance the customer will pay on delivery once the advance is confirmed.
	 * (The courier-facing amount is RAR_WAP_Order::collectable_amount().)
	 */
	public static function customer_balance( WC_Order $order ) {
		$balance = $order->get_meta( '_rar_wap_balance_due' );
		if ( '' === $balance || null === $balance ) {
			return RAR_WAP_Order::collectable_amount( $order );
		}
		return max( 0.0, min( (float) $balance, RAR_WAP_Order::net_total( $order ) ) );
	}

	/* ------------------------------------------------------------------ *
	 * Totals & e-mail
	 * ------------------------------------------------------------------ */

	public static function inject_totals( $totals, $order, $tax_display = '' ) {
		if ( ! RAR_WAP_Order::is_rar_order( $order ) || ! RAR_WAP_Order::has_submission( $order ) ) {
			return $totals;
		}

		$status = RAR_WAP_Order::get_status( $order );
		$extra  = array(
			'rar_wap_advance' => array(
				'label' => RAR_WAP_I18n::t( 'advance' ) . ':',
				'value' => wc_price( (float) $order->get_meta( '_rar_wap_required_amount' ), array( 'currency' => $order->get_currency() ) ) . ' — ' . esc_html( RAR_WAP_I18n::t( 'status_' . $status ) ),
			),
			'rar_wap_due'     => array(
				'label' => RAR_WAP_I18n::t( 'on_delivery' ) . ':',
				'value' => wc_price( self::customer_balance( $order ), array( 'currency' => $order->get_currency() ) ),
			),
		);

		if ( ! isset( $totals['payment_method'] ) ) {
			return $totals + $extra;
		}

		$out = array();
		foreach ( $totals as $key => $row ) {
			if ( 'payment_method' === $key ) {
				$out += $extra;
			}
			$out[ $key ] = $row;
		}
		return $out;
	}

	public static function email_summary( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( ! RAR_WAP_Order::is_rar_order( $order ) || ! RAR_WAP_Order::has_submission( $order ) ) {
			return;
		}

		$status  = RAR_WAP_Order::get_status( $order );
		$channel = (string) $order->get_meta( '_rar_wap_channel_label' );
		$amount  = (float) $order->get_meta( '_rar_wap_required_amount' );
		$collect = self::customer_balance( $order );

		if ( $plain_text ) {
			echo "\n" . esc_html( RAR_WAP_I18n::t( 'payment_status' ) ) . "\n";
			echo esc_html( RAR_WAP_I18n::t( 'method' ) ) . ': ' . esc_html( $channel ) . "\n";
			echo esc_html( RAR_WAP_I18n::t( 'advance' ) ) . ': ' . esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ) ) ) . ' — ' . esc_html( RAR_WAP_I18n::t( 'status_' . $status ) ) . "\n";
			echo esc_html( RAR_WAP_I18n::t( 'on_delivery' ) ) . ': ' . esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $collect, array( 'currency' => $order->get_currency() ) ) ) ) ) . "\n\n";
			return;
		}
		?>
		<div style="margin:0 0 24px;padding:14px 16px;border:1px solid #e5e7eb;border-radius:8px">
			<h3 style="margin:0 0 8px"><?php echo esc_html( RAR_WAP_I18n::t( 'payment_status' ) ); ?></h3>
			<p style="margin:3px 0"><strong><?php echo esc_html( RAR_WAP_I18n::t( 'method' ) ); ?>:</strong> <?php echo esc_html( $channel ); ?></p>
			<p style="margin:3px 0"><strong><?php echo esc_html( RAR_WAP_I18n::t( 'advance' ) ); ?>:</strong> <?php echo wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ); ?> — <?php echo esc_html( RAR_WAP_I18n::t( 'status_' . $status ) ); ?></p>
			<p style="margin:3px 0"><strong><?php echo esc_html( RAR_WAP_I18n::t( 'on_delivery' ) ); ?>:</strong> <?php echo wp_kses_post( wc_price( $collect, array( 'currency' => $order->get_currency() ) ) ); ?></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Order page card
	 * ------------------------------------------------------------------ */

	public static function order_card( $order ) {
		if ( ! RAR_WAP_Order::is_rar_order( $order ) || ! RAR_WAP_Order::has_submission( $order ) ) {
			return;
		}

		$status   = RAR_WAP_Order::get_status( $order );
		$settings = RAR_WAP_Plugin::settings();
		$closed   = $order->has_status( array( 'cancelled', 'refunded', 'failed', 'completed' ) );
		$currency = array( 'currency' => $order->get_currency() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg   = isset( $_GET['rar_wap_msg'] ) ? sanitize_key( wp_unslash( $_GET['rar_wap_msg'] ) ) : '';
		$error = get_transient( 'rar_wap_err_' . $order->get_id() );
		if ( $error ) {
			delete_transient( 'rar_wap_err_' . $order->get_id() );
		}

		$icons = array(
			'submitted'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 5v5.4l4 2.3-1 1.7-5-2.9V7h2z"/></svg>',
			'verified'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 16.2 5.3 12l-1.4 1.4 5.6 5.6L20.1 8.4 18.7 7z"/></svg>',
			'unverified' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 7h2v7h-2V7zm0 9h2v2h-2v-2zm1-14a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/></svg>',
		);
		$track = array(
			'submitted'  => array( 'is-done', 'is-now', '' ),
			'verified'   => array( 'is-done', 'is-done', 'is-done' ),
			'unverified' => array( 'is-done', 'is-now', '' ),
		);
		$accent = RAR_WAP_Gateway::accent_color();
		?>
		<section class="rw-order is-<?php echo esc_attr( $status ); ?>" style="--rw-accent:<?php echo esc_attr( $accent ); ?>">
			<div class="rw-order-head">
				<span class="rw-state"><?php echo $icons[ $status ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div>
					<h2><?php RAR_WAP_I18n::e( 'status_title_' . $status ); ?></h2>
					<p><?php RAR_WAP_I18n::e( 'status_text_' . $status ); ?></p>
				</div>
			</div>

			<ol class="rw-track">
				<li class="<?php echo esc_attr( $track[ $status ][0] ); ?>"><?php RAR_WAP_I18n::e( 'track_sent' ); ?></li>
				<li class="<?php echo esc_attr( $track[ $status ][1] ); ?>"><?php RAR_WAP_I18n::e( 'unverified' === $status ? 'status_unverified' : 'track_checking' ); ?></li>
				<li class="<?php echo esc_attr( $track[ $status ][2] ); ?>"><?php RAR_WAP_I18n::e( 'track_done' ); ?></li>
			</ol>

			<dl class="rw-facts">
				<div><dt><?php RAR_WAP_I18n::e( 'advance' ); ?></dt><dd><?php echo wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_required_amount' ), $currency ) ); ?></dd></div>
				<div><dt><?php RAR_WAP_I18n::e( 'on_delivery' ); ?></dt><dd><?php echo wp_kses_post( wc_price( self::customer_balance( $order ), $currency ) ); ?></dd></div>
				<div><dt><?php RAR_WAP_I18n::e( 'method' ); ?></dt><dd><?php echo esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) ); ?></dd></div>
				<div><dt><?php RAR_WAP_I18n::e( 'reference' ); ?></dt><dd><?php echo esc_html( (string) $order->get_meta( '_rar_wap_reference' ) ); ?></dd></div>
			</dl>

			<?php if ( 'resubmitted' === $msg ) : ?>
				<p class="rw-alert is-ok"><?php RAR_WAP_I18n::e( 'resubmitted' ); ?></p>
			<?php elseif ( 'proof' === $msg ) : ?>
				<p class="rw-alert is-ok"><?php RAR_WAP_I18n::e( 'proof_received' ); ?></p>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<p class="rw-alert is-bad"><?php echo esc_html( (string) $error ); ?></p>
			<?php endif; ?>

			<?php
			if ( 'unverified' === $status && ! $closed ) {
				$reason = (string) $order->get_meta( '_rar_wap_reject_reason' );
				if ( $reason ) {
					echo '<p class="rw-alert is-bad">' . RAR_WAP_I18n::h( 'reason' ) . ': ' . esc_html( $reason ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				if ( 'no' !== ( $settings['allow_resubmit'] ?? 'yes' ) ) {
					self::resubmit_form( $order );
				}
			}

			if ( 'verified' !== $status && ! $closed && 'proof' !== $msg && 'no' !== ( $settings['proof_upload'] ?? 'yes' ) ) {
				self::proof_form( $order );
			}
			?>
		</section>
		<?php
	}

	private static function hidden_fields( WC_Order $order, $action ) {
		wp_nonce_field( 'rar_wap_customer_' . $order->get_id(), 'rar_wap_nonce' );
		echo '<input type="hidden" name="rar_wap_action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="rar_wap_order" value="' . esc_attr( (string) $order->get_id() ) . '">';
		echo '<input type="hidden" name="rar_wap_key" value="' . esc_attr( $order->get_order_key() ) . '">';
	}

	private static function resubmit_form( WC_Order $order ) {
		?>
		<form method="post" class="rw-form">
			<h3><?php RAR_WAP_I18n::e( 'fix_title' ); ?></h3>
			<?php self::hidden_fields( $order, 'resubmit' ); ?>
			<p class="rw-field">
				<label for="rar_wap_payer_fix"><?php RAR_WAP_I18n::e( 'payer_label_generic' ); ?></label>
				<input type="text" id="rar_wap_payer_fix" name="rar_wap_payer" inputmode="tel" maxlength="80" required value="<?php echo esc_attr( (string) $order->get_meta( '_rar_wap_payer' ) ); ?>">
			</p>
			<p class="rw-field">
				<label for="rar_wap_reference_fix"><?php RAR_WAP_I18n::e( 'trx_label' ); ?></label>
				<input type="text" id="rar_wap_reference_fix" name="rar_wap_reference" autocapitalize="characters" spellcheck="false" maxlength="60" required placeholder="<?php echo esc_attr( RAR_WAP_I18n::t( 'ph_trx' ) ); ?>">
			</p>
			<button type="submit" class="rw-btn"><?php RAR_WAP_I18n::e( 'fix_button' ); ?></button>
		</form>
		<?php
	}

	private static function proof_form( WC_Order $order ) {
		if ( $order->get_meta( '_rar_wap_proof_file' ) ) {
			echo '<p class="rw-alert is-ok">📎 ' . RAR_WAP_I18n::h( 'proof_received' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		?>
		<form method="post" enctype="multipart/form-data" class="rw-form">
			<h3><?php RAR_WAP_I18n::e( 'proof_title' ); ?></h3>
			<p class="rw-muted"><?php RAR_WAP_I18n::e( 'proof_help' ); ?></p>
			<?php self::hidden_fields( $order, 'proof' ); ?>
			<div class="rw-file">
				<input type="file" name="rar_wap_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required aria-label="<?php echo esc_attr( RAR_WAP_I18n::t( 'proof_choose' ) ); ?>">
				<button type="submit" class="rw-btn"><?php RAR_WAP_I18n::e( 'proof_button' ); ?></button>
			</div>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Form handling
	 * ------------------------------------------------------------------ */

	public static function handle_post() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['rar_wap_action'] ) ) {
			return;
		}

		$order_id = isset( $_POST['rar_wap_order'] ) ? absint( $_POST['rar_wap_order'] ) : 0;
		$key      = isset( $_POST['rar_wap_key'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_key'] ) ) : '';
		$action   = sanitize_key( wp_unslash( $_POST['rar_wap_action'] ) );
		$order    = wc_get_order( $order_id );

		if ( ! $order || ! RAR_WAP_Order::is_rar_order( $order ) || ! hash_equals( $order->get_order_key(), $key ) ) {
			return;
		}
		if ( ! isset( $_POST['rar_wap_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rar_wap_nonce'] ) ), 'rar_wap_customer_' . $order_id ) ) {
			return;
		}
		if ( $order->get_customer_id() && get_current_user_id() !== $order->get_customer_id() && ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$settings = RAR_WAP_Plugin::settings();
		$msg      = '';
		$error    = '';

		if ( 'resubmit' === $action && 'no' !== ( $settings['allow_resubmit'] ?? 'yes' ) ) {
			$gateway = RAR_WAP_Plugin::gateway();
			if ( $gateway ) {
				$channel = (string) $order->get_meta( '_rar_wap_channel' );
				$check   = $gateway->check_submission(
					$channel,
					isset( $_POST['rar_wap_payer'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_payer'] ) ) : '',
					isset( $_POST['rar_wap_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['rar_wap_reference'] ) ) : '',
					$order_id,
					// Keep the channel the customer originally paid with, even if later disabled.
					array( $channel => array( 'type' => self::channel_type( $channel ) ) )
				);
				if ( $check['error'] ) {
					$error = $check['error'];
				} else {
					$result = RAR_WAP_Order::resubmit( $order, $check['payer'], $check['reference'] );
					if ( is_wp_error( $result ) ) {
						$error = $result->get_error_message();
					} else {
						$msg = 'resubmitted';
					}
				}
			}
		} elseif ( 'proof' === $action && 'no' !== ( $settings['proof_upload'] ?? 'yes' ) ) {
			$result = RAR_WAP_Proofs::handle_customer_upload( $order );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$msg = 'proof';
			}
		}

		if ( $error ) {
			set_transient( 'rar_wap_err_' . $order_id, $error, 5 * MINUTE_IN_SECONDS );
		}

		$back = wp_get_referer();
		$back = $back ? remove_query_arg( array( 'rar_wap_msg', 'rar_wap_access' ), $back ) : $order->get_checkout_order_received_url();
		if ( ! $order->get_customer_id() ) {
			$back = add_query_arg( 'rar_wap_access', self::access_token( $order, 2 * HOUR_IN_SECONDS ), $back );
		}
		wp_safe_redirect( $msg ? add_query_arg( 'rar_wap_msg', $msg, $back ) : $back );
		exit;
	}

	private static function channel_type( $channel ) {
		return isset( RAR_WAP_Gateway::CHANNELS[ $channel ] ) ? RAR_WAP_Gateway::CHANNELS[ $channel ][0] : 'custom';
	}
}

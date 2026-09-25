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
	}

	/**
	 * Best URL for the customer to see their order (guest-safe).
	 */
	public static function customer_order_url( WC_Order $order ) {
		if ( $order->get_customer_id() ) {
			return $order->get_view_order_url();
		}
		return $order->get_checkout_order_received_url();
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
				'label' => RAR_WAP_I18n::t( 'due_on_delivery' ) . ':',
				'value' => wc_price( RAR_WAP_Order::collectable_amount( $order ), array( 'currency' => $order->get_currency() ) ),
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
		$collect = RAR_WAP_Order::collectable_amount( $order );

		if ( $plain_text ) {
			echo "\n" . esc_html( RAR_WAP_I18n::t( 'payment_status' ) ) . "\n";
			echo esc_html( RAR_WAP_I18n::t( 'method' ) ) . ': ' . esc_html( $channel ) . "\n";
			echo esc_html( RAR_WAP_I18n::t( 'advance' ) ) . ': ' . esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ) ) ) . ' — ' . esc_html( RAR_WAP_I18n::t( 'status_' . $status ) ) . "\n";
			echo esc_html( RAR_WAP_I18n::t( 'due_on_delivery' ) ) . ': ' . esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $collect, array( 'currency' => $order->get_currency() ) ) ) ) ) . "\n\n";
			return;
		}
		?>
		<div style="margin:0 0 24px;padding:14px 16px;border:1px solid #e5e7eb;border-radius:8px">
			<h3 style="margin:0 0 8px"><?php echo esc_html( RAR_WAP_I18n::t( 'payment_status' ) ); ?></h3>
			<p style="margin:3px 0"><strong><?php echo esc_html( RAR_WAP_I18n::t( 'method' ) ); ?>:</strong> <?php echo esc_html( $channel ); ?></p>
			<p style="margin:3px 0"><strong><?php echo esc_html( RAR_WAP_I18n::t( 'advance' ) ); ?>:</strong> <?php echo wp_kses_post( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ); ?> — <?php echo esc_html( RAR_WAP_I18n::t( 'status_' . $status ) ); ?></p>
			<p style="margin:3px 0"><strong><?php echo esc_html( RAR_WAP_I18n::t( 'due_on_delivery' ) ); ?>:</strong> <?php echo wp_kses_post( wc_price( $collect, array( 'currency' => $order->get_currency() ) ) ); ?></p>
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg      = isset( $_GET['rar_wap_msg'] ) ? sanitize_key( wp_unslash( $_GET['rar_wap_msg'] ) ) : '';
		$error    = get_transient( 'rar_wap_err_' . $order->get_id() );
		if ( $error ) {
			delete_transient( 'rar_wap_err_' . $order->get_id() );
		}
		?>
		<section class="rar-wap-order-card is-<?php echo esc_attr( $status ); ?>">
			<header>
				<h2><?php RAR_WAP_I18n::e( 'payment_status' ); ?></h2>
				<span class="rar-wap-pill is-<?php echo esc_attr( $status ); ?>"><?php RAR_WAP_I18n::e( 'status_' . $status ); ?></span>
			</header>

			<?php if ( 'resubmitted' === $msg ) : ?>
				<p class="rar-wap-alert is-success"><?php RAR_WAP_I18n::e( 'resubmitted' ); ?></p>
			<?php elseif ( 'proof' === $msg ) : ?>
				<p class="rar-wap-alert is-success"><?php RAR_WAP_I18n::e( 'proof_received' ); ?></p>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<p class="rar-wap-alert is-error"><?php echo esc_html( (string) $error ); ?></p>
			<?php endif; ?>

			<ol class="rar-wap-timeline">
				<li class="is-done"><?php RAR_WAP_I18n::e( 'step_submit' ); ?></li>
				<li class="<?php echo 'verified' === $status ? 'is-done' : ( 'unverified' === $status ? 'is-error' : 'is-current' ); ?>"><?php RAR_WAP_I18n::e( 'status_' . $status ); ?></li>
			</ol>

			<dl class="rar-wap-order-facts">
				<div><dt><?php RAR_WAP_I18n::e( 'method' ); ?></dt><dd><?php echo esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) ); ?></dd></div>
				<div><dt><?php RAR_WAP_I18n::e( 'advance' ); ?></dt><dd><?php echo wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_required_amount' ), array( 'currency' => $order->get_currency() ) ) ); ?></dd></div>
				<div><dt><?php RAR_WAP_I18n::e( 'due_on_delivery' ); ?></dt><dd><?php echo wp_kses_post( wc_price( RAR_WAP_Order::collectable_amount( $order ), array( 'currency' => $order->get_currency() ) ) ); ?></dd></div>
				<div><dt><?php RAR_WAP_I18n::e( 'reference' ); ?></dt><dd><code><?php echo esc_html( (string) $order->get_meta( '_rar_wap_reference' ) ); ?></code></dd></div>
			</dl>

			<?php if ( 'verified' === $status ) : ?>
				<p class="rar-wap-order-next"><?php RAR_WAP_I18n::e( 'next_step_verified' ); ?></p>
			<?php elseif ( 'submitted' === $status ) : ?>
				<p class="rar-wap-order-next"><?php RAR_WAP_I18n::e( 'next_step_pending' ); ?></p>
			<?php endif; ?>

			<?php
			if ( 'unverified' === $status && ! $closed ) {
				$reason = (string) $order->get_meta( '_rar_wap_reject_reason' );
				if ( $reason ) {
					echo '<p class="rar-wap-alert is-error"><strong>' . RAR_WAP_I18n::h( 'reason' ) . ':</strong> ' . esc_html( $reason ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				if ( 'no' !== ( $settings['allow_resubmit'] ?? 'yes' ) ) {
					self::resubmit_form( $order );
				}
			}

			if ( 'verified' !== $status && ! $closed && 'no' !== ( $settings['proof_upload'] ?? 'yes' ) ) {
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
		<form method="post" class="rar-wap-customer-form">
			<h3><?php RAR_WAP_I18n::e( 'update_details' ); ?></h3>
			<p class="rar-wap-help"><?php RAR_WAP_I18n::e( 'update_help' ); ?></p>
			<?php self::hidden_fields( $order, 'resubmit' ); ?>
			<p>
				<label for="rar_wap_payer_fix"><?php RAR_WAP_I18n::e( 'payer_label' ); ?></label>
				<input type="text" id="rar_wap_payer_fix" name="rar_wap_payer" inputmode="tel" maxlength="80" required value="<?php echo esc_attr( (string) $order->get_meta( '_rar_wap_payer' ) ); ?>">
			</p>
			<p>
				<label for="rar_wap_reference_fix"><?php RAR_WAP_I18n::e( 'trx_label' ); ?></label>
				<input type="text" id="rar_wap_reference_fix" name="rar_wap_reference" autocapitalize="characters" spellcheck="false" maxlength="60" required placeholder="<?php echo esc_attr( RAR_WAP_I18n::t( 'ph_trx' ) ); ?>">
			</p>
			<p class="rar-wap-safety"><strong>🔒</strong> <?php RAR_WAP_I18n::e( 'security' ); ?></p>
			<button type="submit" class="button alt"><?php RAR_WAP_I18n::e( 'resubmit_button' ); ?></button>
		</form>
		<?php
	}

	private static function proof_form( WC_Order $order ) {
		if ( $order->get_meta( '_rar_wap_proof_file' ) ) {
			echo '<p class="rar-wap-alert is-success">📎 ' . RAR_WAP_I18n::h( 'proof_received' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		?>
		<form method="post" enctype="multipart/form-data" class="rar-wap-customer-form">
			<h3><?php RAR_WAP_I18n::e( 'proof_title' ); ?></h3>
			<p class="rar-wap-help"><?php RAR_WAP_I18n::e( 'proof_help' ); ?></p>
			<?php self::hidden_fields( $order, 'proof' ); ?>
			<p><input type="file" name="rar_wap_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></p>
			<button type="submit" class="button"><?php RAR_WAP_I18n::e( 'proof_button' ); ?></button>
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
		$back = $back ? $back : self::customer_order_url( $order );
		$back = remove_query_arg( 'rar_wap_msg', $back );
		wp_safe_redirect( $msg ? add_query_arg( 'rar_wap_msg', $msg, $back ) : $back );
		exit;
	}

	private static function channel_type( $channel ) {
		return isset( RAR_WAP_Gateway::CHANNELS[ $channel ] ) ? RAR_WAP_Gateway::CHANNELS[ $channel ][0] : 'custom';
	}
}

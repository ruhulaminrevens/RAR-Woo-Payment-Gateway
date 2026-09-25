<?php
/**
 * Background automation (Action Scheduler): overdue reminders, auto-cancel of
 * uncorrected rejected payments, and signed webhooks.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Automation {

	const GROUP = 'rar-wap';

	public static function init() {
		add_action( 'rar_wap_maintenance', array( __CLASS__, 'run_maintenance' ) );
		add_action( 'rar_wap_dispatch_webhook', array( __CLASS__, 'dispatch' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_scheduled' ) );
	}

	private static function has_scheduler() {
		return function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_has_scheduled_action' );
	}

	public static function ensure_scheduled() {
		if ( ! self::has_scheduler() || get_transient( 'rar_wap_schedule_checked' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( 'rar_wap_maintenance', array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + 5 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 'rar_wap_maintenance', array(), self::GROUP );
		}
		set_transient( 'rar_wap_schedule_checked', 1, 12 * HOUR_IN_SECONDS );
	}

	public static function status_text() {
		$settings = RAR_WAP_Plugin::settings();
		$overdue  = absint( $settings['overdue_hours'] ?? 6 );
		$cancel   = absint( $settings['auto_cancel_hours'] ?? 0 );

		$parts   = array();
		$parts[] = $overdue
			/* translators: %d: hours */
			? sprintf( __( 'Overdue reminder after %d h', 'rar-woo-advance-payment' ), $overdue )
			: __( 'Overdue reminder off', 'rar-woo-advance-payment' );
		$parts[] = $cancel
			/* translators: %d: hours */
			? sprintf( __( 'auto-cancel rejected after %d h', 'rar-woo-advance-payment' ), $cancel )
			: __( 'auto-cancel off', 'rar-woo-advance-payment' );
		$parts[] = self::has_scheduler() ? __( 'runs hourly', 'rar-woo-advance-payment' ) : __( 'Action Scheduler unavailable', 'rar-woo-advance-payment' );

		return implode( ' · ', $parts ) . '.';
	}

	/**
	 * @return array{overdue:int,cancelled:int}
	 */
	public static function run_maintenance() {
		$settings = RAR_WAP_Plugin::settings();
		$report   = array(
			'overdue'   => 0,
			'cancelled' => 0,
		);

		if ( 'yes' !== ( $settings['enabled'] ?? 'no' ) && ! RAR_WAP_Query::pending_count( true ) ) {
			return $report;
		}

		// 1) Overdue verification digest (each order reported once).
		$hours = absint( $settings['overdue_hours'] ?? 6 );
		if ( $hours ) {
			$ids = RAR_WAP_Query::list_ids(
				array(
					'status'       => 'submitted',
					'older_than'   => wp_date( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS ),
					'meta_missing' => '_rar_wap_overdue_notified',
				),
				1,
				50
			);

			$orders = array();
			foreach ( $ids['ids'] as $id ) {
				$order = wc_get_order( $id );
				if ( $order && ! $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
					$orders[] = $order;
				}
			}

			if ( $orders && RAR_WAP_Emails::overdue_digest( $orders, $hours ) ) {
				foreach ( $orders as $order ) {
					$order->update_meta_data( '_rar_wap_overdue_notified', current_time( 'mysql' ) );
					$order->save_meta_data();
				}
				$report['overdue'] = count( $orders );
			}
		}

		// 2) Auto-cancel rejected payments that were never corrected.
		$cancel = absint( $settings['auto_cancel_hours'] ?? 0 );
		if ( $cancel ) {
			$ids = RAR_WAP_Query::list_ids(
				array(
					'status'          => 'unverified',
					'rejected_before' => wp_date( 'Y-m-d H:i:s', time() - $cancel * HOUR_IN_SECONDS ),
					'order_status_in' => array( 'on-hold', 'pending' ),
				),
				1,
				25
			);
			foreach ( $ids['ids'] as $id ) {
				$order = wc_get_order( $id );
				if ( ! $order || 'unverified' !== RAR_WAP_Order::get_status( $order ) ) {
					continue;
				}
				$order->update_status(
					'cancelled',
					/* translators: %d: hours */
					sprintf( __( 'Auto-cancelled: advance payment was rejected and not corrected within %d hours.', 'rar-woo-advance-payment' ), $cancel )
				);
				RAR_WAP_Order::fire( 'auto_cancelled', $order );
				++$report['cancelled'];
			}
		}

		RAR_WAP_Plugin::log( 'Maintenance run.', 'info', $report );
		return $report;
	}

	/* ------------------------------------------------------------------ *
	 * Webhooks
	 * ------------------------------------------------------------------ */

	private static function webhook_url() {
		$url = esc_url_raw( (string) ( RAR_WAP_Plugin::settings()['webhook_url'] ?? '' ) );
		return ( $url && wp_http_validate_url( $url ) ) ? $url : '';
	}

	public static function queue_webhook( $order_id, $event ) {
		if ( ! self::webhook_url() ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'rar_wap_dispatch_webhook', array( absint( $order_id ), sanitize_key( $event ) ), self::GROUP );
			return;
		}
		self::dispatch( $order_id, $event );
	}

	/**
	 * @return int|WP_Error HTTP status code.
	 */
	private static function post( array $payload ) {
		$url = self::webhook_url();
		if ( ! $url ) {
			return new WP_Error( 'rar_wap_webhook', __( 'Webhook URL is not configured or not valid.', 'rar-woo-advance-payment' ) );
		}

		$body    = wp_json_encode( $payload );
		$secret  = (string) ( RAR_WAP_Plugin::settings()['webhook_secret'] ?? '' );
		$headers = array(
			'Content-Type'      => 'application/json; charset=utf-8',
			'User-Agent'        => 'RAR-WAP/' . RAR_WAP_VERSION . '; ' . home_url(),
			'X-RAR-WAP-Event'   => $payload['event'],
			'X-RAR-WAP-Delivery' => $payload['delivery_id'],
		);
		if ( '' !== $secret ) {
			$headers['X-RAR-WAP-Signature'] = 'sha256=' . hash_hmac( 'sha256', (string) $body, $secret );
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP code */
			return new WP_Error( 'rar_wap_webhook_http', sprintf( __( 'Webhook endpoint answered HTTP %d.', 'rar-woo-advance-payment' ), $code ) );
		}
		return $code;
	}

	public static function dispatch( $order_id, $event ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! RAR_WAP_Order::is_rar_order( $order ) ) {
			return;
		}

		$result = self::post(
			array(
				'event'       => sanitize_key( $event ),
				'delivery_id' => wp_generate_uuid4(),
				'occurred_at' => gmdate( 'c' ),
				'site'        => home_url(),
				'payment'     => RAR_WAP_Order::snapshot( $order ),
			)
		);

		if ( is_wp_error( $result ) ) {
			RAR_WAP_Plugin::log( 'Webhook failed: ' . $result->get_error_message(), 'warning', array( 'order_id' => $order_id, 'event' => $event ) );
			// Let Action Scheduler mark the action failed so it is visible under Tools → Scheduled Actions.
			if ( doing_action( 'rar_wap_dispatch_webhook' ) ) {
				throw new Exception( esc_html( $result->get_error_message() ) );
			}
		}
	}

	/**
	 * @return int|WP_Error
	 */
	public static function send_test_webhook() {
		return self::post(
			array(
				'event'       => 'test',
				'delivery_id' => wp_generate_uuid4(),
				'occurred_at' => gmdate( 'c' ),
				'site'        => home_url(),
				'payment'     => null,
			)
		);
	}
}

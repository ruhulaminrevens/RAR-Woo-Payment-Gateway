<?php
/**
 * REST API: rar-wap/v1 — lets the staff app, ERP or other RAR plugins read and
 * act on advance payments.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_REST {

	const NS = 'rar-wap/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function permission() {
		return RAR_WAP_Plugin::can_manage()
			? true
			: new WP_Error( 'rest_forbidden', __( 'You need order-management permission.', 'rar-woo-advance-payment' ), array( 'status' => rest_authorization_required_code() ) );
	}

	public static function routes() {
		$date = array(
			'type'              => 'string',
			'validate_callback' => static function ( $value ) {
				return '' === $value || (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value );
			},
		);

		register_rest_route(
			self::NS,
			'/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'summary' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'from' => $date,
					'to'   => $date,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/payments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_payments' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'status'   => array(
						'type'    => 'string',
						'enum'    => array_merge( array( '' ), RAR_WAP_Order::STATUSES ),
						'default' => 'submitted',
					),
					'channel'  => array( 'type' => 'string' ),
					'search'   => array( 'type' => 'string' ),
					'from'     => $date,
					'to'       => $date,
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/payments/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_payment' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);

		foreach ( array( 'verify', 'reject', 'reset' ) as $op ) {
			register_rest_route(
				self::NS,
				'/payments/(?P<id>\d+)/' . $op,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'action_' . $op ),
					'permission_callback' => array( __CLASS__, 'permission' ),
					'args'                => array(
						'amount' => array( 'type' => array( 'number', 'string', 'null' ) ),
						'reason' => array(
							'type'    => 'string',
							'enum'    => array_keys( RAR_WAP_Order::REJECT_REASONS ),
							'default' => 'not_found',
						),
						'note'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'notify' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				)
			);
		}
	}

	private static function order_or_error( WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request['id'] ) );
		if ( ! RAR_WAP_Order::is_rar_order( $order ) ) {
			return new WP_Error( 'rar_wap_not_found', __( 'Advance-payment order not found.', 'rar-woo-advance-payment' ), array( 'status' => 404 ) );
		}
		return $order;
	}

	public static function summary( WP_REST_Request $request ) {
		$from = (string) $request->get_param( 'from' );
		$to   = (string) $request->get_param( 'to' );
		if ( '' === $from && '' === $to ) {
			$from = wp_date( 'Y-m-01' );
			$to   = wp_date( 'Y-m-d' );
		}

		$period = RAR_WAP_Query::grouped( array( 'date_from' => $from, 'date_to' => $to ) );
		$out    = array(
			'from'                  => $from,
			'to'                    => $to,
			'currency'              => get_woocommerce_currency(),
			'awaiting_verification' => array( 'orders' => 0, 'amount' => 0.0 ),
			'by_status'             => array(),
			'by_channel'            => array(),
		);

		foreach ( RAR_WAP_Query::grouped( array( 'status' => 'submitted' ) ) as $row ) {
			$out['awaiting_verification']['orders'] += $row['orders'];
			$out['awaiting_verification']['amount'] += $row['amount'];
		}

		foreach ( $period as $row ) {
			$status  = $row['status'];
			$channel = $row['channel'] ? $row['channel'] : 'unknown';
			$out['by_status'][ $status ]['orders']              = ( $out['by_status'][ $status ]['orders'] ?? 0 ) + $row['orders'];
			$out['by_status'][ $status ]['amount']              = ( $out['by_status'][ $status ]['amount'] ?? 0 ) + $row['amount'];
			$out['by_channel'][ $channel ][ $status ]['orders'] = ( $out['by_channel'][ $channel ][ $status ]['orders'] ?? 0 ) + $row['orders'];
			$out['by_channel'][ $channel ][ $status ]['amount'] = ( $out['by_channel'][ $channel ][ $status ]['amount'] ?? 0 ) + $row['amount'];
		}

		return rest_ensure_response( $out );
	}

	public static function list_payments( WP_REST_Request $request ) {
		$args = array(
			'status'  => (string) $request->get_param( 'status' ),
			'channel' => sanitize_key( (string) $request->get_param( 'channel' ) ),
			'search'  => sanitize_text_field( (string) $request->get_param( 'search' ) ),
		);
		if ( $request->get_param( 'from' ) ) {
			$args['date_from'] = $request->get_param( 'from' );
		}
		if ( $request->get_param( 'to' ) ) {
			$args['date_to'] = $request->get_param( 'to' );
		}

		$per_page = (int) $request->get_param( 'per_page' );
		$result   = RAR_WAP_Query::list_ids( $args, (int) $request->get_param( 'page' ), $per_page );
		$items    = array();
		foreach ( $result['ids'] as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$items[] = RAR_WAP_Order::snapshot( $order );
			}
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / max( 1, $per_page ) ) );
		return $response;
	}

	public static function get_payment( WP_REST_Request $request ) {
		$order = self::order_or_error( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$data            = RAR_WAP_Order::snapshot( $order );
		$data['history'] = RAR_WAP_Order::get_history( $order );
		return rest_ensure_response( $data );
	}

	private static function finish( $order, $result ) {
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 409 ) );
			return $result;
		}
		return rest_ensure_response( RAR_WAP_Order::snapshot( wc_get_order( $order->get_id() ) ) );
	}

	public static function action_verify( WP_REST_Request $request ) {
		$order = self::order_or_error( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$amount = $request->get_param( 'amount' );
		return self::finish(
			$order,
			RAR_WAP_Order::verify( $order, ( null === $amount || '' === $amount ) ? null : (float) $amount, (string) $request->get_param( 'note' ), array( 'notify' => (bool) $request->get_param( 'notify' ) ) )
		);
	}

	public static function action_reject( WP_REST_Request $request ) {
		$order = self::order_or_error( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		return self::finish(
			$order,
			RAR_WAP_Order::reject( $order, (string) $request->get_param( 'reason' ), (string) $request->get_param( 'note' ), array( 'notify' => (bool) $request->get_param( 'notify' ) ) )
		);
	}

	public static function action_reset( WP_REST_Request $request ) {
		$order = self::order_or_error( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		return self::finish( $order, RAR_WAP_Order::reset( $order, (string) $request->get_param( 'note' ) ) );
	}
}

<?php
/**
 * WooCommerce → Advance Payments: KPI dashboard, verification queue, channel
 * reconciliation and CSV export.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Dashboard {

	const SLUG = 'rar-wap-payments';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_rar_wap_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'wp_ajax_rar_wap_tool', array( __CLASS__, 'ajax_tool' ) );
	}

	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		$count = 0;
		if ( RAR_WAP_Plugin::can_manage() ) {
			$count = RAR_WAP_Query::pending_count();
		}
		$badge = $count ? ' <span class="awaiting-mod count-' . absint( $count ) . '"><span class="pending-count">' . absint( $count ) . '</span></span>' : '';

		add_submenu_page(
			'woocommerce',
			__( 'Advance Payments', 'rar-woo-advance-payment' ),
			__( 'Advance Payments', 'rar-woo-advance-payment' ) . $badge,
			'edit_shop_orders',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Sanitised filters from the request.
	 */
	private static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$get = static function ( $key ) {
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};
		// phpcs:enable

		$date = static function ( $value ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ? $value : '';
		};

		$from = $date( $get( 'from' ) );
		$to   = $date( $get( 'to' ) );
		if ( '' === $from && '' === $to ) {
			$from = wp_date( 'Y-m-01' );
			$to   = wp_date( 'Y-m-d' );
		}

		$status = $get( 'status' );
		$status = in_array( $status, array_merge( RAR_WAP_Order::STATUSES, array( 'all' ) ), true ) ? $status : 'submitted';

		return array(
			'from'    => $from,
			'to'      => $to,
			'status'  => $status,
			'channel' => sanitize_key( $get( 'channel' ) ),
			'search'  => $get( 's' ),
			'paged'   => max( 1, absint( $get( 'paged' ) ) ),
		);
	}

	/**
	 * Query args for the queue. Pending items ignore the period so nothing is missed.
	 */
	private static function queue_args( array $f ) {
		$args = array(
			'status'  => 'all' === $f['status'] ? '' : $f['status'],
			'channel' => $f['channel'],
			'search'  => $f['search'],
		);
		if ( 'submitted' !== $f['status'] ) {
			$args['date_from'] = $f['from'];
			$args['date_to']   = $f['to'];
		}
		return $args;
	}

	private static function price( $amount ) {
		return wp_kses_post( wc_price( (float) $amount ) );
	}

	public static function render() {
		if ( ! RAR_WAP_Plugin::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'rar-woo-advance-payment' ) );
		}

		$f        = self::filters();
		$settings = RAR_WAP_Plugin::settings();
		$gateway  = RAR_WAP_Plugin::gateway();
		$channels = $gateway ? $gateway->get_enabled_channels() : array();

		// KPIs.
		$pending = array( 'orders' => 0, 'amount' => 0.0 );
		foreach ( RAR_WAP_Query::grouped( array( 'status' => 'submitted' ) ) as $row ) {
			$pending['orders'] += $row['orders'];
			$pending['amount'] += $row['amount'];
		}

		$verified = array( 'orders' => 0, 'amount' => 0.0 );
		foreach ( RAR_WAP_Query::grouped( array( 'status' => 'verified', 'date_field' => 'verified', 'date_from' => $f['from'], 'date_to' => $f['to'] ) ) as $row ) {
			$verified['orders'] += $row['orders'];
			$verified['amount'] += $row['amount'];
		}

		$period     = RAR_WAP_Query::grouped( array( 'date_from' => $f['from'], 'date_to' => $f['to'] ) );
		$by_channel = array();
		$submitted  = 0;
		$rejected   = 0;
		foreach ( $period as $row ) {
			$key = $row['channel'] ? $row['channel'] : 'unknown';
			if ( ! isset( $by_channel[ $key ] ) ) {
				$by_channel[ $key ] = array(
					'submitted'  => array( 0, 0.0 ),
					'verified'   => array( 0, 0.0 ),
					'unverified' => array( 0, 0.0 ),
					'total'      => array( 0, 0.0 ),
				);
			}
			$by_channel[ $key ][ $row['status'] ][0] += $row['orders'];
			$by_channel[ $key ][ $row['status'] ][1] += $row['amount'];
			$by_channel[ $key ]['total'][0]          += $row['orders'];
			$by_channel[ $key ]['total'][1]          += $row['amount'];
			$submitted                               += $row['orders'];
			if ( 'unverified' === $row['status'] ) {
				$rejected += $row['orders'];
			}
		}

		$overdue_hours = absint( $settings['overdue_hours'] ?? 6 );
		$overdue       = 0;
		if ( $overdue_hours ) {
			$cutoff  = wp_date( 'Y-m-d H:i:s', time() - $overdue_hours * HOUR_IN_SECONDS );
			$overdue = RAR_WAP_Query::list_ids( array( 'status' => 'submitted', 'older_than' => $cutoff ), 1, 1 )['total'];
		}

		$queue = RAR_WAP_Query::list_ids( self::queue_args( $f ), $f['paged'], 25 );

		echo '<div class="wrap rar-wap-dashboard">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Advance Payments', 'rar-woo-advance-payment' ) . '</h1> ';
		echo '<a class="page-title-action" href="' . esc_url( RAR_WAP_Plugin::settings_url() ) . '">' . esc_html__( 'Gateway settings', 'rar-woo-advance-payment' ) . '</a>';
		echo '<hr class="wp-header-end">';

		if ( $gateway && $gateway->is_safe_test_mode() && 'yes' === $gateway->enabled ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Safe Test Mode is ON — only administrators and shop managers can see the gateway at checkout.', 'rar-woo-advance-payment' ) . '</p></div>';
		}

		/* translators: 1: from date, 2: to date */
		echo '<p class="rar-wap-period">' . esc_html( sprintf( __( 'Period: %1$s → %2$s · Currency: %3$s · Basis: submission date (verified KPI uses verification date)', 'rar-woo-advance-payment' ), $f['from'], $f['to'], get_woocommerce_currency() ) ) . '</p>';

		$rate = $submitted ? round( ( $by_channel ? array_sum( array_map( static fn( $c ) => $c['verified'][0], $by_channel ) ) : 0 ) / $submitted * 100, 1 ) : 0;

		echo '<div class="rar-wap-kpis">';
		self::kpi( __( 'Awaiting verification', 'rar-woo-advance-payment' ), (string) $pending['orders'], self::price( $pending['amount'] ) . ' ' . esc_html__( 'claimed', 'rar-woo-advance-payment' ), $pending['orders'] ? 'is-attention' : '' );
		self::kpi( __( 'Overdue', 'rar-woo-advance-payment' ), (string) $overdue, $overdue_hours ? esc_html( sprintf( __( 'older than %d h', 'rar-woo-advance-payment' ), $overdue_hours ) ) : esc_html__( 'reminder off', 'rar-woo-advance-payment' ), $overdue ? 'is-danger' : '' );
		self::kpi( __( 'Verified (period)', 'rar-woo-advance-payment' ), (string) $verified['orders'], self::price( $verified['amount'] ) . ' ' . esc_html__( 'received', 'rar-woo-advance-payment' ), 'is-good' );
		self::kpi( __( 'Submitted (period)', 'rar-woo-advance-payment' ), (string) $submitted, esc_html( sprintf( __( 'verification rate %s%%', 'rar-woo-advance-payment' ), $rate ) ) );
		self::kpi( __( 'Rejected (period)', 'rar-woo-advance-payment' ), (string) $rejected, esc_html__( 'needs customer correction', 'rar-woo-advance-payment' ), $rejected ? 'is-warning' : '' );
		echo '</div>';

		// Filters.
		echo '<form method="get" class="rar-wap-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		echo '<label>' . esc_html__( 'From', 'rar-woo-advance-payment' ) . ' <input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"></label>';
		echo '<label>' . esc_html__( 'To', 'rar-woo-advance-payment' ) . ' <input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"></label>';
		echo '<label>' . esc_html__( 'Status', 'rar-woo-advance-payment' ) . ' <select name="status">';
		echo '<option value="all"' . selected( $f['status'], 'all', false ) . '>' . esc_html__( 'All', 'rar-woo-advance-payment' ) . '</option>';
		foreach ( RAR_WAP_Order::STATUSES as $status ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $f['status'], $status, false ) . '>' . esc_html( RAR_WAP_Order::status_label( $status ) ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Channel', 'rar-woo-advance-payment' ) . ' <select name="channel"><option value="">' . esc_html__( 'All', 'rar-woo-advance-payment' ) . '</option>';
		foreach ( $channels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['channel'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Search', 'rar-woo-advance-payment' ) . ' <input type="search" name="s" value="' . esc_attr( $f['search'] ) . '" placeholder="' . esc_attr__( 'Order #, TrxID or phone', 'rar-woo-advance-payment' ) . '"></label>';
		echo '<button class="button">' . esc_html__( 'Apply', 'rar-woo-advance-payment' ) . '</button>';

		$export = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'rar_wap_export',
					'from'    => $f['from'],
					'to'      => $f['to'],
					'status'  => $f['status'],
					'channel' => $f['channel'],
					's'       => $f['search'],
				),
				admin_url( 'admin-post.php' )
			),
			'rar_wap_export'
		);
		echo ' <a class="button button-secondary" href="' . esc_url( $export ) . '">⬇ ' . esc_html__( 'Export CSV', 'rar-woo-advance-payment' ) . '</a>';
		echo '</form>';

		if ( 'submitted' === $f['status'] ) {
			echo '<p class="description">' . esc_html__( 'The "Awaiting verification" queue shows every pending payment regardless of the period, oldest risk first at the bottom.', 'rar-woo-advance-payment' ) . '</p>';
		}

		self::queue_table( $queue['ids'] );
		self::pagination( $queue['total'], $f['paged'], 25 );

		// Channel reconciliation.
		echo '<h2>' . esc_html__( 'Channel reconciliation (period)', 'rar-woo-advance-payment' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Match "Verified" against your bKash/Nagad/Rocket merchant statements and bank statement for the same period. Amounts: requested advance until verified, verified amount afterwards.', 'rar-woo-advance-payment' ) . '</p>';
		echo '<table class="widefat striped rar-wap-recon"><thead><tr><th>' . esc_html__( 'Channel', 'rar-woo-advance-payment' ) . '</th><th>' . esc_html__( 'Submitted', 'rar-woo-advance-payment' ) . '</th><th>' . esc_html__( 'Verified', 'rar-woo-advance-payment' ) . '</th><th>' . esc_html__( 'Awaiting', 'rar-woo-advance-payment' ) . '</th><th>' . esc_html__( 'Rejected', 'rar-woo-advance-payment' ) . '</th></tr></thead><tbody>';
		if ( ! $by_channel ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No advance payments in this period.', 'rar-woo-advance-payment' ) . '</td></tr>';
		}
		$totals = array( 'total' => array( 0, 0.0 ), 'verified' => array( 0, 0.0 ), 'submitted' => array( 0, 0.0 ), 'unverified' => array( 0, 0.0 ) );
		foreach ( $by_channel as $key => $row ) {
			echo '<tr><td><strong>' . esc_html( $channels[ $key ] ?? ucfirst( $key ) ) . '</strong></td>';
			foreach ( array( 'total', 'verified', 'submitted', 'unverified' ) as $col ) {
				$totals[ $col ][0] += $row[ $col ][0];
				$totals[ $col ][1] += $row[ $col ][1];
				echo '<td>' . esc_html( (string) $row[ $col ][0] ) . ' · ' . self::price( $row[ $col ][1] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tr>';
		}
		if ( $by_channel ) {
			echo '<tr class="rar-wap-total"><td><strong>' . esc_html__( 'Total', 'rar-woo-advance-payment' ) . '</strong></td>';
			foreach ( array( 'total', 'verified', 'submitted', 'unverified' ) as $col ) {
				echo '<td><strong>' . esc_html( (string) $totals[ $col ][0] ) . ' · ' . self::price( $totals[ $col ][1] ) . '</strong></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		// Tools.
		echo '<h2>' . esc_html__( 'Tools & integrations', 'rar-woo-advance-payment' ) . '</h2><div class="rar-wap-tools">';
		echo '<div><strong>' . esc_html__( 'Automation', 'rar-woo-advance-payment' ) . '</strong><p>' . esc_html( RAR_WAP_Automation::status_text() ) . '</p><button type="button" class="button rar-wap-tool" data-tool="maintenance">' . esc_html__( 'Run checks now', 'rar-woo-advance-payment' ) . '</button></div>';
		echo '<div><strong>' . esc_html__( 'Webhook', 'rar-woo-advance-payment' ) . '</strong><p>' . ( empty( $settings['webhook_url'] ) ? esc_html__( 'Not configured.', 'rar-woo-advance-payment' ) : esc_html( wp_parse_url( $settings['webhook_url'], PHP_URL_HOST ) ) ) . '</p>';
		if ( ! empty( $settings['webhook_url'] ) ) {
			echo '<button type="button" class="button rar-wap-tool" data-tool="webhook">' . esc_html__( 'Send test event', 'rar-woo-advance-payment' ) . '</button>';
		}
		echo '</div>';
		echo '<div><strong>' . esc_html__( 'REST API', 'rar-woo-advance-payment' ) . '</strong><p><code>' . esc_html( rest_url( RAR_WAP_REST::NS ) ) . '</code><br><small>' . esc_html__( 'Endpoints: /summary, /payments, /payments/{order_id}, /payments/{order_id}/verify|reject|reset. Auth: logged-in cookie + nonce or Application Password (shop manager).', 'rar-woo-advance-payment' ) . '</small></p></div>';
		echo '<div><strong>' . esc_html__( 'Developer hooks', 'rar-woo-advance-payment' ) . '</strong><p><small><code>rar_wap_payment_event</code>, <code>rar_wap_payment_verified</code>, <code>rar_wap_payment_rejected</code>, <code>rar_wap_amount_due</code>, <code>rar_wap_collectable_amount</code>, <code>rar_wap_get_collectable_amount( $order )</code></small></p></div>';
		echo '</div><p class="rar-wap-tool-msg" role="status" aria-live="polite"></p>';

		echo '</div>';
	}

	private static function kpi( $label, $value, $sub_html, $class = '' ) {
		echo '<div class="rar-wap-kpi ' . esc_attr( $class ) . '"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong><small>' . $sub_html . '</small></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sub_html escaped by caller.
	}

	private static function queue_table( array $ids ) {
		echo '<table class="widefat striped rar-wap-queue"><thead><tr>';
		$cols = array(
			__( 'Order', 'rar-woo-advance-payment' ),
			__( 'Submitted', 'rar-woo-advance-payment' ),
			__( 'Customer', 'rar-woo-advance-payment' ),
			__( 'Channel', 'rar-woo-advance-payment' ),
			__( 'Paid from', 'rar-woo-advance-payment' ),
			__( 'Transaction ID', 'rar-woo-advance-payment' ),
			__( 'Advance', 'rar-woo-advance-payment' ),
			__( 'Collect', 'rar-woo-advance-payment' ),
			__( 'Status', 'rar-woo-advance-payment' ),
			__( 'Actions', 'rar-woo-advance-payment' ),
		);
		foreach ( $cols as $col ) {
			echo '<th>' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( ! $ids ) {
			echo '<tr><td colspan="10" class="rar-wap-empty">✓ ' . esc_html__( 'Nothing here. All caught up.', 'rar-woo-advance-payment' ) . '</td></tr>';
		}

		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			$status = RAR_WAP_Order::get_status( $order );
			$flags  = '';
			if ( absint( $order->get_meta( '_rar_wap_duplicate_of' ) ) ) {
				$flags .= ' <span class="rar-wap-flag is-danger" title="' . esc_attr__( 'Duplicate reference', 'rar-woo-advance-payment' ) . '">DUP</span>';
			}
			if ( $order->get_meta( '_rar_wap_proof_file' ) ) {
				$flags .= ' <a class="rar-wap-flag" target="_blank" href="' . esc_url( RAR_WAP_Proofs::admin_url( $order ) ) . '">📎</a>';
			}
			if ( absint( $order->get_meta( '_rar_wap_resubmission_count' ) ) ) {
				$flags .= ' <span class="rar-wap-flag" title="' . esc_attr__( 'Customer corrected details', 'rar-woo-advance-payment' ) . '">↺</span>';
			}

			echo '<tr data-order-id="' . esc_attr( (string) $order->get_id() ) . '" data-amount="' . esc_attr( (string) RAR_WAP_Order::requested_amount( $order ) ) . '">';
			echo '<td><a href="' . esc_url( $order->get_edit_order_url() ) . '"><strong>#' . esc_html( $order->get_order_number() ) . '</strong></a><br><small>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</small></td>';
			echo '<td>' . esc_html( (string) $order->get_meta( '_rar_wap_submitted_at' ) ) . '</td>';
			echo '<td>' . esc_html( $order->get_formatted_billing_full_name() ) . '<br><small>' . esc_html( $order->get_billing_phone() ) . '</small></td>';
			echo '<td>' . esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) ) . '</td>';
			echo '<td><code>' . esc_html( (string) $order->get_meta( '_rar_wap_payer' ) ) . '</code></td>';
			echo '<td><code>' . esc_html( (string) $order->get_meta( '_rar_wap_reference' ) ) . '</code>' . $flags . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . self::price( $order->get_meta( '_rar_wap_required_amount' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . self::price( RAR_WAP_Order::collectable_amount( $order ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="rar-wap-row-status"><span class="rar-wap-column-status ' . esc_attr( $status ) . '">' . esc_html( RAR_WAP_Order::status_label( $status ) ) . '</span></td>';
			echo '<td class="rar-wap-row-actions">';
			if ( 'submitted' === $status ) {
				echo '<button type="button" class="button button-primary button-small rar-wap-row-act" data-op="verify">' . esc_html__( 'Verify', 'rar-woo-advance-payment' ) . '</button> ';
				echo '<button type="button" class="button button-small rar-wap-row-act" data-op="reject">' . esc_html__( 'Reject', 'rar-woo-advance-payment' ) . '</button>';
			} else {
				echo '<a class="button button-small" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Open', 'rar-woo-advance-payment' ) . '</a>';
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	private static function pagination( $total, $paged, $per_page ) {
		$pages = (int) ceil( $total / $per_page );
		if ( $pages <= 1 ) {
			return;
		}

		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			)
		);
		echo '</div></div>';
	}

	/* ------------------------------------------------------------------ *
	 * CSV export
	 * ------------------------------------------------------------------ */

	private static function csv_cell( $value ) {
		$value = (string) $value;
		// Spreadsheet formula-injection guard.
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public static function export_csv() {
		if ( ! RAR_WAP_Plugin::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to export payments.', 'rar-woo-advance-payment' ) );
		}
		check_admin_referer( 'rar_wap_export' );

		$f    = self::filters();
		$args = self::queue_args( $f );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="advance-payments-' . sanitize_file_name( $f['from'] . '-to-' . $f['to'] . '-' . $f['status'] ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel (Bangla names).

		fputcsv(
			$out,
			array( 'Order ID', 'Order No', 'Order status', 'Submitted at', 'Customer', 'Phone', 'Channel', 'Paid to', 'Paid from', 'Transaction ID', 'Rule', 'Order total', 'Requested advance', 'Verified advance', 'Collect on delivery', 'Payment status', 'Verified at', 'Verified by', 'Reject reason', 'Corrections', 'Duplicate of', 'Currency' )
		);

		$page = 1;
		do {
			$batch = RAR_WAP_Query::list_ids( $args, $page, 500 );
			foreach ( $batch['ids'] as $id ) {
				$order = wc_get_order( $id );
				if ( ! $order ) {
					continue;
				}
				$s = RAR_WAP_Order::snapshot( $order );
				fputcsv(
					$out,
					array_map(
						array( __CLASS__, 'csv_cell' ),
						array(
							$s['order_id'],
							$s['order_number'],
							$s['order_status'],
							$s['submitted_at'],
							$s['customer_name'],
							$s['customer_phone'],
							$s['channel_label'],
							$s['destination'],
							$s['payer'],
							$s['reference'],
							$s['rule'],
							wc_format_decimal( $s['order_total'], 2 ),
							wc_format_decimal( $s['requested_amount'], 2 ),
							'verified' === $s['payment_status'] ? wc_format_decimal( $s['advance_amount'], 2 ) : '0.00',
							wc_format_decimal( $s['collect_on_delivery'], 2 ),
							$s['payment_status_label'],
							$s['verified_at'],
							$s['verified_by'],
							$s['reject_reason'],
							$s['resubmissions'],
							$s['duplicate_of'] ? $s['duplicate_of'] : '',
							$s['currency'],
						)
					)
				);
			}
			++$page;
		} while ( $batch['ids'] && ( $page - 1 ) * 500 < $batch['total'] );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Tools
	 * ------------------------------------------------------------------ */

	public static function ajax_tool() {
		check_ajax_referer( 'rar_wap_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only shop managers can run tools.', 'rar-woo-advance-payment' ) ), 403 );
		}

		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';

		if ( 'webhook' === $tool ) {
			$result = RAR_WAP_Automation::send_test_webhook();
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			/* translators: %d: HTTP code */
			wp_send_json_success( array( 'message' => sprintf( __( 'Test event delivered (HTTP %d).', 'rar-woo-advance-payment' ), $result ) ) );
		}

		if ( 'maintenance' === $tool ) {
			$report = RAR_WAP_Automation::run_maintenance();
			wp_send_json_success(
				array(
					/* translators: 1: overdue count, 2: cancelled count */
					'message' => sprintf( __( 'Checks complete. Overdue reminders sent for %1$d payment(s); %2$d rejected order(s) auto-cancelled.', 'rar-woo-advance-payment' ), $report['overdue'], $report['cancelled'] ),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'Unknown tool.', 'rar-woo-advance-payment' ) ), 400 );
	}
}

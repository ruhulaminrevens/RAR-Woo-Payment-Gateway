<?php
/**
 * WooCommerce → Advance Payments: live KPI dashboard, verification queue,
 * trend chart, channel mix, team speed, activity feed and CSV export.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Dashboard {

	const SLUG     = 'rar-wap-payments';
	const PER_PAGE = 20;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_rar_wap_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'wp_ajax_rar_wap_tool', array( __CLASS__, 'ajax_tool' ) );
		add_action( 'wp_ajax_rar_wap_poll', array( __CLASS__, 'ajax_poll' ) );
	}

	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		$count = RAR_WAP_Plugin::can_manage() ? RAR_WAP_Query::pending_count() : 0;
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

	/* ------------------------------------------------------------------ *
	 * Filters
	 * ------------------------------------------------------------------ */

	public static function ranges() {
		return array(
			'today'      => __( 'Today', 'rar-woo-advance-payment' ),
			'7d'         => __( 'Last 7 days', 'rar-woo-advance-payment' ),
			'30d'        => __( 'Last 30 days', 'rar-woo-advance-payment' ),
			'month'      => __( 'This month', 'rar-woo-advance-payment' ),
			'last_month' => __( 'Last month', 'rar-woo-advance-payment' ),
			'custom'     => __( 'Custom…', 'rar-woo-advance-payment' ),
		);
	}

	private static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$get = static function ( $key ) {
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};
		// phpcs:enable
		$date = static function ( $value ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ? $value : '';
		};

		$range = $get( 'range' );
		$from  = $date( $get( 'from' ) );
		$to    = $date( $get( 'to' ) );
		if ( ! isset( self::ranges()[ $range ] ) ) {
			$range = ( $from || $to ) ? 'custom' : 'month';
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local calendar maths.
		switch ( $range ) {
			case 'today':
				$from = $to = gmdate( 'Y-m-d', $now );
				break;
			case '7d':
				$from = gmdate( 'Y-m-d', $now - 6 * DAY_IN_SECONDS );
				$to   = gmdate( 'Y-m-d', $now );
				break;
			case '30d':
				$from = gmdate( 'Y-m-d', $now - 29 * DAY_IN_SECONDS );
				$to   = gmdate( 'Y-m-d', $now );
				break;
			case 'last_month':
				$first = strtotime( gmdate( 'Y-m-01', $now ) . ' -1 month' );
				$from  = gmdate( 'Y-m-01', $first );
				$to    = gmdate( 'Y-m-t', $first );
				break;
			case 'month':
				$from = gmdate( 'Y-m-01', $now );
				$to   = gmdate( 'Y-m-d', $now );
				break;
			default:
				$from = $from ? $from : gmdate( 'Y-m-01', $now );
				$to   = $to ? $to : gmdate( 'Y-m-d', $now );
		}
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}

		$status = $get( 'status' );
		$status = in_array( $status, array_merge( RAR_WAP_Order::STATUSES, array( 'all' ) ), true ) ? $status : 'submitted';

		return array(
			'range'   => $range,
			'from'    => $from,
			'to'      => $to,
			'status'  => $status,
			'channel' => sanitize_key( $get( 'channel' ) ),
			'search'  => $get( 's' ),
			'paged'   => max( 1, absint( $get( 'paged' ) ) ),
		);
	}

	/** Queue query. Pending and rejected items ignore the period so nothing is missed. */
	private static function queue_args( array $f ) {
		$args = array(
			'status'  => 'all' === $f['status'] ? '' : $f['status'],
			'channel' => $f['channel'],
			'search'  => $f['search'],
		);
		if ( in_array( $f['status'], array( 'verified', 'all' ), true ) ) {
			$args['date_from'] = $f['from'];
			$args['date_to']   = $f['to'];
		}
		return $args;
	}

	private static function link( array $f, array $change = array() ) {
		$args = array_merge(
			array(
				'range'   => $f['range'],
				'status'  => $f['status'],
				'channel' => $f['channel'],
				's'       => $f['search'],
			),
			$change
		);
		if ( 'custom' === $args['range'] ) {
			$args['from'] = $f['from'];
			$args['to']   = $f['to'];
		}
		return self::url(
			array_filter(
				$args,
				static function ( $value ) {
					return '' !== (string) $value;
				}
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	private static function money( $amount, $decimals = null ) {
		$args = array();
		if ( null !== $decimals ) {
			$args['decimals'] = $decimals;
		}
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
	}

	public static function local_ts( $mysql ) {
		if ( '' === (string) $mysql ) {
			return 0;
		}
		$ts = strtotime( get_gmt_from_date( $mysql ) . ' UTC' );
		return $ts ? $ts : 0;
	}

	public static function duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		if ( $seconds < HOUR_IN_SECONDS ) {
			/* translators: %d: minutes */
			return sprintf( __( '%dm', 'rar-woo-advance-payment' ), max( 1, (int) round( $seconds / 60 ) ) );
		}
		if ( $seconds < DAY_IN_SECONDS ) {
			/* translators: 1: hours, 2: minutes */
			return sprintf( __( '%1$dh %2$dm', 'rar-woo-advance-payment' ), (int) floor( $seconds / HOUR_IN_SECONDS ), (int) round( ( $seconds % HOUR_IN_SECONDS ) / 60 ) );
		}
		/* translators: 1: days, 2: hours */
		return sprintf( __( '%1$dd %2$dh', 'rar-woo-advance-payment' ), (int) floor( $seconds / DAY_IN_SECONDS ), (int) round( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ) );
	}

	public static function icon( $name ) {
		$paths = array(
			'clock'  => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 5v5.4l4 2.3-1 1.7-5-2.9V7h2z',
			'alert'  => 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
			'check'  => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.5 14.5-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7z',
			'bolt'   => 'M11 21h-1l1-7H7.5c-.6 0-.6-.3-.4-.6L13 3h1l-1 7h3.5c.5 0 .6.3.4.6L11 21z',
			'rate'   => 'M16 6l2.3 2.3-4.9 4.9-4-4L2 16.6 3.4 18l6-6 4 4 6.3-6.3L22 12V6z',
			'search' => 'M15.5 14h-.8l-.3-.3A6.5 6.5 0 1 0 14 15.5l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z',
			'copy'   => 'M8 3h9a2 2 0 0 1 2 2v11h-2V5H8V3zM5 7h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2zm0 2v10h9V9H5z',
			'phone'  => 'M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z',
			'chat'   => 'M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.8 0-1.4.7-2 1-2.3.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.2 1.4 2.5 1.5.3.2.5.1.6-.1l.9-1c.2-.3.4-.2.7-.1l1.9.9c.3.1.5.2.5.3.1.2.1.8-.1 1.3z',
			'file'   => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm0 7V3.5L19.5 9H14z',
			'cog'    => 'M19.4 13a7.7 7.7 0 0 0 0-2l2.1-1.6-2-3.5-2.5 1a7 7 0 0 0-1.7-1L15 3h-4l-.4 2.9a7 7 0 0 0-1.7 1l-2.5-1-2 3.5L6.6 11a7.7 7.7 0 0 0 0 2l-2.1 1.6 2 3.5 2.5-1c.5.4 1.1.7 1.7 1L11 21h4l.4-2.9a7 7 0 0 0 1.7-1l2.5 1 2-3.5-2.2-1.6zM13 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z',
			'down'   => 'M5 20h14v-2H5v2zm7-3 6-6-1.4-1.4-3.6 3.6V4h-2v9.2L7.4 9.6 6 11l6 6z',
			'user'   => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z',
		);
		return '<svg class="rwa-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( $paths[ $name ] ?? $paths['clock'] ) . '"/></svg>';
	}

	/* ------------------------------------------------------------------ *
	 * Page
	 * ------------------------------------------------------------------ */

	public static function render() {
		if ( ! RAR_WAP_Plugin::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'rar-woo-advance-payment' ) );
		}

		$f        = self::filters();
		$gateway  = RAR_WAP_Plugin::gateway();
		$channels = $gateway ? $gateway->get_enabled_channels() : array();
		$accent   = RAR_WAP_Gateway::accent_color();

		$export = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'rar_wap_export',
					'range'   => $f['range'],
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
		?>
		<div class="wrap rwa" style="--rwa-accent:<?php echo esc_attr( $accent ); ?>" data-poll="<?php echo esc_attr( RAR_WAP_Query::pending_count() . '|' . RAR_WAP_Query::latest_submission() ); ?>">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Advance Payments', 'rar-woo-advance-payment' ); ?></h1>
			<hr class="wp-header-end">

			<header class="rwa-hero">
				<div>
					<span class="rwa-live"><i></i><?php esc_html_e( 'Live', 'rar-woo-advance-payment' ); ?></span>
					<h2><?php esc_html_e( 'Advance Payments', 'rar-woo-advance-payment' ); ?></h2>
					<p><?php esc_html_e( 'Check every transfer, keep the COD amount right, and see how each channel performs.', 'rar-woo-advance-payment' ); ?></p>
				</div>
				<div class="rwa-hero-actions">
					<a class="rwa-btn is-glass" href="<?php echo esc_url( $export ); ?>"><?php echo self::icon( 'down' ); // phpcs:ignore ?><span><?php esc_html_e( 'Export CSV', 'rar-woo-advance-payment' ); ?></span></a>
					<a class="rwa-btn is-glass" href="<?php echo esc_url( RAR_WAP_Plugin::settings_url() ); ?>"><?php echo self::icon( 'cog' ); // phpcs:ignore ?><span><?php esc_html_e( 'Settings', 'rar-woo-advance-payment' ); ?></span></a>
				</div>
			</header>

			<?php if ( $gateway && 'yes' === $gateway->enabled && $gateway->is_safe_test_mode() ) : ?>
				<div class="rwa-banner is-info"><?php echo self::icon( 'alert' ); // phpcs:ignore ?><span><?php esc_html_e( 'Safe Test Mode is ON — only administrators and shop managers see the gateway at checkout.', 'rar-woo-advance-payment' ); ?></span> <a href="<?php echo esc_url( RAR_WAP_Plugin::settings_url() ); ?>"><?php esc_html_e( 'Go live', 'rar-woo-advance-payment' ); ?></a></div>
			<?php elseif ( ! $gateway || 'yes' !== $gateway->enabled ) : ?>
				<div class="rwa-banner is-warn"><?php echo self::icon( 'alert' ); // phpcs:ignore ?><span><?php esc_html_e( 'The gateway is disabled — customers cannot pay an advance yet.', 'rar-woo-advance-payment' ); ?></span> <a href="<?php echo esc_url( RAR_WAP_Plugin::settings_url() ); ?>"><?php esc_html_e( 'Enable', 'rar-woo-advance-payment' ); ?></a></div>
			<?php endif; ?>

			<div id="rwa-live">
				<?php self::render_live( $f, $channels ); ?>
			</div>

			<details class="rwa-card rwa-integrations">
				<summary><?php echo self::icon( 'bolt' ); // phpcs:ignore ?><span><?php esc_html_e( 'Automation & integrations', 'rar-woo-advance-payment' ); ?></span></summary>
				<?php self::render_tools(); ?>
			</details>

			<?php self::render_modal(); ?>
			<div class="rwa-toasts" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * The part of the page that refreshes live.
	 */
	private static function render_live( array $f, array $channels ) {
		$settings = RAR_WAP_Plugin::settings();

		// Queue tab counts.
		$counts = array_fill_keys( array( 'submitted', 'unverified', 'verified', 'all' ), 0 );
		$sums   = array( 'submitted' => 0.0 );
		foreach ( RAR_WAP_Query::grouped( array( 'channel' => $f['channel'] ) ) as $row ) {
			if ( 'submitted' === $row['status'] ) {
				$counts['submitted'] += $row['orders'];
				$sums['submitted']   += $row['amount'];
			} elseif ( 'unverified' === $row['status'] ) {
				$counts['unverified'] += $row['orders'];
			}
		}

		$period = RAR_WAP_Query::grouped( array( 'date_from' => $f['from'], 'date_to' => $f['to'] ) );
		$by_ch  = array();
		$sub_n  = 0;
		$ver_n  = 0;
		foreach ( $period as $row ) {
			$key = $row['channel'] ? $row['channel'] : 'unknown';
			if ( ! isset( $by_ch[ $key ] ) ) {
				$by_ch[ $key ] = array( 'submitted' => 0, 'verified' => 0, 'unverified' => 0, 'amount' => 0.0, 'orders' => 0 );
			}
			$by_ch[ $key ][ $row['status'] ] += $row['orders'];
			$by_ch[ $key ]['orders']         += $row['orders'];
			$by_ch[ $key ]['amount']         += 'verified' === $row['status'] ? $row['amount'] : 0;
			$sub_n                           += $row['orders'];
			$ver_n                           += 'verified' === $row['status'] ? $row['orders'] : 0;
			if ( ! $f['channel'] || $f['channel'] === $row['channel'] ) {
				$counts['all'] += $row['orders'];
			}
		}

		$verified_rows = RAR_WAP_Query::verified_rows( $f['from'], $f['to'] );
		$ver_amount    = 0.0;
		$durations     = array();
		$team          = array();
		foreach ( $verified_rows as $row ) {
			$ver_amount += $row['amount'];
			$start       = self::local_ts( $row['submitted'] );
			$end         = self::local_ts( $row['verified'] );
			if ( $start && $end >= $start ) {
				$durations[] = $end - $start;
			}
			$uid = $row['user'];
			if ( ! isset( $team[ $uid ] ) ) {
				$team[ $uid ] = array( 'n' => 0, 'amount' => 0.0, 't' => array() );
			}
			++$team[ $uid ]['n'];
			$team[ $uid ]['amount'] += $row['amount'];
			if ( $start && $end >= $start ) {
				$team[ $uid ]['t'][] = $end - $start;
			}
		}
		$counts['verified'] = count( $verified_rows );
		sort( $durations );
		$median = $durations ? $durations[ (int) floor( ( count( $durations ) - 1 ) / 2 ) ] : 0;

		$overdue_hours = absint( $settings['overdue_hours'] ?? 6 );
		$overdue       = 0;
		if ( $overdue_hours ) {
			$overdue = RAR_WAP_Query::list_ids( array( 'status' => 'submitted', 'older_than' => wp_date( 'Y-m-d H:i:s', time() - $overdue_hours * HOUR_IN_SECONDS ) ), 1, 1 )['total'];
		}
		$rate = $sub_n ? round( $ver_n / $sub_n * 100 ) : 0;

		// KPI cards.
		echo '<section class="rwa-kpis">';
		self::kpi( 'is-blue', 'clock', __( 'Awaiting verification', 'rar-woo-advance-payment' ), (string) $counts['submitted'], self::money( $sums['submitted'] ) . ' ' . __( 'claimed', 'rar-woo-advance-payment' ), self::link( $f, array( 'status' => 'submitted', 'paged' => '' ) ) );
		/* translators: %d: hours */
		self::kpi( $overdue ? 'is-red' : 'is-grey', 'alert', __( 'Overdue', 'rar-woo-advance-payment' ), (string) $overdue, $overdue_hours ? sprintf( __( 'waiting over %d hours', 'rar-woo-advance-payment' ), $overdue_hours ) : __( 'reminders off', 'rar-woo-advance-payment' ) );
		/* translators: %d: payments */
		self::kpi( 'is-green', 'check', __( 'Verified', 'rar-woo-advance-payment' ), self::money( $ver_amount, 0 ), sprintf( _n( '%d payment in period', '%d payments in period', $counts['verified'], 'rar-woo-advance-payment' ), $counts['verified'] ), self::link( $f, array( 'status' => 'verified', 'paged' => '' ) ) );
		self::kpi( 'is-violet', 'bolt', __( 'Typical check time', 'rar-woo-advance-payment' ), $durations ? self::duration( $median ) : '—', __( 'median, submit → verify', 'rar-woo-advance-payment' ) );
		/* translators: 1: verified, 2: submitted */
		self::kpi( 'is-amber', 'rate', __( 'Verification rate', 'rar-woo-advance-payment' ), $rate . '%', sprintf( __( '%1$d of %2$d submissions', 'rar-woo-advance-payment' ), $ver_n, $sub_n ), '', $rate );
		echo '</section>';

		echo '<div class="rwa-grid"><div class="rwa-main">';
		self::render_queue( $f, $channels, $counts );
		echo '</div><aside class="rwa-side">';
		self::render_trend();
		self::render_channels( $by_ch, $channels, $f );
		self::render_team( $team );
		self::render_activity();
		echo '</aside></div>';
	}

	private static function kpi( $tone, $icon, $label, $value, $sub, $url = '', $meter = null ) {
		$tag = $url ? 'a' : 'div';
		echo '<' . $tag . ' class="rwa-kpi ' . esc_attr( $tone ) . '"' . ( $url ? ' href="' . esc_url( $url ) . '"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="rwa-kpi-ico">' . self::icon( $icon ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="rwa-kpi-label">' . esc_html( $label ) . '</span>';
		echo '<strong class="rwa-kpi-value">' . esc_html( $value ) . '</strong>';
		if ( null !== $meter ) {
			echo '<span class="rwa-meter"><i style="width:' . esc_attr( (string) min( 100, max( 0, (int) $meter ) ) ) . '%"></i></span>';
		}
		echo '<span class="rwa-kpi-sub">' . esc_html( $sub ) . '</span>';
		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/* ------------------------------------------------------------------ *
	 * Queue
	 * ------------------------------------------------------------------ */

	private static function render_queue( array $f, array $channels, array $counts ) {
		$tabs = array(
			'submitted'  => __( 'Awaiting', 'rar-woo-advance-payment' ),
			'unverified' => __( 'Rejected', 'rar-woo-advance-payment' ),
			'verified'   => __( 'Verified', 'rar-woo-advance-payment' ),
			'all'        => __( 'All', 'rar-woo-advance-payment' ),
		);
		$queue = RAR_WAP_Query::list_ids( self::queue_args( $f ), $f['paged'], self::PER_PAGE );

		echo '<section class="rwa-card rwa-queue">';
		echo '<div class="rwa-card-head"><h3>' . esc_html__( 'Verification queue', 'rar-woo-advance-payment' ) . '</h3>';
		echo '<nav class="rwa-tabs" aria-label="' . esc_attr__( 'Payment status', 'rar-woo-advance-payment' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$on = $f['status'] === $key;
			echo '<a class="rwa-tab' . ( $on ? ' is-on' : '' ) . ( 'submitted' === $key && $counts['submitted'] ? ' has-dot' : '' ) . '" href="' . esc_url( self::link( $f, array( 'status' => $key, 'paged' => '' ) ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <b>' . esc_html( (string) $counts[ $key ] ) . '</b></a>';
		}
		echo '</nav></div>';

		// Toolbar.
		echo '<form class="rwa-toolbar" method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="status" value="' . esc_attr( $f['status'] ) . '">';
		echo '<label class="rwa-search">' . self::icon( 'search' ) . '<input type="search" name="s" value="' . esc_attr( $f['search'] ) . '" placeholder="' . esc_attr__( 'Order #, TrxID or phone', 'rar-woo-advance-payment' ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<select name="channel" aria-label="' . esc_attr__( 'Channel', 'rar-woo-advance-payment' ) . '"><option value="">' . esc_html__( 'All channels', 'rar-woo-advance-payment' ) . '</option>';
		foreach ( $channels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['channel'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<select name="range" class="rwa-range" aria-label="' . esc_attr__( 'Period', 'rar-woo-advance-payment' ) . '">';
		foreach ( self::ranges() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['range'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<span class="rwa-dates"' . ( 'custom' === $f['range'] ? '' : ' hidden' ) . '><input type="date" name="from" value="' . esc_attr( $f['from'] ) . '" aria-label="' . esc_attr__( 'From', 'rar-woo-advance-payment' ) . '"><input type="date" name="to" value="' . esc_attr( $f['to'] ) . '" aria-label="' . esc_attr__( 'To', 'rar-woo-advance-payment' ) . '"></span>';
		echo '<button class="rwa-btn is-primary">' . esc_html__( 'Apply', 'rar-woo-advance-payment' ) . '</button>';
		echo '</form>';

		/* translators: 1: from, 2: to */
		$period_text = sprintf( __( 'Period %1$s → %2$s', 'rar-woo-advance-payment' ), wp_date( 'd M Y', strtotime( $f['from'] . ' 12:00' ) ), wp_date( 'd M Y', strtotime( $f['to'] . ' 12:00' ) ) );
		$note        = in_array( $f['status'], array( 'submitted', 'unverified' ), true )
			? __( 'Showing every open item regardless of period, newest first.', 'rar-woo-advance-payment' )
			: $period_text . ' · ' . get_woocommerce_currency();
		echo '<p class="rwa-note">' . esc_html( $note ) . ' · ' . esc_html( sprintf( _n( '%d result', '%d results', $queue['total'], 'rar-woo-advance-payment' ), $queue['total'] ) ) . '</p>';

		if ( ! $queue['ids'] ) {
			echo '<div class="rwa-empty">' . self::icon( 'check' ) . '<strong>' . esc_html__( 'All caught up', 'rar-woo-advance-payment' ) . '</strong><span>' . esc_html__( 'Nothing to show for this filter.', 'rar-woo-advance-payment' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<div class="rwa-list" role="list">';
			foreach ( $queue['ids'] as $id ) {
				$order = wc_get_order( $id );
				if ( $order ) {
					self::render_row( $order );
				}
			}
			echo '</div>';
		}

		$pages = (int) ceil( $queue['total'] / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<nav class="rwa-pages">';
			if ( $f['paged'] > 1 ) {
				echo '<a class="rwa-btn" href="' . esc_url( self::link( $f, array( 'paged' => (string) ( $f['paged'] - 1 ) ) ) ) . '">‹ ' . esc_html__( 'Newer', 'rar-woo-advance-payment' ) . '</a>';
			}
			/* translators: 1: page, 2: pages */
			echo '<span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'rar-woo-advance-payment' ), $f['paged'], $pages ) ) . '</span>';
			if ( $f['paged'] < $pages ) {
				echo '<a class="rwa-btn" href="' . esc_url( self::link( $f, array( 'paged' => (string) ( $f['paged'] + 1 ) ) ) ) . '">' . esc_html__( 'Older', 'rar-woo-advance-payment' ) . ' ›</a>';
			}
			echo '</nav>';
		}
		echo '</section>';
	}

	private static function copy_chip( $value ) {
		if ( '' === (string) $value ) {
			return '<span class="rwa-muted">—</span>';
		}
		return '<button type="button" class="rwa-chip" data-copy="' . esc_attr( $value ) . '" title="' . esc_attr__( 'Copy', 'rar-woo-advance-payment' ) . '"><code>' . esc_html( $value ) . '</code>' . self::icon( 'copy' ) . '</button>';
	}

	private static function render_row( WC_Order $order ) {
		$status   = RAR_WAP_Order::get_status( $order );
		$settings = RAR_WAP_Plugin::settings();
		$hours    = absint( $settings['overdue_hours'] ?? 6 );
		$sub_ts   = self::local_ts( (string) $order->get_meta( '_rar_wap_submitted_at' ) );
		$age      = $sub_ts ? time() - $sub_ts : 0;
		$late     = 'submitted' === $status && $hours && $age > $hours * HOUR_IN_SECONDS;
		$channel  = (string) $order->get_meta( '_rar_wap_channel' );
		$tint     = RAR_WAP_Gateway::TINTS[ $channel ] ?? RAR_WAP_Gateway::TINTS['custom'];
		$currency = array( 'currency' => $order->get_currency() );
		$phone    = $order->get_billing_phone();
		$wa       = RAR_WAP_Admin::whatsapp_url( $order );

		echo '<article class="rwa-row is-' . esc_attr( $status ) . ( $late ? ' is-late' : '' ) . '" role="listitem" data-order-id="' . esc_attr( (string) $order->get_id() ) . '"'
			. ' data-number="' . esc_attr( $order->get_order_number() ) . '" data-amount="' . esc_attr( (string) RAR_WAP_Order::requested_amount( $order ) ) . '"'
			. ' data-ref="' . esc_attr( (string) $order->get_meta( '_rar_wap_reference' ) ) . '" data-payer="' . esc_attr( (string) $order->get_meta( '_rar_wap_payer' ) ) . '"'
			. ' data-channel="' . esc_attr( (string) $order->get_meta( '_rar_wap_channel_label' ) ) . '" data-total="' . esc_attr( wp_strip_all_tags( wc_price( (float) $order->get_total(), $currency ) ) ) . '">';

		// Order + customer.
		echo '<div class="rwa-row-who">';
		echo '<a class="rwa-order-no" href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
		echo '<span class="rwa-status-pill is-' . esc_attr( $status ) . '">' . esc_html( RAR_WAP_Order::status_label( $status ) ) . '</span>';
		echo '<strong class="rwa-name">' . esc_html( $order->get_formatted_billing_full_name() ) . '</strong>';
		echo '<span class="rwa-contact">';
		if ( $phone ) {
			echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '" title="' . esc_attr__( 'Call', 'rar-woo-advance-payment' ) . '">' . self::icon( 'phone' ) . esc_html( $phone ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $wa ) {
			echo '<a class="is-wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener" title="WhatsApp">' . self::icon( 'chat' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</span></div>';

		// Payment.
		echo '<div class="rwa-row-pay">';
		echo '<span class="rwa-channel" style="--rwa-tint:' . esc_attr( $tint ) . '"><i></i>' . esc_html( (string) $order->get_meta( '_rar_wap_channel_label' ) ) . '</span>';
		echo '<dl><div><dt>' . esc_html__( 'TrxID', 'rar-woo-advance-payment' ) . '</dt><dd>' . self::copy_chip( (string) $order->get_meta( '_rar_wap_reference' ) ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div><dt>' . esc_html__( 'From', 'rar-woo-advance-payment' ) . '</dt><dd>' . self::copy_chip( (string) $order->get_meta( '_rar_wap_payer' ) ) . '</dd></div></dl>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$flags = array();
		if ( absint( $order->get_meta( '_rar_wap_duplicate_of' ) ) ) {
			$flags[] = '<span class="rwa-flag is-red">' . esc_html__( 'Duplicate TrxID', 'rar-woo-advance-payment' ) . '</span>';
		}
		if ( $order->get_meta( '_rar_wap_proof_file' ) ) {
			$flags[] = '<a class="rwa-flag" target="_blank" rel="noopener" href="' . esc_url( RAR_WAP_Proofs::admin_url( $order ) ) . '">' . self::icon( 'file' ) . esc_html__( 'Screenshot', 'rar-woo-advance-payment' ) . '</a>';
		}
		if ( absint( $order->get_meta( '_rar_wap_resubmission_count' ) ) ) {
			$flags[] = '<span class="rwa-flag">' . esc_html__( 'Corrected', 'rar-woo-advance-payment' ) . '</span>';
		}
		if ( 'unverified' === $status && $order->get_meta( '_rar_wap_reject_reason' ) ) {
			$flags[] = '<span class="rwa-flag is-amber" title="' . esc_attr( (string) $order->get_meta( '_rar_wap_reject_reason' ) ) . '">' . esc_html( wp_trim_words( (string) $order->get_meta( '_rar_wap_reject_reason' ), 6 ) ) . '</span>';
		}
		if ( $flags ) {
			echo '<div class="rwa-flags">' . implode( '', $flags ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		// Money + time.
		echo '<div class="rwa-row-money">';
		echo '<strong>' . wp_kses_post( wc_price( (float) $order->get_meta( '_rar_wap_required_amount' ), $currency ) ) . '</strong>';
		echo '<span>' . esc_html__( 'collect', 'rar-woo-advance-payment' ) . ' ' . wp_kses_post( wc_price( RAR_WAP_Order::collectable_amount( $order ), $currency ) ) . '</span>';
		/* translators: %s: human time */
		echo '<time class="' . ( $late ? 'is-late' : '' ) . '" datetime="' . esc_attr( $sub_ts ? gmdate( 'c', $sub_ts ) : '' ) . '" title="' . esc_attr( (string) $order->get_meta( '_rar_wap_submitted_at' ) ) . '">' . self::icon( 'clock' ) . esc_html( $sub_ts ? sprintf( __( '%s ago', 'rar-woo-advance-payment' ), human_time_diff( $sub_ts ) ) : '—' ) . '</time>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		// Actions.
		echo '<div class="rwa-row-actions">';
		if ( 'submitted' === $status ) {
			echo '<button type="button" class="rwa-btn is-primary rwa-open" data-op="verify">' . esc_html__( 'Verify', 'rar-woo-advance-payment' ) . '</button>';
			echo '<button type="button" class="rwa-btn is-ghost rwa-open" data-op="reject">' . esc_html__( 'Reject', 'rar-woo-advance-payment' ) . '</button>';
		} else {
			echo '<button type="button" class="rwa-btn is-ghost rwa-open" data-op="reset">' . esc_html__( 'Reopen', 'rar-woo-advance-payment' ) . '</button>';
			echo '<a class="rwa-btn is-ghost" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Open', 'rar-woo-advance-payment' ) . '</a>';
		}
		echo '</div>';
		echo '</article>';
	}

	/* ------------------------------------------------------------------ *
	 * Side cards
	 * ------------------------------------------------------------------ */

	private static function render_trend() {
		$days  = 14;
		$now   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$from  = gmdate( 'Y-m-d', $now - ( $days - 1 ) * DAY_IN_SECONDS );
		$to    = gmdate( 'Y-m-d', $now );
		$data  = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$data[ gmdate( 'Y-m-d', $now - ( $days - 1 - $i ) * DAY_IN_SECONDS ) ] = array( 'verified' => 0, 'submitted' => 0, 'unverified' => 0, 'amount' => 0.0 );
		}
		foreach ( RAR_WAP_Query::daily( $from, $to ) as $row ) {
			if ( isset( $data[ $row['day'] ][ $row['status'] ] ) ) {
				$data[ $row['day'] ][ $row['status'] ] += $row['orders'];
				$data[ $row['day'] ]['amount']          += $row['amount'];
			}
		}

		$max   = 1;
		$total = 0;
		foreach ( $data as $d ) {
			$max    = max( $max, $d['verified'] + $d['submitted'] + $d['unverified'] );
			$total += $d['verified'] + $d['submitted'] + $d['unverified'];
		}

		$w     = 280;
		$h     = 110;
		$gap   = 4;
		$bw    = ( $w - $gap * ( $days - 1 ) ) / $days;
		$bars  = '';
		$i     = 0;
		$order = array( 'verified' => 'rwa-c-ok', 'submitted' => 'rwa-c-wait', 'unverified' => 'rwa-c-bad' );
		foreach ( $data as $day => $d ) {
			$x = $i * ( $bw + $gap );
			$y = $h;
			$n = $d['verified'] + $d['submitted'] + $d['unverified'];
			/* translators: 1: date, 2: submissions, 3: verified, 4: amount */
			$tip   = sprintf( __( '%1$s — %2$d submitted, %3$d verified, %4$s', 'rar-woo-advance-payment' ), wp_date( 'd M', strtotime( $day . ' 12:00' ) ), $n, $d['verified'], self::money( $d['amount'], 0 ) );
			$bars .= '<g><title>' . esc_html( $tip ) . '</title>';
			$bars .= '<rect class="rwa-c-bg" x="' . round( $x, 2 ) . '" y="0" width="' . round( $bw, 2 ) . '" height="' . $h . '" rx="3"/>';
			foreach ( $order as $key => $class ) {
				if ( ! $d[ $key ] ) {
					continue;
				}
				$bh    = max( 2, $d[ $key ] / $max * ( $h - 4 ) );
				$y    -= $bh;
				$bars .= '<rect class="' . $class . '" x="' . round( $x, 2 ) . '" y="' . round( $y, 2 ) . '" width="' . round( $bw, 2 ) . '" height="' . round( $bh, 2 ) . '" rx="3"/>';
			}
			$bars .= '</g>';
			++$i;
		}

		echo '<section class="rwa-card"><div class="rwa-card-head"><h3>' . esc_html__( 'Last 14 days', 'rar-woo-advance-payment' ) . '</h3><span class="rwa-muted">' . esc_html( sprintf( _n( '%d submission', '%d submissions', $total, 'rar-woo-advance-payment' ), $total ) ) . '</span></div>';
		echo '<svg class="rwa-chart" viewBox="0 0 ' . $w . ' ' . ( $h + 16 ) . '" role="img" aria-label="' . esc_attr__( 'Daily advance payments, last 14 days', 'rar-woo-advance-payment' ) . '">' . $bars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<text x="0" y="' . ( $h + 13 ) . '">' . esc_html( wp_date( 'd M', strtotime( $from . ' 12:00' ) ) ) . '</text><text x="' . $w . '" y="' . ( $h + 13 ) . '" text-anchor="end">' . esc_html__( 'Today', 'rar-woo-advance-payment' ) . '</text></svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="rwa-legend"><span><i class="rwa-c-ok"></i>' . esc_html__( 'Verified', 'rar-woo-advance-payment' ) . '</span><span><i class="rwa-c-wait"></i>' . esc_html__( 'Awaiting', 'rar-woo-advance-payment' ) . '</span><span><i class="rwa-c-bad"></i>' . esc_html__( 'Rejected', 'rar-woo-advance-payment' ) . '</span></div>';
		echo '</section>';
	}

	private static function render_channels( array $by_ch, array $channels, array $f ) {
		echo '<section class="rwa-card"><div class="rwa-card-head"><h3>' . esc_html__( 'Channels', 'rar-woo-advance-payment' ) . '</h3><span class="rwa-muted">' . esc_html__( 'this period', 'rar-woo-advance-payment' ) . '</span></div>';
		if ( ! $by_ch ) {
			echo '<p class="rwa-muted rwa-pad">' . esc_html__( 'No advance payments in this period.', 'rar-woo-advance-payment' ) . '</p></section>';
			return;
		}
		uasort(
			$by_ch,
			static function ( $a, $b ) {
				return $b['orders'] <=> $a['orders'];
			}
		);
		$max = max( array_column( $by_ch, 'orders' ) );
		echo '<ul class="rwa-bars">';
		foreach ( $by_ch as $key => $row ) {
			$tint = RAR_WAP_Gateway::TINTS[ $key ] ?? RAR_WAP_Gateway::TINTS['custom'];
			$pct  = static function ( $n ) use ( $max ) {
				return round( $n / max( 1, $max ) * 100, 2 );
			};
			echo '<li><a href="' . esc_url( self::link( $f, array( 'channel' => $key, 'status' => 'all', 'paged' => '' ) ) ) . '">';
			echo '<span class="rwa-bar-top"><span class="rwa-channel" style="--rwa-tint:' . esc_attr( $tint ) . '"><i></i>' . esc_html( $channels[ $key ] ?? ucfirst( $key ) ) . '</span><b>' . esc_html( self::money( $row['amount'], 0 ) ) . '</b></span>';
			echo '<span class="rwa-bar"><i class="rwa-c-ok" style="width:' . esc_attr( (string) $pct( $row['verified'] ) ) . '%"></i><i class="rwa-c-wait" style="width:' . esc_attr( (string) $pct( $row['submitted'] ) ) . '%"></i><i class="rwa-c-bad" style="width:' . esc_attr( (string) $pct( $row['unverified'] ) ) . '%"></i></span>';
			/* translators: 1: verified, 2: awaiting, 3: rejected */
			echo '<small>' . esc_html( sprintf( __( '%1$d verified · %2$d awaiting · %3$d rejected', 'rar-woo-advance-payment' ), $row['verified'], $row['submitted'], $row['unverified'] ) ) . '</small>';
			echo '</a></li>';
		}
		echo '</ul></section>';
	}

	private static function render_team( array $team ) {
		if ( ! $team ) {
			return;
		}
		uasort(
			$team,
			static function ( $a, $b ) {
				return $b['n'] <=> $a['n'];
			}
		);
		echo '<section class="rwa-card"><div class="rwa-card-head"><h3>' . esc_html__( 'Team', 'rar-woo-advance-payment' ) . '</h3><span class="rwa-muted">' . esc_html__( 'verified this period', 'rar-woo-advance-payment' ) . '</span></div><ul class="rwa-team">';
		foreach ( array_slice( $team, 0, 6, true ) as $uid => $row ) {
			$user = $uid ? get_user_by( 'id', $uid ) : false;
			$name = $user ? $user->display_name : __( 'System / API', 'rar-woo-advance-payment' );
			sort( $row['t'] );
			$median = $row['t'] ? $row['t'][ (int) floor( ( count( $row['t'] ) - 1 ) / 2 ) ] : 0;
			echo '<li><span class="rwa-avatar">' . esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span><span><strong>' . esc_html( $name ) . '</strong>';
			/* translators: %s: duration */
			echo '<small>' . esc_html( $row['t'] ? sprintf( __( 'typical %s', 'rar-woo-advance-payment' ), self::duration( $median ) ) : '' ) . '</small></span><b>' . esc_html( (string) $row['n'] ) . '</b></li>';
		}
		echo '</ul></section>';
	}

	private static function render_activity() {
		$labels = array(
			'submitted'      => __( 'submitted', 'rar-woo-advance-payment' ),
			'verified'       => __( 'verified', 'rar-woo-advance-payment' ),
			'rejected'       => __( 'rejected', 'rar-woo-advance-payment' ),
			'reset'          => __( 'reopened', 'rar-woo-advance-payment' ),
			'resubmitted'    => __( 'corrected by customer', 'rar-woo-advance-payment' ),
			'proof_uploaded' => __( 'screenshot uploaded', 'rar-woo-advance-payment' ),
		);
		$events = array();
		foreach ( RAR_WAP_Query::recent_activity_ids( 10 ) as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			foreach ( RAR_WAP_Order::get_history( $order ) as $item ) {
				$events[] = array( $item, $order );
			}
		}
		if ( ! $events ) {
			return;
		}
		usort(
			$events,
			static function ( $a, $b ) {
				return $b[0]['t'] <=> $a[0]['t'];
			}
		);

		echo '<section class="rwa-card"><div class="rwa-card-head"><h3>' . esc_html__( 'Recent activity', 'rar-woo-advance-payment' ) . '</h3></div><ol class="rwa-feed">';
		foreach ( array_slice( $events, 0, 8 ) as $event ) {
			list( $item, $order ) = $event;
			$user = ! empty( $item['u'] ) ? get_user_by( 'id', absint( $item['u'] ) ) : false;
			$who  = $user ? $user->display_name : $order->get_formatted_billing_full_name();
			echo '<li class="is-' . esc_attr( $item['a'] ) . '"><i></i><span><a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a> ' . esc_html( $labels[ $item['a'] ] ?? $item['a'] ) . ' <small>' . esc_html( $who ) . ' · ' . esc_html( $item['t'] ? human_time_diff( (int) $item['t'] ) : '' ) . '</small></span></li>';
		}
		echo '</ol></section>';
	}

	private static function render_tools() {
		$settings = RAR_WAP_Plugin::settings();
		echo '<div class="rwa-tools">';
		echo '<div><strong>' . esc_html__( 'Automation', 'rar-woo-advance-payment' ) . '</strong><p>' . esc_html( RAR_WAP_Automation::status_text() ) . '</p><button type="button" class="rwa-btn rwa-tool" data-tool="maintenance">' . esc_html__( 'Run checks now', 'rar-woo-advance-payment' ) . '</button></div>';
		echo '<div><strong>' . esc_html__( 'Webhook', 'rar-woo-advance-payment' ) . '</strong><p>' . ( empty( $settings['webhook_url'] ) ? esc_html__( 'Not configured. Add a URL in settings to send every payment event to an SMS gateway, Zapier or your ERP.', 'rar-woo-advance-payment' ) : esc_html( (string) wp_parse_url( $settings['webhook_url'], PHP_URL_HOST ) ) ) . '</p>';
		if ( ! empty( $settings['webhook_url'] ) ) {
			echo '<button type="button" class="rwa-btn rwa-tool" data-tool="webhook">' . esc_html__( 'Send test event', 'rar-woo-advance-payment' ) . '</button>';
		}
		echo '</div>';
		echo '<div><strong>' . esc_html__( 'REST API', 'rar-woo-advance-payment' ) . '</strong><p><code>' . esc_html( rest_url( RAR_WAP_REST::NS ) ) . '</code></p><p class="rwa-muted">' . esc_html__( '/summary · /payments · /payments/{id} · /payments/{id}/verify | reject | reset', 'rar-woo-advance-payment' ) . '</p></div>';
		echo '<div><strong>' . esc_html__( 'Developer hooks', 'rar-woo-advance-payment' ) . '</strong><p class="rwa-muted"><code>rar_wap_payment_event</code> <code>rar_wap_amount_due</code> <code>rar_wap_collectable_amount</code> <code>rar_wap_get_collectable_amount()</code></p></div>';
		echo '</div>';
	}

	private static function render_modal() {
		?>
		<div class="rwa-modal" id="rwa-modal" hidden>
			<div class="rwa-modal-backdrop" data-close></div>
			<form class="rwa-modal-box" role="dialog" aria-modal="true" aria-labelledby="rwa-modal-title">
				<header>
					<h2 id="rwa-modal-title"></h2>
					<button type="button" class="rwa-x" data-close aria-label="<?php esc_attr_e( 'Close', 'rar-woo-advance-payment' ); ?>">×</button>
				</header>
				<dl class="rwa-modal-facts"></dl>
				<div class="rwa-modal-body" data-for="verify">
					<p class="rwa-callout"><?php esc_html_e( 'Confirm this TrxID in your merchant app or bank statement before verifying.', 'rar-woo-advance-payment' ); ?></p>
					<label><?php esc_html_e( 'Amount received', 'rar-woo-advance-payment' ); ?><input type="number" step="0.01" min="0" name="amount" inputmode="decimal"></label>
					<label><?php esc_html_e( 'Internal note (optional)', 'rar-woo-advance-payment' ); ?><input type="text" name="note" maxlength="200"></label>
				</div>
				<div class="rwa-modal-body" data-for="reject">
					<label><?php esc_html_e( 'Reason', 'rar-woo-advance-payment' ); ?><select name="reason">
						<?php foreach ( array_keys( RAR_WAP_Order::REJECT_REASONS ) as $key ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( RAR_WAP_Order::reject_reason_label( $key ) ); ?></option>
						<?php endforeach; ?>
					</select></label>
					<label><?php esc_html_e( 'Message to customer (optional)', 'rar-woo-advance-payment' ); ?><input type="text" name="note" maxlength="200"></label>
					<p class="rwa-muted"><?php esc_html_e( 'The customer gets an e-mail with a link to correct the Transaction ID.', 'rar-woo-advance-payment' ); ?></p>
				</div>
				<div class="rwa-modal-body" data-for="reset">
					<p><?php esc_html_e( 'Move this payment back to “Awaiting verification”? If it was verified, the order returns to On hold.', 'rar-woo-advance-payment' ); ?></p>
				</div>
				<footer>
					<button type="button" class="rwa-btn is-ghost" data-close><?php esc_html_e( 'Cancel', 'rar-woo-advance-payment' ); ?></button>
					<button type="submit" class="rwa-btn is-primary rwa-modal-go"></button>
				</footer>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * AJAX
	 * ------------------------------------------------------------------ */

	public static function ajax_poll() {
		check_ajax_referer( 'rar_wap_admin', 'nonce' );
		if ( ! RAR_WAP_Plugin::can_manage() ) {
			wp_send_json_error( null, 403 );
		}
		$pending = RAR_WAP_Query::pending_count( true );
		wp_send_json_success(
			array(
				'pending' => $pending,
				'token'   => $pending . '|' . RAR_WAP_Query::latest_submission(),
			)
		);
	}

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
}

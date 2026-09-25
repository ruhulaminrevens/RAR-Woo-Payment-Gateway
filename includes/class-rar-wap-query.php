<?php
/**
 * Storage-aware (HPOS / legacy CPT) queries for advance-payment orders.
 *
 * Direct SQL is used deliberately: it is exact, indexed on meta_key, and
 * avoids differences in how each data store treats custom query vars.
 *
 * @package RAR_Woo_Advance_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WAP_Query {

	/** Order statuses whose references no longer block re-use. */
	const INACTIVE_STATUSES = array( 'trash', 'cancelled', 'failed', 'checkout-draft' );

	public static function is_hpos() {
		return class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Table/column map for the authoritative order store.
	 *
	 * @return array{orders:string,meta:string,pk:string,fk:string,status:string,type_col:string,type:string,date:string}
	 */
	private static function schema() {
		global $wpdb;

		if ( self::is_hpos() ) {
			return array(
				'orders'   => $wpdb->prefix . 'wc_orders',
				'meta'     => $wpdb->prefix . 'wc_orders_meta',
				'pk'       => 'id',
				'fk'       => 'order_id',
				'status'   => 'status',
				'type_col' => 'type',
				'type'     => 'shop_order',
				'date'     => 'date_created_gmt',
			);
		}

		return array(
			'orders'   => $wpdb->posts,
			'meta'     => $wpdb->postmeta,
			'pk'       => 'ID',
			'fk'       => 'post_id',
			'status'   => 'post_status',
			'type_col' => 'post_type',
			'type'     => 'shop_order',
			'date'     => 'post_date_gmt',
		);
	}

	private static function status_values( array $statuses ) {
		$out = array();
		foreach ( $statuses as $status ) {
			$out[] = $status;
			if ( 'trash' !== $status && 0 !== strpos( $status, 'wc-' ) ) {
				$out[] = 'wc-' . $status;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function in_placeholders( array $values ) {
		return implode( ',', array_fill( 0, count( $values ), '%s' ) );
	}

	/**
	 * Does an active order already carry this channel + reference?
	 *
	 * @return int Matching order ID or 0.
	 */
	public static function find_reference( $channel, $raw, $normalized, $exclude_order_id = 0 ) {
		global $wpdb;

		$channel = sanitize_key( $channel );
		if ( '' === $channel || '' === $normalized ) {
			return 0;
		}

		$s        = self::schema();
		$inactive = self::status_values( self::INACTIVE_STATUSES );
		$in       = self::in_placeholders( $inactive );

		$sql = "SELECT o.{$s['pk']}
			FROM {$s['meta']} ref
			INNER JOIN {$s['meta']} ch ON ch.{$s['fk']} = ref.{$s['fk']} AND ch.meta_key = '_rar_wap_channel'
			INNER JOIN {$s['orders']} o ON o.{$s['pk']} = ref.{$s['fk']}
			WHERE o.{$s['type_col']} = 'shop_order'
				AND o.{$s['status']} NOT IN ({$in})
				AND o.{$s['pk']} <> %d
				AND ch.meta_value = %s
				AND (
					( ref.meta_key = '_rar_wap_reference_normalized' AND ref.meta_value = %s )
					OR ( ref.meta_key = '_rar_wap_reference' AND ref.meta_value = %s )
				)
			LIMIT 1";

		$args = array_merge( $inactive, array( absint( $exclude_order_id ), $channel, $normalized, (string) $raw ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are internal.
		return absint( $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) );
	}

	/**
	 * Build the WHERE/JOIN for list and summary queries.
	 *
	 * @param array $args status, channel, search, date_from, date_to (Y-m-d), date_field (submitted|verified).
	 * @return array{0:string,1:string,2:array}
	 */
	private static function build( array $args ) {
		global $wpdb;

		$s      = self::schema();
		$joins  = array();
		$where  = array();
		$params = array();

		$meta_join = static function ( $alias, $key ) use ( $s ) {
			return "LEFT JOIN {$s['meta']} {$alias} ON {$alias}.{$s['fk']} = o.{$s['pk']} AND {$alias}.meta_key = '" . esc_sql( $key ) . "'";
		};

		$joins[] = "INNER JOIN {$s['meta']} st ON st.{$s['fk']} = o.{$s['pk']} AND st.meta_key = '_rar_wap_status'";
		$joins[] = $meta_join( 'sub', '_rar_wap_submitted_at' );
		$joins[] = $meta_join( 'ver', '_rar_wap_verified_at' );
		$joins[] = $meta_join( 'ch', '_rar_wap_channel' );
		$joins[] = $meta_join( 'amt', '_rar_wap_required_amount' );

		$where[] = "o.{$s['type_col']} = 'shop_order'";
		$trash   = self::status_values( array( 'trash', 'checkout-draft' ) );
		$where[] = "o.{$s['status']} NOT IN (" . self::in_placeholders( $trash ) . ')';
		$params  = array_merge( $params, $trash );

		if ( ! empty( $args['status'] ) && in_array( $args['status'], RAR_WAP_Order::STATUSES, true ) ) {
			$where[]  = 'st.meta_value = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['channel'] ) ) {
			$where[]  = 'ch.meta_value = %s';
			$params[] = sanitize_key( $args['channel'] );
		}

		$date_col = ( isset( $args['date_field'] ) && 'verified' === $args['date_field'] ) ? 'ver.meta_value' : 'sub.meta_value';
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = "{$date_col} >= %s";
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = "{$date_col} <= %s";
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['search'] ) ) {
			$search   = trim( (string) $args['search'] );
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$joins[]  = $meta_join( 'rf', '_rar_wap_reference_normalized' );
			$joins[]  = $meta_join( 'py', '_rar_wap_payer' );
			$clause   = '( rf.meta_value LIKE %s OR py.meta_value LIKE %s';
			$params[] = '%' . $wpdb->esc_like( RAR_WAP_Order::normalize_reference( $search ) ) . '%';
			$params[] = $like;
			if ( ctype_digit( ltrim( $search, '#' ) ) ) {
				$clause  .= " OR o.{$s['pk']} = %d";
				$params[] = absint( ltrim( $search, '#' ) );
			}
			$where[] = $clause . ' )';
		}

		if ( ! empty( $args['older_than'] ) ) {
			$where[]  = 'sub.meta_value < %s';
			$params[] = $args['older_than'];
		}

		if ( ! empty( $args['meta_missing'] ) ) {
			$joins[] = $meta_join( 'mm', (string) $args['meta_missing'] );
			$where[] = 'mm.meta_value IS NULL';
		}

		if ( ! empty( $args['rejected_before'] ) ) {
			$joins[]  = $meta_join( 'rj', '_rar_wap_rejected_at' );
			$where[]  = 'rj.meta_value < %s';
			$params[] = $args['rejected_before'];
		}

		if ( ! empty( $args['order_status_in'] ) && is_array( $args['order_status_in'] ) ) {
			$vals    = self::status_values( array_map( 'sanitize_key', $args['order_status_in'] ) );
			$where[] = "o.{$s['status']} IN (" . self::in_placeholders( $vals ) . ')';
			$params  = array_merge( $params, $vals );
		}

		return array( implode( ' ', $joins ), implode( ' AND ', $where ), $params );
	}

	/**
	 * Paginated order IDs, newest submission first.
	 *
	 * @return array{ids:int[],total:int}
	 */
	public static function list_ids( array $args, $page = 1, $per_page = 20 ) {
		global $wpdb;

		$s = self::schema();
		list( $joins, $where, $params ) = self::build( $args );

		$per_page = max( 1, min( 500, absint( $per_page ) ) );
		$offset   = max( 0, ( absint( $page ) - 1 ) * $per_page );

		$from = "FROM {$s['orders']} o {$joins} WHERE {$where}";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT o.{$s['pk']}) {$from}", $params ) );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT o.{$s['pk']} AS oid, sub.meta_value AS submitted_sort {$from} ORDER BY submitted_sort DESC, oid DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			)
		);
		// phpcs:enable

		return array(
			'ids'   => array_map( 'absint', (array) $ids ),
			'total' => $total,
		);
	}

	/**
	 * Count + sum grouped by payment state (and optionally channel).
	 *
	 * @return array<int,array{status:string,channel:string,orders:int,amount:float}>
	 */
	public static function grouped( array $args ) {
		global $wpdb;

		$s = self::schema();
		list( $joins, $where, $params ) = self::build( $args );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT st.meta_value AS status, COALESCE(ch.meta_value,'') AS channel, COUNT(DISTINCT o.{$s['pk']}) AS orders, COALESCE(SUM(CAST(amt.meta_value AS DECIMAL(14,2))),0) AS amount
				FROM {$s['orders']} o {$joins} WHERE {$where}
				GROUP BY st.meta_value, ch.meta_value",
				$params
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'status'  => (string) $row['status'],
				'channel' => (string) $row['channel'],
				'orders'  => (int) $row['orders'],
				'amount'  => (float) $row['amount'],
			);
		}
		return $out;
	}

	/**
	 * Number of orders awaiting verification (cached briefly for the menu badge).
	 */
	public static function pending_count( $force = false ) {
		$cached = get_transient( 'rar_wap_pending_count' );
		if ( ! $force && false !== $cached ) {
			return (int) $cached;
		}

		$result = self::list_ids( array( 'status' => 'submitted' ), 1, 1 );
		set_transient( 'rar_wap_pending_count', (int) $result['total'], 5 * MINUTE_IN_SECONDS );
		return (int) $result['total'];
	}

	public static function flush_counts() {
		delete_transient( 'rar_wap_pending_count' );
	}
}

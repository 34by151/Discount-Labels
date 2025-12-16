<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Reports {

	const TABLE_NAME = 'wccs_asnp_analytics';
	const RULE_TABLE_NAME = 'wccs_conditions';

	/**
	 * Get totals for a given date range.
	 *
	 * @param string $start_date (Y-m-d)
	 * @param string|null $end_date (Y-m-d)
	 * @param int|null $rule_id
	 * 
	 * @return array
	 */
	public static function get_totals( $start_date, $end_date = null, $rule_id = null ) {
		if ( empty( $start_date ) && empty( $end_date ) ) {
			return [];
		}

		global $wpdb;

		$table_name = $wpdb->prefix . static::TABLE_NAME;
		$logs_table = $wpdb->prefix . 'wccs_rule_usage_logs';
		$order_stats_table = $wpdb->prefix . 'wc_order_stats';

		// Build WHERE clause
		$where = $orders_where = '1=1';
		if ( ! empty( $start_date ) && ! empty( $end_date ) && $start_date < $end_date ) {
			$where = $wpdb->prepare( 'date BETWEEN %s AND %s', $start_date, $end_date );
			$orders_where = $wpdb->prepare( 'rul.created_at BETWEEN %s AND %s', $start_date . ' 00:00:00', $end_date . ' 23:59:59' );
		} elseif ( ! empty( $start_date ) ) {
			$where = $wpdb->prepare( 'DATE(date) = %s', $start_date );
			$orders_where = $wpdb->prepare( 'DATE(rul.created_at) = %s', $start_date );
		} elseif ( ! empty( $end_date ) ) {
			$where = $wpdb->prepare( 'DATE(date) = %s', $end_date );
			$orders_where = $wpdb->prepare( 'DATE(rul.created_at) = %s', $end_date );
		}

		if ( $rule_id ) {
			$where .= $wpdb->prepare( ' AND rule_id = %d', $rule_id );
			$orders_where .= $wpdb->prepare( ' AND rul.rule_id = %d', $rule_id );
		}

		// Common SELECT query for analytics
		$sql = "
			SELECT 
				SUM(impressions) AS impressions,
				SUM(add_to_cart) AS add_to_cart,
				SUM(checkouts) AS checkouts,
				SUM(orders) AS orders,
				SUM(rejections) AS rejections,
				SUM(free_shippings) AS free_shippings,
				SUM(items_discounted) AS items_discounted,
				SUM(shipping_discounts) AS shipping_discounts,
				SUM(fees) AS fees,
				SUM(revenue) AS rule_revenue,
				SUM(discounts) AS discounts
			FROM {$table_name}
			WHERE {$where}
		";

		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! is_array( $row ) ) {
			$row = [];
		}

		// Get order IDs
		$order_ids_sql = "
			SELECT DISTINCT rul.order_id
			FROM {$logs_table} rul
			INNER JOIN {$table_name} a 
				ON rul.rule_id = a.rule_id AND a.date = DATE(rul.created_at)
			WHERE {$orders_where}
		";

		$order_ids = $wpdb->get_col( $order_ids_sql );

		// Initialize stats
		$stats_row = [
			'total_orders' => 0,
			'revenue' => 0,
			'net_revenue' => 0,
		];

		if ( ! empty( $order_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
			$stats_sql = $wpdb->prepare(
				"SELECT 
				COUNT(order_id) AS total_orders,
				SUM(total_sales) AS revenue,
				SUM(net_total) AS net_revenue
				FROM {$order_stats_table}
				WHERE order_id IN ($placeholders)",
				$order_ids
			);

			$result = $wpdb->get_row( $stats_sql, ARRAY_A );
			if ( is_array( $result ) ) {
				$stats_row = array_merge( $stats_row, $result );
			}
		}

		// Normalize defaults
		$defaults = [
			'impressions' => 0,
			'add_to_cart' => 0,
			'checkouts' => 0,
			'orders' => 0,
			'rejections' => 0,
			'free_shippings' => 0,
			'items_discounted' => 0,
			'shipping_discounts' => 0.0,
			'fees' => 0.0,
			'revenue' => 0.0,
			'net_revenue' => 0.0,
			'rule_revenue' => 0.0,
			'discounts' => 0.0,
		];

		$row = array_merge( $defaults, $row );

		// Apply stats
		$row['orders'] = ! empty( $stats_row['total_orders'] ) ? (int) $stats_row['total_orders'] : (int) $row['orders'];
		$row['revenue'] = ! empty( $stats_row['revenue'] ) ? (float) $stats_row['revenue'] : (float) $row['revenue'];
		$row['net_revenue'] = ! empty( $stats_row['net_revenue'] ) ? (float) $stats_row['net_revenue'] : 0.0;

		// Derived metrics
		$row['avg_order_value'] = $row['orders'] > 0 ? (float) $row['revenue'] / $row['orders'] : 0.0;
		$row['conversion_rate'] = $row['impressions'] > 0 ? ( (float) $row['orders'] / $row['impressions'] ) * 100 : 0.0;

		// Final output
		$totals = [
			'impressions' => (int) $row['impressions'],
			'add_to_cart' => (int) $row['add_to_cart'],
			'checkouts' => (int) $row['checkouts'],
			'orders' => (int) $row['orders'],
			'rejections' => (int) $row['rejections'],
			'free_shippings' => (int) $row['free_shippings'],
			'items_discounted' => (int) $row['items_discounted'],
			'shipping_discounts' => (float) $row['shipping_discounts'],
			'fees' => (float) $row['fees'],
			'revenue' => (float) $row['revenue'],
			'net_revenue' => (float) $row['net_revenue'],
			'rule_revenue' => (float) $row['rule_revenue'],
			'discounts' => (float) $row['discounts'],
			'avg_order_value' => (float) $row['avg_order_value'],
			'conversion_rate' => (float) $row['conversion_rate'],
		];

		return $totals;
	}

	/**
	 * Get timeseries revenue data for charts.
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * @param int|null $rule_id
	 * @param boolean $for_chart
	 * 
	 * @return array
	 */
	public static function get_revenue_timeseries( $start_date, $end_date, $rule_id = null, $for_chart = true ) {
		if ( empty( $start_date ) && empty( $end_date ) ) {
			return [];
		}

		global $wpdb;

		$table_name = $wpdb->prefix . static::TABLE_NAME;
		$logs_table = $wpdb->prefix . 'wccs_rule_usage_logs';
		$order_stats_table = $wpdb->prefix . 'wc_order_stats';

		// Build WHERE conditions for analytics and logs.
		if ( ! empty( $start_date ) && ! empty( $end_date ) && $start_date < $end_date ) {
			$where = $wpdb->prepare( 'a.date BETWEEN %s AND %s', $start_date, $end_date );
			$orders_where = $wpdb->prepare( 'rul.created_at BETWEEN %s AND %s', $start_date . ' 00:00:00', $end_date . ' 23:59:59' );
		} elseif ( ! empty( $start_date ) ) {
			$where = $wpdb->prepare( 'a.date = %s', $start_date );
			$orders_where = $wpdb->prepare( 'DATE(rul.created_at) = %s', $start_date );
		} elseif ( ! empty( $end_date ) ) {
			$where = $wpdb->prepare( 'a.date = %s', $end_date );
			$orders_where = $wpdb->prepare( 'DATE(rul.created_at) = %s', $end_date );
		} else {
			$where = $orders_where = '1=1';
		}

		if ( $rule_id ) {
			$where .= $wpdb->prepare( ' AND a.rule_id = %d', absint( $rule_id ) );
			$orders_where .= $wpdb->prepare( ' AND rul.rule_id = %d', absint( $rule_id ) );
		}

		$sql = "
			SELECT 
				a.date,
				SUM(a.revenue) AS rule_revenue,
				SUM(a.discounts) AS discounts,
				SUM(a.impressions) AS impressions,
				SUM(a.rejections) AS rejections,
				SUM(a.items_discounted) AS items_discounted,
				SUM(a.shipping_discounts) AS shipping_discounts,
				SUM(a.fees) AS fees,
				COALESCE(MAX(order_stats.total_orders), 0) AS orders,
				COALESCE(MAX(order_stats.revenue), 0) AS revenue,
				COALESCE(MAX(order_stats.net_revenue), 0) AS net_revenue
			FROM {$table_name} a
			LEFT JOIN (
				SELECT 
					orders_per_day.order_date,
					COUNT(orders_per_day.order_id) AS total_orders,
					SUM(os.total_sales) AS revenue,
					SUM(os.net_total) AS net_revenue
				FROM (
					SELECT DISTINCT DATE(rul.created_at) AS order_date, rul.order_id
					FROM {$logs_table} rul
					WHERE {$orders_where}
				) AS orders_per_day
				INNER JOIN {$order_stats_table} os ON orders_per_day.order_id = os.order_id
				GROUP BY orders_per_day.order_date
			) AS order_stats ON a.date = order_stats.order_date
			WHERE {$where}
			GROUP BY a.date
			ORDER BY a.date ASC
		";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( $for_chart ) {
			$results = static::prepare_for_chart( $results, $start_date, $end_date );
		}

		return $results;
	}

	/**
	 * Get report grouped by rule (for "Best Performing" / "Most Effective").
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * 
	 * @return array
	 */
	public static function get_rule_reports( $start_date, $end_date, array $args = [] ) {
		if ( empty( $start_date ) && empty( $end_date ) ) {
			return [];
		}

		global $wpdb;

		$args = array_merge(
			[
				'number' => 20,
				'offset' => 0,
				'paginate' => true,
			],
			$args
		);

		if ( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
			$args['paginate'] = false;
		}

		if ( ! empty( $start_date ) && ! empty( $end_date ) && $start_date < $end_date ) {
			$where = $wpdb->prepare( 'date BETWEEN %s AND %s', $start_date, $end_date );
		} elseif ( ! empty( $start_date ) ) {
			$where = $wpdb->prepare( 'date = %s', $start_date );
		} elseif ( ! empty( $end_date ) ) {
			$where = $wpdb->prepare( 'date = %s', $end_date );
		}

		$select_args = [ absint( $args['offset'] ), absint( $args['number'] ) ];

		$sql = "
            SELECT 
                a.rule_id,
                r.name,
                r.type,
                SUM(a.impressions)        AS impressions,
                SUM(a.add_to_cart)        AS add_to_cart,
                SUM(a.orders)             AS orders,
                SUM(a.rejections)         AS rejections,
                SUM(a.checkouts)          AS checkouts,
                SUM(a.free_shippings)     AS free_shippings,
                SUM(a.items_discounted)   AS items_discounted,
                SUM(a.revenue)            AS revenue,
                SUM(a.discounts)          AS discounts,
                SUM(a.shipping_discounts) AS shipping_discounts,
                SUM(a.fees)               AS fees,
                IFNULL(
                    ROUND(SUM(a.orders) / NULLIF(SUM(a.impressions), 0) * 100, 2),
                    0
                ) AS conversion_rate,
                IFNULL( 
                    ROUND(SUM(a.revenue) / NULLIF(SUM(a.orders), 0), 2),
                    0
                ) AS avg_order_value
            FROM {$wpdb->prefix}" . static::TABLE_NAME . " AS a
            INNER JOIN {$wpdb->prefix}" . static::RULE_TABLE_NAME . " AS r ON a.rule_id = r.id AND r.type IN ( 'pricing', 'cart-discount', 'checkout-fee', 'auto-add-products', 'shipping', 'shipping-discount' )
            WHERE {$where}
            GROUP BY rule_id
            ORDER BY conversion_rate DESC, revenue DESC
            LIMIT %d, %d
        ";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $select_args ), ARRAY_A );

		if ( empty( $args['paginate'] ) ) {
			return $results;
		}

		if ( empty( $results ) ) {
			return [
				'items' => [],
				'total' => 0,
				'pages' => 0,
			];
		}

		$total = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(DISTINCT a.rule_id)
            FROM {$wpdb->prefix}" . static::TABLE_NAME . " AS a
            INNER JOIN {$wpdb->prefix}" . static::RULE_TABLE_NAME . " AS r ON a.rule_id = r.id AND r.type IN ( 'pricing', 'cart-discount', 'auto-add-products', 'shipping', 'shipping-discount' )
            WHERE {$where}"
		) );

		if ( 0 >= $total ) {
			return [
				'items' => [],
				'total' => 0,
				'pages' => 0,
			];
		}

		return [
			'items' => $results,
			'total' => absint( $total ),
			'pages' => ceil( absint( $total ) / absint( $args['number'] ) ),
		];
	}

	/**
	 * Get single rule report.
	 */
	public static function get_rule_report( $rule_id, $start_date, $end_date ) {
		return static::get_totals( $start_date, $end_date, $rule_id );
	}

	public static function prepare_for_chart(
		$results,
		$start_date,
		$end_date,
		$fields = [ 'revenue', 'net_revenue', 'rule_revenue', 'orders', 'impressions', 'rejections', 'add_to_cart', 'discounts', 'checkouts', 'free_shippings', 'items_discounted', 'shipping_discounts', 'fees' ]
	) {
		if ( empty( $start_date ) || empty( $end_date ) ) {
			return $results;
		}

		if ( empty( $fields ) ) {
			return [];
		}

		$days = [];

		// Normalize results
		if ( ! empty( $results ) && is_array( $results ) ) {
			foreach ( $results as $result ) {
				if ( empty( $result['date'] ) ) {
					continue;
				}

				$days[ $result['date'] ] = [];
				foreach ( $fields as $field ) {
					if ( in_array( $field, [ 'revenue', 'net_revenue', 'rule_revenue', 'discounts', 'shipping_discounts', 'fees' ], true ) ) {
						$days[ $result['date'] ][ $field ] = ! empty( $result[ $field ] ) ? (float) $result[ $field ] : 0;
					} else {
						$days[ $result['date'] ][ $field ] = ! empty( $result[ $field ] ) ? (int) $result[ $field ] : 0;
					}
				}
			}
		}

		// Generate period
		$start = new \DateTime( $start_date );
		$end = new \DateTime( $end_date );
		$end->modify( '+1 day' ); // include end date
		$interval = new \DateInterval( 'P1D' );
		$period = new \DatePeriod( $start, $interval, $end );

		// Fill missing days
		foreach ( $period as $date ) {
			$key = $date->format( 'Y-m-d' );
			if ( ! isset( $days[ $key ] ) ) {
				$days[ $key ] = [];
				foreach ( $fields as $field ) {
					$days[ $key ][ $field ] = 0;
				}
			}
		}

		ksort( $days );

		return $days;
	}

}

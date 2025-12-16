<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

class WCCS_Report {

	private $report;

	public function __construct() {
		$this->report = new WC_Report_Sales_By_Date();
	}

	public function get_top_sellers( $filter = array() ) {
		// set date filtering.
		$this->setup_report( $filter );

		$query_args = array(
			'data' => array(
				'_product_id' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function'        => '',
					'name'            => 'product_id',
				),
				'_qty' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function'        => 'SUM',
					'name'            => 'order_item_qty',
				),
			),
			'order_by'     => 'order_item_qty DESC',
			'group_by'     => 'product_id',
			'query_type'   => 'get_results',
			'filter_range' => true,
		);

		if ( ! empty( $filter['limit'] ) && (int) $filter['limit'] > 0 ) {
			$query_args['limit'] = intval( $filter['limit'] );
		}

		$top_sellers = $this->report->get_order_report_data( $query_args );

		$top_sellers_data = array();

		if ( ! empty( $top_sellers ) ) {
			foreach ( $top_sellers as $top_seller ) {
				$product = wc_get_product( $top_seller->product_id );

				if ( $product ) {
					$top_sellers_data[] = $top_seller->product_id;
				}
			}
		}

		return apply_filters( 'woocommerce_conditions_report_get_top_sellers', $top_sellers_data, $this->report );
	}

	public function get_top_earners( $filter = array() ) {
		// set date filtering.
		$this->setup_report( $filter );

		$query_args = array(
			'data' => array(
				'_product_id' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function'        => '',
					'name'            => 'product_id',
				),
				'_line_total' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function'        => 'SUM',
					'name'            => 'order_item_total',
				),
			),
			'order_by'     => 'order_item_total DESC',
			'group_by'     => 'product_id',
			'query_type'   => 'get_results',
			'filter_range' => true,
		);

		if ( ! empty( $filter['limit'] ) && (int) $filter['limit'] > 0 ) {
			$query_args['limit'] = intval( $filter['limit'] );
		}

		$top_earners = $this->report->get_order_report_data( $query_args );

		$top_earners_data = array();

		if ( ! empty( $top_earners ) ) {
			foreach ( $top_earners as $top_earner ) {
				$product = wc_get_product( $top_earner->product_id );

				if ( $product ) {
					$top_earners_data[] = $top_earner->product_id;
				}
			}
		}

		return apply_filters( 'woocommerce_conditions_report_get_top_earners', $top_earners_data, $this->report );
	}

	public function get_top_freebies( $filter = array() ) {
		// set date filtering.
		$this->setup_report( $filter );

		$query_args = array(
			'data' => array(
				'_product_id' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function'        => '',
					'name'            => 'product_id',
				),
				'_qty' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function'        => 'SUM',
					'name'            => 'order_item_qty',
				),
			),
			'where_meta'   => array(
				array(
					'type'       => 'order_item_meta',
					'meta_key'   => '_line_subtotal',
					'meta_value' => '0',
					'operator'   => '=',
				),
			),
			'order_by'     => 'order_item_qty DESC',
			'group_by'     => 'product_id',
			'query_type'   => 'get_results',
			'filter_range' => true,
		);

		if ( ! empty( $filter['limit'] ) && (int) $filter['limit'] > 0 ) {
			$query_args['limit'] = intval( $filter['limit'] );
		}

		$top_freebies = $this->report->get_order_report_data( $query_args );

		$top_freebies_data = array();

		if ( ! empty( $top_freebies ) ) {
			foreach ( $top_freebies as $top_free ) {
				$product = wc_get_product( $top_free->product_id );

				if ( $product ) {
					$top_freebies_data[] = $top_free->product_id;
				}
			}
		}

		return apply_filters( 'woocommerce_conditions_report_get_top_freebies', $top_freebies_data, $this->report );
	}

	/**
	 * Setup the report object and parse any date filtering
	 *
	 * @since  1.0.0
	 *
	 * @param  array $filter date filtering
	 *
	 * @return void
	 */
	private function setup_report( $filter ) {
		if ( empty( $filter['period'] ) ) {

			// custom date range
			$filter['period'] = 'custom';

			if ( ! empty( $filter['date_min'] ) || ! empty( $filter['date_max'] ) ) {

				// overwrite _GET to make use of WC_Admin_Report::calculate_current_range() for custom date ranges
				$_GET['start_date'] = $this->server->parse_datetime( $filter['date_min'] );
				$_GET['end_date'] = isset( $filter['date_max'] ) ? $this->server->parse_datetime( $filter['date_max'] ) : null;

			} else {

				// default custom range to today
				$_GET['start_date'] = $_GET['end_date'] = date( 'Y-m-d', current_time( 'timestamp' ) );
			}
		} else {

			// ensure period is valid
			if ( ! in_array( $filter['period'], array( 'week', 'month', 'last_month', 'year' ) ) ) {
				$filter['period'] = 'week';
			}

			// TODO: change WC_Admin_Report class to use "week" instead, as it's more consistent with other periods
			// allow "week" for period instead of "7day"
			if ( 'week' === $filter['period'] ) {
				$filter['period'] = '7day';
			}
		}

		$this->report->calculate_current_range( $filter['period'] );
	}

}

<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Rest_Analytics extends WCCS_Rest_Base_Controller {

	protected $rest_base = 'analytics';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'totals' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/chart',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'chart' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/best-performing',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'best_performing' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/search',
			[
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => [ $this, 'search' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	public function totals( $request ) {
		try {
			$rule_id = ! empty( $request['rule_id'] ) ? absint( $request['rule_id'] ) : null;
			$date = $this->get_date_param( $request );

			$results = WCCS_Reports::get_totals( $date['start_date'], $date['end_date'], $rule_id );
			$prev_results = WCCS_Reports::get_totals( $date['prev_start_date'], $date['prev_end_date'], $rule_id );

			// Ensure we have arrays to work with
			$results = is_array( $results ) ? $results : [];
			$prev_results = is_array( $prev_results ) ? $prev_results : [];

			// Add growth data
			$growth = [ 'message' => $date['message'] ];

			// Compute percentage change for each key
			foreach ( $results as $key => $value ) {
				$previous = isset( $prev_results[ $key ] ) ? (float) $prev_results[ $key ] : 0.0;

				if ( $previous == 0 ) {
					// If previously zero, show 100% growth only if we have new data
					$growth[ $key ] = (float) $value > 0 ? '100.00' : '0.00';
				} else {
					$growth[ $key ] = number_format( ( (float) $value - $previous ) / $previous * 100, 2 );
				}

				if ( in_array( $key, [ 'discounts', 'shipping_discounts', 'fees', 'revenue', 'net_revenue', 'rule_revenue', 'avg_order_value' ], true ) ) {
					$results[ $key ] = wc_price( (float) $value );
				}
			}

			$results['growth'] = $growth;

			return rest_ensure_response( $results );
		} catch (Exception $e) {
			return new WP_Error( 'asnp_ewd_rest_analytics_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function chart( $request ) {
		try {
			$rule_id = ! empty( $request['rule_id'] ) ? absint( $request['rule_id'] ) : null;
			$date = $this->get_date_param( $request );

			$results = WCCS_Reports::get_revenue_timeseries( $date['start_date'], $date['end_date'], $rule_id );

			return rest_ensure_response( $results );
		} catch (Exception $e) {
			return new WP_Error( 'asnp_ewd_rest_analytics_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function best_performing( $request ) {
		try {
			$date = $this->get_date_param( $request );
			$page = ! empty( $request['page'] ) ? absint( $request['page'] ) : 1;
			$per_page = ! empty( $request['per_page'] ) && 100 >= absint( $request['per_page'] ) ? absint( $request['per_page'] ) : 10;

			$results = WCCS_Reports::get_rule_reports( $date['start_date'], $date['end_date'], [ 'number' => $per_page, 'offset' => $page * $per_page - $per_page ] );

			if ( ! empty( $results['items'] ) ) {
				$price_keys = [ 'discounts', 'shipping_discounts', 'fees', 'revenue', 'net_revenue', 'rule_revenue', 'avg_order_value' ];
				foreach ( $results['items'] as $index => $result ) {
					foreach ( $price_keys as $key ) {
						if ( isset( $result[ $key ] ) ) {
							$results['items'][ $index ][ $key ] = wc_price( (float) $result[ $key ] );
						}
					}
				}
			}

			return rest_ensure_response( $results );
		} catch (Exception $e) {
			return new WP_Error( 'asnp_ewd_rest_analytics_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function search( $request ) {
		try {
			$search = ! empty( $request['search'] ) ? sanitize_text_field( trim( $request['search'] ) ) : '';
			$per_page = ! empty( $request['per_page'] ) && 100 >= absint( $request['per_page'] ) ? absint( $request['per_page'] ) : 20;
			if ( empty( $search ) ) {
				throw new Exception( __( 'Please provide a search query.', 'easy-woocommerce-discounts' ) );
			}

			$args = array(
				'name' => $search,
				'number' => $per_page,
				'orderby' => 'ordering',
				'order' => 'ASC',
			);

			$results = WCCS()->conditions->get_conditions( $args );

			return rest_ensure_response( $results );
		} catch (Exception $e) {
			return new WP_Error( 'asnp_ewd_rest_analytics_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	protected function get_date_param( $request ) {
		try {
			$start_date = ! empty( $request['start_date'] ) ? sanitize_text_field( $request['start_date'] ) : null;
			$end_date = ! empty( $request['end_date'] ) ? sanitize_text_field( $request['end_date'] ) : null;
			$range = ! empty( $request['range'] ) ? sanitize_text_field( $request['range'] ) : 'last_week';

			if ( empty( $start_date ) || empty( $end_date ) ) {
				throw new Exception( __( 'Please provide a valid date range.', 'easy-woocommerce-discounts' ) );
			}

			$start_date = new DateTime( $start_date );
			$end_date = new DateTime( $end_date );

			// Ensure end date is not before start date
			if ( $end_date < $start_date ) {
				throw new Exception( __( 'End date cannot be earlier than start date.', 'easy-woocommerce-discounts' ) );
			}

			$interval_days = $start_date->diff( $end_date )->format( '%a' );

			$result = [
				'start_date' => $start_date->format( 'Y-m-d' ),
				'end_date' => $end_date->format( 'Y-m-d' ),
				'prev_start_date' => '',
				'prev_end_date' => '',
				'message' => '',
			];

			$prev_start = clone $start_date;
			$prev_end = clone $end_date;

			switch ( $range ) {
				case 'today':
					$prev_start->modify( '-1 day' );
					$prev_end->modify( '-1 day' );
					$result['message'] = __( 'from yesterday', 'easy-woocommerce-discounts' );
					break;

				case 'last_week':
					$prev_start->modify( '-7 days' );
					$prev_end->modify( '-7 days' );
					$result['message'] = __( 'from last week', 'easy-woocommerce-discounts' );
					break;

				case 'this_month':
				case 'last_month':
					$prev_start->modify( '-1 month' );
					$prev_end->modify( '-1 month' );
					$result['message'] = __( 'from previous month', 'easy-woocommerce-discounts' );
					break;

				case 'this_year':
				case 'last_year':
					$prev_start->modify( '-1 year' );
					$prev_end->modify( '-1 year' );
					$result['message'] = __( 'from previous year', 'easy-woocommerce-discounts' );
					break;

				default: // previous period of same length
					$prev_start->modify( "-{$interval_days} days" );
					$prev_end->modify( "-{$interval_days} days" );
					$result['message'] = __( 'from previous period', 'easy-woocommerce-discounts' );
					break;
			}

			$result['prev_start_date'] = $prev_start->format( 'Y-m-d' );
			$result['prev_end_date'] = $prev_end->format( 'Y-m-d' );

			return $result;
		} catch (Exception $e) {
			throw $e;
		}
	}

}

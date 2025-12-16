<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Shortcode_Products_List {

	public function output( $atts, $content = null ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'wccs_products_list' );

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		$wccs = WCCS();

		$condition = $wccs->conditions->get_conditions( array( 'id' => $atts['id'], 'type' => 'products-list' ) );
		$condition = ! empty( $condition ) ? $condition[0] : null;
		if ( ! $condition ) {
			return '';
		}

		if ( ! empty( $condition->date_time ) && ! $wccs->WCCS_Date_Time_Validator->is_valid_date_times( $condition->date_time, ( ! empty( $condition->date_times_match_mode ) ? $condition->date_times_match_mode : 'one' ) ) ) {
			return do_action( 'woocommerce_no_products_found' );
		}

		if ( ! empty( $condition->conditions ) && ! $wccs->WCCS_Condition_Validator->is_valid_conditions( $condition, ( ! empty( $condition->conditions_match_mode ) ? $condition->conditions_match_mode : 'all' ) ) ) {
			return do_action( 'woocommerce_no_products_found' );
		}

		$products_selector = new WCCS_Products_Selector();

		$includes = $products_selector->select_products( $condition->include );
		$excludes = $products_selector->select_products( $condition->exclude, 'exclude' );

		if ( array( 'all_products' ) === $includes['include'] || array( 'all_products' ) === $excludes['include'] ) {
			$include = array( 'all_products' );
		} else {
			$include = array_merge( $includes['include'], $excludes['include'] );
		}

		if ( array( 'all_products' ) === $includes['exclude'] || array( 'all_products' ) === $excludes['exclude'] ) {
			return do_action( 'woocommerce_no_products_found' );
		} else {
			$exclude = array_merge( $includes['exclude'], $excludes['exclude'] );
			if ( array( 'all_products' ) !== $include ) {
				$include = array_diff( $include, $exclude );
			}
		}

		if ( empty( $include ) && empty( $exclude ) ) {
			return do_action( 'woocommerce_no_products_found' );
		}

		if ( array( 'all_products' ) === $include ) {
			$include = array();
		}

		$products_list = new WCCS_Public_Products_List(
			array(
				'include' => $include,
				'exclude' => $exclude,
				'paginate' => ! empty( $condition->paginate ) ? wc_string_to_bool( $condition->paginate ) : true,
			)
		);

		ob_start();
		$products_list->display();
		return ob_get_clean();
	}

}

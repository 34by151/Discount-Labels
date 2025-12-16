<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Content wrapper start.
$view_args['controller']->render_view( 'products-list/wrapper-start' );

if ( $view_args['products']->have_posts() ) {
	$_wp_query           = $GLOBALS['wp_query'];
	$GLOBALS['wp_query'] = $view_args['products'];

	if ( WCCS_Helpers::wc_version_check( '3.3' ) ) {
		$paginated = ! $view_args['products']->get( 'no_found_rows' );
		// Setup the loop.
		wc_setup_loop( array(
			'name'         => 'wccs_products',
			'is_shortcode' => true,
			'is_search'    => false,
			'total'        => $paginated ? (int) $view_args['products']->found_posts : count( $view_args['products']->posts ),
			'total_pages'  => $paginated ? (int) $view_args['products']->max_num_pages : 1,
			'per_page'     => (int) $view_args['products']->get( 'posts_per_page' ),
			'current_page' => $paginated ? (int) max( 1, $view_args['products']->get( 'paged', 1 ) ) : 1,
		) );
	}

	/**
	 * woocommerce_before_shop_loop hook.
	 *
	 * @hooked wc_print_notices - 10
	 * @hooked woocommerce_result_count - 20
	 * @hooked woocommerce_catalog_ordering - 30
	 */
	do_action( 'woocommerce_before_shop_loop' );

	woocommerce_product_loop_start();

	if ( ! WCCS_Helpers::wc_version_check( '3.3' ) ) {
		woocommerce_product_subcategories();
	}

	if ( WCCS_Helpers::wc_version_check( '3.3' ) ) {
		if ( wc_get_loop_prop( 'total' ) ) {
			while ( $view_args['products']->have_posts() ) {
				$view_args['products']->the_post();
				wc_get_template_part( 'content', 'product' );
			}
		}
	} else {
		while ( $view_args['products']->have_posts() ) {
			$view_args['products']->the_post();
			wc_get_template_part( 'content', 'product' );
		}
	}

	woocommerce_product_loop_end();

	/**
	 * woocommerce_after_shop_loop hook.
	 *
	 * @hooked woocommerce_pagination - 10
	 */
	do_action( 'woocommerce_after_shop_loop' );

	wp_reset_postdata();

	if ( WCCS_Helpers::wc_version_check( '3.3' ) ) {
		wc_reset_loop();
	}

	wp_reset_query();

	$GLOBALS['wp_query'] = $_wp_query;
} else {
	/**
	 * woocommerce_no_products_found hook.
	 *
	 * @hooked wc_no_products_found - 10
	 */
	do_action( 'woocommerce_no_products_found' );
}

// Content wrapper end.
$view_args['controller']->render_view( 'products-list/wrapper-end' );

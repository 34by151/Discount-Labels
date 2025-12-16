<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Public_Products_List extends WCCS_Public_Controller {

	protected $args;

	public function __construct( $args ) {
		$this->args = wp_parse_args( $args, array(
			'include'      => array(),
			'exclude'      => array(),
			'status'       => 'publish',
			'paginate'     => true,
			'set_order_by' => '',
		) );
		add_filter( 'wccs_products_query', array( $this, 'products_query' ), 10 );
	}

	public function display() {
		global $wp_query;

		$wc_query = WC()->query;
		$wc_query->product_query( $wp_query );

		$query_args = array(
			'include'    => $this->args['include'],
			'exclude'    => $this->args['exclude'],
			'status'     => $this->args['status'],
			'return'     => 'wp_query',
			'paginate'   => $this->args['paginate'] && wc_string_to_bool( $this->args['paginate'] ) ? true : false,
			'page'       => 1,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => array(),
		);

		if ( WCCS_Helpers::wc_version_check( '3.3' ) ) {
			if ( $query_args['paginate'] ) {
				$query_args['page'] = absint( empty( $_GET['product-page'] ) ? 1 : $_GET['product-page'] );
			}

			if (
				! empty( $query_args['include'] ) &&
				false !== strpos( $this->args['set_order_by'], 'price' )
			) {
				$wc_query->remove_ordering_args();
				$query_args['orderby'] = 'post__in';
				$query_args['order']   = 'price' === $this->args['set_order_by'] ? 'asc' : 'desc';
			} else {
				$ordering_args         = WC()->query->get_catalog_ordering_args( $query_args['orderby'], $query_args['order'] );
				$query_args['orderby'] = $ordering_args['orderby'];
				$query_args['order']   = $ordering_args['order'];
			}
		} else {
			if ( $query_args['paginate'] ) {
				$query_args['page'] = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;
			}

			if (
				! empty( $query_args['include'] ) &&
				false !== strpos( $this->args['set_order_by'], 'price' )
			) {
				$wc_query->remove_ordering_args();
				$query_args['orderby'] = 'post__in';
				$query_args['order']   = 'price' === $this->args['set_order_by'] ? 'asc' : 'desc';
			} else {
				$query_args['orderby'] = get_query_var( 'orderby' ) ? wc_clean( get_query_var( 'orderby' ) ) : 'date';
				$query_args['order']   = get_query_var( 'order' ) ? wc_clean( get_query_var( 'order' ) ) : 'DESC';
			}
		}

		if ( ! $query_args['paginate'] ) {
			$query_args['limit'] = '-1';
		}

		$meta_query = array();

		if ( get_query_var( 'meta_key' ) ) {
			$meta_query['key'] = wc_clean( get_query_var( 'meta_key' ) );
		}
		if ( get_query_var( 'meta_value' ) ) {
			$meta_query['value'] = wc_clean( get_query_var( 'meta_value' ) );
		}
		if ( get_query_var( 'meta_compare' ) ) {
			$meta_query['compare'] = wc_clean( get_query_var( 'meta_compare' ) );
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'][] = $meta_query;
		}

		$query = WCCS()->products->get_products( $query_args );

		if ( ! empty( $this->args['set_order_by'] ) ) {
			$_GET['orderby'] = wc_clean( wp_unslash( $this->args['set_order_by'] ) );
		}

		// Removing WooCommerce ordering args is necessary.
		$wc_query->remove_ordering_args();

		$this->render_view( 'products-list/default', array(
			'controller' => $this,
			'products'   => $query_args['paginate'] ? $query->products : $query,
		) );
	}

	public function products_query( $query_args ) {
		return apply_filters( 'woocommerce_shortcode_products_query', $query_args, array(), 'products' );
	}

}

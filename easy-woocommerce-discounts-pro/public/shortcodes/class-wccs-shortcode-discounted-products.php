<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Shortcode_Discounted_Products {

	protected $orderby = '';

	public function output( $atts, $content = null ) {
		$atts = shortcode_atts( array( 'onsale_products' => 1 ), $atts, 'wccs_discounted_products' );
		$discounted_products = WCCS()->products->get_discounted_products( $atts );
		if ( empty( $discounted_products ) ) {
			return do_action( 'woocommerce_no_products_found' );
		} elseif ( 'all_products' === $discounted_products ) {
			$discounted_products = array();
		}

		if ( ! empty( $_GET['orderby'] ) && 'all_products' !== $discounted_products ) {
			$discounted_products = $this->orderby_price( $discounted_products );
		}

		$products_list = new WCCS_Public_Products_List( array( 'include' => $discounted_products, 'set_order_by' => $this->orderby ) );

		ob_start();
		$products_list->display();
		return ob_get_clean();
	}

	public function orderby_price( $products ) {
		$orderby_value = isset( $_GET['orderby'] ) ? wc_clean( (string) wp_unslash( $_GET['orderby'] ) ) : wc_clean( get_query_var( 'orderby' ) );
		if ( ! $orderby_value ) {
			return $products;
		}

		$orderby_value = is_array( $orderby_value ) ? $orderby_value : explode( '-', $orderby_value );
		$orderby       = esc_attr( $orderby_value[0] );
		$order         = ! empty( $orderby_value[1] ) ? $orderby_value[1] : 'asc';

		if ( 'price' !== $orderby ) {
			return $products;
		}

		$this->orderby = $_GET['orderby'];
		unset( $_GET['orderby'] );

		$products = array_map( 'wc_get_product', $products );
		// Sort products by price.
		$products = wc_products_array_orderby( $products, 'price', $order );

		return wp_list_pluck( $products, 'id' );
	}

}

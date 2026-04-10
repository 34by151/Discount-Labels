<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Order_Total_Discount {

	public static function is_order_total_discount( $discount ) {
		if ( ! isset( $discount->discount_type ) ) {
			return false;
		}

		return in_array( $discount->discount_type, [ 'cart_subtotal_including_tax', 'cart_subtotal_excluding_tax', 'products_subtotal' ] );
	}

	public static function get_discount( $discount ) {
		if ( ! static::is_order_total_discount( $discount ) ) {
			return false;
		}

		$range = static::get_valid_range( $discount );
		if ( ! $range ) {
			return false;
		}

		if ( ! isset( $range['discount'] ) || ! isset( $range['discount_type'] ) ) {
			return false;
		}

		$value = [
			'discount_type' => $range['discount_type'],
			'discount' => (float) $range['discount'],
		];

		if ( 'products_subtotal' === $discount->discount_type ) {
			$value['discount_type'] = 'percentage' === $value['discount_type'] ? 'percentage_discount_per_item' : 'fixed_price';
		}

		return $value;
	}

	public static function get_valid_range( $discount ) {
		if ( empty( $discount->ranges ) ) {
			return null;
		}

		$subtotal = static::get_subtotal( $discount );
		if ( false === $subtotal ) {
			return null;
		}

		$ranges = array_reverse( $discount->ranges );
		foreach ( $ranges as $range ) {
			if ( $subtotal >= $range['min'] && ( '' === $range['max'] || $subtotal <= $range['max'] ) ) {
				return $range;
			}
		}

		return null;
	}

	protected static function get_subtotal( $discount ) {
		if ( ! WC()->cart ) {
			return false;
		}

		switch ( $discount->discount_type ) {
			case 'cart_subtotal_including_tax':
				return WC()->cart->subtotal;

			case 'cart_subtotal_excluding_tax':
				return WC()->cart->subtotal_ex_tax;

			case 'products_subtotal':
				return static::products_subtotal( $discount );
		}

		return false;
	}

	protected static function products_subtotal( $discount ) {
		if ( ! empty( $discount->items ) ) {
			$cart_items = WCCS()->cart->filter_cart_items( $discount->items, false, ! empty( $discount->exclude_items ) ? $discount->exclude_items : array(), true );
		} else {
			$cart_items = empty( $discount->exclude_items ) ? WCCS()->cart->get_cart() : WCCS()->cart->filter_cart_items( array( array( 'item' => 'all_products' ) ), false, $discount->exclude_items, true );
		}

		return WCCS()->cart->get_cart_items_subtotal( $cart_items, wc_prices_include_tax() );
	}

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Order_Item_Product_Helpers {

	/**
	 * Get Meta Data by Key.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order_Item $order_item
	 * @param  string        $key
	 * @param  boolean       $single return first found meta with key, or all with $key
	 *
	 * @return mixed
	 */
	public static function get_meta( $order_item, $key, $single = true ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return is_callable( array( $order_item, 'get_meta' ) ) ? $order_item->get_meta( $key, $single ) : false;
		}

		if ( isset( $order_item['item_meta'][ $key ] ) ) {
			return $single ? $order_item['item_meta'][ $key ][0] : $order_item['item_meta'][ $key ];
		}
		return false;
	}

	/**
	 * Get quantity.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order_Item $order_item
	 * @param  string        $context
	 *
	 * @return string|false
	 */
	public static function get_quantity( $order_item, $context = 'view' ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return is_callable( array( $order_item, 'get_quantity' ) ) ? $order_item->get_quantity( $context ) : false;
		}

		return isset( $order_item['qty'] ) ? $order_item['qty'] : false;
	}

	/**
	 * Get subtotal.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order_Item $order_item
	 * @param  string        $context
	 *
	 * @return string|false
	 */
	public static function get_subtotal( $order_item, $context = 'view' ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return is_callable( array( $order_item, 'get_subtotal' ) ) ? $order_item->get_subtotal( $context ) : false;
		}

		return isset( $order_item['line_subtotal'] ) ? $order_item['line_subtotal'] : false;
	}

	/**
	 * Get subtotal tax.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order_Item $order_item
	 * @param  string        $context
	 *
	 * @return string|false
	 */
	public static function get_subtotal_tax( $order_item, $context = 'view' ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return is_callable( array( $order_item, 'get_subtotal_tax' ) ) ? $order_item->get_subtotal_tax( $context ) : false;
		}

		return isset( $order_item['line_subtotal_tax'] ) ? $order_item['line_subtotal_tax'] : false;
	}

	/**
	 * Get total.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order_Item $order_item
	 * @param  string        $context
	 *
	 * @return string|false
	 */
	public static function get_total( $order_item, $context = 'view' ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return is_callable( array( $order_item, 'get_total' ) ) ? $order_item->get_total( $context ) : false;
		}

		return isset( $order_item['line_total'] ) ? $order_item['line_total'] : false;
	}

	/**
	 * Get total tax.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order_Item $order_item
	 * @param  string        $context
	 *
	 * @return string|false
	 */
	public static function get_total_tax( $order_item, $context = 'view' ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return is_callable( array( $order_item, 'get_total_tax' ) ) ? $order_item->get_total_tax( $context ) : false;
		}

		return isset( $order_item['line_tax'] ) ? $order_item['line_tax'] : false;
	}

	/**
	 * Get order item attributes.
	 *
	 * @param WC_Order_Item $order_item
	 *
	 * @return array
	 */
	public static function get_attributes( $order_item ) {
		$attributes = array();
		$meta_data  = $order_item->get_meta_data();
		foreach ( $meta_data as $meta ) {
			if ( empty( $meta->id ) || '' === $meta->value || ! is_scalar( $meta->value ) ) {
				continue;
			}

			$meta->key     = rawurldecode( (string) $meta->key );
			$meta->value   = rawurldecode( (string) $meta->value );
			$attribute_key = str_replace( 'attribute_', '', $meta->key );

			if ( taxonomy_exists( $attribute_key ) ) {
				$term = get_term_by( 'slug', $meta->value, $attribute_key );
				if ( ! is_wp_error( $term ) && is_object( $term ) && $term->term_id ) {
					$attributes[] = $term->term_id;
				}
			}
		}

		return $attributes;
	}

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Conditions_Provider {

	/**
	 * Retrieve products list conditions.
	 *
	 * @since  1.1.0
	 *
	 * @param  $args array
	 *
	 * @return array
	 */
	public static function get_products_lists( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'type'    => 'products-list',
			'number'  => -1,
			'orderby' => 'ordering',
			'order'   => 'ASC',
		) );
		$products_list = WCCS()->conditions->get_conditions( $args );
		if ( empty( $products_list ) ) {
			return array();
		}

		$current_db_version = get_option( 'woocommerce_conditions_db_version' );
		if ( version_compare( $current_db_version, '1.1.0', '<' ) ) {
			foreach ( $products_list as &$p_list ) {
				if ( ! isset( $p_list->date_times_match_mode ) ) {
					$p_list->date_times_match_mode = 'one';
				}
				if ( ! isset( $p_list->conditions_match_mode ) ) {
					$p_list->conditions_match_mode = 'all';
				}
			}
			unset( $p_list );
		}

		return $products_list;
	}

	/**
	 * Retrieve cart discount list conditions.
	 *
	 * @since  1.1.0
	 *
	 * @param  $args array
	 *
	 * @return array
	 */
	public static function get_cart_discounts( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'type'    => 'cart-discount',
			'number'  => -1,
			'orderby' => 'ordering',
			'order'   => 'ASC',
		) );
		$discount_list = WCCS()->conditions->get_conditions( $args );
		if ( empty( $discount_list ) ) {
			return array();
		}

		$current_db_version = get_option( 'woocommerce_conditions_db_version' );
		if ( version_compare( $current_db_version, '1.1.0', '<' ) ) {
			foreach ( $discount_list as &$discount ) {
				if ( ! isset( $discount->apply_mode ) ) {
					$discount->apply_mode = 'all';
				}
				if ( ! isset( $discount->private_note ) ) {
					$discount->private_note = '';
				}
				if ( ! isset( $discount->date_times_match_mode ) ) {
					$discount->date_times_match_mode = 'one';
				}
				if ( ! isset( $discount->conditions_match_mode ) ) {
					$discount->conditions_match_mode = 'all';
				}
			}
			unset( $discount );
		}

		return $discount_list;
	}

	/**
	 * Retrieve pricing conditions.
	 *
	 * @since  1.1.0
	 *
	 * @param  $args array
	 *
	 * @return array
	 */
	public static function get_pricings( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'type'    => 'pricing',
			'number'  => -1,
			'orderby' => 'ordering',
			'order'   => 'ASC',
		) );
		$pricing_list = WCCS()->conditions->get_conditions( $args );

		if ( empty( $pricing_list ) ) {
			return $pricing_list;
		}

		$current_db_version = get_option( 'woocommerce_conditions_db_version' );
		if ( version_compare( $current_db_version, '1.1.0', '<' ) ) {
			foreach ( $pricing_list as &$pricing ) {
				if ( ! isset( $pricing->apply_mode ) ) {
					$pricing->apply_mode = 'all';
				}
				if ( ! isset( $pricing->exclude_items ) ) {
					$pricing->exclude_items = array();
				}
				if ( ! isset( $pricing->date_times_match_mode ) ) {
					$pricing->date_times_match_mode = 'one';
				}
				if ( ! isset( $pricing->conditions_match_mode ) ) {
					$pricing->conditions_match_mode = 'all';
				}
				if ( ! empty( $pricing->items ) ) {
					foreach ( $pricing->items as &$item ) {
						if ( ! isset( $item['quantity'] ) ) {
							$item['quantity'] = '';
						}
					}
					unset( $item );
				}
			}
			unset( $pricing );
		}

		return $pricing_list;
	}

	/**
	 * Retrieve checkout fees conditions.
	 *
	 * @since  2.3.0
	 *
	 * @param  $args array
	 *
	 * @return array
	 */
	public static function get_checkout_fees( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'type'    => 'checkout-fee',
			'number'  => -1,
			'orderby' => 'ordering',
			'order'   => 'ASC',
		) );

		return WCCS()->conditions->get_conditions( $args );
	}

	/**
	 * Retrieve shipping conditions.
	 *
	 * @since  4.0.0
	 *
	 * @param  $args array
	 *
	 * @return array
	 */
	public static function get_shippings( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'type'    => 'shipping',
			'number'  => -1,
			'orderby' => 'ordering',
			'order'   => 'ASC',
		) );

		return WCCS()->conditions->get_conditions( $args );
	}

}

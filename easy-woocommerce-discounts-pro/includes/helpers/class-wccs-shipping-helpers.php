<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Shipping_Helpers {

    /**
	 * Get number of items in the shipping package.
	 *
     * @since  4.0.0
     * 
     * @param  array $package
     * 
	 * @return int
	 */
	public function get_shipping_package_contents_count( array $package ) {
        if ( empty( $package ) || empty( $package['contents'] ) ) {
            return apply_filters( 'wccs_get_shipping_package_contents_count', 0, $package );
        }
		return apply_filters( 'wccs_get_shipping_package_contents_count', array_sum( wp_list_pluck( $package['contents'], 'quantity' ) ) );
    }
    
    /**
	 * Get shipping package items quantities - merged so we can do accurate stock checks on items across multiple lines.
     * 
     * @since  4.0.0
	 *
     * @param  array $package
     * 
	 * @return array
	 */
	public function get_shipping_package_item_quantities( array $package ) {
        if ( empty( $package ) || empty( $package['contents'] ) ) {
            return apply_filters( 'wccs_get_shipping_package_item_quantities', array(), $package );
        }

        $quantities = array();
        
		foreach ( $package['contents'] as $cart_item_key => $values ) {
			$product = $values['data'];
			$quantities[ $product->get_stock_managed_by_id() ] = isset( $quantities[ $product->get_stock_managed_by_id() ] ) ? $quantities[ $product->get_stock_managed_by_id() ] + $values['quantity'] : $values['quantity'];
		}

		return apply_filters( 'wccs_get_shipping_package_item_quantities', $quantities, $package );
	}

    /**
     * Get a shipping package total weight.
     * 
     * @since  4.0.0
     * 
     * @param  array $package
     * 
     * @return float
     */
    public function get_shipping_package_weight( array $package ) {
        if ( empty( $package ) || empty( $package['contents'] ) ) {
            return apply_filters( 'wccs_get_shipping_package_weight', 0, $package );
        }

        $weight = 0;
        foreach ( $package['contents'] as $cart_item_key => $values ) {
			if ( $values['data']->has_weight() && $values['data']->needs_shipping() ) {
				$weight += (float) $values['data']->get_weight() * $values['quantity'];
			}
        }
        
        return apply_filters( 'wccs_get_shipping_package_weight', $weight, $package );
    }

}

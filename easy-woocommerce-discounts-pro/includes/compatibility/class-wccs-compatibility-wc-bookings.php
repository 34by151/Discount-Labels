<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with WooCommerce Bookings.
 * 
 * @link  https://woocommerce.com/products/woocommerce-bookings/
 *
 * @since 4.6.0
 */
class WCCS_Compatibility_WC_Bookings {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        if ( 'product_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
            $this->loader->add_filter( 'wccs_public_cart_item_pricing_get_base_price', $this, 'get_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_price', $this, 'get_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_display_price', $this, 'get_price', 10, 2 );
        }
    }

    public function get_price( $price, $cart_item ) {
        if ( ! empty( $cart_item ) 
            && ! empty( $cart_item['booking'] ) 
            && isset( $cart_item['booking']['_cost'] ) 
            && '' !== $cart_item['booking']['_cost'] 
        ) {
			return $cart_item['booking']['_cost'];
        }
        return $price;
    }

}

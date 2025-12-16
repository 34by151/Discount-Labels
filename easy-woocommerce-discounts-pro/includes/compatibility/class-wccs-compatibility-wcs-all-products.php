<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Compatibility for WooCommerce All Products For Subscriptions
 */
class WCCS_Compatibility_WCS_All_Products {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        $this->loader->add_action( 'wccs_apply_pricing_before_set_item_prices', $this, 'remove' );
        $this->loader->add_action( 'wccs_apply_pricing_after_set_item_prices', $this, 'add' );
    }

    public function remove() {
        WCS_ATT_Product_Price_Filters::remove( 'price' );
    }

    public function add() {
        WCS_ATT_Product_Price_Filters::add( 'price' );
    }

}

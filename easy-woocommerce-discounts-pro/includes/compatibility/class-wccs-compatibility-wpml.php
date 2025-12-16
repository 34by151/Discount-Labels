<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with WPML.
 *
 * @since 4.9.0
 */
class WCCS_Compatibility_WPML {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        $this->loader->add_filter( 'wccs_exact_item_id', $this, 'exact_item_id', 100, 2 );
        $this->loader->add_filter( 'wccs_exact_product', $this, 'exact_product', 100, 2 );
    }

    public function exact_item_id( $id, $type ) {
        return apply_filters( 'wpml_object_id', $id, $type, true );
    }

    public function exact_product( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return $product;
        }
        return wc_get_product( $this->exact_item_id( $product->get_id(), 'product' ) );
    }

}

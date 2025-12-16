<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_Product_Feed_Pro {

    protected $loader;

    protected $enabled_change_price = false;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        $this->loader->add_action( 'woosea_cron_hook', $this, 'enable_change_price_hooks', 1 );
        $this->loader->add_action( 'woosea_create_batch_event', $this, 'enable_change_price_hooks', 0 );
        $this->loader->add_action( 'wccs_product_price_replace_should_replace', $this, 'should_replace', 10, 4 );
    }

    public function enable_change_price_hooks() {
        if ( $this->enabled_change_price ) {
            return;
        }

        $pricing = WCCS()->container()->get( 'pricing' );
        if ( ! $pricing ) {
            $pricing = new WCCS_Pricing( WCCS_Conditions_Provider::get_pricings( array( 'status' => 1 ) ) );
            WCCS()->container()->set( 'pricing', $pricing );
        }

        $pricing_hooks = WCCS()->container()->get( 'WCCS_Public_Pricing_Hooks', array( $this->loader ) );
        if ( $pricing_hooks ) {
            $pricing_hooks->enable_change_price_hooks();
        }

        $this->enabled_change_price = true;
    }

    public function should_replace( $value, $price, $product, $price_type ) {
        if ( $value || ! $this->enabled_change_price ) {
            return $value;
        }

        if ( $product->is_type( 'variable' ) ) {
            return false;
        }

        if ( '' === $price && 'price' === $price_type ) {
            return false;
        }

        // Do not replace price for cart items.
        if ( null !== WCCS()->custom_props->get_prop( $product, 'wccs_is_cart_item' ) ) {
            return false;
        }

        return true;
    }

}

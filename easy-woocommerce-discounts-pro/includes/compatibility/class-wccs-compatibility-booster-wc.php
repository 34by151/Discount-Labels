<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with Booster for WooCommerce.
 *
 * @since 3.10.0
 */
class WCCS_Compatibility_Booster_WC {

    protected $loader;

    protected $multicurrency;

    protected $disabled_price_hooks;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        if ( ! function_exists( 'WCJ' ) ) {
            return;
        }

        $booster = WCJ();

        $this->multicurrency = isset( $booster->modules['multicurrency'] ) ? $booster->modules['multicurrency'] : null;
        if ( $this->multicurrency && $this->multicurrency->is_enabled() ) {
            $this->loader->add_action( 'wccs_public_pricing_hooks_before_apply_pricings', $this, 'disable_price_hooks' );
            $this->loader->add_action( 'wccs_public_pricing_hooks_after_apply_pricings', $this, 'enable_price_hooks' );
            $this->loader->add_action( 'wccs_public_cart_item_pricing_before_get_price', $this, 'disable_price_hooks' );
            $this->loader->add_action( 'wccs_public_cart_item_pricing_after_get_price', $this, 'enable_price_hooks' );
            $this->loader->add_action( 'wccs_public_product_pricing_before_get_discounted_price', $this, 'disable_price_hooks' );
            $this->loader->add_action( 'wccs_public_product_pricing_after_get_discounted_price', $this, 'enable_price_hooks' );
            $this->loader->add_filter( 'wccs_cart_item_price_before_discounted_price', $this, 'cart_item_price_before_discounted_price', 100, 2 );
            $this->loader->add_filter( 'wccs_cart_item_price_prices_price', $this, 'cart_item_price_prices_price', 100, 3 );
            $this->loader->add_filter( 'wccs_live_price_cart_item_discounted_price', $this, 'live_price_cart_item_discounted_price', 100, 2 );
            $this->loader->add_filter( 'wccs_public_product_pricing_get_price_html_min_variation_price', $this, 'change_price', 100, 2 );
            $this->loader->add_filter( 'wccs_public_product_pricing_get_price_html_max_variation_price', $this, 'change_price', 100, 2 );
            $this->loader->add_filter( 'wccs_public_product_pricing_get_discounted_price_variation', $this, 'change_price', 100, 2 );
            $this->loader->add_filter( 'wccs_public_product_pricing_get_discounted_price_product', $this, 'change_price', 100, 2 );
            $this->loader->add_filter( 'wccs_live_price_prices_quantities_formated_price', $this, 'cart_item_price_prices_price', 100, 3 );
            $this->loader->add_filter( 'wccs_live_price_get_sum_of_prices_quantities', $this, 'cart_item_price_prices_price', 100, 3 );
        }
    }

    public function disable_price_hooks() {
        if ( ! empty( $this->disabled_price_hooks ) ) {
            return;
        }
        wcj_remove_change_price_hooks( $this->multicurrency, $this->multicurrency->price_hooks_priority );
        $this->disabled_price_hooks = current_filter();
    }

    public function enable_price_hooks() {
        if ( $this->disabled_price_hooks !== str_replace( 'after', 'before', current_filter() ) ) {
            return;
        }
        wcj_add_change_price_hooks( $this->multicurrency, $this->multicurrency->price_hooks_priority );
        $this->disabled_price_hooks = '';
    }

    public function cart_item_price_before_discounted_price( $before_discounted_price, $cart_item ) {
        if ( ! isset( $cart_item['_wccs_main_display_price'] ) ) {
            return $before_discounted_price;
        }
        return wc_price( $this->multicurrency->change_price( $cart_item['_wccs_main_display_price'], $cart_item['data'] ) );
    }

    public function cart_item_price_prices_price( $formated_price, $price, $cart_item ) {
        return wc_price( $this->multicurrency->change_price( $price, $cart_item['data'] ) );
    }

    public function live_price_cart_item_discounted_price( $discounted_price, $cart_item ) {
        return $this->multicurrency->change_price( $discounted_price, $cart_item['data'] );
    }

    public function get_variation_prices_hash( $price_hash, $product, $for_display ) {
        $price_hash[] = $this->multicurrency->get_current_currency_code();
        return $price_hash;
    }

    public function change_price( $price, $product ) {
        $product = $product instanceof WC_Product ? $product : wc_get_product( $product );
        return $this->multicurrency->change_price( $price, $product );
    }

}

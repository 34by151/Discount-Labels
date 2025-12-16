<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with YITH WooCommerce Product Add-ons.
 * 
 * @link  https://wordpress.org/plugins/yith-woocommerce-product-add-ons/
 *
 * @since 3.5.0
 */
class WCCS_Compatibility_Yith_WAPO {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        if ( 'product_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
            $this->loader->add_filter( 'wccs_cart_item_discounted_price', $this, 'cart_item_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_price', $this, 'cart_item_main_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_display_price', $this, 'cart_item_main_display_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_before_discounted_price', $this, 'cart_item_before_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_prices', $this, 'cart_item_prices', 10, 2 );
            $this->loader->add_filter( 'wccs_live_price_cart_item_prices', $this, 'cart_item_prices', 10, 2 );
            $this->loader->add_filter( 'wccs_live_price_get_prices_quantities', $this, 'cart_item_prices', 10, 2 );
            $this->loader->add_filter( 'wccs_live_price_cart_item_discounted_price', $this, 'cart_item_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_live_price_cart_item_main_price', $this, 'cart_item_discounted_price', 10, 2 );
        }

        $this->loader->add_filter( 'wccs_apply_pricing_on_cart_item', $this, 'apply_pricing_on_cart_item', 10, 2 );
    }

    public function cart_item_discounted_price( $discounted_price, $cart_item ) {
        if ( empty( $cart_item['yith_wapo_options'] ) || ! apply_filters( 'yith_wapo_adjust_price', true, $cart_item ) ) {
            return $discounted_price;
        }

        $yith_wapo = YITH_WAPO();
        if ( ! isset( $yith_wapo->frontend ) || ! is_callable( array( $yith_wapo->frontend, 'get_total_add_ons_price' ) ) ) {
            return $discounted_price;
        }

        $options_prices = $yith_wapo->frontend->get_total_add_ons_price( $cart_item );
        if ( empty( $options_prices ) ) {
            return $discounted_price;
        }

        return (float) $discounted_price + (float) $options_prices;
    }

    public function cart_item_main_price( $price, $cart_item ) {
        if ( empty( $cart_item['yith_wapo_options'] ) || ! apply_filters( 'yith_wapo_adjust_price', true, $cart_item ) ) {
            return $price;
        }

        $yith_wapo = YITH_WAPO();
        if ( ! isset( $yith_wapo->frontend ) || ! is_callable( array( $yith_wapo->frontend, 'get_total_add_ons_price' ) ) ) {
            return $price;
        }

        $options_prices = $yith_wapo->frontend->get_total_add_ons_price( $cart_item );
        if ( empty( $options_prices ) ) {
            return $price;
        }

        return (float) $price + (float) $options_prices;
    }

    public function cart_item_main_display_price( $price, $cart_item ) {
        if ( empty( $cart_item['yith_wapo_options'] ) || ! apply_filters( 'yith_wapo_adjust_price', true, $cart_item ) ) {
            return $price;
        }

        $yith_wapo = YITH_WAPO();
        if ( ! isset( $yith_wapo->frontend ) || ! is_callable( array( $yith_wapo->frontend, 'get_total_add_ons_price' ) ) ) {
            return $price;
        }

        $options_prices = $yith_wapo->frontend->get_total_add_ons_price( $cart_item );
        if ( empty( $options_prices ) ) {
            return $price;
        }

        return (float) $price + (float) $options_prices;
    }

    public function cart_item_before_discounted_price( $price, $cart_item ) {
        if ( empty( $cart_item['yith_wapo_options'] ) || ! apply_filters( 'yith_wapo_adjust_price', true, $cart_item ) ) {
            return $price;
        }

        $yith_wapo = YITH_WAPO();
        if ( ! isset( $yith_wapo->frontend ) || ! is_callable( array( $yith_wapo->frontend, 'get_total_add_ons_price' ) ) ) {
            return $price;
        }

        $options_prices = $yith_wapo->frontend->get_total_add_ons_price( $cart_item );
        if ( empty( $options_prices ) ) {
            return $price;
        }

        return WCCS()->cart->get_product_price(
            $cart_item['data'],
            array(
                'price' => (float) WCCS()->product_helpers->wc_get_price( $cart_item['data']->get_id() ) + (float) $options_prices,
            )
        );
    }

    public function cart_item_prices( $prices, $cart_item ) {
        if ( empty( $cart_item['yith_wapo_options'] ) || ! apply_filters( 'yith_wapo_adjust_price', true, $cart_item ) || empty( $prices ) ) {
            return $prices;
        }

        $yith_wapo = YITH_WAPO();
        if ( ! isset( $yith_wapo->frontend ) || ! is_callable( array( $yith_wapo->frontend, 'get_total_add_ons_price' ) ) ) {
            return $prices;
        }

        $options_prices = $yith_wapo->frontend->get_total_add_ons_price( $cart_item );
        if ( empty( $options_prices ) ) {
            return $prices;
        }

        $value = array();

        foreach ( $prices as $price => $qty ) {
            $price                    = (float) $price + $options_prices;
            $value[ (string) $price ] = $qty;
        }

        return $value;
    }

    public function apply_pricing_on_cart_item( $value, $cart_item ) {
        if ( isset( $cart_item['yith_wapo_sold_individually'] ) && $cart_item['yith_wapo_sold_individually'] ) {
            return false;
        }

        return $value;
    }

}

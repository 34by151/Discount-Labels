<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with Extra Product Options.
 *
 * @link  https://codecanyon.net/item/woocommerce-extra-product-options/7908619
 *
 * @since 3.5.0
 */
class WCCS_Compatibility_TM_EPO {

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
        } elseif ( 'cart_item_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
            $this->loader->add_filter( 'wccs_virtual_cart_add_to_cart_item_data', $this, 'virtual_cart_add_to_cart_item_data', 10, 2 );
        }
    }

    public function cart_item_discounted_price( $discounted_price, $cart_item ) {
        if ( empty( $cart_item['tm_epo_options_prices'] ) ) {
            return $discounted_price;
        }

        return (float) $discounted_price + (float) wc_format_decimal( apply_filters( 'wc_epo_option_price_correction', $cart_item['tm_epo_options_prices'], $cart_item ) );
    }

    public function cart_item_main_price( $price, $cart_item ) {
        if ( empty( $cart_item['tm_epo_options_prices'] ) ) {
            return $price;
        }

        return (float) $price + (float) wc_format_decimal( apply_filters( 'wc_epo_option_price_correction', $cart_item['tm_epo_options_prices'], $cart_item ) );
    }

    public function cart_item_main_display_price( $price, $cart_item ) {
        if ( empty( $cart_item['tm_epo_options_prices'] ) ) {
            return $price;
        }

        return (float) $price + (float) wc_format_decimal( apply_filters( 'wc_epo_option_price_correction', $cart_item['tm_epo_options_prices'], $cart_item ) );
    }

    public function cart_item_before_discounted_price( $price, $cart_item ) {
        if ( empty( $cart_item['tm_epo_options_prices'] ) ) {
            return $price;
        }

        return WCCS()->cart->get_product_price(
            $cart_item['data'],
            array(
                'price' => (float) WCCS()->product_helpers->wc_get_price( $cart_item['data']->get_id() ) + (float) wc_format_decimal( apply_filters( 'wc_epo_option_price_correction', $cart_item['tm_epo_options_prices'], $cart_item ) ),
            )
        );
    }

    public function cart_item_prices( $prices, $cart_item ) {
        if ( empty( $cart_item['tm_epo_options_prices'] ) || empty( $prices ) ) {
            return $prices;
        }

        $options_prices = (float) wc_format_decimal( apply_filters( 'wc_epo_option_price_correction', $cart_item['tm_epo_options_prices'], $cart_item ) );
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

    public function virtual_cart_add_to_cart_item_data( $cart_item_data, $cart_item_key ) {
        if (
            empty( $cart_item_data ) ||
            empty( $cart_item_key ) ||
            isset( $cart_item_data['tm_epo_options_prices'] )
        ) {
            return $cart_item_data;
        }

        return THEMECOMPLETE_EPO_CART()->add_cart_item( $cart_item_data, $cart_item_key );
    }

}

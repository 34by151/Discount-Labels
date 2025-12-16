<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CURCY - Multi Currency for WooCommerce compatibility.
 *
 * @link  https://wordpress.org/plugins/woo-multi-currency/
 *
 * @since 6.7.0
 */
class WCCS_Compatibility_Curcy {

    protected $loader;

    protected $settings;

    public function __construct( WCCS_Loader $loader, $settings ) {
        $this->loader   = $loader;
        $this->settings = $settings;
    }

    public function init() {
        if ( ! $this->settings ) {
            return;
        }

        $this->loader->add_filter( 'wccs_cart_item_price_prices_price', $this, 'cart_item_price_prices_price', 100, 2 );
        $this->loader->add_filter( 'wccs_live_price_prices_quantities_formated_price', $this, 'cart_item_price_prices_price', 100, 2 );
        $this->loader->add_filter( 'wccs_live_price_get_sum_of_prices_quantities', $this, 'cart_item_price_prices_price', 100, 2 );
        $this->loader->add_filter( 'wccs_live_price_cart_item_discounted_price', $this, 'maybe_exchange_price', 100 );
        $this->loader->add_filter( 'wccs_maybe_exchange_price', $this, 'maybe_exchange_price', 100 );
    }

    public function cart_item_price_prices_price( $formated_price, $price ) {
        if ( empty( $price ) ) {
            return $formated_price;
        }

        $default_currency = $this->settings->get_default_currency();
		$current_currency = $this->settings->get_current_currency();

        if ( ! $current_currency || $default_currency === $current_currency ) {
            return $formated_price;
        }

        return wc_price( wmc_get_price( $price ) );
    }

    public function maybe_exchange_price( $price ) {
        if ( empty( $price ) ) {
            return $price;
        }

        $default_currency = $this->settings->get_default_currency();
		$current_currency = $this->settings->get_current_currency();

        if ( ! $current_currency || $default_currency === $current_currency ) {
            return $price;
        }

        return wmc_get_price( $price );
    }

}

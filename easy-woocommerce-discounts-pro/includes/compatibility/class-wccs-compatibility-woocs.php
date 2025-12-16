<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with WooCommerce Currency Switcher(WOOCS).
 *
 * @since 4.3.0
 */
class WCCS_Compatibility_WOOCS {

    protected $woocs;

    protected $loader;

    protected $disabled_price_hooks;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
        $this->woocs  = isset( $GLOBALS['WOOCS'] ) ? $GLOBALS['WOOCS'] : null;
    }

    public function init() {
        if ( ! $this->woocs ) {
            return;
        }

        // If multiple currency allowed in WOOCS.
        if ( $this->woocs->is_multiple_allowed ) {
            $this->loader->add_filter( 'wccs_cart_item_price_prices_price', $this, 'cart_item_price_prices_price', 100, 2 );
            $this->loader->add_filter( 'wccs_live_price_prices_quantities_formated_price', $this, 'cart_item_price_prices_price', 100, 2 );
            $this->loader->add_filter( 'wccs_live_price_get_sum_of_prices_quantities', $this, 'cart_item_price_prices_price', 100, 2 );
            $this->loader->add_filter( 'wccs_live_price_cart_item_discounted_price', $this, 'maybe_exchange_price', 100 );
            $this->loader->add_filter( 'wccs_maybe_exchange_price', $this, 'maybe_exchange_price', 100 );
        }
    }

    public function cart_item_price_prices_price( $formated_price, $price ) {
        if ( $this->woocs->current_currency == $this->woocs->default_currency ) {
            return $formated_price;
        }

        return wc_price( $this->woocs->woocs_exchange_value( $price ) );
    }

    public function maybe_exchange_price( $price ) {
        if ( $this->woocs->current_currency == $this->woocs->default_currency ) {
            return $price;
        }

        return $this->woocs->woocs_exchange_value( $price );
    }

}

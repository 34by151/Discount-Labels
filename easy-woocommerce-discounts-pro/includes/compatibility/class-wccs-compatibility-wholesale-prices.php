<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_WholeSale_Prices {

    public static function init() {
        add_filter( 'wccs_public_product_pricing_get_base_price', [ __CLASS__, 'get_product_base_price' ], 10, 2 );
        add_filter( 'wccs_product_pricing_get_price_html', [ __CLASS__, 'get_price_html' ], 10, 3 );
        add_filter( 'wccs_get_bulk_price_html_display_price', [ __CLASS__, 'get_bulk_price_html_display_price' ], 10, 2 );
    }

    public static function get_price_html( $discounted_price, $product, $price ) {
        if ( false === strpos( $price, 'wholesale_price_container' ) ) {
            return $discounted_price;
        }

        $wholesale_price_title_text = trim( apply_filters( 'wwp_filter_wholesale_price_title_text', __( 'Wholesale Price:', 'woocommerce-wholesale-prices' ) ) );

        if ( false !== strpos( $discounted_price, '<ins' ) ) {
            $wholesale_price_html = '<span style="display: block;" class="wholesale_price_container">
                                 <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span>';

            // Wrap the first <ins>...</ins> block in the wholesale price container
            $discounted_price = preg_replace(
                '#(<ins\b[^>]*>.*?</ins>)#i',
                $wholesale_price_html . ' $1</span>',
                $discounted_price,
                1 // Only replace the first match
            );

            return $discounted_price;
        }

        return $price;
    }

    public static function get_product_base_price( $base_price, $product ) {
        $prices = static::get_wholesale_prices( $product );

        return isset( $prices['wholesale_price_raw'] ) && '' !== trim( $prices['wholesale_price_raw'] ) ? 
            (float) trim( $prices['wholesale_price_raw'] ) : $base_price;
    }

    public static function get_bulk_price_html_display_price( $display_price, $product ) {
        $prices = static::get_wholesale_prices( $product );

        if ( ! isset( $prices['wholesale_price_raw'] ) || '' === trim( $prices['wholesale_price_raw'] ) ) {
            return $display_price;
        }

        return (float) wc_get_price_to_display( $product, [ 'price' => (float) trim( $prices['wholesale_price_raw'] ) ] );
    }

    protected static function get_wholesale_prices( $product ) {
        global $wc_wholesale_prices;

        if ( ! $wc_wholesale_prices ) {
            return [];
        }

        // If get price html is called from rest api request, then get wholesale role from request.
        // The wholesale role verification is done in the rest api request.
        if ( WC()->is_rest_api_request() ) {
            $user_wholesale_role = isset( $_REQUEST['wholesale_role'] ) ? array( $_REQUEST['wholesale_role'] ) : $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        } else {
            $user_wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
        }

        if ( empty( $user_wholesale_role ) ) {
            return [];
        }

        remove_filter( 'wccs_public_product_pricing_get_base_price', [ __CLASS__, 'get_product_base_price' ], 10 );
        $prices = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );
        add_filter( 'wccs_public_product_pricing_get_base_price', [ __CLASS__, 'get_product_base_price' ], 10, 2 );

        return $prices;
    }
    
}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Cart_Item_Helpers {

    protected static function get_prop( $cart_item, $prop, $context = 'view' ) {
        if ( empty( $cart_item ) || ! isset( $cart_item[ $prop ] ) ) {
            return false;
        }

        if ( 'view' === $context ) {
            return apply_filters( 'wccs_cart_item_get_' . str_replace( '_wccs_', '', $prop ), $cart_item[ $prop ], $cart_item, $prop );
        }

        return $cart_item[ $prop ];
    }

    public static function get_main_price( $cart_item, $context = 'view' ) {
        return self::get_prop( $cart_item, '_wccs_main_price', $context );
    }

    public static function get_main_display_price( $cart_item, $context = 'view' ) {
        return self::get_prop( $cart_item, '_wccs_main_display_price', $context );
    }

    public static function get_before_discounted_price( $cart_item, $context = 'view' ) {
        return self::get_prop( $cart_item, '_wccs_before_discounted_price', $context );
    }

    public static function get_discounted_price( $cart_item, $context = 'view' ) {
        return self::get_prop( $cart_item, '_wccs_discounted_price', $context );
    }

    public static function get_prices( $cart_item, $context = 'view' ) {
        return self::get_prop( $cart_item, '_wccs_prices', $context );
    }

    public static function get_prices_main( $cart_item, $context = 'view' ) {
        return self::get_prop( $cart_item, '_wccs_prices_main', $context );
    }

    public static function get_main_sale_price( $cart_item, $context = 'view' ) {
        return self::get_prop( $cart_item, '_wccs_main_sale_price', $context );
    }

}

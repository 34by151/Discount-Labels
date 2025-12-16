<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_Product_Bundles {

    public static function init() {
        add_filter( 'asnp_wepb_maybe_change_price', [ __CLASS__, 'maybe_change_price' ], 99, 3 );
        add_filter( 'asnp_wccs_total_discounts_process_cart_item', [ __CLASS__, 'include_in_total_discount' ], 10, 2 );
        add_filter( 'wccs_auto_add_products_bogo_process_cart_item', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_purchase_discounts', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_purchase_pricings', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_bulk_discounts', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_bulk_pricings', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_tiered_discounts', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_tiered_pricings', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_products_group_discounts', [ __CLASS__, 'process_cart_item' ], 10, 2 );
        add_filter( 'wccs_cart_item_products_group_pricings', [ __CLASS__, 'process_cart_item' ], 10, 2 );
    }

    public static function maybe_change_price( $price, $product, $price_type ) {
        if ( ! $product ) {
            return $price;
        }

        return WCCS()->WCCS_Product_Price_Replace->replace( $price, $product, $price_type, false );
    }

    public static function process_cart_item( $process, $cart_item ) {
        if ( isset( $cart_item['asnp_wepb_items'] ) || isset( $cart_item['asnp_wepb_parent_id'] ) ) {
            return false;
        }

        return $process;
    }

    public static function include_in_total_discount( $include, $cart_item ) {
        if ( ! $include ) {
            return false;
        }

        if ( 
            isset( $cart_item['asnp_wepb_items'] ) && 
            isset( $cart_item['asnp_wepb_is_fixed_price'] ) && 
            false === $cart_item['asnp_wepb_is_fixed_price'] &&
            'true' !== $cart_item['data']->get_include_parent_price()
        ) {
            return false;
        }

        if ( 
            isset( $cart_item['asnp_wepb_parent_id'] ) && 
            isset( $cart_item['asnp_wepb_parent_is_fixed_price'] ) && 
            true === $cart_item['asnp_wepb_parent_is_fixed_price'] 
        ) {
            return false;
        }

        return true;
    }

}

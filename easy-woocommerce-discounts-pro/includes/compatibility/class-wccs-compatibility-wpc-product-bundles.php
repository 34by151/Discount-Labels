<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_WPC_Product_Bundles {

    public static function init() {
        add_filter( 'wccs_cart_item_line_subtotal', [ __CLASS__, 'cart_item_subtotal' ], 10, 2 );
        add_filter( 'wccs_product_validator_is_valid_cart_item', [ __CLASS__, 'is_valid_cart_item' ], 100, 2 );
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

    public static function cart_item_subtotal( $subtotal, $cart_item ) {
        if ( ! empty( $subtotal ) ) {
            return $subtotal;
        }

        if ( isset( $cart_item['woosb_ids'], $cart_item['woosb_price'], $cart_item['woosb_fixed_price'] ) && ! $cart_item['woosb_fixed_price'] ) {
            return $cart_item['woosb_price'] * $cart_item['quantity'];
        }

        if ( isset( $cart_item['woosb_parent_id'], $cart_item['woosb_price'], $cart_item['woosb_fixed_price'] ) && $cart_item['woosb_fixed_price'] ) {
            return $cart_item['woosb_price'] * $cart_item['quantity'];
        }

        return $subtotal;
    }

    public static function is_valid_cart_item( $valid, $cart_item ) {
        if ( isset( $cart_item['woosb_parent_id'] ) ) {
            $valid = false;
        }

        return apply_filters( 'wccs_compatibility_wpc_product_bundles_' . __FUNCTION__, $valid, $cart_item );
    }

    public static function process_cart_item( $process, $cart_item ) {
        if ( isset( $cart_item['woosb_ids'] ) || isset( $cart_item['woosb_parent_id'] ) ) {
            return false;
        }

        return $process;
    }

}

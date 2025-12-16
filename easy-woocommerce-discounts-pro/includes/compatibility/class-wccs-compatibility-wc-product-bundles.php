<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_WC_Product_Bundles {

    public static function init() {
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

    public static function is_valid_cart_item( $valid, $cart_item ) {
        if ( $bundle_container_item = wc_pb_get_bundled_cart_item_container( $cart_item ) ) {
            $bundle          = $bundle_container_item['data'];
            $bundled_item_id = $cart_item['bundled_item_id'];
            if ( ! empty( $bundled_item_id ) ) {
                $bundled_item = $bundle->get_bundled_item( $bundled_item_id );
                if ( false === $bundled_item->is_priced_individually() ) {
                    $valid = false;
                }
            }
        }

        return apply_filters( 'wccs_compatibility_wc_product_bundles_' . __FUNCTION__, $valid, $cart_item );
    }

    public static function process_cart_item( $process, $cart_item ) {
        if ( isset( $cart_item['bundled_items'] ) || isset( $cart_item['bundled_by'] ) ) {
            return false;
        }

        return $process;
    }

}

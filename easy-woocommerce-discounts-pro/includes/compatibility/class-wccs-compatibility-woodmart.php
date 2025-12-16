<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_Woodmart {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        $this->loader->add_filter( 'woodmart_show_widget_cart_item_quantity', $this, 'widget_cart_item_quantity', 100, 2 );
        $this->loader->add_filter( 'loop_shop_post_in', $this, 'show_on_sale_products', 100 );
    }

    public function widget_cart_item_quantity( $show, $cart_item_key ) {
        $cart_items = WC()->cart->get_cart();
        if ( empty( $cart_items ) || ! isset( $cart_items[ $cart_item_key ] ) ) {
            return $show;
        }

        if ( isset( $cart_items[ $cart_item_key ][ WCCS_Public_Auto_Add_To_Cart::CART_ITEM_ID ] ) ) {
            return false;
        }

        if ( isset( $cart_items[ $cart_item_key ]['_ewd_auto_added_product'] ) ) {
            if (
                ! isset( $cart_items[ $cart_item_key ]['_ewd_auto_added_product_discount_type'] ) ||
                'product_price' !== $cart_items[ $cart_item_key ]['_ewd_auto_added_product_discount_type']
            ) {
                return false;
            }
        }


        if ( isset( $cart_items[ $cart_item_key ]['_ewd_urlc_auto_added_product'] ) ) {
            if (
                ! isset( $cart_items[ $cart_item_key ]['_ewd_urlc_auto_added_product_discount_type'] ) ||
                'product_price' !== $cart_items[ $cart_item_key ]['_ewd_urlc_auto_added_product_discount_type']
            ) {
                return false;
            }
        }

        return $show;
    }

    public function show_on_sale_products( $ids ) {
        $current_stock_status = isset( $_GET['stock_status'] ) ? explode( ',', sanitize_text_field( $_GET['stock_status'] ) ) : array();
        if ( in_array( 'onsale', $current_stock_status ) ) {
            $discounted_products = WCCS()->products->get_discounted_products();
            if ( ! empty( $discounted_products ) ) {
                $ids = array_merge( $ids, $discounted_products );
            }
        }

        return $ids;
    }

}

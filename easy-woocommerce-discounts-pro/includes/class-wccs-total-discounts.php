<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Total_Discounts {

    public function get_discounts() {
        if ( ! WC()->cart ) {
            return false;
        }

        $cart_items = WC()->cart->get_cart();
        if ( empty( $cart_items ) ) {
            return false;
        }

        $include_tax = wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' );
        $discounts   = 0;

        if ( (int) WCCS()->settings->get_setting( 'total_discounts_include_cart_discounts', 1 ) ) {
            foreach ( WC()->cart->get_applied_coupons() as $coupon ) {
                $discounts += WC()->cart->get_coupon_discount_amount( $coupon, ! $include_tax );
            }
        }

        foreach ( $cart_items as $cart_item ) {
            if ( ! apply_filters( 'asnp_wccs_total_discounts_process_cart_item', true, $cart_item ) ) {
                continue;
            }

            // Compatibility with the Custom Product Boxes plugin.
            if ( '' === $cart_item['line_subtotal'] || ! empty( $cart_item['cpb_custom_parent_id'] ) ) {
                continue;
            }

            $line_subtotal      = $include_tax ?
                apply_filters( 'wccs_cart_item_line_subtotal', (float) $cart_item['line_subtotal'], $cart_item ) +
                apply_filters( 'wccs_cart_item_line_subtotal_tax', (float) $cart_item['line_subtotal_tax'], $cart_item ) :
                apply_filters( 'wccs_cart_item_line_subtotal', (float) $cart_item['line_subtotal'], $cart_item );
            $main_line_subtotal = isset( $cart_item['_wccs_main_price'] ) &&
                ( ! isset( $cart_item['_wccs_main_sale_price'] ) || $cart_item['_wccs_main_sale_price'] != $cart_item['_wccs_main_price'] ) ?
                (float) WCCS_Cart_Item_Helpers::get_main_price( $cart_item ) * $cart_item['quantity'] :
                (float) $cart_item['data']->get_regular_price() * $cart_item['quantity'];
            if ( $include_tax ) {
                $main_line_subtotal = wc_get_price_including_tax( $cart_item['data'], array( 'qty' => 1, 'price' => $main_line_subtotal ) );
            } else {
                $main_line_subtotal = wc_get_price_excluding_tax( $cart_item['data'], array( 'qty' => 1, 'price' => $main_line_subtotal ) );
            }

            $subtract = wc_format_decimal( $main_line_subtotal - $line_subtotal, wc_get_price_decimals() );
            if ( 0 < $subtract ) {
                $discounts += $subtract;
            }
        }

        $discounts = wc_format_decimal( $discounts, wc_get_price_decimals() );
        if ( 0 >= $discounts ) {
            return false;
        }

        return apply_filters( 'wccs_total_discounts_' . __FUNCTION__, $discounts, $include_tax );
    }

    public function get_discounts_html( $discounts = null ) {
        $discounts = null === $discounts ? $this->get_discounts() : $discounts;
        if ( false === $discounts ) {
            return '';
        }
        $value = '<strong>' . apply_filters( 'wccs_cart_total_discounts_html_prefix', '-' ) . wc_price( $discounts ) . '</strong>';
        return apply_filters( 'wccs_total_discounts_' . __FUNCTION__, $value );
    }

}

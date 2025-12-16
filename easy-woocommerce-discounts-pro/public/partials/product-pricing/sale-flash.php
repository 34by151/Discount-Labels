<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post, $product;

if ( ! $product || $product->is_on_sale() ) {
    return;
}

// Getting sale badge except of simple pricing rules.
$sale_badge = WCCS()->settings->get_setting( 'sale_badge', array() );
$type       = WCCS()->settings->get_setting( 'sale_badge_type', 'sale' );

if ( WCCS_Helpers::should_change_display_price_html() ) {
    if ( 'sale' === $type ) {
        unset( $sale_badge['simple'] );
    } elseif ( 'discount' === $type && isset( $sale_badge['simple'] ) ) {
        if ( $discount = WCCS()->product_helpers->get_percentage_badge_value( $product ) ) {
            $html = '<span class="onsale wccs-onsale-badge wccs-onsale-badge-discount">';
            $html .= apply_filters( 'wccs_sale_flash_negative_symbol', '<span class="wccs-sale-flash-negative-symbol">-</span>' )
                    . esc_html( apply_filters( 'wccs_sale_flash_percentage_value', round( $discount ), $discount ) )
                    . apply_filters( 'wccs_sale_flash_percentage_symbol', '<span class="wccs-sale-flash-percentage-symbol">%</span>' );
            $html .= '</span>';
            echo apply_filters( 'wccs_sale_flash_discount_value', $html, $discount, $product, $post );
            return;
        }
        unset( $sale_badge['simple'] );
    }
}

if ( empty( $sale_badge ) ) {
    return;
}

// If product has any pricing rule except simple rules show sale tag.
if ( WCCS()->WCCS_Product_Onsale_Cache->is_onsale( $product, $sale_badge ) ) {
    echo apply_filters( 'wccs_sale_flash', '<span class="onsale wccs-onsale-badge">' . esc_html__( 'Sale!', 'woocommerce' ) . '</span>', $post, $product );
}

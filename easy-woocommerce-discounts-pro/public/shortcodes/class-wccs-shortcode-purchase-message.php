<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Shortcode_Purchase_Message {

	public function output( $atts, $content = null ) {
        $atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'wccs_purchase_message' );
        if ( 0 < absint( $atts['product_id'] ) ) {
            $product = wc_get_product( $atts['product_id'] );
        } else {
            global $product;
        }

        if ( ! $product ) {
            return '';
        }

        $product_pricing = new WCCS_Public_Product_Pricing( $product, WCCS()->pricing );

        ob_start();
        $product_pricing->purchase_message();
        return ob_get_clean();
	}

}

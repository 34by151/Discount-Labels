<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_WC_Payments {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'exchange_price' ), 999 );
    }

    public static function exchange_price() {
        $multi_currency = WC_Payments_Multi_Currency();
        $enabled_currencies = $multi_currency->get_enabled_currencies();
        if ( 1 < count( $enabled_currencies ) ) {
            add_filter( 'wccs_maybe_exchange_price', array( __CLASS__, 'maybe_exchange_price' ), 10, 2 );
        }
    }

    public static function maybe_exchange_price( $price, $type = 'product' ) {
        $multi_currency = WC_Payments_Multi_Currency();
        return $multi_currency->get_price( $price, $type );
    }

}

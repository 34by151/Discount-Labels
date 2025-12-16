<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Product_Purchase_Message_Cache extends WCCS_Abstract_Cache {

    const TYPE = 'message';

    public function __construct() {
        parent::__construct( 'wccs_product_purchase_message_', 'wccs_product_purchase_message' );
    }

    public function get_purchase_message( array $args ) {
        if ( empty( $args ) || empty( $args['product_id'] ) ) {
            return false;
        }

        if ( 0 >= absint( $args['product_id'] ) ) {
            return false;
        }

        $cache = WCCS()->WCCS_DB_Cache->get_item_by_product( absint( $args['product_id'] ), static::TYPE );
        $value = ! empty( $cache->value ) && is_array( $cache->value ) ? $cache->value : array();
        $key   = md5( wp_json_encode( $args ) );

        return isset( $value[ $key ] ) ? $value[ $key ] : false;
    }

    public function set_purchase_message( array $args, $message ) {
        if ( empty( $args ) || empty( $args['product_id'] ) ) {
            return false;
        }

        if ( 0 >= absint( $args['product_id'] ) ) {
            return false;
        }

        $cache = WCCS()->WCCS_DB_Cache->get_item_by_product( absint( $args['product_id'] ), static::TYPE );
        $value = ! empty( $cache->value ) && is_array( $cache->value ) ? $cache->value : array();
        $key   = md5( wp_json_encode( $args ) );

        $value[ $key ] = $message;

        if ( $cache ) {
            return WCCS()->WCCS_DB_Cache->update( $cache->id, array( 'value' => maybe_serialize( $value ) ) ); 
        }
        
        return WCCS()->WCCS_DB_Cache->add( array( 'product_id' => absint( $args['product_id'] ), 'cache_type' => static::TYPE, 'value' => maybe_serialize( $value ) ) );
    }

}

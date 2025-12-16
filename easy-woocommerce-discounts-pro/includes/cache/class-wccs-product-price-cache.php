<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Product_Price_Cache extends WCCS_Abstract_Cache {

    const TYPE = 'price';

    protected $pricing;

    protected $product_pricing;

    public function __construct( WCCS_Pricing $pricing = null ) {
        $this->pricing = null === $pricing ? WCCS()->pricing : $pricing;
        parent::__construct( 'wccs_product_price_', 'wccs_product_price' );
    }

    public function get_price( $product, $price, $price_type ) {
        $this->product_pricing = new WCCS_Public_Product_Pricing( $product, $this->pricing );

        $valid_rules = $this->get_valid_rules();
        if ( empty( $valid_rules ) ) {
            return $price;
        }

        if ( 0 === (int) WCCS()->settings->get_setting( 'cache_prices', 1 ) ) {
            $product_price = $this->product_pricing->get_price();
            if ( is_numeric( $product_price ) && 0 <= $product_price ) {
                return $product_price;
            }

            return $price;
        }

        $cache = WCCS()->WCCS_DB_Cache->get_item_by_product( $this->product_pricing->product_id, static::TYPE );
        $value = ! empty( $cache->value ) && is_array( $cache->value ) ? $cache->value : array();

        $key = md5( wp_json_encode(
            array(
                'product_id'    => $this->product_pricing->product_id,
                'parent_id'     => $this->product_pricing->parent_id,
                'price'         => $price,
                'price_type'    => $price_type,
                'rules'         => $valid_rules,
                'exclude_rules' => $this->pricing->get_exclude_rules(),
                'base_price'    => $this->product_pricing->get_base_price(),
            )
        ) );

        if ( ! isset( $value[ $key ] ) ) {
            $value[ $key ] = $this->product_pricing->get_price();
            if ( $cache ) {
                WCCS()->WCCS_DB_Cache->update( $cache->id, array( 'value' => maybe_serialize( $value ) ) ); 
            } else {
                WCCS()->WCCS_DB_Cache->add( array( 'product_id' => (int) $this->product_pricing->product_id, 'cache_type' => static::TYPE, 'value' => maybe_serialize( $value ) ) );
            }
        }

        if ( is_numeric( $value[ $key ] ) && 0 <= $value[ $key ] ) {
            return $value[ $key ];
        }

        // Note: Do not cast price to float that will causes issue for on sale tag of WooCommerce.
        return $price;
    }

    public function cache_price( $product, $price, array $args ) {
        if ( ! $product || empty( $price ) || empty( $args ) ) {
            return false;
        }

        if ( 0 === (int) WCCS()->settings->get_setting( 'cache_prices', 1 ) ) {
            return false;
        }

        $product = is_numeric( $product ) ? $product : $product->get_id();

        $cache = WCCS()->WCCS_DB_Cache->get_item_by_product( $product, static::TYPE  );
        $value = ! empty( $cache->value ) && is_array( $cache->value ) ? $cache->value : array();
        $key   = md5( wp_json_encode( $args ) );

        if ( ! isset( $value[ $key ] ) ) {
            $value[ $key ] = $price;
            if ( $cache ) {
                WCCS()->WCCS_DB_Cache->update( $cache->id, array( 'value' => maybe_serialize( $value ) ) ); 
            } else {
                WCCS()->WCCS_DB_Cache->add( array( 'product_id' => (int) $product, 'cache_type' => static::TYPE, 'value' => maybe_serialize( $value ) ) );
            }
        }

        return true;
    }

    public function get_cached_price( $product, array $args ) {
        if ( ! $product || empty( $args ) ) {
            return false;
        }

        if ( 0 === (int) WCCS()->settings->get_setting( 'cache_prices', 1 ) ) {
            return false;
        }

        $product = is_numeric( $product ) ? $product : $product->get_id();

        $cache = WCCS()->WCCS_DB_Cache->get_item_by_product( $product, static::TYPE );
        $value = ! empty( $cache->value ) && is_array( $cache->value ) ? $cache->value : array();
        $key   = md5( wp_json_encode( $args ) );

        return isset( $value[ $key ] ) ? $value[ $key ] : false;
    }

    protected function get_valid_rules() {
        if ( ! $this->product_pricing ) {
            return array();
        }

        $discounts = $this->product_pricing->get_simple_discounts();
        $fees      = $this->product_pricing->get_simple_fees();

        return $discounts + $fees;
    }

}

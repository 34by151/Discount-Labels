<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Product_Onsale_Cache extends WCCS_Abstract_Cache {

    const TYPE = 'onsale';
    
    protected $pricing;

    public function __construct( WCCS_Pricing $pricing = null ) {
        $this->pricing = null === $pricing ? WCCS()->pricing : $pricing;
        parent::__construct( 'wccs_product_onsale_', 'wccs_product_onsale' );
    }

    public function is_onsale( $product, $pricing_types ) {
        if ( ! $product || empty( $pricing_types ) ) {
            return false;
        }

        if ( ! empty( $pricing_types['simple'] ) ) {
            if ( $this->onsale_simple( $product ) ) {
                return true;
            }
        }

        if ( ! empty( $pricing_types['bulk'] ) ) {
            if ( $this->onsale_bulk( $product ) ) {
                return true;
            }
        }

        if ( ! empty( $pricing_types['tiered'] ) ) {
            if ( $this->onsale_tiered( $product ) ) {
                return true;
            }
        }

        if ( ! empty( $pricing_types['purchase'] ) ) {
            if ( $this->onsale_purchase( $product ) ) {
                return true;
            }
        }

        if ( ! empty( $pricing_types['products_group'] ) ) {
            if ( $this->onsale_products_group( $product ) ) {
                return true;
            }
        }

        return false;
    }

    public function onsale_simple( $product ) {
        if ( ! $product ) {
            return;
        }

        $rules = $this->pricing->get_simple_pricings();
        if ( empty( $rules ) ) {
            return false;
        }

        return $this->get_onsale( $product, $rules, 'simple' );
    }

    public function onsale_bulk( $product ) {
        if ( ! $product ) {
            return;
        }

        $rules = $this->pricing->get_bulk_pricings();
        if ( empty( $rules ) ) {
            return false;
        }

        return $this->get_onsale( $product, $rules, 'bulk' );
    }

    public function onsale_tiered( $product ) {
        if ( ! $product ) {
            return;
        }

        $rules = $this->pricing->get_tiered_pricings();
        if ( empty( $rules ) ) {
            return false;
        }

        return $this->get_onsale( $product, $rules, 'tiered' );
    }

    public function onsale_purchase( $product ) {
        if ( ! $product ) {
            return;
        }

        $rules = $this->pricing->get_purchase_pricings();
        if ( empty( $rules ) ) {
            return false;
        }

        return $this->get_onsale( $product, $rules, 'purchase' );
    }

    public function onsale_products_group( $product ) {
        if ( ! $product ) {
            return;
        }

        $rules = $this->pricing->get_products_group_pricings();
        if ( empty( $rules ) ) {
            return false;
        }

        return $this->get_onsale( $product, $rules, 'products_group' );
    }

    protected function get_onsale( $product, $rules, $type ) {
        if ( ! $product || empty( $rules ) || empty( $type ) ) {
            return false;
        }

        $cache_enabled = (int) WCCS()->settings->get_setting( 'cache_onsale_badge', 1 );

        if ( $cache_enabled ) {
            $cache = WCCS()->WCCS_DB_Cache->get_item_by_product( $product->get_id(), static::TYPE );
            $value = ! empty( $cache->value ) && is_array( $cache->value ) ? $cache->value : array();
            $key   = md5( wp_json_encode(
                array(
                    'type'          => $type,
                    'rules'         => $rules,
                    'exclude_rules' => $this->pricing->get_exclude_rules(),
                )
            ) );

            if ( ! empty( $value[ $key ] ) ) {
                return 'yes' === $value[ $key ];
            }
        }

        // Product should not inside exclude rules to have a sale badge.
        if ( $this->pricing->is_in_exclude_rules( $product->get_id(), 0, array() ) ) {
            if ( $cache_enabled ) {
                $value[ $key ] = 'no';
                if ( $cache ) {
                    WCCS()->WCCS_DB_Cache->update( $cache->id, array( 'value' => maybe_serialize( $value ) ) ); 
                } else {
                    WCCS()->WCCS_DB_Cache->add( array( 'product_id' => $product->get_id(), 'cache_type' => static::TYPE, 'value' => maybe_serialize( $value ) ) );
                }
            }
            
            return false;
        }

        $onsale = $this->check_rules( $rules, $product->get_id() );

        // if product is a variable product and one of its variations is onsale set product onsale badge to true.
        if ( ! $onsale && 'variable' === $product->get_type() ) {
            $varations = WCCS()->product_helpers->get_available_variations( $product );
            foreach ( $varations as $variation ) {
                $attributes = WCCS()->WCCS_Attribute_Helpers->get_product_attributes( $variation['variation_id'] );
                // Checking variation not in exclude rules.
                if ( $this->pricing->is_in_exclude_rules( $product->get_id(), $variation['variation_id'], $attributes ) ) {
                    continue;
                }

                $onsale = $this->check_rules( $rules, $product->get_id(), $variation['variation_id'], $attributes );
                if ( $onsale ) {
                    break;
                }
            }
        }

        if ( $cache_enabled ) {
            $value[ $key ] = $onsale ? 'yes' : 'no';
            if ( $cache ) {
                WCCS()->WCCS_DB_Cache->update( $cache->id, array( 'value' => maybe_serialize( $value ) ) ); 
            } else {
                WCCS()->WCCS_DB_Cache->add( array( 'product_id' => $product->get_id(), 'cache_type' => static::TYPE, 'value' => maybe_serialize( $value ) ) );
            }
        }

        return $onsale;
    }

    protected function check_rules( $rules, $product_id, $variation_id = 0, array $attributes = array() ) {
        if ( empty( $rules ) || empty( $product_id ) ) {
            return false;
        }

        foreach ( $rules as $rule ) {
            if ( empty( $rule['mode'] ) ) {
                continue;
            }

            if ( 'products_group' === $rule['mode'] && $this->check_groups_rule( $rule, $product_id, $variation_id, $attributes ) ) {
                return true;
            } elseif ( 'products_group' !== $rule['mode'] && $this->check_rule( $rule, $product_id, $variation_id, $attributes ) ) {
                return true;
            }
        }

        return false;
    }

    protected function check_rule( $rule, $product_id, $variation_id = 0, array $attributes = array() ) {
        if ( empty( $rule ) || empty( $product_id ) ) {
            return false;
        }

        if ( ! empty( $rule['mode'] ) && 'simple' === $rule['mode'] ) {
            if ( isset( $rule['discount_type'] ) && in_array( $rule['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
                return false;
            }
        }

        if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $rule['items'], $product_id, $variation_id, $attributes ) ) {
            return false;
        }

        if ( ! empty( $rule['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $rule['exclude_items'], $product_id, $variation_id, $attributes ) ) {
            return false;
        }

        return true;
    }

    protected function check_groups_rule( $rule, $product_id, $variation_id = 0, array $attributes = array() ) {
        if ( empty( $rule ) || empty( $product_id ) ) {
            return false;
        }

        if ( empty( $rule['mode'] ) || 'products_group' !== $rule['mode'] ) {
            return false;
        }

        if ( empty( $rule['groups'] ) ) {
            return false;
        }

        foreach ( $rule['groups'] as $group ) {
            if ( empty( $group['items'] ) || ! WCCS()->WCCS_Product_Validator->is_valid_product( $group['items'], $product_id, $variation_id, $attributes ) ) {
                continue;
            }

            if ( ! empty( $rule['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $rule['exclude_items'], $product_id, $variation_id, $attributes ) ) {
                continue;
            }

            return true;
        }

        return false;
    }

}

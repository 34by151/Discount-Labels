<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Rules_Helpers {

    public static function has_cart_conditions( array $rules ) {
        $cart_conditions = apply_filters( 'wccs_cart_conditions', array(
            'products_in_cart',
            'product_variations_in_cart',
            'featured_products_in_cart',
            'onsale_products_in_cart',
            'product_categories_in_cart',
            'product_attributes_in_cart',
            'product_tags_in_cart',
            'number_of_cart_items',
            'subtotal_excluding_tax',
            'subtotal_including_tax',
            'quantity_of_cart_items',
            'cart_total_weight',
            'coupons_applied',
            'quantity_of_products',
            'quantity_of_variations',
            'quantity_of_categories',
            'quantity_of_attributes',
            'quantity_of_tags',
            'payment_method',
            'shipping_method',
            'shipping_country',
            'shipping_state',
            'shipping_postcode',
            'shipping_zone',
            'subtotal_of_products_include_tax',
            'subtotal_of_products_exclude_tax',
            'subtotal_of_variations_include_tax',
            'subtotal_of_variations_exclude_tax',
            'subtotal_of_categories_include_tax',
            'subtotal_of_categories_exclude_tax',
            'subtotal_of_attributes_include_tax',
            'subtotal_of_attributes_exclude_tax',
            'subtotal_of_tags_include_tax',
            'subtotal_of_tags_exclude_tax',
        ) );

        return self::has_conditions( $rules, $cart_conditions );  
    }

    public static function has_user_conditions( array $rules ) {
        $user_conditions = apply_filters( 'wccs_user_conditions', array(
            'customers',
            'money_spent',
            'user_usage_limit',
            'number_of_orders',
            'last_order_amount',
            'roles',
            'email',
            'bought_products',
            'bought_product_variations',
            'bought_product_categories',
            'bought_product_attributes',
            'bought_product_tags',
            'bought_featured_products',
            'bought_onsale_products',
            'user_capability',
            'user_meta',
            'average_money_spent_per_order',
            'last_order_date',
            'number_of_products_reviews',
            'quantity_of_bought_products',
            'quantity_of_bought_variations',
            'quantity_of_bought_categories',
            'quantity_of_bought_attributes',
            'quantity_of_bought_tags',
            'amount_of_bought_products',
            'amount_of_bought_variations',
            'amount_of_bought_categories',
            'amount_of_bought_attributes',
            'amount_of_bought_tags',
        ) );

        return self::has_conditions( $rules, $user_conditions );
    }

    public static function has_conditions( array $rules, array $conditions, $check_all_conditions = false ) {
        if ( empty( $rules ) || empty( $conditions ) ) {
            return false;
        }

        foreach ( $conditions as $condition ) {
            foreach ( $rules as $rule ) {
                if ( $check_all_conditions && ! self::has_condition( $rule, $condition ) ) {
                    return false;
                } elseif ( ! $check_all_conditions && self::has_condition( $rule, $condition ) ) {
                    return true;
                }
            }
        }

        return $check_all_conditions ? true : false;
    }

    public static function has_condition( $rule, $condition ) {
        if ( empty( $rule ) || empty( $condition ) || empty( $rule['conditions'] ) ) {
            return false;
        }

        foreach ( $rule['conditions'] as $group ) {
            if ( isset( $group['condition'] ) && $group['condition'] == $condition ) {
                return true;
            } elseif ( ! empty( $group ) ) {
                foreach ( $group as $rule_condition ) {
                    if ( ! empty( $rule_condition['condition'] ) && $rule_condition['condition'] == $condition ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

}

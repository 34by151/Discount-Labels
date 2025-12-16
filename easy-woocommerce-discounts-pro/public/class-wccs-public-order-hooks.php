<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Public_Order_Hooks {

    public static function init() {
        add_action( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'add_applied_rules' ] );
        add_action( 'woocommerce_store_api_checkout_order_processed', [ __CLASS__, 'add_applied_rules' ] );
        add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'checkout_create_order_line_item' ], 10, 3 );
    }

    public static function add_applied_rules( $order ) {
        $order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
        if ( ! $order ) {
            return;
        }

        static::add_pricing_rules_usage( $order );
        static::add_rules_usage_from_session( $order );
        static::add_shipping_rules_usage( $order );
    }

    public static function add_pricing_rules_usage( $order ) {
        if ( ! WC()->cart ) {
            return;
        }

        $cart_items = WC()->cart->get_cart();
        if ( empty( $cart_items ) ) {
            return;
        }

        $applied_rules = array();
        foreach ( $cart_items as $cart_item ) {
            if ( ! empty( $cart_item['_wccs_applied_rules'] ) ) {
                $applied_rules = array_merge( $applied_rules, $cart_item['_wccs_applied_rules'] );
            }
        }

        if ( empty( $applied_rules ) ) {
            return;
        }

        $rule_usage = array();
        foreach ( $applied_rules as $applied_rule ) {
            if ( isset( $rule_usage[ $applied_rule['id'] ] ) ) {
                $rule_usage[ $applied_rule['id'] ]['usage_count']++;
            } else {
                $rule_usage[ $applied_rule['id'] ] = array(
                    'rule_id'     => $applied_rule['id'],
                    'order_id'    => $order->get_id(),
                    'usage_count' => 1,
                );
            }
        }

        static::log_rules_usage( $rule_usage );
    }

    public static function add_rules_usage_from_session( $order ) {
        if ( ! WC()->session ) {
            return;
        }

        $session_keys = [
            'wccs_applied_coupons',
            'wccs_applied_fees',
            'wccs_applied_shipping_discounts',
        ];

        foreach ( $session_keys as $session_key ) {
            $applied_rules = WC()->session->get( $session_key );
            if ( empty( $applied_rules ) ) {
                continue;
            }

            $rule_usage = [];
            foreach ( $applied_rules as $rule ) {
                if ( isset( $rule['id'] ) ) {
                    $rule_usage[ $rule['id'] ] = [
                        'rule_id'     => $rule['id'],
                        'order_id'    => $order->get_id(),
                        'usage_count' => 1,
                    ];
                }
            }

            static::log_rules_usage( $rule_usage );
        }
    }

    public static function add_shipping_rules_usage( $order ) {
        if ( ! WC()->session ) {
            return;
        }

        $shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $shipping_methods ) ) {
			return;
		}

        $rule_usage = array();
        foreach ( $shipping_methods as $shipping_method ) {
            if ( false !== strpos( $shipping_method, 'dynamic_shipping:' ) ) {
                $rule_usage[ str_replace( 'dynamic_shipping:', '', $shipping_method ) ] = array(
                    'rule_id'     => str_replace( 'dynamic_shipping:', '', $shipping_method ),
                    'order_id'    => $order->get_id(),
                    'usage_count' => 1,
                );
            }
        }

        static::log_rules_usage( $rule_usage );
    }

    protected static function log_rules_usage( $rule_usage ) {
        if ( empty( $rule_usage ) ) {
            return;
        }

        $rule_usage_logs = WCCS()->container()->get( WCCS_DB_Rule_Usage_Logs::class );
        $rule_usage_logs->log_rules_usage( $rule_usage );

        $used_by = WCCS_Helpers::get_used_by();
        if ( empty( $used_by ) ) {
            return;
        }

        $user_usage_logs = WCCS()->container()->get( WCCS_DB_User_Usage_Logs::class );
        foreach ( $rule_usage as $rule ) {
            $user_usage_logs->log_user_usage( $rule['rule_id'], $used_by );
        }
    }

    public static function checkout_create_order_line_item( $item, $cart_item_key, $values ) {
        $keys = [
            '_wccs_main_price',
            '_wccs_main_display_price',
            '_wccs_before_discounted_price',
            '_wccs_discounted_price',
            '_wccs_prices',
            '_wccs_prices_main',
            '_wccs_main_sale_price',
            '_wccs_applied_rules',
        ];

        foreach ( $keys as $key ) {
            if ( isset( $values[ $key ] ) ) {
                $item->add_meta_data( $key, $values[ $key ], true );
            }
        }
    }

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Public_Checkout_Fee_Hooks {

    const SESSION_KEY = 'wccs_applied_fees';

    protected $display_multiple;

    public function __construct( WCCS_Loader $loader ) {
        $this->display_multiple = WCCS()->settings->get_setting( 'checkout_fee_display_multiple_fees', 'separate' );

        $loader->add_action( 'woocommerce_cart_calculate_fees', $this, 'add_fees' );
        $loader->add_filter( 'woocommerce_cart_totals_fee_html', $this, 'cart_totals_fee_html', 10, 2 );
    }

    public function add_fees( $cart ) {
        $fees = WCCS()->WCCS_Checkout_Fee->get_possible_fees( $cart );
        if ( empty( $fees ) ) {
            if ( WC()->session ) {
                WC()->session->set( static::SESSION_KEY, [] );
            }
            return;
        }

        if ( WC()->session ) {
            $applied_fees = array_map( function( $fee ) {
                return [
                    'id'          => $fee->id,
                    'name'        => $fee->name,
                    'description' => ! empty( $fee->description ) ? $fee->description : '',
                    'apply_mode'  => isset( $fee->apply_mode ) ? $fee->apply_mode : '',
                    'fee_type'    => isset( $fee->fee_type ) ? $fee->fee_type : '',
                    'unique_name' => isset( $fee->unique_name ) ? $fee->unique_name : '',
                    'fee_amount'  => isset( $fee->fee_amount ) ? $fee->fee_amount : 0,
                ];
            }, $fees );
            WC()->session->set( static::SESSION_KEY, $applied_fees );
        }

        $tax_class = WCCS()->settings->get_setting( 'checkout_fee_tax_class', 'not_taxable' );
        $taxable   = 'not_taxable' === $tax_class ? false : true;

        if ( 'combine' === $this->display_multiple ) {
            $amount = 0;
            foreach ( $fees as $fee ) {
                $fee_amount = $fee->fee_amount;
                if ( 'price_fee' === $fee->fee_type || 'price_fee_per_item' === $fee->fee_type ) {
                    $fee_amount = WCCS_Helpers::maybe_exchange_price( $fee_amount, 'coupon' );
                }
                $amount += $fee_amount;
            }

            $cart->add_fee( WCCS()->WCCS_Checkout_Fee->get_combine_fee_name(), $amount, $taxable, $tax_class );
        } else {
            foreach ( $fees as $fee ) {
                $fee_amount = $fee->fee_amount;
                if ( 'price_fee' === $fee->fee_type || 'price_fee_per_item' === $fee->fee_type ) {
                    $fee_amount = WCCS_Helpers::maybe_exchange_price( $fee_amount, 'coupon' );
                }
                $cart->add_fee( $fee->unique_name, $fee_amount, $taxable, $tax_class );
            }
        }
    }

    public function cart_totals_fee_html( $fee_html, $fee ) {
        $fees = WCCS()->WCCS_Checkout_Fee->get_possible_fees();
        if ( empty( $fees ) ) {
            return $fee_html;
        }

        if ( 'combine' === $this->display_multiple ) {
            if ( WCCS()->WCCS_Checkout_Fee->get_combine_fee_name() === $fee->name ) {
                if ( 0 < $fee->amount ) {
                    return apply_filters( 'wccs_cart_totals_fee_html_prefix', '+' ) . $fee_html;
                }
            }
        } else {
            foreach ( $fees as $p_fee ) {
                if ( $p_fee->name === $fee->name ) {
                    if ( 0 < $fee->amount ) {
                        return apply_filters( 'wccs_cart_totals_fee_html_prefix', '+' ) . $fee_html;
                    }
                    break;
                }
            }
        }

        return $fee_html;
    }

}

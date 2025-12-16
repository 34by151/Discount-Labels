<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Public_Total_Discounts_Hooks extends WCCS_Public_Controller {

    public $total_discounts;

    protected $loader;

    public function __construct( WCCS_Loader $loader, WCCS_Total_Discounts $total_discounts = null ) {
        $this->loader          = $loader;
        $this->total_discounts = null !== $total_discounts ? $total_discounts : new WCCS_Total_Discounts(); 
        $this->total_discounts_hooks();
    }

    public function display_total_discounts() {
        $label = __( 'Total Discounts', 'easy-woocommerce-discounts' );
        if ( (int) WCCS()->settings->get_setting( 'localization_enabled', 1 ) ) {
            $label = WCCS()->settings->get_setting( 'total_discounts_label', $label );
        }

        $discount = $this->total_discounts->get_discounts();
        if ( ! $discount ) {
            return;
        }

        $this->render_view(
            'product-pricing.total-discounts',
            array(
                'controller'    => $this,
                'discount'      => $discount,
                'discount_html' => $this->total_discounts->get_discounts_html(),
                'label'         => $label,
            )
        );
    }

    public function checkout_create_order( $order ) {
        if ( ! $order ) {
            return;
        }

        $discount = $this->total_discounts->get_discounts();
        if ( ! $discount ) {
            return;
        }

        $order->add_meta_data( 'wccs_total_discounts', $discount, true );
    }

    public function get_order_item_totals( $total_rows, $order ) {
        $discount = $order->get_meta( 'wccs_total_discounts' );
        if ( empty( $discount ) ) {
            return $total_rows;
        }

        $label = __( 'Total Discounts', 'easy-woocommerce-discounts' );
        if ( (int) WCCS()->settings->get_setting( 'localization_enabled', 1 ) ) {
            $label = WCCS()->settings->get_setting( 'total_discounts_label', $label );
        }

        $position = array_search( 'order_total', array_keys( $total_rows ) );
        if ( false === $position ) {
            $total_rows['wccs_total_discounts'] = array(
                'label' => $label . ':',
                'value' => apply_filters( 'wccs_cart_total_discounts_html_prefix', '-' ) . wc_price( $discount, array( 'currency' => $order->get_currency() ) ),
            );
            return $total_rows;
        }
        
        return array_merge(
            array_slice( $total_rows, 0, $position ),
            array(
                'wccs_total_discounts' => array(
                    'label' => $label . ':',
                    'value' => apply_filters( 'wccs_cart_total_discounts_html_prefix', '-' ) . wc_price( $discount, array( 'currency' => $order->get_currency() ) ),
                ),
            ),
            array_slice( $total_rows, $position )
        );
    }

    public function total_discounts_hooks() {
        $hook = WCCS()->settings->get_setting( 'total_discounts_position_cart', 'woocommerce_cart_totals_before_order_total' );
        if ( 'none' !== $hook ) {
            $this->loader->add_action( 
                $hook, 
                $this, 
                'display_total_discounts' 
            );
        }
        $hook = WCCS()->settings->get_setting( 'total_discounts_position_checkout', 'woocommerce_review_order_before_order_total' );
        if ( 'none' !== $hook ) {
            $this->loader->add_action( 
                $hook, 
                $this, 
                'display_total_discounts' 
            );
        }

        $this->loader->add_action( 'woocommerce_checkout_create_order', $this, 'checkout_create_order' );
        $this->loader->add_filter( 'woocommerce_get_order_item_totals', $this, 'get_order_item_totals', 10, 2 );
    }

}

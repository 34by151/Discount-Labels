<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Admin_Order_Hooks extends WCCS_Admin_Controller {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function enable_hooks() {
        if ( (int) WCCS()->settings->get_setting( 'display_total_discounts', 0 ) ) {
            $this->loader->add_action(
                'woocommerce_admin_order_totals_after_tax',
                $this,
                'order_total_discounts'
            );
        }

        $this->loader->add_filter( 'woocommerce_hidden_order_itemmeta', $this, 'hidden_order_itemmeta' );
    }

    public function order_total_discounts( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $discount = $order->get_meta( 'wccs_total_discounts' );
        if ( empty( $discount ) ) {
            return;
        }

        $label = __( 'Total Discounts', 'easy-woocommerce-discounts' );
        if ( (int) WCCS()->settings->get_setting( 'localization_enabled', 1 ) ) {
            $label = WCCS()->settings->get_setting( 'total_discounts_label', $label );
        }

        $this->render_view(
            'order.total-discounts',
            array(
                'controller' => $this,
                'order'      => $order,
                'discount'   => $discount,
                'label'      => $label,
            )
        );
    }

    public function hidden_order_itemmeta( $items ) {
		return array_merge( $items, [
			'_wccs_main_price',
            '_wccs_main_display_price',
            '_wccs_before_discounted_price',
            '_wccs_discounted_price',
            '_wccs_prices',
            '_wccs_prices_main',
            '_wccs_main_sale_price',
            '_wccs_applied_rules',
		] );
	}

}

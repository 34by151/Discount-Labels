<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Compatibility_WC_Subscriptions {

	protected $loader;

	public function __construct( WCCS_Loader $loader ) {
		$this->loader = $loader;
	}

	public function init() {
		// $this->loader->add_filter( 'wccs_should_apply_cart_discounts', $this, 'should_apply' );
	}

	public function should_apply( $apply ) {
		if ( ! is_callable( array( 'WC_Subscriptions_Cart', 'get_calculation_type' ) ) ) {
			return $apply;
		}

		if ( $apply && 'recurring_total' === WC_Subscriptions_Cart::get_calculation_type() ) {
			return false;
		}

		return $apply;
	}

}

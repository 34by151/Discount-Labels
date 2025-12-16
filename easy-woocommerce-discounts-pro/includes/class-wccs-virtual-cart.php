<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Virtual_Cart extends WCCS_Abstract_Virtual_Cart {

	public function __construct( $cart = null ) {
		parent::__construct( $cart );
	}

	/**
	 * Reset cart totals to the defaults. Useful before running calculations.
	 *
	 * @access protected
	 */
	protected function reset_totals() {
		$this->set_totals( array() );
		$this->fees_api()->remove_all_fees();
		do_action( 'wccs_virtual_cart_reset', $this->cart, false );
	}

	/**
	 * Calculate totals for the items in the cart.
	 *
	 * @since 2.2.0
	 *
	 * @uses  WC_Cart_Totals
	 */
	public function calculate_totals() {
		$this->reset_totals();

		if ( $this->is_empty() ) {
			return;
		}

		do_action( 'wccs_virtual_cart_before_calculate_totals', $this->cart );

		new WC_Cart_Totals( $this->cart );

		do_action( 'wccs_virtual_cart_after_calculate_totals', $this->cart );
	}

}

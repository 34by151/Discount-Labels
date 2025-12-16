<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;

class WCCS_Blocks_Hooks {

	public static function init() {
		$extend = StoreApi::container()->get( ExtendSchema::class );
		WCCS_Store_API::init( $extend );

		add_action(
			'woocommerce_blocks_mini-cart_block_registration',
			function( $registry ) {
				$registry->register( new WCCS_Checkout_Integration() );
			}
		);
		add_action(
			'woocommerce_blocks_cart_block_registration',
			function( $registry ) {
				$registry->register( new WCCS_Checkout_Integration() );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function( $registry ) {
				$registry->register( new WCCS_Checkout_Integration() );
			}
		);
	}

}

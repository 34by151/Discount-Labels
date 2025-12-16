<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class WCCS_Checkout_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wccs-checkout-integration';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		/* wp_enqueue_style(
			'wccs-checkout-integration',
			$this->get_url( 'checkout-integration/style', 'css' ),
			[],
			WCCS_VERSION
		); */

		wp_register_script(
			'wccs-checkout-integration',
			$this->get_url( 'checkout-integration/index', 'js' ),
			[ 'wc-blocks-checkout' ],
			WCCS_VERSION,
			true
		);

		/* wp_set_script_translations(
			'wccs-checkout-integration',
			'easy-woocommerce-discounts',
			WCCS_PLUGIN_DIR . 'languages'
		); */
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [ 'wccs-checkout-integration' ];
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [];
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
	    return [];
	}

	public function get_url( $file, $ext ) {
		return plugins_url( $this->get_path( $ext ) . $file . '.' . $ext, WCCS_PLUGIN_FILE );
    }

    protected function get_path( $ext ) {
        return 'css' === $ext ? 'pubilc/css/' : 'public/js/';
    }

}

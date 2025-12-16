<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Pricing_Condition_Validator extends WCCS_Condition_Validator {

    protected $cart_totals;

    public function subtotal_excluding_tax( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		$this->calculate_cart_totals();

		return WCCS()->WCCS_Comparison->math_compare(
			$this->cart_totals->get_total( 'items_subtotal' ),
			WCCS_Helpers::maybe_exchange_price( $value, 'coupon' ),
			$condition['math_operation_type']
		);
	}

	public function subtotal_including_tax( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		$this->calculate_cart_totals();

		return WCCS()->WCCS_Comparison->math_compare(
			$this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' ),
			WCCS_Helpers::maybe_exchange_price( $value, 'coupon' ),
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_products_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['products'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_products_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['products'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_regular_products_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->get_items_subtotal( array( $condition ), true ),
			WCCS_Helpers::maybe_exchange_price( $value, 'coupon' ),
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_regular_products_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->get_items_subtotal( array( $condition ), false ),
			WCCS_Helpers::maybe_exchange_price( $value, 'coupon' ),
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_onsale_products_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->get_items_subtotal( array( $condition ), true ),
			WCCS_Helpers::maybe_exchange_price( $value, 'coupon' ),
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_onsale_products_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->get_items_subtotal( array( $condition ), false ),
			WCCS_Helpers::maybe_exchange_price( $value, 'coupon' ),
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_variations_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['variations'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_variations_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['variations'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_categories_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['categories'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_categories_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['categories'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_attributes_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['attributes'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_attributes_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['attributes'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_tags_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['tags'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_tags_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['tags'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function taxonomy_subtotal_including_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['taxonomies'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function taxonomy_subtotal_excluding_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['taxonomies'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$this->calculate_cart_totals();

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$cart_subtotal = $this->cart_totals->get_total( 'items_subtotal' );
			$subtotal      = 0 < $cart_subtotal - $subtotal ? $cart_subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	protected function get_items_subtotal( array $items, $include_tax = true ) {
		if ( empty( $items ) ) {
			return 0;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return 0;
		}

		$cart_items = $this->cart->filter_cart_items( $items, false );
		if ( empty( $cart_items ) ) {
			return 0;
		}

		$subtotal = 0;
		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			$subtotal += $include_tax ?
				$this->cart_totals->get_line_item_subtotal( $cart_item_key ) +
				$this->cart_totals->get_line_item_subtotal_tax( $cart_item_key ) :
				$this->cart_totals->get_line_item_subtotal( $cart_item_key );
		}

		return $subtotal;
	}

    protected function calculate_cart_totals( $force = true ) {
        if ( ! $this->cart_totals ) {
            $this->cart_totals = new WCCS_Cart_Totals( WC()->cart );
        }

        $this->cart_totals->calculate( $force );
    }

}

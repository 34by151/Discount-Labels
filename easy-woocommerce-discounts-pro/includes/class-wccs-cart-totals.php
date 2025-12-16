<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Cart_Totals {

	/**
	 * Reference to cart object.
	 *
	 * @var WC_Cart
	 */
	protected $cart;

	/**
	 * Line items to calculate.
	 *
	 * @since 3.2.0
	 * @var array
	 */
	protected $items = array();

    protected $calculated = false;

    protected $cart_contents = array();

    /**
	 * Should taxes be calculated?
	 *
	 * @var boolean
	 */
	protected $calculate_tax = true;

    /**
	 * Stores totals.
	 *
	 * @since 5.2.0
	 * @var array
	 */
	protected $totals = array(
		'items_subtotal'     => 0,
		'items_subtotal_tax' => 0,
	);

	/**
	 * Cache of tax rates for a given tax class.
	 *
	 * @var array
	 */
	protected $item_tax_rates;

    /**
	 * Sets up the items provided, and calculate totals.
	 *
	 * @since 5.2.0
	 * @throws Exception If missing WC_Cart object.
	 * @param WC_Cart $cart Cart object to calculate totals for.
	 */
	public function __construct( &$cart = null ) {
        if ( ! is_a( $cart, 'WC_Cart' ) ) {
			throw new Exception( 'A valid WC_Cart object is required' );
		}

		$this->cart          = $cart;
		$this->calculate_tax = wc_tax_enabled() && ! WC()->customer->get_is_vat_exempt();
    }

    public function calculate( $force = false ) {
        if ( ! $force && $this->calculated ) {
            return;
        }

        $this->calculate_item_subtotals();
        $this->calculated = true;
    }

    /**
	 * Get default blank set of props used per item.
	 *
	 * @since  5.2.0
	 * @return object
	 */
	protected function get_default_item_props() {
		return (object) array(
			'object'             => null,
			'tax_class'          => '',
			'taxable'            => false,
			'quantity'           => 0,
			'product'            => false,
			'price_includes_tax' => false,
			'subtotal'           => 0,
			'subtotal_tax'       => 0,
			'subtotal_taxes'     => array(),
			'total'              => 0,
			'total_tax'          => 0,
			'taxes'              => array(),
		);
	}

    /**
	 * Get tax rates for an item. Caches rates in class to avoid multiple look ups.
	 *
	 * @param  object $item Item to get tax rates for.
	 * @return array of taxes
	 */
	protected function get_item_tax_rates( $item ) {
		if ( ! wc_tax_enabled() ) {
			return array();
		}
		$tax_class      = $item->product->get_tax_class();
		$item_tax_rates = isset( $this->item_tax_rates[ $tax_class ] ) ? $this->item_tax_rates[ $tax_class ] : $this->item_tax_rates[ $tax_class ] = WC_Tax::get_rates( $item->product->get_tax_class(), WC()->customer );

		// Allow plugins to filter item tax rates.
		return apply_filters( 'woocommerce_cart_totals_get_item_tax_rates', $item_tax_rates, $item, $this->cart );
	}

	/**
	 * Handles a cart or order object passed in for calculation. Normalises data
	 * into the same format for use by this class.
	 *
	 * Each item is made up of the following props, in addition to those returned by get_default_item_props() for totals.
	 *  - key: An identifier for the item (cart item key or line item ID).
	 *  - cart_item: For carts, the cart item from the cart which may include custom data.
	 *  - quantity: The qty for this line.
	 *  - price: The line price in cents.
	 *  - product: The product object this cart item is for.
	 *
	 * @since 5.2.0
	 */
    protected function get_items_from_cart() {
        $this->items = array();

		foreach ( $this->cart->get_cart() as $cart_item_key => $cart_item ) {
			$item                          = $this->get_default_item_props();
			$item->key                     = $cart_item_key;
			$item->object                  = $cart_item;
			$item->tax_class               = $cart_item['data']->get_tax_class();
			$item->taxable                 = 'taxable' === $cart_item['data']->get_tax_status();
			$item->price_includes_tax      = wc_prices_include_tax();
			$item->quantity                = $cart_item['quantity'];
			$item->price                   = WCCS_Helpers::wc_add_number_precision_deep( $cart_item['data']->get_price() * $cart_item['quantity'] );
			$item->product                 = $cart_item['data'];
			$item->tax_rates               = $this->get_item_tax_rates( $item );
			$this->items[ $cart_item_key ] = $item;
		}
    }

	/**
	 * Subtotals are costs before discounts.
	 *
	 * To prevent rounding issues we need to work with the inclusive price where possible
	 * otherwise we'll see errors such as when working with a 9.99 inc price, 20% VAT which would
	 * be 8.325 leading to totals being 1p off.
	 *
	 * Pre tax coupons come off the price the customer thinks they are paying - tax is calculated
	 * afterwards.
	 *
	 * e.g. $100 bike with $10 coupon = customer pays $90 and tax worked backwards from that.
	 *
	 * @since 5.2.0
	 */
    public function calculate_item_subtotals() {
        $this->get_items_from_cart();

        $merged_subtotal_taxes = array(); // Taxes indexed by tax rate ID for storage later.

		$adjust_non_base_location_prices = apply_filters( 'woocommerce_adjust_non_base_location_prices', true );
		$is_customer_vat_exempt          = WC()->customer->get_is_vat_exempt();

		foreach ( $this->items as $item_key => $item ) {
			if ( $item->price_includes_tax ) {
				if ( $is_customer_vat_exempt ) {
					$item = $this->remove_item_base_taxes( $item );
				} elseif ( $adjust_non_base_location_prices ) {
					$item = $this->adjust_non_base_location_price( $item );
				}
			}

			$item->subtotal = $item->price;

			if ( $this->calculate_tax && $item->product->is_taxable() ) {
				$item->subtotal_taxes = WC_Tax::calc_tax( $item->subtotal, $item->tax_rates, $item->price_includes_tax );
				$item->subtotal_tax   = array_sum( array_map( array( $this, 'round_line_tax' ), $item->subtotal_taxes ) );

				if ( $item->price_includes_tax ) {
					// Use unrounded taxes so we can re-calculate from the orders screen accurately later.
					$item->subtotal = $item->subtotal - array_sum( $item->subtotal_taxes );
				}

				foreach ( $item->subtotal_taxes as $rate_id => $rate ) {
					if ( ! isset( $merged_subtotal_taxes[ $rate_id ] ) ) {
						$merged_subtotal_taxes[ $rate_id ] = 0;
					}
					$merged_subtotal_taxes[ $rate_id ] += $this->round_line_tax( $rate );
				}
			}

			$this->cart_contents[ $item_key ]['line_subtotal']     = WCCS_Helpers::wc_remove_number_precision( $item->subtotal );
			$this->cart_contents[ $item_key ]['line_subtotal_tax'] = WCCS_Helpers::wc_remove_number_precision( $item->subtotal_tax );
		}

		$items_subtotal = $this->get_rounded_items_total( $this->get_values_for_total( 'subtotal' ) );

		// Prices are not rounded here because they should already be rounded based on settings in `get_rounded_items_total` and in `round_line_tax` method calls.
		$this->set_total( 'items_subtotal', $items_subtotal );
		$this->set_total( 'items_subtotal_tax', array_sum( $merged_subtotal_taxes ), 0 );
    }

    /**
	 * Ran to remove all base taxes from an item. Used when prices include tax, and the customer is tax exempt.
	 *
	 * @since 5.2.0
	 * @param object $item Item to adjust the prices of.
	 * @return object
	 */
	protected function remove_item_base_taxes( $item ) {
		if ( $item->price_includes_tax && $item->taxable ) {
			if ( apply_filters( 'woocommerce_adjust_non_base_location_prices', true ) ) {
				$base_tax_rates = WC_Tax::get_base_tax_rates( $item->product->get_tax_class( 'unfiltered' ) );
			} else {
				/**
				 * If we want all customers to pay the same price on this store, we should not remove base taxes from a VAT exempt user's price,
				 * but just the relevent tax rate. See issue #20911.
				 */
				$base_tax_rates = $item->tax_rates;
			}

			// Work out a new base price without the shop's base tax.
			$taxes = WC_Tax::calc_tax( $item->price, $base_tax_rates, true );

			// Now we have a new item price (excluding TAX).
			$item->price              = WCCS_Helpers::round( $item->price - array_sum( $taxes ) );
			$item->price_includes_tax = false;
		}
		return $item;
	}

    /**
	 * Only ran if woocommerce_adjust_non_base_location_prices is true.
	 *
	 * If the customer is outside of the base location, this removes the base
	 * taxes. This is off by default unless the filter is used.
	 *
	 * Uses edit context so unfiltered tax class is returned.
	 *
	 * @since 5.2.0
	 * @param object $item Item to adjust the prices of.
	 * @return object
	 */
	protected function adjust_non_base_location_price( $item ) {
		if ( $item->price_includes_tax && $item->taxable ) {
			$base_tax_rates = WC_Tax::get_base_tax_rates( $item->product->get_tax_class( 'unfiltered' ) );

			if ( $item->tax_rates !== $base_tax_rates ) {
				// Work out a new base price without the shop's base tax.
				$taxes     = WC_Tax::calc_tax( $item->price, $base_tax_rates, true );
				$new_taxes = WC_Tax::calc_tax( $item->price - array_sum( $taxes ), $item->tax_rates, false );

				// Now we have a new item price.
				$item->price = $item->price - array_sum( $taxes ) + array_sum( $new_taxes );
			}
		}
		return $item;
	}

    public function get_line_item_subtotal( $cart_item_key ) {
        return isset( $this->cart_contents[ $cart_item_key ]['line_subtotal'] ) ?
            $this->cart_contents[ $cart_item_key ]['line_subtotal'] : 0;
    }

    public function get_line_item_subtotal_tax( $cart_item_key ) {
        return isset( $this->cart_contents[ $cart_item_key ]['line_subtotal_tax'] ) ?
            $this->cart_contents[ $cart_item_key ]['line_subtotal_tax'] : 0;
    }

    /**
	 * Get a single total with or without precision (in cents).
	 *
	 * @since  5.2.0
	 * @param  string $key Total to get.
	 * @param  bool   $in_cents Should the totals be returned in cents, or without precision.
	 * @return int|float
	 */
	public function get_total( $key = 'total', $in_cents = false ) {
		$totals = $this->get_totals( $in_cents );
		return isset( $totals[ $key ] ) ? $totals[ $key ] : 0;
	}

	/**
	 * Set a single total.
	 *
	 * @since  5.2.0
	 * @param string $key Total name you want to set.
	 * @param int    $total Total to set.
	 */
	protected function set_total( $key, $total ) {
		$this->totals[ $key ] = $total;
	}

    /**
	 * Get all totals with or without precision (in cents).
	 *
	 * @since  5.2.0
	 * @param  bool $in_cents Should the totals be returned in cents, or without precision.
	 * @return array.
	 */
	public function get_totals( $in_cents = false ) {
		return $in_cents ? $this->totals : WCCS_Helpers::wc_remove_number_precision_deep( $this->totals );
	}

    /**
	 * Returns array of values for totals calculation.
	 *
	 * @param string $field Field name. Will probably be `total` or `subtotal`.
	 * @return array Items object
	 */
	protected function get_values_for_total( $field ) {
		return array_values( wp_list_pluck( $this->items, $field ) );
	}

    /**
	 * Should always round at subtotal?
	 *
	 * @since 5.2.0
	 * @return bool
	 */
	protected function round_at_subtotal() {
		return 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' );
	}

    /**
	 * Apply rounding to item subtotal before summing.
	 *
	 * @since 5.2.0
	 * @param float $value Item subtotal value.
	 * @return float
	 */
	public function round_item_subtotal( $value ) {
		if ( ! $this->round_at_subtotal() ) {
			$value = WCCS_Helpers::round( $value );
		}
		return $value;
	}

    /**
	 * Return rounded total based on settings. Will be used by Cart and Orders.
	 *
	 * @since 5.2.0
	 *
	 * @param array $values Values to round. Should be with precision.
	 *
	 * @return float|int Appropriately rounded value.
	 */
	public function get_rounded_items_total( $values ) {
		return array_sum(
			array_map(
				array( $this, 'round_item_subtotal' ),
				$values
			)
		);
	}

    /**
	 * Apply rounding to an array of taxes before summing. Rounds to store DP setting, ignoring precision.
	 *
	 * @since  5.2.0
	 * @param  float $value    Tax value.
	 * @param  bool  $in_cents Whether precision of value is in cents.
	 * @return float
	 */
	protected function round_line_tax( $value, $in_cents = true ) {
		if ( ! $this->round_at_subtotal() ) {
			$value = wc_round_tax_total( $value, $in_cents ? 0 : null );
		}
		return $value;
	}

}

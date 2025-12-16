<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Cart_Discount {

	protected $discounts;

	protected $cart;

	protected $apply_method;

	protected $date_time_validator;

	protected $condition_validator;

	protected $coupon_codes;

	public $rules_filter;

	const DISCOUNT_SUFFIX = 'wccs_cart_discount_';

	public function __construct( array $discounts, WCCS_Cart $cart = null, $apply_method = null ) {
		$wccs = WCCS();

		$this->discounts = $discounts;
		$this->cart = null === $cart ? $wccs->cart : $cart;
		$this->apply_method = null === $apply_method ? $wccs->settings->get_setting( 'cart_discount_apply_method', 'sum' ) : $apply_method;
		$this->date_time_validator = $wccs->WCCS_Date_Time_Validator;
		$this->condition_validator = $wccs->WCCS_Condition_Validator;
		$this->rules_filter = new WCCS_Rules_Filter();
	}

	public function get_discounts() {
		return $this->discounts;
	}

	/**
	 * Get all of enabled coupon codes of the plugin.
	 *
	 * @return array
	 */
	public function get_coupon_codes() {
		if ( isset( $this->coupon_codes ) ) {
			return $this->coupon_codes;
		}

		$this->coupon_codes = array();
		if ( empty( $this->discounts ) ) {
			return $this->coupon_codes;
		}

		foreach ( $this->discounts as $discount ) {
			if ( ! isset( $this->coupon_codes[ wc_format_coupon_code( $discount->name ) ] ) ) {
				$this->coupon_codes[ wc_format_coupon_code( $discount->name ) ] = $discount;
			}
		}

		return $this->coupon_codes;
	}

	public function get_valid_discounts() {
		if ( ! isset( $this->cart ) || ! WC()->cart || empty( $this->discounts ) ) {
			return array();
		}

		$valid_discounts = array();
		$applied_coupons = WC()->cart->get_applied_coupons();
		$applying_coupon = WCCS_Helpers::get_applying_coupon();
		$individual_manual = false;
		$manuals = array();

		foreach ( $this->discounts as $discount ) {
			// Skip URL-based coupons.
			if ( ! empty( $discount->url_coupon ) ) {
				continue;
			}

			// Validating rule usage limit.
			if ( ! empty( $discount->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $discount ) ) {
				continue;
			}

			if ( ! $this->date_time_validator->is_valid_date_times( $discount->date_time, isset( $discount->date_times_match_mode ) ? $discount->date_times_match_mode : 'one' ) ) {
				continue;
			}

			if ( ! $this->condition_validator->is_valid_conditions( $discount, isset( $discount->conditions_match_mode ) ? $discount->conditions_match_mode : 'all' ) ) {
				continue;
			}

			// Handle manual discounts
			if ( ! empty( $discount->manual ) ) {
				$code = wc_format_coupon_code( $discount->name );
				$is_applying = ( $code === $applying_coupon );
				$is_individual = ( isset( $discount->apply_mode ) && 'individually' === $discount->apply_mode );

				if ( $is_applying && $is_individual ) {
					$individual_manual = $discount->id;
				}

				if ( in_array( $code, $applied_coupons, true ) || $is_applying ) {
					$manuals[] = $discount;
				} else {
					continue;
				}
			}

			$valid_discounts[] = $discount;
		}

		if ( $individual_manual ) {
			// Remove all other applied coupons when individual-use coupon is applied
			$applied_coupons = array_filter( $applied_coupons, function ( $applied ) use ( $manuals, $individual_manual ) {
				foreach ( $manuals as $manual ) {
					$code = wc_format_coupon_code( $manual->name );
					if ( $code === $applied && $manual->id !== $individual_manual ) {
						return false;
					}
				}
				return true;
			} );

			WC()->cart->applied_coupons = array_values( $applied_coupons );

			// Filter valid discounts to include only the individual-use discount
			$valid_discounts = array_filter( $valid_discounts, function ( $discount ) use ( $individual_manual ) {
				return empty( $discount->manual ) || $discount->id === $individual_manual;
			} );
		}

		$valid_discounts = apply_filters( 'wccs_cart_discount_valid_discounts', $valid_discounts, $this );

		if ( ! empty( $valid_discounts ) ) {
			usort( $valid_discounts, array( WCCS()->WCCS_Sorting, 'sort_by_ordering_asc' ) );
			$valid_discounts = $this->rules_filter->by_apply_mode( $valid_discounts );
		}

		return $valid_discounts;
	}

	public function get_possible_discounts() {
		$valids = $this->get_valid_discounts();
		if ( empty( $valids ) ) {
			return array();
		}

		$prices_include_tax = wc_prices_include_tax();
		$cart_subtotal = $prices_include_tax ? $this->cart->subtotal : $this->cart->subtotal_ex_tax;
		if ( 0 >= (float) $cart_subtotal ) {
			return array();
		}

		// Applying limit on discount amount.
		$limit = WCCS_Helpers::get_cart_discount_limit( $cart_subtotal );

		$possibles = array();
		foreach ( $valids as $discount ) {
			if ( 0 === $limit ) {
				break;
			} elseif ( empty( $discount->discount_amount ) ) {
				continue;
			}

			$discount = $this->get_discount_data( $discount, $prices_include_tax, $limit );
			if ( ! $discount || empty( $discount->discount_amount ) || 0 >= $discount->discount_amount ) {
				continue;
			}

			$possibles[ $discount->code ] = $discount;
		}

		if ( ! empty( $possibles ) ) {
			if ( 'first' === $this->apply_method ) {
				$first = array_shift( $possibles );
				return array( $first->code => $first );
			} elseif ( 'max' === $this->apply_method ) {
				$max = array_shift( $possibles );
				foreach ( $possibles as $discount ) {
					if ( $discount->discount_amount > $max->discount_amount ) {
						$max = $discount;
					}
				}
				return array( $max->code => $max );
			} elseif ( 'min' === $this->apply_method ) {
				$min = array_shift( $possibles );
				foreach ( $possibles as $discount ) {
					if ( $discount->discount_amount < $min->discount_amount ) {
						$min = $discount;
					}
				}
				return array( $min->code => $min );
			}
		}

		return $possibles;
	}

	protected function get_discount_data( $discount, $prices_include_tax = null, $limit = null ) {
		$prices_include_tax = null === $prices_include_tax ? wc_prices_include_tax() : $prices_include_tax;
		$cart_subtotal = $prices_include_tax ? $this->cart->subtotal : $this->cart->subtotal_ex_tax;
		$limit = null === $limit ? WCCS_Helpers::get_cart_discount_limit( $cart_subtotal ) : $limit;
		if ( 0 >= (float) $cart_subtotal ) {
			return false;
		}

		if ( ! $discount || empty( $discount->discount_amount ) ) {
			return false;
		}

		$discount = clone $discount;

		$discount->code = wc_format_coupon_code( $discount->name );
		if ( ! strlen( trim( $discount->code ) ) ) {
			return false;
		}

		$discount_amount = (float) $discount->discount_amount;
		if (
			'percentage' === $discount->discount_type ||
			( 'recurring_percent' === $discount->discount_type && empty( $discount->items ) && empty( $discount->exclude_items ) )
		) {
			$discount->amount = $discount_amount;
			$discount_amount = $discount_amount / 100 * $cart_subtotal;
		} elseif ( 'percentage_discount_per_item' === $discount->discount_type || 'recurring_percent' === $discount->discount_type ) {
			$discount->amount = $discount_amount;
			$discount->product_ids = array();
			$discount_amount = 0;

			if ( ! empty( $discount->items ) ) {
				$cart_items = $this->cart->filter_cart_items( $discount->items, false, ! empty( $discount->exclude_items ) ? $discount->exclude_items : array() );
			} else {
				$cart_items = empty( $discount->exclude_items ) ? $this->cart->get_cart() : $this->cart->filter_cart_items( array( array( 'item' => 'all_products' ) ), false, $discount->exclude_items );
			}

			if ( empty( $cart_items ) ) {
				return false;
			}

			foreach ( $cart_items as $cart_item ) {
				if (
					'recurring_percent' === $discount->discount_type &&
					is_callable( 'WC_Subscriptions_Product::is_subscription' ) && ! WC_Subscriptions_Product::is_subscription( $cart_item['data'] )
				) {
					continue;
				}

				$discount_amount += $prices_include_tax ?
					apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item ) +
					apply_filters( 'wccs_cart_item_line_subtotal_tax', $cart_item['line_subtotal_tax'], $cart_item ) :
					apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item );

				$product_id = (int) $cart_item['product_id'];
				if ( isset( $cart_item['variation_id'] ) && 0 < (int) $cart_item['variation_id'] ) {
					$product_id = (int) $cart_item['variation_id'];
				}
				$discount->product_ids[] = $product_id;
			}

			if ( 0 < $discount_amount ) {
				$discount_amount = (float) $discount->discount_amount / 100 * $discount_amount;
			}
		} elseif (
			'price_discount_per_item' === $discount->discount_type ||
			( 'recurring_fee' === $discount->discount_type && ( ! empty( $discount->items ) || ! empty( $discount->exclude_items ) ) )
		) {
			$discount->amount = $discount_amount;
			$discount->product_ids = array();
			$discount_amount = 0;

			if ( ! empty( $discount->items ) ) {
				$cart_items = $this->cart->filter_cart_items( $discount->items, false, ! empty( $discount->exclude_items ) ? $discount->exclude_items : array() );
			} else {
				$cart_items = empty( $discount->exclude_items ) ? $this->cart->get_cart() : $this->cart->filter_cart_items( array( array( 'item' => 'all_products' ) ), false, $discount->exclude_items );
			}

			if ( empty( $cart_items ) ) {
				return false;
			}

			foreach ( $cart_items as $cart_item ) {
				if (
					'recurring_fee' === $discount->discount_type &&
					is_callable( 'WC_Subscriptions_Product::is_subscription' ) && ! WC_Subscriptions_Product::is_subscription( $cart_item['data'] )
				) {
					continue;
				}

				$item_price = $prices_include_tax ?
					apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item ) +
					apply_filters( 'wccs_cart_item_line_subtotal_tax', $cart_item['line_subtotal_tax'], $cart_item ) :
					apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item );

				$item_discount = min( $discount->amount * $cart_item['quantity'], $item_price );
				if ( 0 >= $item_discount ) {
					return false;
				}
				$discount_amount += $item_discount;
				$product_id = (int) $cart_item['product_id'];
				if ( isset( $cart_item['variation_id'] ) && 0 < (int) $cart_item['variation_id'] ) {
					$product_id = (int) $cart_item['variation_id'];
				}
				$discount->product_ids[] = $product_id;
			}
		} elseif ( 'fixed_price' === $discount->discount_type ) {
			$avail_discount = $discount_amount;
			$discount->product_ids = array();
			$discount_amount = 0;

			if ( ! empty( $discount->items ) ) {
				$cart_items = $this->cart->filter_cart_items( $discount->items, false, ! empty( $discount->exclude_items ) ? $discount->exclude_items : array() );
			} else {
				$cart_items = empty( $discount->exclude_items ) ? $this->cart->get_cart() : $this->cart->filter_cart_items( array( array( 'item' => 'all_products' ) ), false, $discount->exclude_items );
			}

			if ( empty( $cart_items ) ) {
				return false;
			}

			foreach ( $cart_items as $cart_item ) {
				if ( 0 >= $avail_discount ) {
					break;
				}

				$item_price = $prices_include_tax ?
					apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item ) +
					apply_filters( 'wccs_cart_item_line_subtotal_tax', $cart_item['line_subtotal_tax'], $cart_item ) :
					apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item );

				$item_discount = min( $avail_discount, $item_price );
				if ( 0 >= $item_discount ) {
					return false;
				}

				$discount_amount += $item_discount;
				$avail_discount -= $item_discount;
			}

			if ( 0 < $discount_amount ) {
				$discount->amount = $discount_amount;
			}
		}

		if ( '' !== $limit ) {
			$discount_amount = $discount_amount > $limit ? $limit : $discount_amount;
			$limit = $limit - $discount_amount > 0 ? $limit - $discount_amount : 0;
		}

		if ( 0 >= $discount_amount ) {
			return false;
		}

		$discount->discount_amount = $discount_amount;

		return $discount;
	}

	public function get_combine_coupon_code() {
		$coupon_code = WCCS()->settings->get_setting( 'coupon_label', 'discount' );
		if ( strlen( trim( $coupon_code ) ) ) {
			$coupon_code = WCCS_Helpers::wc_version_check() ? wc_format_coupon_code( $coupon_code ) : apply_filters( 'woocommerce_coupon_code', $coupon_code );
		}
		$coupon_code = strlen( $coupon_code ) ? $coupon_code : 'discount';

		return apply_filters( 'wccs_cart_discount_combine_coupon_code', $coupon_code );
	}

	/**
	 * Checking is the given coupon belong to the plugin.
	 *
	 * @since  2.3.0
	 *
	 * @param  string     $coupon_code
	 * @param  array|null $coupon_codes
	 *
	 * @return boolean
	 */
	public function is_cart_discount_coupon( $coupon_code, $coupon_codes = null ) {
		if ( $coupon_code === $this->get_combine_coupon_code() ) {
			return apply_filters( 'wccs_is_cart_discount_coupon', true, $coupon_code );
		}

		$coupon_codes = null !== $coupon_codes ? $coupon_codes : $this->get_coupon_codes();
		if ( ! empty( $coupon_codes ) && isset( $coupon_codes[ $coupon_code ] ) ) {
			return apply_filters( 'wccs_is_cart_discount_coupon', true, $coupon_code );
		}

		return apply_filters( 'wccs_is_cart_discount_coupon', false, $coupon_code );
	}

	public function get_manual_coupon( $coupon_code ) {
		if ( empty( $coupon_code ) || empty( $this->discounts ) ) {
			return false;
		}

		if ( ! isset( $this->cart ) || ! WC()->cart ) {
			return false;
		}

		$possibles = $this->get_possible_discounts();
		if ( ! isset( $possibles[ $coupon_code ] ) ) {
			foreach ( $this->discounts as $discount ) {
				if (
					isset( $discount->manual ) &&
					1 == $discount->manual &&
					1 == $discount->status &&
					wc_format_coupon_code( $discount->name ) === $coupon_code
				) {
					return apply_filters( 'wccs_get_manual_coupon', $this->get_discount_data( $discount ), $coupon_code );
				}
			}

			return false;
		}

		$discount = $possibles[ $coupon_code ];
		if ( isset( $discount->manual ) && 1 == $discount->manual ) {
			return apply_filters( 'wccs_get_manual_coupon', $discount, $coupon_code );
		}

		return false;
	}

	public function is_manual_coupon( $coupon_code ) {
		if ( empty( $coupon_code ) || empty( $this->discounts ) ) {
			return false;
		}

		foreach ( $this->discounts as $discount ) {
			if ( isset( $discount->manual ) && 1 == $discount->manual && wc_format_coupon_code( $discount->name ) === $coupon_code ) {
				return true;
			}
		}

		return false;
	}

	public function get_discount_amount() {
		$valid_discounts = $this->get_valid_discounts();

		if ( empty( $valid_discounts ) ) {
			return 0;
		}

		$cart_subtotal = wc_prices_include_tax() ? $this->cart->subtotal : $this->cart->subtotal_ex_tax;

		$amounts = array();

		foreach ( $valid_discounts as $discount ) {
			if ( empty( $discount->discount_amount ) ) {
				continue;
			}

			$discount_amount = (float) $discount->discount_amount;

			if ( $discount_amount > 0 ) {
				if ( 'percentage' == $discount->discount_type ) {
					$discount_amount = $discount_amount / 100 * $cart_subtotal;
					if ( $discount_amount > 0 ) {
						$amounts[] = $discount_amount;
					}
				} else {
					$amounts[] = $discount_amount;
				}

				if ( 'first' === $this->apply_method ) {
					break;
				}
			}
		}

		$discount_amount = 0;
		if ( ! empty( $amounts ) ) {
			if ( 'first' === $this->apply_method ) {
				$discount_amount = $amounts[0];
			} elseif ( 'max' === $this->apply_method ) {
				$discount_amount = max( $amounts );
			} elseif ( 'min' === $this->apply_method ) {
				$discount_amount = min( $amounts );
			} elseif ( 'sum' === $this->apply_method ) {
				$discount_amount = array_sum( $amounts );
			}
		}

		// Applying limit on discount amount.
		$discount_limit = WCCS_Helpers::get_cart_discount_limit( $cart_subtotal );
		if ( '' !== $discount_limit && 0 <= (float) $discount_limit ) {
			$discount_amount = $discount_amount > $discount_limit ? $discount_limit : $discount_amount;
		}

		return $discount_amount;
	}

}

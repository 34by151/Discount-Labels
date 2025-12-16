<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Public_Cart_Item_Pricing {

	protected $pricing;

	protected $apply_method;

	protected $cart;

	protected $discounts;

	/**
	 * An array containing discount_id and it's related prices with quantity of each price.
	 *
	 * @var array
	 */
	protected $discount_prices = array();

	/**
	 * An array containing applied discounts.
	 *
	 * @var array
	 */
	protected $applied_discounts = array();

	protected $applied_fees = array();

	/**
	 * An array containing range with associated price of the range.
	 *
	 * @var array
	 */
	protected $range_price = array();

	/**
	 * An array of prices that applied to this item associated with their applied quantites.
	 *
	 * @var array
	 */
	public $prices = array();

	public $item;

	public $product_id;

	public $variation_id;

	public $product;

	public function __construct( $cart_item_id, $cart_item, WCCS_Pricing $pricing, $apply_method = '', $cart = null, WCCS_Cart_Pricing_Cache $pricing_cache = null ) {
		$this->item = $cart_item;
		$this->pricing = $pricing;
		$this->apply_method = ! empty( $apply_method ) ? $apply_method : WCCS()->settings->get_setting( 'product_pricing_discount_apply_method', 'sum' );
		$this->product_id = $cart_item['product_id'];
		$this->variation_id = $cart_item['variation_id'];
		$this->product = ! empty( $this->variation_id ) ? wc_get_product( $this->variation_id ) : wc_get_product( $this->product_id );
		$this->cart = null !== $cart ? $cart : WCCS()->cart;
		$this->discounts = new WCCS_Cart_Item_Pricing_Discounts( $cart_item_id, $this->item, $this->pricing, $this->cart, $pricing_cache );
	}

	/**
	 * Getting price.
	 *
	 * @since  1.0.0
	 *
	 * @return float
	 */
	public function get_price() {
		if ( ! $this->product ) {
			return false;
		}

		if ( $this->pricing->is_in_exclude_rules( $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ) ) ) {
			return apply_filters( 'wccs_public_cart_item_pricing_' . __FUNCTION__, false, $this );
		}

		do_action( 'wccs_public_cart_item_pricing_before_get_price', $this );

		$base_price = $this->get_base_price();
		$adjusted_price = $this->apply_discounts( $base_price, $this->item['data']->get_price( 'edit' ) );
		$adjusted_price = $this->apply_fees( $adjusted_price );

		if ( $base_price != $adjusted_price ) {
			// Round adjusted price or no.
			if ( 'yes' === WCCS()->settings->get_setting( 'round_product_adjustment', 'no' ) ) {
				$adjusted_price = round( $adjusted_price, wc_get_price_decimals() );
			}

			do_action( 'wccs_public_cart_item_pricing_after_get_price', $this );

			return apply_filters( 'wccs_public_cart_item_pricing_' . __FUNCTION__, $adjusted_price, $this );
		}

		do_action( 'wccs_public_cart_item_pricing_after_get_price', $this );

		return apply_filters( 'wccs_public_cart_item_pricing_' . __FUNCTION__, false, $this );
	}

	public function get_base_price() {
		if ( 'cart_item_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
			return (float) apply_filters( 'wccs_public_cart_item_pricing_' . __FUNCTION__, (float) $this->item['data']->get_price( 'edit' ), $this->item, $this->product, $this );
		}

		do_action( 'wccs_public_cart_item_pricing_before_get_base_price', $this );

		$base_price = (float) $this->product->get_price( 'edit' );
		if ( WCCS()->product_helpers->is_on_sale( $this->product, 'edit' ) ) {
			if ( 'regular_price' === WCCS()->settings->get_setting( 'on_sale_products_price', 'on_sale_price' ) ) {
				$base_price = (float) $this->product->get_regular_price( 'edit' );
			}
		}

		do_action( 'wccs_public_cart_item_pricing_after_get_base_price', $this );

		return (float) apply_filters( 'wccs_public_cart_item_pricing_' . __FUNCTION__, $base_price, $this->item, $this->product, $this );
	}

	/**
	 * Getting prices applied to this item.
	 *
	 * @since  2.7.0
	 *
	 * @return array
	 */
	public function get_prices() {
		return apply_filters( 'wccs_public_cart_item_pricing_' . __FUNCTION__, $this->prices, $this );
	}

	public function get_ranges_prices() {
		return apply_filters( 'wccs_public_cart_item_pricing_' . __FUNCTION__, $this->range_price, $this );
	}

	/**
	 * Get nearest end time of discounts.
	 *
	 * @param  boolean $available_discounts When it is true it gets time based of all of the product available discounts
	 * 										but when it is false it gets time from applied discounts.
	 *
	 * @return false|string
	 */
	public function get_nearest_end_time( $available_discounts = true ) {
		$discounts = true === $available_discounts ? $this->discounts->get_pricings() : $this->applied_discounts;
		if ( empty( $discounts ) ) {
			return apply_filters( 'wccs_cart_item_pricing_nearest_end_time', false );
		}

		$countdown_timer = new WCCS_Countdown_Timer();

		$ends = array();
		$end = false;
		foreach ( $discounts as $discount ) {
			if ( empty( $discount['date_time'] ) ) {
				continue;
			}

			$end = $countdown_timer->get_valid_nearest_end_time( $discount['date_time'], $discount['date_times_match_mode'] );
			if ( false !== $end ) {
				$ends[] = $end;
			}
		}

		if ( empty( $ends ) ) {
			return apply_filters( 'wccs_cart_item_pricing_nearest_end_time', false );
		}

		// Find nearest end time or minimum one.
		$end = $ends[0];
		for ( $i = 1; $i < count( $ends ); $i++ ) {
			if ( strtotime( $end ) > strtotime( $ends[ $i ] ) ) {
				$end = $ends[ $i ];
			}
		}

		return apply_filters( 'wccs_cart_item_pricing_nearest_end_time', $end );
	}

	public function get_applied_rules() {
		if ( empty( $this->applied_discounts ) && empty( $this->applied_fees ) ) {
			return array();
		}

		$rules = array_merge( $this->applied_discounts, $this->applied_fees );

		return $rules;
	}

	/**
	 * Setting applied prices to the item.
	 *
	 * @since  2.7.0
	 *
	 * @param  array $applied_discounts     // An array of applied discounts to the item.
	 * @param  float $product_display_price // Product display price.
	 *
	 * @return void
	 */
	protected function set_applied_prices( array $applied_discounts, $product_display_price ) {
		$this->prices = array();

		if ( empty( $this->discount_prices ) || empty( $applied_discounts ) ) {
			return;
		}

		$product_display_price = (string) wc_format_decimal( $product_display_price, '' );

		// All quantities of the product that exists in one of discounted_prices.
		$all_quantities = 0;
		$all_qty_processed = false;
		foreach ( $this->discount_prices as $discount_id => $prices ) {
			if ( in_array( $discount_id, $applied_discounts ) ) {
				foreach ( $prices as $price => $quantity ) {
					if ( ! $all_qty_processed ) {
						$all_quantities += $quantity;
					}

					$price = (string) wc_format_decimal( WCCS()->product_helpers->wc_get_price_to_display( $this->product, array( 'price' => $price ) ), '' );
					if ( isset( $this->prices[ $price ] ) ) {
						/**
						 * Do not apply product_display_price when method is sum.
						 * Because maybe product_display_price included twice times by different rules.
						 */
						if ( 'sum' !== $this->apply_method || $price != $product_display_price ) {
							$this->prices[ $price ] += $quantity;
						}
					} else {
						// Do not set quantity of product_display_price when apply_method is sum and calculate it later.
						if ( 'sum' === $this->apply_method && $price == $product_display_price ) {
							$this->prices[ $price ] = 0;
						} else {
							$this->prices[ $price ] = $quantity;
						}
					}
				}

				$all_qty_processed = true;
			}
		}

		// Calculate product_display_price quantity when apply_method is sum.
		if ( 'sum' === $this->apply_method && 0 < $all_quantities ) {
			if ( isset( $this->prices[ $product_display_price ] ) ) {
				// If quantity of display price greater than 0 then display it otherwise remove it.
				if ( 0 < $all_quantities - array_sum( array_values( $this->prices ) ) ) {
					$this->prices[ $product_display_price ] = $all_quantities - array_sum( array_values( $this->prices ) );
				} else {
					unset( $this->prices[ $product_display_price ] );
				}
			}
		}
	}

	protected function apply_fees( $base_price ) {
		$this->applied_fees = array();
		$fees = $this->get_simple_fees();
		if ( empty( $fees ) ) {
			return $base_price;
		}

		$fee_amounts = array();
		foreach ( $fees as $fee ) {
			if ( 'percentage_fee' === $fee['discount_type'] ) {
				if ( (float) $fee['discount'] / 100 * $base_price > 0 ) {
					$fee['amount'] = (float) $fee['discount'] / 100 * $base_price;
					$fee_amounts[] = $fee;
				}
			} elseif ( 'price_fee' === $fee['discount_type'] ) {
				if ( (float) $fee['discount'] > 0 ) {
					$fee['amount'] = (float) $fee['discount'];
					$fee_amounts[] = $fee;
				}
			}
		}

		if ( ! empty( $fee_amounts ) ) {
			$fee_amount = 0;
			if ( 'first' === $this->apply_method ) {
				$fee_amount = $fee_amounts[0]['amount'];
				$this->applied_fees[0] = $fee_amounts[0];
			} elseif ( 'max' === $this->apply_method ) {
				$fee_amount = max( wp_list_pluck( $fee_amounts, 'amount' ) );
				foreach ( $fee_amounts as $fee ) {
					if ( $fee['amount'] == $fee_amount ) {
						$this->applied_fees[0] = $fee;
						break;
					}
				}
			} elseif ( 'min' === $this->apply_method ) {
				$fee_amount = min( wp_list_pluck( $fee_amounts, 'amount' ) );
				foreach ( $fee_amounts as $fee ) {
					if ( $fee['amount'] == $fee_amount ) {
						$this->applied_fees[0] = $fee;
						break;
					}
				}
			} elseif ( 'sum' === $this->apply_method ) {
				$fee_amount = array_sum( wp_list_pluck( $fee_amounts, 'amount' ) );
				$this->applied_fees = $fee_amounts;
			}

			if ( $base_price + $fee_amount >= 0 ) {
				return $base_price + $fee_amount;
			}
		}

		return $base_price;
	}

	protected function apply_discounts( $base_price, $in_cart_price ) {
		$this->prices = array();
		$this->applied_discounts = array();
		$discounts = $this->discounts->get_discounts();
		if ( empty( $discounts ) ) {
			return $base_price;
		}

		// Get discount limit.
		$discount_limit = WCCS_Helpers::get_pricing_discount_limit( $base_price );

		$discount_amounts = array();
		foreach ( $discounts as $discount ) {
			if ( '' !== $discount_limit && 0 >= $discount_limit ) {
				break;
			}

			$discount_amount = $this->calculate_discount_amount( $discount, $base_price, $in_cart_price, $discount['id'], $discount_limit );
			if ( false !== $discount_amount ) {
				if ( '' !== $discount_limit ) {
					$discount_limit -= $discount_amount;
				}

				$discount_amounts[] = array(
					'id' => $discount['id'],
					'name' => $discount['name'],
					'description' => $discount['description'],
					'type' => 'discount',
					'mode' => ! empty( $discount['mode'] ) ? $discount['mode'] : '',
					'apply_mode' => ! empty( $discount['apply_mode'] ) ? $discount['apply_mode'] : '',
					'discount_type' => ! empty( $discount['discount_type'] ) ? $discount['discount_type'] : '',
					'discount' => ! empty( $discount['discount'] ) ? $discount['discount'] : 0,
					'amount' => $discount_amount,
					'date_time' => $discount['date_time'],
					'date_times_match_mode' => $discount['date_times_match_mode'],
				);
			}
		}

		if ( empty( $discount_amounts ) ) {
			return $base_price;
		}

		$applied_discounts = array();
		$discount_amount = 0;
		if ( 'first' === $this->apply_method ) {
			$discount_amount = $discount_amounts[0]['amount'];
			$applied_discounts[0] = $discount_amounts[0]['id'];
			$this->applied_discounts[0] = $discount_amounts[0];
		} elseif ( 'max' === $this->apply_method ) {
			$discount_amount = $discount_amounts[0]['amount'];
			$applied_discounts[0] = $discount_amounts[0]['id'];
			$this->applied_discounts[0] = $discount_amounts[0];
			for ( $i = 1; $i < count( $discount_amounts ); $i++ ) {
				if ( $discount_amount < $discount_amounts[ $i ]['amount'] ) {
					$discount_amount = $discount_amounts[ $i ]['amount'];
					$applied_discounts[0] = $discount_amounts[ $i ]['id'];
					$this->applied_discounts[0] = $discount_amounts[ $i ];
				}
			}
		} elseif ( 'min' === $this->apply_method ) {
			$discount_amount = $discount_amounts[0]['amount'];
			$applied_discounts[0] = $discount_amounts[0]['id'];
			$this->applied_discounts[0] = $discount_amounts[0];
			for ( $i = 1; $i < count( $discount_amounts ); $i++ ) {
				if ( $discount_amount > $discount_amounts[ $i ]['amount'] ) {
					$discount_amount = $discount_amounts[ $i ]['amount'];
					$applied_discounts[0] = $discount_amounts[ $i ]['id'];
					$this->applied_discounts[0] = $discount_amounts[ $i ];
				}
			}
		} elseif ( 'sum' === $this->apply_method ) {
			$discount_amount = array_sum( wp_list_pluck( $discount_amounts, 'amount' ) );
			$applied_discounts = wp_list_pluck( $discount_amounts, 'id' );
			$this->applied_discounts = $discount_amounts;
		}

		if ( $base_price - $discount_amount >= 0 ) {
			$this->set_applied_prices( $applied_discounts, $in_cart_price );
			return $base_price - $discount_amount;
		}

		// Reset applied discounts when discounts didn't applied.
		$this->applied_discounts = array();

		return $base_price;
	}

	protected function calculate_discount_amount( $discount, $base_price, $in_cart_price, $discount_id, $discount_limit ) {
		$this->discount_prices[ $discount_id ] = array();

		if ( 'purchase' === $discount['mode'] || 'products_group' === $discount['mode'] ) {
			if ( 'percentage_discount' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] / 100 * $base_price > 0 ) {
					// Limit discount amount if limit exists.
					$discount_amount = (float) $discount['discount'] / 100 * $base_price;
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}

					$discounted_price = $base_price - $discount_amount;
					if ( $discounted_price >= 0 ) {
						if ( $this->item['quantity'] > $discount['receive_quantity'] ) {
							$product_discounted_price = $discount['receive_quantity'] * $discounted_price + ( $this->item['quantity'] - $discount['receive_quantity'] ) * $in_cart_price;
							// Set discount prices.
							$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $discount['receive_quantity'];
							$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] = ! empty( $this->discount_prices[ $discount_id ][ (string) $in_cart_price ] ) ?
								$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] + ( $this->item['quantity'] - $discount['receive_quantity'] ) :
								( $this->item['quantity'] - $discount['receive_quantity'] );
						} else {
							$product_discounted_price = $this->item['quantity'] * $discounted_price;
							// Set discount prices.
							$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $this->item['quantity'];
						}

						return $base_price - ( $product_discounted_price / $this->item['quantity'] );
					}
				}
			} elseif ( 'price_discount' === $discount['discount_type'] || 'fixed_discount_per_item' === $discount['discount_type'] ) {
				// Limit discount amount if limit exists.
				$discount_amount = (float) $discount['discount'];
				if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
					$discount_amount = (float) $discount_limit;
				}

				$discounted_price = $base_price - $discount_amount;
				$discounted_price = 0 > (float) $discounted_price ? 0 : $discounted_price;
				if ( $discounted_price >= 0 ) {
					if ( $this->item['quantity'] > $discount['receive_quantity'] ) {
						$product_discounted_price = $discount['receive_quantity'] * $discounted_price + ( $this->item['quantity'] - $discount['receive_quantity'] ) * $in_cart_price;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $discount['receive_quantity'];
						$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] = ! empty( $this->discount_prices[ $discount_id ][ (string) $in_cart_price ] ) ?
							$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] + ( $this->item['quantity'] - $discount['receive_quantity'] ) :
							( $this->item['quantity'] - $discount['receive_quantity'] );
					} else {
						$product_discounted_price = $this->item['quantity'] * $discounted_price;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $this->item['quantity'];
					}

					return $base_price - ( $product_discounted_price / $this->item['quantity'] );
				}
			} elseif ( 'fixed_price' === $discount['discount_type'] || 'fixed_price_per_item' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] >= 0 ) {
					// Limit discount amount if limit exists.
					$discount_amount = (float) $discount['discount'];
					if ( '' !== $discount_limit && (float) $base_price - (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $base_price - (float) $discount_limit;
					}

					if ( $this->item['quantity'] > $discount['receive_quantity'] ) {
						$product_discounted_price = $discount['receive_quantity'] * $discount_amount + ( $this->item['quantity'] - $discount['receive_quantity'] ) * $in_cart_price;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discount_amount ] = $discount['receive_quantity'];
						$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] = ! empty( $this->discount_prices[ $discount_id ][ (string) $in_cart_price ] ) ?
							$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] + ( $this->item['quantity'] - $discount['receive_quantity'] ) :
							( $this->item['quantity'] - $discount['receive_quantity'] );
					} else {
						$product_discounted_price = $this->item['quantity'] * $discount_amount;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discount_amount ] = $this->item['quantity'];
					}

					return $base_price - ( $product_discounted_price / $this->item['quantity'] );
				}
			} elseif ( 'fixed_price_per_group' === $discount['discount_type'] ) {
				// Limit discount amount if limit exists.
				$discount_amount = (float) $discount['discount'];
				if ( '' !== $discount_limit && (float) $base_price - (float) $discount_amount > (float) $discount_limit ) {
					$discount_amount = (float) $base_price - (float) $discount_limit;
				}

				$discounted_price = $discount_amount / $discount['group_quantity'];
				if ( $discounted_price >= 0 ) {
					if ( $this->item['quantity'] > $discount['receive_quantity'] ) {
						$product_discounted_price = $discount['receive_quantity'] * $discounted_price + ( $this->item['quantity'] - $discount['receive_quantity'] ) * $in_cart_price;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $discount['receive_quantity'];
						$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] = ! empty( $this->discount_prices[ $discount_id ][ (string) $in_cart_price ] ) ?
							$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] + ( $this->item['quantity'] - $discount['receive_quantity'] ) :
							( $this->item['quantity'] - $discount['receive_quantity'] );
					} else {
						$product_discounted_price = $this->item['quantity'] * $discounted_price;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $this->item['quantity'];
					}

					return $base_price - ( $product_discounted_price / $this->item['quantity'] );
				}
			} elseif ( 'fixed_discount_per_group' === $discount['discount_type'] ) {
				// Limit discount amount if limit exists.
				$discount_amount = (float) $discount['discount'];
				if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
					$discount_amount = (float) $discount_limit;
				}

				$discounted_price = $base_price - ( $discount_amount / $discount['group_quantity'] );
				$discounted_price = 0 > (float) $discounted_price ? 0 : $discounted_price;
				if ( $discounted_price >= 0 ) {
					if ( $this->item['quantity'] > $discount['receive_quantity'] ) {
						$product_discounted_price = $discount['receive_quantity'] * $discounted_price + ( $this->item['quantity'] - $discount['receive_quantity'] ) * $in_cart_price;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $discount['receive_quantity'];
						$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] = ! empty( $this->discount_prices[ $discount_id ][ (string) $in_cart_price ] ) ?
							$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] + ( $this->item['quantity'] - $discount['receive_quantity'] ) :
							( $this->item['quantity'] - $discount['receive_quantity'] );
					} else {
						$product_discounted_price = $this->item['quantity'] * $discounted_price;
						// Set discount prices.
						$this->discount_prices[ $discount_id ][ (string) $discounted_price ] = $this->item['quantity'];
					}

					return $base_price - ( $product_discounted_price / $this->item['quantity'] );
				}
			}
		} elseif ( 'tiered' === $discount['mode'] ) {
			return $this->calculate_tiered_discount_amount( $discount, $base_price, $in_cart_price, $discount_id, $discount_limit );
		} else {
			if ( 'percentage_discount' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] / 100 * $base_price > 0 ) {
					// Limit discount amount if limit exists.
					$discount_amount = (float) $discount['discount'] / 100 * $base_price;
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}

					// Set discount prices.
					if ( 0 <= $base_price - $discount_amount ) {
						$this->discount_prices[ $discount_id ][ strval( $base_price - $discount_amount ) ] = 1;
					}

					return $discount_amount;
				}
			} elseif ( 'price_discount' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] > 0 ) {
					// Limit discount amount if limit exists.
					$discount_amount = (float) $discount['discount'];
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}

					// Set discount prices.
					if ( 0 <= $base_price - $discount_amount ) {
						$this->discount_prices[ $discount_id ][ strval( $base_price - $discount_amount ) ] = 1;
					} else {
						$discount_amount = $base_price;
						$this->discount_prices[ $discount_id ]['0'] = 1;
					}

					return $discount_amount;
				}
			} elseif ( 'fixed_price' === $discount['discount_type'] ) {
				// Limit discount amount if limit exists.
				$discount_amount = (float) $discount['discount'];
				if ( '' !== $discount_limit && (float) $base_price - (float) $discount_amount > (float) $discount_limit ) {
					$discount_amount = (float) $discount_limit;
					// Set discount prices.
					$this->discount_prices[ $discount_id ][ (string) ( (float) $base_price - (float) $discount_limit ) ] = 1;
				} else {
					// Set discount prices.
					$this->discount_prices[ $discount_id ][ (string) $discount_amount ] = 1;
				}

				return $base_price - $discount_amount;
			} elseif ( 'percentage_fee' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] / 100 * $base_price > 0 ) {
					$fee_amount = (float) $discount['discount'] / 100 * $base_price;
					// Set discount prices.
					if ( 0 <= $base_price + $fee_amount ) {
						$this->discount_prices[ $discount_id ][ strval( $base_price + $fee_amount ) ] = 1;
					}

					return $fee_amount * -1;
				}
			} elseif ( 'price_fee' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] > 0 ) {
					$fee_amount = (float) $discount['discount'];
					// Set discount prices.
					if ( 0 <= $base_price + $fee_amount ) {
						$this->discount_prices[ $discount_id ][ strval( $base_price + $fee_amount ) ] = 1;
					}

					return $fee_amount * -1;
				}
			}
		}

		return false;
	}

	protected function calculate_tiered_discount_amount( $discount, $base_price, $in_cart_price, $discount_id, $discount_limit ) {
		/**
		 * Initial Price.
		 * If first range starts from greater of 1 or start range min > 1 calculate price for them that are not in any range.
		 */
		$price = (int) $discount['quantities'][0]['min'] - $discount['quantity_from'] > 0 ? ( (int) $discount['quantities'][0]['min'] - $discount['quantity_from'] ) * $in_cart_price : 0;

		if ( (int) $discount['quantities'][0]['min'] - $discount['quantity_from'] > 0 ) {
			// Set discount prices.
			$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] = (int) $discount['quantities'][0]['min'] - $discount['quantity_from'];
			$range_str = $discount['quantity_from'] . ' - ' . ( (int) $discount['quantities'][0]['min'] - 1 );
			$this->range_price[ $discount_id ][ $range_str ] = array(
				'from' => $discount['quantity_from'],
				'to' => (int) $discount['quantities'][0]['min'] - 1,
				'price' => $in_cart_price,
			);
		}

		/**
		 * Quantities of item that tiered pricing will calculate on them.
		 * Do not add quantities that are not in any range.
		 * In other words if first range start from 2 subtract 1 quantity because 1 is not in any range.
		 */
		$qty = (int) $discount['quantities'][0]['min'] - $discount['quantity_from'] > 0 ? $discount['quantity'] - ( (int) $discount['quantities'][0]['min'] - $discount['quantity_from'] ) : $discount['quantity'];

		foreach ( $discount['quantities'] as $quantity ) {
			if ( 0 >= $qty ) {
				break;
			}

			if ( 'percentage_discount' === $quantity['discount_type'] ) {
				// Limit discount amount if limit exists.
				$amount = (float) $quantity['discount'] / 100 * $base_price;
				if ( '' !== $discount_limit && (float) $amount > (float) $discount_limit ) {
					$amount = (float) $discount_limit;
				}

				$range = '' !== $quantity['max'] ? ( (int) $quantity['max'] - (int) $quantity['min'] ) + 1 : ( $discount['quantity_to'] - (int) $quantity['min'] ) + 1;
				$range = $range < $qty ? $range : $qty;
				$price += $range * ( $base_price - $amount );
				$qty -= $range;

				// Set discount prices.
				$this->discount_prices[ $discount_id ][ strval( $base_price - $amount ) ] = ! empty( $this->discount_prices[ $discount_id ][ strval( $base_price - $amount ) ] ) ?
					$this->discount_prices[ $discount_id ][ strval( $base_price - $amount ) ] + $range :
					$range;

				$range_str = $quantity['min'] . ' - ' . $quantity['max'];
				$this->range_price[ $discount_id ][ $range_str ] = array(
					'from' => $quantity['min'],
					'to' => $quantity['max'],
					'price' => $base_price - $amount,
				);
			} elseif ( 'price_discount' === $quantity['discount_type'] ) {
				// Limit discount amount if limit exists.
				$amount = (float) $quantity['discount'];
				if ( '' !== $discount_limit && (float) $amount > (float) $discount_limit ) {
					$amount = (float) $discount_limit;
				}

				$amount = $base_price - $amount;
				$amount = 0 > $amount ? 0 : $amount;

				$range = '' !== $quantity['max'] ? ( (int) $quantity['max'] - (int) $quantity['min'] ) + 1 : ( $discount['quantity_to'] - (int) $quantity['min'] ) + 1;
				$range = $range < $qty ? $range : $qty;
				$price += $range * $amount;
				$qty -= $range;

				// Set discount prices.
				$this->discount_prices[ $discount_id ][ strval( $amount ) ] = ! empty( $this->discount_prices[ $discount_id ][ strval( $amount ) ] ) ?
					$this->discount_prices[ $discount_id ][ strval( $amount ) ] + $range :
					$range;

				$range_str = $quantity['min'] . ' - ' . $quantity['max'];
				$this->range_price[ $discount_id ][ $range_str ] = array(
					'from' => $quantity['min'],
					'to' => $quantity['max'],
					'price' => $amount,
				);
			} elseif ( 'fixed_price' === $quantity['discount_type'] ) {
				// Limit discount amount if limit exists.
				$amount = (float) $quantity['discount'];
				if ( '' !== $discount_limit && (float) $base_price - (float) $amount > (float) $discount_limit ) {
					$amount = (float) $base_price - (float) $discount_limit;
				}

				$range = '' !== $quantity['max'] ? ( (int) $quantity['max'] - (int) $quantity['min'] ) + 1 : ( $discount['quantity_to'] - (int) $quantity['min'] ) + 1;
				$range = $range < $qty ? $range : $qty;
				$price += $range * $amount;
				$qty -= $range;

				// Set discount prices.
				$this->discount_prices[ $discount_id ][ strval( $amount ) ] = ! empty( $this->discount_prices[ $discount_id ][ strval( $amount ) ] ) ?
					$this->discount_prices[ $discount_id ][ strval( $amount ) ] + $range :
					$range;

				$range_str = $quantity['min'] . ' - ' . $quantity['max'];
				$this->range_price[ $discount_id ][ $range_str ] = array(
					'from' => $quantity['min'],
					'to' => $quantity['max'],
					'price' => $amount,
				);
			} elseif ( 'percentage_fee' === $quantity['discount_type'] ) {
				$amount = (float) $quantity['discount'] / 100 * $base_price;
				$range = '' !== $quantity['max'] ? ( (int) $quantity['max'] - (int) $quantity['min'] ) + 1 : ( $discount['quantity_to'] - (int) $quantity['min'] ) + 1;
				$range = $range < $qty ? $range : $qty;
				$price += $range * ( $base_price + $amount );
				$qty -= $range;

				// Set discount prices.
				$this->discount_prices[ $discount_id ][ strval( $base_price + $amount ) ] = ! empty( $this->discount_prices[ $discount_id ][ strval( $base_price + $amount ) ] ) ?
					$this->discount_prices[ $discount_id ][ strval( $base_price + $amount ) ] + $range :
					$range;

				$range_str = $quantity['min'] . ' - ' . $quantity['max'];
				$this->range_price[ $discount_id ][ $range_str ] = array(
					'from' => $quantity['min'],
					'to' => $quantity['max'],
					'price' => $base_price + $amount,
				);
			} elseif ( 'price_fee' === $quantity['discount_type'] ) {
				$amount = (float) $quantity['discount'];
				$range = '' !== $quantity['max'] ? ( (int) $quantity['max'] - (int) $quantity['min'] ) + 1 : ( $discount['quantity_to'] - (int) $quantity['min'] ) + 1;
				$range = $range < $qty ? $range : $qty;
				$price += $range * ( $base_price + $amount );
				$qty -= $range;

				// Set discount prices.
				$this->discount_prices[ $discount_id ][ strval( $base_price + $amount ) ] = ! empty( $this->discount_prices[ $discount_id ][ strval( $base_price + $amount ) ] ) ?
					$this->discount_prices[ $discount_id ][ strval( $base_price + $amount ) ] + $range :
					$range;

				$range_str = $quantity['min'] . ' - ' . $quantity['max'];
				$this->range_price[ $discount_id ][ $range_str ] = array(
					'from' => $quantity['min'],
					'to' => $quantity['max'],
					'price' => $base_price + $amount,
				);
			}
		}

		if ( 0 < $qty ) {
			$price += $qty * $in_cart_price;

			// Set discount prices.
			$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] = ! empty( $this->discount_prices[ $discount_id ][ (string) $in_cart_price ] ) ?
				$this->discount_prices[ $discount_id ][ (string) $in_cart_price ] + $qty :
				$qty;

			$range_start = count( $this->range_price ) && count( $this->range_price[ $discount_id ] ) ? end( $this->range_price[ $discount_id ] ) : null;
			$range_start = $range_start && ! empty( $range_start['to'] ) ? (int) $range_start['to'] + 1 : 1;
			$range_str = $range_start . ' - ' . ( $range_start + $qty - 1 );
			$this->range_price[ $discount_id ][ $range_str ] = array(
				'from' => $range_start,
				'to' => $range_start + $qty - 1,
				'price' => $in_cart_price,
			);
			reset( $this->range_price );
		}

		if ( 0 < $base_price * $discount['quantity'] - $price ) {
			return ( $base_price * $discount['quantity'] - $price ) / $discount['quantity'];
		}

		return $base_price;
	}

	protected function get_simple_fees() {
		$pricings = $this->pricing->get_simple_pricings();
		if ( empty( $pricings ) ) {
			return array();
		}

		$fees = array();
		foreach ( $pricings as $pricing_id => $pricing ) {
			if ( ! in_array( $pricing['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
				continue;
			} elseif ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			} elseif ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			}

			$fees[ $pricing_id ] = array(
				'id' => $pricing_id,
				'name' => $pricing['name'],
				'description' => $pricing['description'],
				'type' => 'fee',
				'mode' => $pricing['mode'],
				'apply_mode' => $pricing['apply_mode'],
				'order' => (int) $pricing['order'],
				'discount' => (float) $pricing['discount'],
				'discount_type' => $pricing['discount_type'],
			);
		}

		return $fees;
	}

}

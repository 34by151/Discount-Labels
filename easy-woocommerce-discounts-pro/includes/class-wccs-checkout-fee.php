<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Checkout_Fee {

	protected $fees;

	protected $cart;

	protected $apply_method;

	protected $date_time_validator;

	protected $condition_validator;

	protected $cart_fees_names = array();

	protected $combine_fee_name = null;

	public $rules_filter;

	public function __construct( array $fees, WCCS_Cart $cart = null, $apply_method = null ) {
		$this->fees = $fees;
		$this->cart = null === $cart ? new WCCS_Cart() : $cart;
		$this->apply_method = null === $apply_method ? WCCS()->settings->get_setting( 'checkout_fee_apply_method', 'all' ) : $apply_method;
		$this->date_time_validator = WCCS()->WCCS_Date_Time_Validator;
		$this->condition_validator = WCCS()->WCCS_Condition_Validator;
		$this->rules_filter = new WCCS_Rules_Filter();
	}

	public function get_fees() {
		return $this->fees;
	}

	public function get_valid_fees() {
		if ( empty( $this->fees ) ) {
			return array();
		}

		$valid_fees = array();

		foreach ( $this->fees as $fee ) {
			if ( ! empty( $fee->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $fee ) ) {
				continue;
			}

			if ( ! $this->date_time_validator->is_valid_date_times( $fee->date_time, ( ! empty( $fee->date_times_match_mode ) ? $fee->date_times_match_mode : 'one' ) ) ) {
				continue;
			}

			if ( ! $this->condition_validator->is_valid_conditions( $fee, ( ! empty( $fee->conditions_match_mode ) ? $fee->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			$valid_fees[] = $fee;
		}

		if ( ! empty( $valid_fees ) ) {
			usort( $valid_fees, array( WCCS()->WCCS_Sorting, 'sort_by_ordering_asc' ) );
			$valid_fees = $this->rules_filter->by_apply_mode( $valid_fees );
		}

		return $valid_fees;
	}

	public function get_possible_fees( $cart = null ) {
		if ( null !== $cart && is_a( $cart, 'WC_Cart' ) ) {
			$this->cart->cart = $cart;
		}

		$valids = $this->get_valid_fees();
		if ( empty( $valids ) ) {
			return array();
		}

		$prices_include_tax = wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' );
		$cart_subtotal = $prices_include_tax ? $this->cart->subtotal : $this->cart->subtotal_ex_tax;

		// Applying limit on fee amount.
		$limit = '';
		$limit_type = WCCS()->settings->get_setting( 'checkout_fee_limit_type', 'no_limit' );
		if ( in_array( $limit_type, array( 'total_price_limit', 'total_percentage_limit' ) ) ) {
			$limit = WCCS()->settings->get_setting( 'checkout_fee_limit', '' );
			if ( '' !== $limit ) {
				$limit = 'total_percentage_limit' === $limit_type ? (float) $limit / 100 * $cart_subtotal : (float) $limit;
			}
		}

		$this->cart_fees_names = array_keys( $this->cart->get_fees() );

		$possibles = array();
		foreach ( $valids as $fee ) {
			if ( 0 === $limit ) {
				break;
			} elseif ( ! isset( $fee->fee_amount ) || '' === $fee->fee_amount || 0 == (float) $fee->fee_amount ) {
				continue;
			}

			$fee = clone $fee;

			$fee_amount = (float) $fee->fee_amount;
			if ( 'percentage_fee' === $fee->fee_type ) {
				$fee_amount = $fee_amount / 100 * $cart_subtotal;
			} elseif ( 'percentage_fee_per_item' === $fee->fee_type ) {
				$fee_amount = 0;

				if ( ! empty( $fee->items ) ) {
					$cart_items = $this->cart->filter_cart_items( $fee->items, false, ! empty( $fee->exclude_items ) ? $fee->exclude_items : array() );
				} else {
					$cart_items = empty( $fee->exclude_items ) ? $this->cart->get_cart() : $this->cart->filter_cart_items( array( array( 'item' => 'all_products' ) ), false, $fee->exclude_items );
				}

				if ( ! empty( $cart_items ) ) {
					foreach ( $cart_items as $cart_item ) {
						$item_subtotal = $prices_include_tax ? apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item ) +
							apply_filters( 'wccs_cart_item_line_subtotal_tax', $cart_item['line_subtotal_tax'], $cart_item ) :
							apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item );
						$fee_amount += (float) $fee->fee_amount / 100 * $item_subtotal;
					}
				}
			} elseif ( 'price_fee_per_item' === $fee->fee_type ) {
                // Negative fee value, Fee is a discount.
                if ( 0 > $fee_amount ) {
                    $fee_amount = 0;
                    if ( ! empty( $fee->items ) ) {
                        $cart_items = $this->cart->filter_cart_items( $fee->items, false, ! empty( $fee->exclude_items ) ? $fee->exclude_items : array() );
                    } else {
                        $cart_items = empty( $fee->exclude_items ) ? $this->cart->get_cart() : $this->cart->filter_cart_items( array( array( 'item' => 'all_products' ) ), false, $fee->exclude_items );
                    }

                    if ( empty( $cart_items ) ) {
                        continue;
                    }

                    foreach ( $cart_items as $cart_item ) {
                        $item_price = $prices_include_tax ?
                            apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item ) +
                            apply_filters( 'wccs_cart_item_line_subtotal_tax', $cart_item['line_subtotal_tax'], $cart_item ) :
                            apply_filters( 'wccs_cart_item_line_subtotal', $cart_item['line_subtotal'], $cart_item );

                        $item_fee = min( (float) $fee->fee_amount * $cart_item['quantity'] * -1, $item_price );
                        $fee_amount -= $item_fee;
                    }
                } else {
                    $quantities = 0;
                    if ( ! empty( $fee->items ) ) {
                        $quantities = $this->cart->get_items_quantities(
                            $fee->items,
                            'all_products',
                            false,
                            '',
                            'desc',
                            ! empty( $fee->exclude_items ) ?  $fee->exclude_items : array()
                        );
                        $quantities = isset( $quantities['all_products'] ) ? $quantities['all_products']['count'] : 0;
                    } else {
                        $quantities = empty( $fee->exclude_items ) ?
                            $this->cart->get_cart_quantities_based_on( 'all_products' ) :
                            $this->cart->get_items_quantities(
                                array( array( 'item' => 'all_products' ) ),
                                'all_products',
                                false,
                                '',
                                'desc',
                                $fee->exclude_items
                            );
                        $quantities = isset( $quantities['all_products'] ) ? $quantities['all_products']['count'] : 0;
                    }

                    $fee_amount = $fee_amount * $quantities;
                }
			}

			if ( '' !== $limit ) {
				if ( 0 > $fee_amount ) {
					$fee_amount = ( $fee_amount * -1 ) > $limit ? $limit * -1 : $fee_amount;
					$limit = $limit + $fee_amount > 0 ? $limit + $fee_amount : 0;
				} else {
					$fee_amount = $fee_amount > $limit ? $limit : $fee_amount;
					$limit = $limit - $fee_amount > 0 ? $limit - $fee_amount : 0;
				}
			}

			if ( 0 == $fee_amount ) {
				continue;
			}

			$fee->fee_amount = $fee_amount;
			$fee->unique_name = $this->get_unique_name( $fee->name );

			$possibles[] = $fee;
		}

		if ( ! empty( $possibles ) ) {
			if ( 'first' === $this->apply_method ) {
				return array( $possibles[0] );
			} elseif ( 'max' === $this->apply_method ) {
				$max = $possibles[0];
				for ( $i = 1; $i < count( $possibles ); $i++ ) {
					if ( $possibles[ $i ]->fee_amount > $max->fee_amount ) {
						$max = $possibles[ $i ];
					}
				}
				return array( $max );
			} elseif ( 'min' === $this->apply_method ) {
				$min = $possibles[0];
				for ( $i = 1; $i < count( $possibles ); $i++ ) {
					if ( $possibles[ $i ]->fee_amount < $min->fee_amount ) {
						$min = $possibles[ $i ];
					}
				}
				return array( $min );
			}
		}

		return $possibles;
	}

	public function get_combine_fee_name() {
		if ( null !== $this->combine_fee_name ) {
			return $this->combine_fee_name;
		}

		$fee_label = __( 'Fee', 'easy-woocommerce-discounts' );
		if ( (int) WCCS()->settings->get_setting( 'localization_enabled', 1 ) ) {
			$fee_label = WCCS()->settings->get_setting( 'checkout_fee_label', $fee_label );
		}
		$this->combine_fee_name = apply_filters( 'wccs_checkout_fee_display_combine_name', $fee_label );
		$this->combine_fee_name = $this->get_unique_name( $this->combine_fee_name );

		return $this->combine_fee_name;
	}

	protected function get_unique_name( $name ) {
		$sanitize_name = sanitize_title( $name );
		if ( ! in_array( $sanitize_name, $this->cart_fees_names ) ) {
			$this->cart_fees_names[] = $sanitize_name;
			return $name;
		}

		$i = 2;
		while ( true ) {
			$name = $name . apply_filters( 'wccs_checkout_fee_unique_name_suffix', ' ' . $i++, $name );
			$sanitize_name = sanitize_title( $name );
			if ( ! in_array( $sanitize_name, $this->cart_fees_names ) ) {
				$this->cart_fees_names[] = $sanitize_name;
				return $name;
			}
		}
	}

}

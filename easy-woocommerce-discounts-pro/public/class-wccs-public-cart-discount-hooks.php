<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Public_Cart_Discount_Hooks {

	const SESSION_KEY = 'wccs_applied_coupons';

	public $applying_coupon = false;

	/**
	 * An array of possible cart discounts.
	 *
	 * @var array
	 */
	protected $discounts;

	protected $display_multiple;

	const COUPON_ID = 9999999;

	public function __construct( WCCS_Loader $loader ) {
		$this->display_multiple = WCCS()->settings->get_setting( 'cart_discount_display_multiple_discounts', 'separate' );

		$loader->add_action( 'woocommerce_before_calculate_totals', $this, 'remove_coupons', 1 );
		$loader->add_action( 'woocommerce_after_calculate_totals', $this, 'add_discount', 20, 1 );
		$loader->add_filter( 'woocommerce_get_shop_coupon_data', $this, 'get_coupon_data', 10, 2 );
		$loader->add_filter( 'woocommerce_cart_totals_coupon_html', $this, 'cart_totals_coupon_html', 10, 2 );
		$loader->add_filter( 'woocommerce_cart_totals_coupon_label', $this, 'cart_totals_coupon_label', 10, 2 );
		$loader->add_action( 'woocommerce_check_cart_items', $this, 'maybe_remove_coupon', 1 );
		$loader->add_filter( 'woocommerce_coupon_message', $this, 'maybe_remove_coupon_message', 99, 3 );
		$loader->add_filter( 'woocommerce_apply_individual_use_coupon', $this, 'apply_individual_use_coupon', 10, 3 );
		$loader->add_filter( 'woocommerce_coupon_is_valid', $this, 'coupon_is_valid', 99, 2 );
	}

	public function add_discount( $cart ) {
		if (
			$this->applying_coupon ||
			! WCCS()->cart_discount ||
			! $this->should_apply_cart_discounts()
		) {
			return;
		}

		$this->discounts = array();

		$with_individuals = WCCS()->settings->get_setting( 'cart_discount_with_individual_coupons', 1 );
		$with_regulars = WCCS()->settings->get_setting( 'cart_discount_with_regular_coupons', 1 );
		$add_discounts = true;
		if ( ( 0 == $with_individuals || 0 == $with_regulars ) && ! empty( WC()->cart->applied_coupons ) ) {
			foreach ( WC()->cart->applied_coupons as $code ) {
				// Checking for do not apply with regular coupons.
				if ( 0 == $with_regulars ) {
					$add_discounts = false;
					break;
				}

				// Checking for do not apply with individual use coupons.
				if ( 0 == $with_individuals ) {
					$coupon = new WC_Coupon( $code );
					if ( $coupon->get_individual_use() ) {
						$add_discounts = false;
						break;
					}
				}
			}
		}

		if ( ! $add_discounts ) {
			if ( WC()->session ) {
				WC()->session->set( static::SESSION_KEY, [] );
			}
			return;
		}

		$this->discounts = WCCS()->cart_discount->get_possible_discounts();
		$this->discounts = apply_filters( 'wccs_applicable_cart_discounts', $this->discounts, $this );
		if ( empty( $this->discounts ) ) {
			if ( WC()->session ) {
				WC()->session->set( static::SESSION_KEY, [] );
			}
			return;
		}

		if ( WC()->session ) {
			$applied_coupons = array_map( function ( $discount ) {
				return [
					'id' => $discount->id,
					'name' => $discount->name,
					'code' => $discount->code,
					'description' => ! empty( $discount->description ) ? $discount->description : '',
					'apply_mode' => isset( $discount->apply_mode ) ? $discount->apply_mode : '',
					'manual' => isset( $discount->manual ) ? $discount->manual : 0,
					'discount_type' => isset( $discount->discount_type ) ? $discount->discount_type : '',
					'discount_amount' => isset( $discount->discount_amount ) ? $discount->discount_amount : 0,
					'amount' => isset( $discount->amount ) ? $discount->amount : 0,
					'url_coupon' => isset( $discount->url_coupon ) ? $discount->url_coupon : 0,
				];
			}, $this->discounts );
			WC()->session->set( static::SESSION_KEY, $applied_coupons );
		}

		if ( 'combine' === $this->display_multiple ) {
			$coupon_code = WCCS()->cart_discount->get_combine_coupon_code();
			$this->apply_coupon( $coupon_code, $cart );
		} else {
			foreach ( $this->discounts as $discount ) {
				if ( 0 < $discount->discount_amount ) {
					$this->apply_coupon( $discount->code, $cart );
				}
			}
		}
	}

	public function get_coupon_data( $false, $data ) {
		if (
			! WCCS()->cart_discount ||
			! WCCS()->cart_discount->is_cart_discount_coupon( $data )
		) {
			return $false;
		}

		$manual = $this->get_manual_coupon_data( $data );
		if ( $manual ) {
			return $manual;
		}

		if ( empty( $this->discounts ) ) {
			return $false;
		}

		if ( 'combine' === $this->display_multiple ) {
			$coupon_code = WCCS()->cart_discount->get_combine_coupon_code();
			if ( $data === $coupon_code ) {
				$amount = 0;
				foreach ( $this->discounts as $discount ) {
					if ( in_array( $discount->discount_type, array( 'price', 'fixed_price', 'price_discount_per_item' ) ) ) {
						$amount += WCCS_Helpers::maybe_exchange_price( $discount->discount_amount, 'coupon' );
					} else {
						$amount += $discount->discount_amount;
					}
				}

				return apply_filters(
					'wccs_cart_discount_get_coupon_data',
					array(
						'id' => self::COUPON_ID,
						'code' => $coupon_code,
						'amount' => $amount,
					)
				);
			}
		} elseif ( isset( $this->discounts[ $data ] ) ) {
			$discount = array(
				'id' => self::COUPON_ID,
				'code' => $this->discounts[ $data ]->code,
			);

			$limit_enabled = WCCS_Helpers::is_cart_discount_limit_enabled();

			if ( 'percentage' === $this->discounts[ $data ]->discount_type && ! $limit_enabled ) {
				$discount['discount_type'] = 'percent';
				$discount['amount'] = $this->discounts[ $data ]->amount;
			} elseif ( 'percentage_discount_per_item' === $this->discounts[ $data ]->discount_type && ! $limit_enabled ) {
				$discount['discount_type'] = 'percent';
				$discount['amount'] = $this->discounts[ $data ]->amount;
				$discount['product_ids'] = ! empty( $this->discounts[ $data ]->product_ids ) ? $this->discounts[ $data ]->product_ids : array();
			} elseif ( 'recurring_percent' === $this->discounts[ $data ]->discount_type ) {
				$discount['discount_type'] = 'recurring_percent';
				$discount['amount'] = $this->discounts[ $data ]->amount;
				$discount['product_ids'] = ! empty( $this->discounts[ $data ]->product_ids ) ? $this->discounts[ $data ]->product_ids : array();
			} elseif ( 'recurring_fee' === $this->discounts[ $data ]->discount_type && ! $limit_enabled ) {
				$discount['discount_type'] = 'recurring_fee';
				$discount['amount'] = WCCS_Helpers::maybe_exchange_price( isset( $this->discounts[ $data ]->amount ) ? $this->discounts[ $data ]->amount : $this->discounts[ $data ]->discount_amount, 'coupon' );
				$discount['product_ids'] = ! empty( $this->discounts[ $data ]->product_ids ) ? $this->discounts[ $data ]->product_ids : array();
			} elseif ( 'price_discount_per_item' === $this->discounts[ $data ]->discount_type && ! $limit_enabled ) {
				$discount['discount_type'] = 'fixed_product';
				$discount['amount'] = WCCS_Helpers::maybe_exchange_price( $this->discounts[ $data ]->amount, 'coupon' );
				$discount['product_ids'] = ! empty( $this->discounts[ $data ]->product_ids ) ? $this->discounts[ $data ]->product_ids : array();
			} elseif (
				'price' === $this->discounts[ $data ]->discount_type ||
				'fixed_price' === $this->discounts[ $data ]->discount_type ||
				$limit_enabled
			) {
				$discount['amount'] = WCCS_Helpers::maybe_exchange_price( $this->discounts[ $data ]->discount_amount, 'coupon' );
				if ( 'recurring_fee' === $this->discounts[ $data ]->discount_type ) {
					$discount['discount_type'] = $this->discounts[ $data ]->discount_type;
				}
			}

			return apply_filters( 'wccs_cart_discount_get_coupon_data', $discount );
		}

		return $false;
	}

	protected function get_manual_coupon_data( $data ) {
		$manual = WCCS()->cart_discount->get_manual_coupon( $data );
		if ( ! $manual ) {
			return false;
		}

		$discount = array(
			'id' => self::COUPON_ID,
			'code' => $manual->code,
		);

		$limit_enabled = WCCS_Helpers::is_cart_discount_limit_enabled();

		if ( 'percentage' === $manual->discount_type && ! $limit_enabled ) {
			$discount['discount_type'] = 'percent';
			$discount['amount'] = $manual->amount;
		} elseif ( 'percentage_discount_per_item' === $manual->discount_type && ! $limit_enabled ) {
			$discount['discount_type'] = 'percent';
			$discount['amount'] = $manual->amount;
			$discount['product_ids'] = ! empty( $manual->product_ids ) ? $manual->product_ids : array();
		} elseif ( 'recurring_percent' === $manual->discount_type ) {
			$discount['discount_type'] = 'recurring_percent';
			$discount['amount'] = $manual->amount;
			$discount['product_ids'] = ! empty( $manual->product_ids ) ? $manual->product_ids : array();
		} elseif ( 'recurring_fee' === $manual->discount_type && ! $limit_enabled ) {
			$discount['discount_type'] = 'recurring_fee';
			$discount['amount'] = WCCS_Helpers::maybe_exchange_price( isset( $manual->amount ) ? $manual->amount : $manual->discount_amount, 'coupon' );
			$discount['product_ids'] = ! empty( $manual->product_ids ) ? $manual->product_ids : array();
		} elseif ( 'price_discount_per_item' === $manual->discount_type && ! $limit_enabled ) {
			$discount['discount_type'] = 'fixed_product';
			$discount['amount'] = WCCS_Helpers::maybe_exchange_price( $manual->amount, 'coupon' );
			$discount['product_ids'] = ! empty( $manual->product_ids ) ? $manual->product_ids : array();
		} elseif (
			'price' === $manual->discount_type ||
			'fixed_price' === $manual->discount_type ||
			$limit_enabled
		) {
			$discount['amount'] = WCCS_Helpers::maybe_exchange_price( $manual->discount_amount, 'coupon' );
			if ( 'recurring_fee' === $manual->discount_type ) {
				$discount['discount_type'] = $manual->discount_type;
			}
		}

		return apply_filters( 'wccs_get_manual_coupon_data', $discount );
	}

	public function cart_totals_coupon_html( $coupon_html, $coupon ) {
		if (
			! WCCS()->cart_discount ||
			! WCCS()->cart_discount->is_cart_discount_coupon( $coupon->get_code() )
		) {
			return $this->maybe_remove_coupon_zero_value( $coupon_html, $coupon );
		}

		if ( WCCS()->cart_discount->is_manual_coupon( $coupon->get_code() ) ) {
			return $coupon_html;
		}

		if ( $amount = WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax ) ) {
			return apply_filters( 'wccs_cart_totals_coupon_html_prefix', '-' ) . wc_price( $amount );
		}

		return $coupon_html;
	}

	public function cart_totals_coupon_label( $label, $coupon ) {
		if ( ! WCCS()->cart_discount ) {
			return $label;
		}

		$code = WCCS_Helpers::wc_version_check() ? $coupon->get_code() : $coupon->code;
		if ( ! WCCS()->cart_discount->is_cart_discount_coupon( $code ) ) {
			return $label;
		}

		if ( WCCS()->cart_discount->is_manual_coupon( $coupon->get_code() ) ) {
			return $label;
		}

		if ( 'combine' === $this->display_multiple ) {
			$label = (int) WCCS()->settings->get_setting( 'localization_enabled', 1 ) ? WCCS()->settings->get_setting( 'coupon_label', '' ) : '';
			if ( ! empty( $label ) ) {
				$label = __( $label, 'easy-woocommerce-discounts' );
			}
			$label = apply_filters( 'wccs_cart_totals_coupon_label_combine', $label );
			return ! empty( $label ) ? esc_html( $label ) : __( 'Discount', 'easy-woocommerce-discounts' );
		} elseif ( isset( $this->discounts[ $code ] ) && ! empty( $this->discounts[ $code ]->name ) ) {
			return esc_html( __( $this->discounts[ $code ]->name, 'easy-woocommerce-discounts' ) );
		}

		return $label;
	}

	public function remove_coupons() {
		if (
			$this->applying_coupon ||
			empty( WC()->cart->applied_coupons ) ||
			! WCCS()->cart_discount
		) {
			return;
		}

		// Get the applied coupons array
		$applied_coupons = WC()->cart->get_applied_coupons();
		$removed = false;

		foreach ( $applied_coupons as $key => $coupon_code ) {
			// Check if the coupon is a cart discount coupon and not a manual coupon
			if (
				WCCS()->cart_discount->is_cart_discount_coupon( $coupon_code ) &&
				! WCCS()->cart_discount->is_manual_coupon( $coupon_code )
			) {
				unset( $applied_coupons[ $key ] );
				$removed = true;
			}
		}

		if ( $removed ) {
			WC()->cart->applied_coupons = array_values( $applied_coupons );
		}
	}

	public function maybe_remove_coupon() {
		if (
			$this->applying_coupon ||
			empty( WC()->cart->applied_coupons ) ||
			! WCCS()->cart_discount
		) {
			return;
		}

		foreach ( WC()->cart->applied_coupons as $coupon_code ) {
			if (
				! WCCS()->cart_discount->is_cart_discount_coupon( $coupon_code ) ||
				WCCS()->cart_discount->is_manual_coupon( $coupon_code )
			) {
				continue;
			}

			$coupon = new WC_Coupon( $coupon_code );
			$amount = WCCS_Helpers::wc_version_check() ? $coupon->get_amount() : $coupon->amount;
			if ( $amount <= 0 ) {
				WC()->cart->remove_coupon( $coupon_code );
			}
		}
	}

	/**
	 * Remove coupon message when it is automatic coupon applied with WooCommerce Conditions.
	 *
	 * @param  string    $msg
	 * @param  integer   $msg_code
	 * @param  WC_Coupon $coupon
	 *
	 * @return string
	 */
	public function maybe_remove_coupon_message( $msg, $msg_code, $coupon ) {
		if ( ! WCCS()->cart_discount ) {
			return $msg;
		}

		$code = WCCS_Helpers::wc_version_check() ? $coupon->get_code() : $coupon->code;
		if (
			WCCS()->cart_discount->is_cart_discount_coupon( $code ) &&
			! WCCS()->cart_discount->is_manual_coupon( $code )
		) {
			return '';
		}

		return $msg;
	}

	public function apply_individual_use_coupon( $keep_coupons, $coupon, $applied_coupons ) {
		if ( ! WCCS()->cart_discount ) {
			return $keep_coupons;
		}

		$with_individuals = WCCS()->settings->get_setting( 'cart_discount_with_individual_coupons', 1 );
		if ( 0 == $with_individuals ) {
			return $keep_coupons;
		}

		foreach ( $applied_coupons as $coupon_code ) {
			if ( WCCS()->cart_discount->is_cart_discount_coupon( $coupon_code ) ) {
				$keep_coupons[] = $coupon_code;
			}
		}

		return $keep_coupons;
	}

	public function coupon_is_valid( $valid, $coupon ) {
		$manual = WCCS()->cart_discount->get_manual_coupon( $coupon->get_code() );
		if ( ! $manual ) {
			return $valid;
		}

		$possibles = WCCS()->cart_discount->get_possible_discounts();
		if ( empty( $possibles ) || ! isset( $possibles[ $coupon->get_code()] ) ) {
			return false;
		}

		return $valid;
	}

	protected function apply_coupon( $coupon_code, $cart ) {
		try {
			if ( empty( $coupon_code ) ) {
				return false;
			}

			$coupon = new WC_Coupon( $coupon_code );
			if ( $coupon->is_valid() && ! $cart->has_discount( $coupon_code ) ) {
				$cart->applied_coupons[] = $coupon_code;

				$this->applying_coupon = true;
				do_action( 'woocommerce_applied_coupon', $coupon_code );
				$this->applying_coupon = false;
			}

			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	protected function should_apply_cart_discounts() {
		return apply_filters( 'wccs_should_apply_cart_discounts', true );
	}

	protected function maybe_remove_coupon_zero_value( $coupon_html, $coupon ) {
		if ( ! (int) WCCS()->settings->get_setting( 'remove_coupons_zero_value', 1 ) ) {
			return $coupon_html;
		}

		if ( $coupon->get_free_shipping() ) {
			return $coupon_html;
		}

		$amount = WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax );
		if ( ! empty( $amount ) ) {
			return $coupon_html;
		}

		return str_replace( '-' . wc_price( $amount ), '', $coupon_html );
	}

}

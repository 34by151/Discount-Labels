<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Condition_Validator {

	protected $customer;

	protected $products;

	protected $cart;

	protected $date_time;

	public function __construct(
		$customer = null,
		WCCS_Products $products = null,
		WCCS_Cart $cart = null,
		WCCS_Date_Time $date_time = null
	) {
		$wccs            = WCCS();
		$this->customer  = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
		$this->products  = ! is_null( $products ) ? $products : $wccs->products;
		$this->cart      = ! is_null( $cart ) ? $cart : $wccs->cart;
		$this->date_time = ! is_null( $date_time ) ? $date_time : new WCCS_Date_Time();
	}

	protected function init_cart() {
		if ( isset( $this->cart ) ) {
			return;
		}

		if ( WCCS()->cart ) {
			$this->cart = WCCS()->cart;
		}
	}

	public function is_valid_conditions( $rule, $match_mode = 'all' ) {
		// Backward compatibility to get the conditions.
		$conditions = $rule;
		if ( is_object( $rule ) && isset( $rule->conditions ) ) {
			$conditions = $rule->conditions;
		} else {
			$rule = null;
		}
		
		if ( empty( $conditions ) ) {
			return true;
		}

		$this->init_cart();

		// New structure conditions that supports OR conditions too.
		if ( is_array( $conditions[0] ) && ! isset( $conditions[0]['condition'] ) ) {
			$empty = true;
			foreach ( $conditions as $group ) {
				if ( empty( $group ) ) {
					continue;
				}

				$empty = false;
				$valid = true;
				foreach ( $group as $condition ) {
					if ( ! $this->is_valid( $condition, $rule ) ) {
						$valid = false;
						break;
					}
				}
				if ( $valid ) {
					return true;
				}
			}
			return $empty;
		}

		foreach ( $conditions as $condition ) {
			if ( 'one' === $match_mode && $this->is_valid( $condition, $rule ) ) {
				return true;
			} elseif ( 'all' === $match_mode && ! $this->is_valid( $condition, $rule ) ) {
				return false;
			}
		}

		return 'all' === $match_mode;
	}

	public function is_valid( array $condition, $rule = null ) {
		if ( empty( $condition ) ) {
			return false;
		}

		$is_valid = false;
		if ( is_callable( array( $this, $condition['condition'] ) ) ) {
            $is_valid = call_user_func_array( array( $this, $condition['condition'] ), array( $condition, $rule ) );
		} elseif ( false !== strpos( $condition['condition'], 'taxonomy_' ) ) {
			$method = strpos( $condition['condition'], '__' );
			if ( false !== $method ) {
				$method = substr( $condition['condition'], 0, $method );
			}

			if ( ! empty( $method ) && is_callable( array( $this, $method ) ) ) {
				$is_valid = $this->{$method}( $condition );
			}
		}

		return apply_filters( 'wccs_condition_validator_is_valid_' . $condition['condition'], $is_valid, $condition );
	}

	public function customers( array $condition ) {
		if ( empty( $condition['customers'] ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( 'selected' === $condition['select_type'] ) {
			return in_array( $this->customer->ID, $condition['customers'] );
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			return ! in_array( $this->customer->ID, $condition['customers'] );
		}

		return false;
	}

	public function is_logged_in( array $condition ) {
		if ( isset( $condition['yes_no'] ) && 'no' === $condition['yes_no'] ) {
			return ! is_user_logged_in();
		}
		return is_user_logged_in();
	}

	public function money_spent( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$money_spent = $this->customer->get_total_spent( $date_time_args );

		return WCCS()->WCCS_Comparison->math_compare( $money_spent, $value, $condition['math_operation_type'] );
	}

	public function number_of_orders( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// For guest users get number of orders only when the billing email is filled.
		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$number_of_orders = $this->customer->get_number_of_orders( $date_time_args );

		return WCCS()->WCCS_Comparison->math_compare( $number_of_orders, $value, $condition['math_operation_type'] );
	}

	public function last_order_amount( array $condition ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$last_order = wc_get_customer_last_order( $this->customer->ID );
		if ( ! $last_order ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( (float) $last_order->get_total(), $value, $condition['math_operation_type'] );
	}

	public function roles( array $condition ) {
		if ( empty( $condition['roles'] ) ) {
			return true;
		}

		if ( 'selected' === $condition['select_type'] ) {
			return $this->customer->has_role( $condition['roles'] );
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			return ! $this->customer->has_role( $condition['roles'] );
		}

		return false;
	}

	public function products_in_cart( array $condition ) {
		if ( empty( $condition['products'] ) ) {
			return true;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return $this->cart->products_exists_in_cart(
			$condition['products'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_variations_in_cart( array $condition ) {
		if ( empty( $condition['variations'] ) ) {
			return true;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return $this->cart->products_exists_in_cart(
			$condition['variations'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function featured_products_in_cart( array $condition ) {
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$featured_products = wc_get_featured_product_ids();
		if ( empty( $featured_products ) ) {
			return true;
		}

		return $this->cart->products_exists_in_cart(
			$featured_products,
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function onsale_products_in_cart( array $condition ) {
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$onsale_products = wc_get_product_ids_on_sale();
		if ( empty( $onsale_products ) ) {
			return true;
		}

		return $this->cart->products_exists_in_cart(
			$onsale_products,
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_categories_in_cart( array $condition ) {
		if ( empty( $condition['categories'] ) ) {
			return true;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return $this->cart->categories_exists_in_cart(
			$condition['categories'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_attributes_in_cart( array $condition ) {
		if ( empty( $condition['attributes'] ) ) {
			return true;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return $this->cart->attributes_terms_exists_in_cart(
			$condition['attributes'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_tags_in_cart( array $condition ) {
		if ( empty( $condition['tags'] ) ) {
			return true;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return $this->cart->tags_exists_in_cart(
			$condition['tags'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function shipping_classes_in_cart( array $condition ) {
		if ( empty( $condition['shipping_classes'] ) ) {
			return true;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return $this->cart->shipping_classes_exists_in_cart(
			$condition['shipping_classes'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function number_of_cart_items( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->get_cart_contents_count(), $value, $condition['math_operation_type'] );
	}

	public function subtotal_excluding_tax( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->subtotal_ex_tax, $value, $condition['math_operation_type'] );
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
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_excluding_tax_excluding_coupons( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		$subtotal = $this->cart->subtotal_ex_tax - $this->cart->discount_cart - $this->cart->discount_cart_tax;

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_including_tax_excluding_coupons( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		$subtotal = $this->cart->subtotal - $this->cart->discount_cart - $this->cart->discount_cart_tax;

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_cart_items( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->quantities_compare(
			$this->cart->get_cart_item_quantities(),
			$value,
			$condition['math_operation_type']
		);
	}

	public function max_width_of_cart_items( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->get_items_max_width(), $value, $condition['math_operation_type'] );
	}

	public function max_height_of_cart_items( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->get_items_max_height(), $value, $condition['math_operation_type'] );
	}

	public function max_length_of_cart_items( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->get_items_max_length(), $value, $condition['math_operation_type'] );
	}

	public function min_stock_of_cart_items( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$min = $this->cart->get_items_min_stock_quantity();

		return false === $min ? false : WCCS()->WCCS_Comparison->math_compare( $min, $value, $condition['math_operation_type'] );
	}

	public function bought_products( array $condition ) {
		if ( empty( $condition['products'] ) ) {
			return true;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// All time bought products.
		if ( empty( $date_time_args ) ) {
			return $this->customer->has_bought_products( $condition['products'], $condition['union_type'] );
		}

		$condition_products = array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $condition['products'] );
		$orders             = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $condition_products, array(), $condition['union_type'] );
		}

		$products = array();
		foreach ( $orders as $order ) {
			$products = array_merge( $products, WCCS()->WCCS_Order_Helpers->get_order_products( $order ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $condition_products, array_unique( $products ), $condition['union_type'] );
	}

	public function bought_product_variations( array $condition ) {
		if ( empty( $condition['variations'] ) ) {
			return true;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// All time bought variations.
		if ( empty( $date_time_args ) ) {
			return $this->customer->has_bought_products( $condition['variations'], $condition['union_type'] );
		}

		$condition_variations = array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $condition['variations'] );
		$orders               = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $condition_variations, array(), $condition['union_type'] );
		}

		$variations = array();
		foreach ( $orders as $order ) {
			$variations = array_merge( $variations, WCCS()->WCCS_Order_Helpers->get_order_variations( $order ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $condition_variations, array_unique( $variations ), $condition['union_type'] );
	}

	public function bought_product_categories( array $condition ) {
		if ( empty( $condition['categories'] ) ) {
			return true;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// All time bought categories.
		if ( empty( $date_time_args ) ) {
			return $this->customer->has_bought_categories( $condition['categories'], $condition['union_type'] );
		}

		$condition_categories = array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $condition['categories'] );
		$orders               = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $condition_categories, array(), $condition['union_type'] );
		}

		$categories = array();
		foreach ( $orders as $order ) {
			$categories = array_merge( $categories, WCCS()->WCCS_Order_Helpers->get_order_categories( $order ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $condition_categories, array_unique( $categories ), $condition['union_type'] );
	}

	public function bought_product_attributes( array $condition ) {
		if ( empty( $condition['attributes'] ) ) {
			return true;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$condition_attributes = WCCS_Helpers::maybe_get_exact_attributes( $condition['attributes'] );
		$orders               = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $condition_attributes, array(), $condition['union_type'] );
		}

		$attributes = array();
		foreach ( $orders as $order ) {
			$attributes = array_merge( $attributes, WCCS()->WCCS_Order_Helpers->get_order_attributes_terms( $order ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $condition_attributes, array_unique( $attributes ), $condition['union_type'] );
	}

	public function bought_product_tags( array $condition ) {
		if ( empty( $condition['tags'] ) ) {
			return true;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// All time bought tags.
		if ( empty( $date_time_args ) ) {
			return $this->customer->has_bought_product_tags( $condition['tags'], $condition['union_type'] );
		}

		$condition_tags = array_map( 'WCCS_Helpers::maybe_get_exact_tag_id', $condition['tags'] );
		$orders         = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $condition_tags, array(), $condition['union_type'] );
		}

		$tags = array();
		foreach ( $orders as $order ) {
			$tags = array_merge( $tags, WCCS()->WCCS_Order_Helpers->get_order_tags( $order ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $condition_tags, array_unique( $tags ), $condition['union_type'] );
	}

	public function bought_featured_products( array $condition ) {
		$featured_products = wc_get_featured_product_ids();
		if ( empty( $featured_products ) ) {
			return true;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// All time bought featured products.
		if ( empty( $date_time_args ) ) {
			return $this->customer->has_bought_products( $featured_products, $condition['union_type'] );
		}

		$featured_products = array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $featured_products );
		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $featured_products, array(), $condition['union_type'] );
		}

		$products = array();
		foreach ( $orders as $order ) {
			$products = array_merge( $products, WCCS()->WCCS_Order_Helpers->get_order_products( $order ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $featured_products, array_unique( $products ), $condition['union_type'] );
	}

	public function bought_onsale_products( array $condition ) {
		$onsale_products = wc_get_product_ids_on_sale();
		if ( empty( $onsale_products ) ) {
			return true;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// All time bought onsale products.
		if ( empty( $date_time_args ) ) {
			return $this->customer->has_bought_products( $onsale_products, $condition['union_type'] );
		}

		$onsale_products = array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $onsale_products );
		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $onsale_products, array(), $condition['union_type'] );
		}

		$products = array();
		foreach ( $orders as $order ) {
			$products = array_merge( $products, WCCS()->WCCS_Order_Helpers->get_order_products( $order ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $onsale_products, array_unique( $products ), $condition['union_type'] );
	}

	public function user_capability( array $condition ) {
		if ( empty( $condition['capabilities'] ) ) {
			return true;
		}

		if ( 'selected' === $condition['select_type'] ) {
			foreach ( $condition['capabilities'] as $capability ) {
				if ( ! $this->customer->has_cap( $capability ) ) {
					return false;
				}
			}
			return true;
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			foreach ( $condition['capabilities'] as $capability ) {
				if ( $this->customer->has_cap( $capability ) ) {
					return false;
				}
			}
			return true;
		}

		return false;
	}

	public function user_meta( array $condition ) {
		if ( empty( $condition['meta_field_key'] ) || empty( $condition['meta_field_condition'] ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_meta = get_user_meta( $this->customer->ID, $condition['meta_field_key'], false );
		if ( ! empty( $user_meta ) && 1 === count( $user_meta ) ) {
			$user_meta = $user_meta[0];
		}

		return WCCS()->WCCS_Comparison->meta_compare( $user_meta, $condition['meta_field_condition'], $condition['meta_field_value'] );
	}

	public function email( array $condition ) {
		if ( empty( $condition['email'] ) ) {
			return true;
		}

		$restrictions = array_unique(
			array_filter(
				array_map(
					'strtolower',
					array_map(
						'trim',
						explode( ',', trim( $condition['email'] ) )
					)
				)
			)
		);
		if ( empty( $restrictions ) ) {
			return true;
		}

		$check_emails = array_unique(
			array_filter(
				array_map(
					'strtolower',
					array_map(
						'sanitize_email',
						array(
							WCCS_Helpers::get_billing_email(),
							( is_user_logged_in() ? $this->customer->user_email : '' ),
						)
					)
				)
			)
		);
		if ( empty( $check_emails ) ) {
			return false;
		}

		if ( WCCS_Email_Helpers::is_emails_allowed( $check_emails, $restrictions ) ) {
			return true;
		}

		return false;
	}

	public function average_money_spent_per_order( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$money_spent = $this->customer->get_total_spent( $date_time_args );
		$num_orders  = $this->customer->get_number_of_orders( $date_time_args );

		if ( 0 >= $num_orders ) {
			$average = 0;
		} else {
			$average = (float) wc_format_decimal( $money_spent / $num_orders, 2 );
		}

		return WCCS()->WCCS_Comparison->math_compare( $average, $value, $condition['math_operation_type'] );
	}

	public function last_order_date( array $condition ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$last_order = wc_get_customer_last_order( $this->customer->ID );
		if ( ! $last_order ) {
			return false;
		}

		$date_created = WCCS()->WCCS_Order_Helpers->get_order_date_created( $last_order );
		if ( ! $date_created ) {
			return false;
		}

		switch ( $condition['time_type'] ) {
			case 'date' :
			case 'date_time' :
				if ( ! empty( $date_time_args['date_before'] ) && ! empty( $date_time_args['date_after'] ) ) {
					return strtotime( $date_created ) >= strtotime( $date_time_args['date_after'] ) && strtotime( $date_created ) <= strtotime( $date_time_args['date_before'] );
				} elseif ( ! empty( $date_time_args['date_before'] ) ) {
					return strtotime( $date_created ) < strtotime( $date_time_args['date_before'] );
				} elseif ( ! empty( $date_time_args['date_after'] ) ) {
					return strtotime( $date_created ) > strtotime( $date_time_args['date_after'] );
				}
			break;

			case 'previous_days':
			case 'previous_weeks':
			case 'previous_months':
			case 'previous_years':
				if ( ! empty( $date_time_args['date_before'] ) && ! empty( $date_time_args['date_after'] ) ) {
					return strtotime( $date_created ) >= strtotime( $date_time_args['date_after'] ) && strtotime( $date_created ) <= strtotime( $date_time_args['date_before'] );
				}
			break;

			case 'current' :
			case 'day' :
			case 'week' :
			case 'month' :
			case 'year' :
				if ( ! empty( $date_time_args['date_after'] ) && ! empty( $condition['before_after'] ) ) {
					if ( 'before' === $condition['before_after'] ) {
						return strtotime( $date_created ) < strtotime( $date_time_args['date_after'] );
					} elseif ( 'after' === $condition['before_after'] ) {
						return strtotime( $date_created ) > strtotime( $date_time_args['date_after'] );
					}
				}
			break;
		}

		return false;
	}

	public function number_of_products_reviews( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->customer->get_number_of_products_reviews( $date_time_args ), $value, $condition['math_operation_type'] );
	}

	public function cart_total_weight( array $condition ) {
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->get_cart_contents_weight(), $value, $condition['math_operation_type'] );
	}

	public function coupons_applied( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$ids                  = $this->cart->get_applied_coupons_ids();
		$condition['coupons'] = empty( $condition['coupons'] ) ? array() : $condition['coupons'];

		if ( ! empty( $condition['coupon_condition'] ) && 'at_least_one_of_selected' === $condition['coupon_condition'] ) {
			return count( array_intersect( $condition['coupons'], $ids ) );
		} elseif ( ! empty( $condition['coupon_condition'] ) && 'all_of_selected' === $condition['coupon_condition'] ) {
			return ! count( array_diff( $condition['coupons'], $ids ) );
		} elseif ( ! empty( $condition['coupon_condition'] ) && 'only_selected' === $condition['coupon_condition'] ) {
			return ! count( array_diff( $condition['coupons'], $ids ) ) && ! count( array_diff( $ids, $condition['coupons'] ) );
		} elseif ( ! empty( $condition['coupon_condition'] ) && 'none_of_selected' === $condition['coupon_condition'] ) {
			return ! count( array_intersect( $condition['coupons'], $ids ) );
		} elseif ( ! empty( $condition['coupon_condition'] ) && 'none_at_all' === $condition['coupon_condition'] ) {
			return empty( $ids );
		} elseif ( ! empty( $condition['coupon_condition'] ) && 'at_least_one_of_any' === $condition['coupon_condition'] ) {
			return ! empty( $ids );
		} elseif ( empty( $condition['coupons'] ) ) {
			return true;
		}

		return false;
	}

	public function quantity_of_products( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['products'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$quantities = $this->cart->get_cart_quantities_based_on( 'single_product' );
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['products'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ]['count'] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_variations( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['variations'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$quantities = $this->cart->get_cart_quantities_based_on( 'single_product_variation' );
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['variations'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ]['count'] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_categories( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['categories'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$quantities = $this->cart->get_cart_quantities_based_on( 'category', null, '', 'desc', true );
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['categories'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_category_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ]['count'] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_attributes( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['attributes'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$quantities = $this->cart->get_cart_quantities_based_on( 'attribute' );
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['attributes'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_attribute_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ]['count'] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_tags( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['tags'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$quantities = $this->cart->get_cart_quantities_based_on( 'tag' );
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['tags'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_tag_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ]['count'] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_bought_products( array $condition ) {
		if ( empty( $condition['products'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantities = array();
		foreach ( $orders as $order ) {
			$order_quantities = WCCS()->WCCS_Order_Helpers->get_order_items_quantities_based_on( $order, 'single_product' );
			if ( ! empty( $order_quantities ) ) {
				foreach ( $order_quantities as $key => $qty ) {
					$quantities[ $key ] = isset( $quantities[ $key ] ) ? $quantities[ $key ] + $qty : $qty;
				}
			}
		}
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['products'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_bought_variations( array $condition ) {
		if ( empty( $condition['variations'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantities = array();
		foreach ( $orders as $order ) {
			$order_quantities = WCCS()->WCCS_Order_Helpers->get_order_items_quantities_based_on( $order, 'single_product_variation' );
			if ( ! empty( $order_quantities ) ) {
				foreach ( $order_quantities as $key => $qty ) {
					$quantities[ $key ] = isset( $quantities[ $key ] ) ? $quantities[ $key ] + $qty : $qty;
				}
			}
		}
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['variations'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_bought_categories( array $condition ) {
		if ( empty( $condition['categories'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantities = array();
		foreach ( $orders as $order ) {
			$order_quantities = WCCS()->WCCS_Order_Helpers->get_order_items_quantities_based_on( $order, 'category' );
			if ( ! empty( $order_quantities ) ) {
				foreach ( $order_quantities as $key => $qty ) {
					$quantities[ $key ] = isset( $quantities[ $key ] ) ? $quantities[ $key ] + $qty : $qty;
				}
			}
		}
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['categories'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_category_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_bought_attributes( array $condition ) {
		if ( empty( $condition['attributes'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantities = array();
		foreach ( $orders as $order ) {
			$order_quantities = WCCS()->WCCS_Order_Helpers->get_order_items_quantities_based_on( $order, 'attribute' );
			if ( ! empty( $order_quantities ) ) {
				foreach ( $order_quantities as $key => $qty ) {
					$quantities[ $key ] = isset( $quantities[ $key ] ) ? $quantities[ $key ] + $qty : $qty;
				}
			}
		}
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['attributes'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_attribute_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function quantity_of_bought_tags( array $condition ) {
		if ( empty( $condition['tags'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantities = array();
		foreach ( $orders as $order ) {
			$order_quantities = WCCS()->WCCS_Order_Helpers->get_order_items_quantities_based_on( $order, 'tag' );
			if ( ! empty( $order_quantities ) ) {
				foreach ( $order_quantities as $key => $qty ) {
					$quantities[ $key ] = isset( $quantities[ $key ] ) ? $quantities[ $key ] + $qty : $qty;
				}
			}
		}
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['tags'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_tag_id( $id );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function amount_of_bought_products( array $condition ) {
		if ( empty( $condition['products'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amounts = array();
		foreach ( $orders as $order ) {
			$order_amounts = WCCS()->WCCS_Order_Helpers->get_order_items_amounts_based_on( $order, 'single_product' );
			if ( ! empty( $order_amounts ) ) {
				foreach ( $order_amounts as $key => $amount ) {
					$amounts[ $key ] = isset( $amounts[ $key ] ) ? $amounts[ $key ] + $amount : $amount;
				}
			}
		}
		if ( empty( $amounts ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amount = 0;
		foreach ( $condition['products'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id );
			$amount += isset( $amounts[ $id ] ) ? $amounts[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $amount, $value, $condition['math_operation_type'] );
	}

	public function amount_of_bought_variations( array $condition ) {
		if ( empty( $condition['variations'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amounts = array();
		foreach ( $orders as $order ) {
			$order_amounts = WCCS()->WCCS_Order_Helpers->get_order_items_amounts_based_on( $order, 'single_product_variation' );
			if ( ! empty( $order_amounts ) ) {
				foreach ( $order_amounts as $key => $amount ) {
					$amounts[ $key ] = isset( $amounts[ $key ] ) ? $amounts[ $key ] + $amount : $amount;
				}
			}
		}
		if ( empty( $amounts ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amount = 0;
		foreach ( $condition['variations'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id );
			$amount += isset( $amounts[ $id ] ) ? $amounts[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $amount, $value, $condition['math_operation_type'] );
	}

	public function amount_of_bought_categories( array $condition ) {
		if ( empty( $condition['categories'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amounts = array();
		foreach ( $orders as $order ) {
			$order_amounts = WCCS()->WCCS_Order_Helpers->get_order_items_amounts_based_on( $order, 'category' );
			if ( ! empty( $order_amounts ) ) {
				foreach ( $order_amounts as $key => $amount ) {
					$amounts[ $key ] = isset( $amounts[ $key ] ) ? $amounts[ $key ] + $amount : $amount;
				}
			}
		}
		if ( empty( $amounts ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amount = 0;
		foreach ( $condition['categories'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_category_id( $id );
			$amount += isset( $amounts[ $id ] ) ? $amounts[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $amount, $value, $condition['math_operation_type'] );
	}

	public function amount_of_bought_attributes( array $condition ) {
		if ( empty( $condition['attributes'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amounts = array();
		foreach ( $orders as $order ) {
			$order_amounts = WCCS()->WCCS_Order_Helpers->get_order_items_amounts_based_on( $order, 'attribute' );
			if ( ! empty( $order_amounts ) ) {
				foreach ( $order_amounts as $key => $amount ) {
					$amounts[ $key ] = isset( $amounts[ $key ] ) ? $amounts[ $key ] + $amount : $amount;
				}
			}
		}
		if ( empty( $amounts ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amount = 0;
		foreach ( $condition['attributes'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_attribute_id( $id );
			$amount += isset( $amounts[ $id ] ) ? $amounts[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $amount, $value, $condition['math_operation_type'] );
	}

	public function amount_of_bought_tags( array $condition ) {
		if ( empty( $condition['tags'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amounts = array();
		foreach ( $orders as $order ) {
			$order_amounts = WCCS()->WCCS_Order_Helpers->get_order_items_amounts_based_on( $order, 'tag' );
			if ( ! empty( $order_amounts ) ) {
				foreach ( $order_amounts as $key => $amount ) {
					$amounts[ $key ] = isset( $amounts[ $key ] ) ? $amounts[ $key ] + $amount : $amount;
				}
			}
		}
		if ( empty( $amounts ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amount = 0;
		foreach ( $condition['tags'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_tag_id( $id );
			$amount += isset( $amounts[ $id ] ) ? $amounts[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $amount, $value, $condition['math_operation_type'] );
	}

	public function payment_method( array $condition ) {
		if ( ! WC()->session ) {
			return false;
		}

		if ( empty( $condition['payment_methods'] ) ) {
			return true;
		}

		$payment_method = WC()->session->get( 'chosen_payment_method' );

		if ( empty( $payment_method ) ) {
			return 'not_selected' === $condition['select_type'];
		}

		if ( 'selected' === $condition['select_type'] ) {
			return in_array( $payment_method, $condition['payment_methods'] );
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			return ! in_array( $payment_method, $condition['payment_methods'] );
		}

		return false;
	}

	public function shipping_method( array $condition ) {
		if ( ! WC()->session ) {
			return false;
		}

		$shipping_methods = ! empty( $condition['shipping_methods'] ) ? $condition['shipping_methods'] : array();
		if ( ! empty( $condition['shipping_methods_search_by'] ) && 'title' === $condition['shipping_methods_search_by'] ) {
			$shipping_methods = ! empty( $condition['shipping_methods_by_title'] ) ? $condition['shipping_methods_by_title'] : array();
		}
		if ( empty( $shipping_methods ) ) {
			return true;
		}

		$shipping_method = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $shipping_method ) ) {
			return 'not_selected' === $condition['select_type'];
		}

		$shipping_method = $shipping_method[0];
		if ( false === strpos( $shipping_method, 'dynamic_shipping:' ) ) {
			if ( empty( $condition['shipping_methods_search_by'] ) || 'type' === $condition['shipping_methods_search_by'] ) {
				if ( false !== strpos( $shipping_method, ':' ) ) {
					$shipping_method = current( explode( ':', $shipping_method ) );
				} elseif ( preg_match( '#(\d+)$#', $shipping_method, $matches ) ) {
					$shipping_method = str_replace( $matches[0], '', $shipping_method );
				}
			} elseif ( 'title' === $condition['shipping_methods_search_by'] ) {
				if ( false === strpos( $shipping_method, ':' ) && preg_match( '#(\d+)$#', $shipping_method, $matches ) ) {
					$shipping_method = preg_replace( '#(\d+)$#', ':' . $matches[0], $shipping_method );
				}
			}
		}

		if ( 'selected' === $condition['select_type'] ) {
			return in_array( $shipping_method, $shipping_methods );
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			return ! in_array( $shipping_method, $shipping_methods );
		}

		return false;
	}

	public function shipping_country( array $condition ) {
		if ( empty( $condition['countries'] ) ) {
			return true;
		}

		if ( ! WC()->customer ) {
			return false;
		}

		$shipping_country = WC()->customer->get_shipping_country();

		if ( empty( $shipping_country ) ) {
			return 'not_selected' === $condition['select_type'];
		}

		if ( 'selected' === $condition['select_type'] ) {
			return in_array( $shipping_country, $condition['countries'] );
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			return ! in_array( $shipping_country, $condition['countries'] );
		}

		return false;
	}

	public function shipping_state( array $condition ) {
		if ( empty( $condition['states'] ) ) {
			return true;
		}

		if ( ! WC()->customer ) {
			return false;
		}

		$shipping_state = WC()->customer->get_shipping_state();
		$shipping_state = WC()->customer->get_shipping_country() . ( $shipping_state ? ':' . $shipping_state : '' );

		if ( empty( $shipping_state ) ) {
			return 'not_selected' === $condition['select_type'];
		}

		if ( 'selected' === $condition['select_type'] ) {
			return in_array( $shipping_state, $condition['states'] );
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			return ! in_array( $shipping_state, $condition['states'] );
		}

		return false;
	}

	public function shipping_city( array $condition ) {
		if ( ! WC()->customer ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->string_compare(
			WC()->customer->get_shipping_city(),
			( ! empty( $condition['string_value'] ) ? $condition['string_value'] : '' ),
			$condition['string_operation_type']
		);
	}

	public function shipping_postcode( array $condition ) {
		if ( empty( $condition['post_code'] ) ) {
			return true;
		}

		if ( ! WC()->customer ) {
			return false;
		}

		$post_codes = array_map( 'trim', explode( ',', trim( $condition['post_code'] ) ) );
		if ( empty( $post_codes ) ) {
			return true;
		}

		$shipping_post_code = WC()->customer->get_shipping_postcode();

		if ( empty( $shipping_post_code ) ) {
			return 'not_match' === $condition['match'];
		}

		if ( 'match' === $condition['match'] ) {
			foreach ( $post_codes as $post_code ) {
				if ( WCCS()->WCCS_Comparison->postcode_compare( $shipping_post_code, $post_code ) ) {
					return true;
				}
			}
		} elseif ( 'not_match' === $condition['match'] ) {
			foreach ( $post_codes as $post_code ) {
				if ( WCCS()->WCCS_Comparison->postcode_compare( $shipping_post_code, $post_code ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	public function shipping_zone( array $condition ) {
		if ( empty( $condition['zones'] ) ) {
			return true;
		}

		if ( ! WC()->customer ) {
			return false;
		}

		$shipping_zone = wc_get_shipping_zone( array(
			'destination' => array(
				'country'  => WC()->customer->get_shipping_country(),
				'state'    => WC()->customer->get_shipping_state(),
				'postcode' => WC()->customer->get_shipping_postcode(),
			),
		) );
		$shipping_zone = $shipping_zone ? $shipping_zone->get_id() : 0;

		if ( 'selected' === $condition['select_type'] ) {
			return in_array( $shipping_zone, $condition['zones'] );
		} elseif ( 'not_selected' === $condition['select_type'] ) {
			return ! in_array( $shipping_zone, $condition['zones'] );
		}

		return false;
	}

	public function subtotal_of_products_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['products'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal - $subtotal ? $this->cart->subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_products_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['products'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal_ex_tax - $subtotal ? $this->cart->subtotal_ex_tax - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_regular_products_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}
		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->cart->get_items_subtotal( array( $condition ), true ),
			$value,
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_regular_products_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}
		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->cart->get_items_subtotal( array( $condition ), false ),
			$value,
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_onsale_products_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}
		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->cart->get_items_subtotal( array( $condition ), true ),
			$value,
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_onsale_products_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}
		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		// To check all of on sale products.
		$condition['limit'] = -1;

		return WCCS()->WCCS_Comparison->math_compare(
			$this->cart->get_items_subtotal( array( $condition ), false ),
			$value,
			$condition['math_operation_type']
		);
	}

	public function subtotal_of_variations_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['variations'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal - $subtotal ? $this->cart->subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_variations_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['variations'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal_ex_tax - $subtotal ? $this->cart->subtotal_ex_tax - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_categories_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['categories'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal - $subtotal ? $this->cart->subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_categories_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['categories'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal_ex_tax - $subtotal ? $this->cart->subtotal_ex_tax - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_attributes_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['attributes'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal - $subtotal ? $this->cart->subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_attributes_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['attributes'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal_ex_tax - $subtotal ? $this->cart->subtotal_ex_tax - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_tags_include_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['tags'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal - $subtotal ? $this->cart->subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function subtotal_of_tags_exclude_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['tags'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal_ex_tax - $subtotal ? $this->cart->subtotal_ex_tax - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function taxonomy_in_cart( array $condition ) {
		if ( empty( $condition['taxonomies'] ) ) {
			return true;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$taxonomy = $this->get_taxonomy_name( $condition['condition'] );
		if ( empty( $taxonomy ) ) {
			return true;
		}

		return $this->cart->taxonomies_exists_in_cart(
			$condition['taxonomies'],
			$taxonomy,
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function taxonomy_subtotal_including_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['taxonomies'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), true );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal - $subtotal ? $this->cart->subtotal - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function taxonomy_subtotal_excluding_tax( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['taxonomies'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$value    = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );
		$subtotal = $this->cart->get_items_subtotal( array( $condition ), false );
		// Subtract cart subtotal from subtotal of selected items to get subtotal of not selected items.
		if ( ! empty( $condition['select_type'] ) && 'not_selected' === $condition['select_type'] ) {
			$subtotal = 0 < $this->cart->subtotal_ex_tax - $subtotal ? $this->cart->subtotal_ex_tax - $subtotal : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $subtotal, $value, $condition['math_operation_type'] );
	}

	public function taxonomy_quantity_in_cart( array $condition ) {
		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		if ( empty( $condition['taxonomies'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_name( $condition['condition'] );
		if ( empty( $taxonomy ) ) {
			return true;
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$quantities = $this->cart->get_cart_quantities_based_on( 'taxonomy_' . $taxonomy );
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['taxonomies'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id, $taxonomy );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ]['count'] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function taxonomy_bought( array $condition ) {
		if ( empty( $condition['taxonomies'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_name( $condition['condition'] );
		if ( empty( $taxonomy ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		// All time bought taxonomies.
		if ( empty( $date_time_args ) ) {
			return $this->customer->has_bought_product_taxonomies( $condition['taxonomies'], $taxonomy, $condition['union_type'] );
		}

		$condition_taxonomies = array();
		for ( $i = 0; $i < count( $condition['taxonomies'] ); $i++ ) {
			$condition_taxonomies[ $i ] = WCCS_Helpers::maybe_get_exact_item_id( $condition['taxonomies'][ $i ], $taxonomy );
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $condition_taxonomies, array(), $condition['union_type'] );
		}

		$taxonomies = array();
		foreach ( $orders as $order ) {
			$taxonomies = array_merge( $taxonomies, WCCS()->WCCS_Order_Helpers->get_order_taxonomies( $order, $taxonomy ) );
		}

		return WCCS()->WCCS_Comparison->union_compare( $condition_taxonomies, array_unique( $taxonomies ), $condition['union_type'] );
	}

	public function taxonomy_bought_amount( array $condition ) {
		if ( empty( $condition['taxonomies'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_name( $condition['condition'] );
		if ( empty( $taxonomy ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amounts = array();
		foreach ( $orders as $order ) {
			$order_amounts = WCCS()->WCCS_Order_Helpers->get_order_items_amounts_based_on( $order, 'taxonomy_' . $taxonomy );
			if ( ! empty( $order_amounts ) ) {
				foreach ( $order_amounts as $key => $amount ) {
					$amounts[ $key ] = isset( $amounts[ $key ] ) ? $amounts[ $key ] + $amount : $amount;
				}
			}
		}
		if ( empty( $amounts ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$amount = 0;
		foreach ( $condition['taxonomies'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id, $taxonomy );
			$amount += isset( $amounts[ $id ] ) ? $amounts[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $amount, $value, $condition['math_operation_type'] );
	}

	public function taxonomy_bought_quantity( array $condition ) {
		if ( empty( $condition['taxonomies'] ) || empty( $condition['math_operation_type'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_name( $condition['condition'] );
		if ( empty( $taxonomy ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			$billing_email = WCCS_Helpers::get_billing_email();
			if ( empty( $billing_email ) ) {
				return false;
			}
		}

		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		$date_time_args = $this->date_time->get_date_time_args( $condition );
		if ( false === $date_time_args ) {
			return false;
		}

		$orders = $this->customer->get_orders( $date_time_args );
		if ( empty( $orders ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantities = array();
		foreach ( $orders as $order ) {
			$order_quantities = WCCS()->WCCS_Order_Helpers->get_order_items_quantities_based_on( $order, 'taxonomy_' . $taxonomy );
			if ( ! empty( $order_quantities ) ) {
				foreach ( $order_quantities as $key => $qty ) {
					$quantities[ $key ] = isset( $quantities[ $key ] ) ? $quantities[ $key ] + $qty : $qty;
				}
			}
		}
		if ( empty( $quantities ) ) {
			return WCCS()->WCCS_Comparison->math_compare( 0, $value, $condition['math_operation_type'] );
		}

		$quantity = 0;
		foreach ( $condition['taxonomies'] as $id ) {
			$id = WCCS_Helpers::maybe_get_exact_item_id( $id, $taxonomy );
			$quantity += isset( $quantities[ $id ] ) ? $quantities[ $id ] : 0;
		}

		return WCCS()->WCCS_Comparison->math_compare( $quantity, $value, $condition['math_operation_type'] );
	}

	public function user_usage_limit( array $condition, $rule ) {
		if ( ! $rule || ! $rule->id ) {
			return false;
		}
		
        $value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( 0 >= $value ) {
			return false;
		}

		$used_by = WCCS_Helpers::get_used_by();
		if ( empty( $used_by ) ) {
			return true;
		}

		try {
			$model = WCCS()->container()->get( WCCS_DB_User_Usage_Logs::class );
			$usage_count = $model->get_user_usage_count( (int) $rule->id, $used_by );
			return $usage_count < $value;
		} catch ( Exception $e ) {
			return false;
		}
	}

	protected function get_taxonomy_name( $str ) {
		$taxonomy = strpos( $str, '__' );
		if ( false !== $taxonomy ) {
			return substr( $str, $taxonomy + 2 );
		}

		return '';
	}

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Pricing {

	protected $pricings;

	protected $date_time_validator;

	protected $condition_validator;

	protected $cache;

	public $rules_filter;

	public function __construct(
		array $pricings,
		WCCS_Pricing_Condition_Validator $condition_validator = null,
		WCCS_Date_Time_Validator $date_time_validator = null,
		WCCS_Rules_Filter $rules_filter = null
	) {
		$wccs = WCCS();

		$this->pricings            = $pricings;
		$this->date_time_validator = null !== $date_time_validator ? $date_time_validator : $wccs->WCCS_Date_Time_Validator;
		$this->condition_validator = null !== $condition_validator ? $condition_validator : $wccs->WCCS_Pricing_Condition_Validator;
		$this->rules_filter        = null !== $rules_filter ? $rules_filter : new WCCS_Rules_Filter();
		$this->cache               = array(
			'simple'         => false,
			'bulk'           => false,
			'tiered'         => false,
			'purchase'       => false,
			'products_group' => false,
			'exclude'        => false,
		);
	}

	public function get_all_pricing_rules() {
		return $this->pricings;
	}

	public function get_simple_pricings() {
		if ( false !== $this->cache['simple'] ) {
			return $this->cache['simple'];
		}

		$this->cache['simple'] = array();
		if ( empty( $this->pricings ) ) {
			return $this->cache['simple'] = apply_filters( 'wccs_pricing_simples', $this->cache['simple'], $this );
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'simple' !== $pricing->mode || empty( $pricing->items ) || empty( $pricing->discount ) || floatval( $pricing->discount ) <= 0 ) {
				continue;
			} 
			
			// Validating rule usage limit.
			if ( ! empty( $pricing->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $pricing ) ) {
				continue;
			} 
			
			// Validating date time.
			if ( ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} 
			
			// Validating conditions.
			if ( ! $this->condition_validator->is_valid_conditions( $pricing, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			$this->cache['simple'][ $pricing->id ] = array(
				'id'                       => absint( $pricing->id ),
				'name'                     => ! empty( $pricing->name ) ? sanitize_text_field( $pricing->name ) : '',
				'description'              => ! empty( $pricing->description ) ? sanitize_text_field( $pricing->description ) : '',
				'mode'                     => 'simple',
				'apply_mode'               => ! empty( $pricing->apply_mode ) ? $pricing->apply_mode : 'all',
				'order'                    => (int) $pricing->ordering,
				'discount'                 => floatval( $pricing->discount ),
				'discount_type'            => $pricing->discount_type,
				'items'                    => $pricing->items,
				'exclude_items'            => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'date_time'                => $pricing->date_time,
				'date_times_match_mode'    => ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one',
				'conditions'               => $pricing->conditions,
				'message_type'             => ! empty( $pricing->message_type ) ? $pricing->message_type : 'text_message',
				'message_background_color' => ! empty( $pricing->message_background_color ) ? $pricing->message_background_color : '',
				'message_color'            => ! empty( $pricing->message_color ) ? $pricing->message_color : '',
				'receive_message'          => ! empty( $pricing->receive_message ) ? $pricing->receive_message : '',
			);
		}

		return $this->cache['simple'] = apply_filters( 'wccs_pricing_simples', $this->cache['simple'], $this );
	}

	public function get_bulk_pricings() {
		if ( false !== $this->cache['bulk'] ) {
			return $this->cache['bulk'];
		}

		$this->cache['bulk'] = array();
		if ( empty( $this->pricings ) ) {
			return $this->cache['bulk'] = apply_filters( 'wccs_pricing_bulks', $this->cache['bulk'], $this );
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'bulk' !== $pricing->mode || empty( $pricing->items ) || empty( $pricing->quantity_based_on ) || empty( $pricing->quantities ) ) {
				continue;
			} 
			
			// Validating rule usage limit.
			if ( ! empty( $pricing->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $pricing ) ) {
				continue;
			} 
			
			// Validating date time.
			if ( ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} 
			
			// Validating conditions.
			if ( ! $this->condition_validator->is_valid_conditions( $pricing, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			// Validating quantities.
			$valid_quantities = array();
			foreach ( $pricing->quantities as $quantity ) {
				if ( empty( $quantity['min'] ) || intval( $quantity['min'] ) < 0 || empty( $quantity['discount_type'] ) || floatval( $quantity['discount'] ) < 0 ) {
					continue;
				} elseif ( ! empty( $quantity['max'] ) && ( intval( $quantity['max'] ) < 0 || intval( $quantity['max'] ) < intval( $quantity['min'] ) ) ) {
					continue;
				}

				$valid_quantities[] = $quantity;
			}
			if ( empty( $valid_quantities ) ) {
				continue;
			}

			$this->cache['bulk'][ $pricing->id ] = array(
				'id'                       => absint( $pricing->id ),
				'name'                     => ! empty( $pricing->name ) ? sanitize_text_field( $pricing->name ) : '',
				'description'              => ! empty( $pricing->description ) ? sanitize_text_field( $pricing->description ) : '',
				'mode'                     => 'bulk',
				'apply_mode'               => ! empty( $pricing->apply_mode ) ? $pricing->apply_mode : 'all',
				'order'                    => (int) $pricing->ordering,
				'quantities'               => $valid_quantities,
				'quantity_based_on'        => $pricing->quantity_based_on,
				'set_min_quantity'         => ! empty( $pricing->set_min_quantity ) ? $pricing->set_min_quantity : 'false',
				'items'                    => $pricing->items,
				'exclude_items'            => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'display_quantity'         => ! empty( $pricing->display_quantity ) ? $pricing->display_quantity : 'yes',
				'display_price'            => ! empty( $pricing->display_price ) ? $pricing->display_price : 'yes',
				'display_discount'         => ! empty( $pricing->display_discount ) ? $pricing->display_discount : 'no',
				'date_time'                => $pricing->date_time,
				'date_times_match_mode'    => ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one',
				'conditions'               => $pricing->conditions,
				'message_type'             => ! empty( $pricing->message_type ) ? $pricing->message_type : 'text_message',
				'message_background_color' => ! empty( $pricing->message_background_color ) ? $pricing->message_background_color : '',
				'message_color'            => ! empty( $pricing->message_color ) ? $pricing->message_color : '',
				'receive_message'          => ! empty( $pricing->receive_message ) ? $pricing->receive_message : '',
			);
		}

		return $this->cache['bulk'] = apply_filters( 'wccs_pricing_bulks', $this->cache['bulk'], $this );
	}

	public function get_tiered_pricings() {
		if ( false !== $this->cache['tiered'] ) {
			return $this->cache['tiered'];
		}

		$this->cache['tiered'] = array();
		if ( empty( $this->pricings ) ) {
			return $this->cache['tiered'] = apply_filters( 'wccs_pricing_tiereds', $this->cache['tiered'], $this );
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'tiered' !== $pricing->mode || empty( $pricing->items ) || empty( $pricing->quantity_based_on ) || empty( $pricing->quantities ) ) {
				continue;
			} 
			
			// Validating rule usage limit.
			if ( ! empty( $pricing->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $pricing ) ) {
				continue;
			} 
			
			// Validating date time.
			if ( ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} 
			
			// Validating conditions.
			if ( ! $this->condition_validator->is_valid_conditions( $pricing, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			// Validating quantities.
			$valid_quantities = array();
			foreach ( $pricing->quantities as $quantity ) {
				if ( empty( $quantity['min'] ) || intval( $quantity['min'] ) < 0 || empty( $quantity['discount_type'] ) || floatval( $quantity['discount'] ) < 0 ) {
					continue;
				} elseif ( ! empty( $quantity['max'] ) && ( intval( $quantity['max'] ) < 0 || intval( $quantity['max'] ) < intval( $quantity['min'] ) ) ) {
					continue;
				}

				$valid_quantities[] = $quantity;
			}
			if ( empty( $valid_quantities ) ) {
				continue;
			}

			$this->cache['tiered'][ $pricing->id ] = array(
				'id'                       => absint( $pricing->id ),
				'name'                     => ! empty( $pricing->name ) ? sanitize_text_field( $pricing->name ) : '',
				'description'              => ! empty( $pricing->description ) ? sanitize_text_field( $pricing->description ) : '',
				'mode'                     => 'tiered',
				'apply_mode'               => ! empty( $pricing->apply_mode ) ? $pricing->apply_mode : 'all',
				'order'                    => (int) $pricing->ordering,
				'reorder'                  => ! empty( $pricing->reorder ) ? $pricing->reorder : 'asc',
				'quantities'               => $valid_quantities,
				'quantity_based_on'        => $pricing->quantity_based_on,
				'set_min_quantity'         => ! empty( $pricing->set_min_quantity ) ? $pricing->set_min_quantity : 'false',
				'items'                    => $pricing->items,
				'exclude_items'            => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'date_time'                => $pricing->date_time,
				'date_times_match_mode'    => ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one',
				'conditions'               => $pricing->conditions,
				'message_type'             => ! empty( $pricing->message_type ) ? $pricing->message_type : 'text_message',
				'message_background_color' => ! empty( $pricing->message_background_color ) ? $pricing->message_background_color : '',
				'message_color'            => ! empty( $pricing->message_color ) ? $pricing->message_color : '',
				'receive_message'          => ! empty( $pricing->receive_message ) ? $pricing->receive_message : '',
			);
		}

		return $this->cache['tiered'] = apply_filters( 'wccs_pricing_tiereds', $this->cache['tiered'], $this );
	}

	/**
	 * Get purchase pricing rules.
	 *
	 * @param  string $type possible values are 'all', 'auto', 'exclude_auto'
	 * @return array
	 */
	public function get_purchase_pricings( $type = 'all' ) {
		if ( false !== $this->cache['purchase'] ) {
			if ( ! empty( $type ) ) {
				return isset( $this->cache['purchase'][ $type ] ) ? $this->cache['purchase'][ $type ] : array();
			}
			return $this->cache['purchase']['all'];
		}

		$this->cache['purchase'] = array(
			'all'          => array(),
			'auto'         => array(),
			'exclude_auto' => array(),
		);
		if ( empty( $this->pricings ) ) {
			$this->cache['purchase'] = apply_filters( 'wccs_pricing_purchases', $this->cache['purchase'], $this );
			if ( ! empty( $type ) ) {
				return isset( $this->cache['purchase'][ $type ] ) ? $this->cache['purchase'][ $type ] : array();
			}
			return $this->cache['purchase']['all'];
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'purchase_x_receive_y' !== $pricing->mode && 'purchase_x_receive_y_same' !== $pricing->mode && 'bogo' !== $pricing->mode ) {
				continue;
			} elseif ( empty( $pricing->items ) || ( 'bogo' !== $pricing->mode && empty( $pricing->purchased_items ) ) ) {
				continue;
			} elseif ( empty( $pricing->purchase['purchase'] ) || intval( $pricing->purchase['purchase'] ) <= 0 ) {
				continue;
			} elseif ( empty( $pricing->purchase['receive'] ) || intval( $pricing->purchase['receive'] ) <= 0 ) {
				continue;
			} elseif ( empty( $pricing->purchase['discount'] ) || floatval( $pricing->purchase['discount'] ) < 0 ) {
				continue;
			} // Validating rule usage limit.
			elseif ( ! empty( $pricing->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $pricing ) ) {
				continue;
			} // Validating date time.
			elseif ( ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} // Validating conditions.
			elseif ( ! $this->condition_validator->is_valid_conditions( $pricing, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			$rule = array(
				'id'                        => absint( $pricing->id ),
				'name'                      => ! empty( $pricing->name ) ? sanitize_text_field( $pricing->name ) : '',
				'description'               => ! empty( $pricing->description ) ? sanitize_text_field( $pricing->description ) : '',
				'mode'                      => 'purchase',
				'mode_type'                 => $pricing->mode,
				'apply_mode'                => ! empty( $pricing->apply_mode ) ? $pricing->apply_mode : 'all',
				'quantity_based_on'         => ! empty( $pricing->quantity_based_on ) ? $pricing->quantity_based_on : 'all_products',
				'order'                     => (int) $pricing->ordering,
				'purchased_items'           => ! empty( $pricing->purchased_items ) ? $pricing->purchased_items : [],
				'purchase'                  => $pricing->purchase,
				'message_type'              => ! empty( $pricing->message_type ) ? $pricing->message_type : 'text_message',
				'message_background_color'  => ! empty( $pricing->message_background_color ) ? $pricing->message_background_color : '',
				'message_color'             => ! empty( $pricing->message_color ) ? $pricing->message_color : '',
				'receive_message'           => $pricing->receive_message,
				'purchased_message'         => ! empty( $pricing->purchased_message ) ? $pricing->purchased_message : '',
				'repeat'                    => $pricing->repeat,
				'items'                     => $pricing->items,
				'exclude_items'             => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'date_time'                 => $pricing->date_time,
				'date_times_match_mode'     => ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one',
				'conditions'                => $pricing->conditions,
			);

			$this->cache['purchase']['all'][ $pricing->id ] = $rule;

			if ( $auto_add_product = $this->is_auto_add_rule( $rule ) ) {
				if ( 'bogo' !== $rule['mode_type'] ) {
					$rule['auto_add_product'] = $auto_add_product;
				}
				$this->cache['purchase']['auto'][ $pricing->id ] = $rule;
			} else {
				$this->cache['purchase']['exclude_auto'][ $pricing->id ] = $rule;
			}
		}

		$this->cache['purchase'] = apply_filters( 'wccs_pricing_purchases', $this->cache['purchase'], $this );

		if ( ! empty( $type ) ) {
			return isset( $this->cache['purchase'][ $type ] ) ? $this->cache['purchase'][ $type ] : array();
		}
		return $this->cache['purchase']['all'];
	}

	public function get_products_group_pricings() {
		if ( false !== $this->cache['products_group'] ) {
			return $this->cache['products_group'];
		}

		$this->cache['products_group'] = array();
		if ( empty( $this->pricings ) ) {
			return $this->cache['products_group'] = apply_filters( 'wccs_pricing_products_groups', $this->cache['products_group'], $this );
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'products_group' !== $pricing->mode || empty( $pricing->items ) || empty( $pricing->discount ) || floatval( $pricing->discount ) <= 0 ) {
				continue;
			} 
			
			// Validating rule usage limit.
			if ( ! empty( $pricing->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $pricing ) ) {
				continue;
			} 
			
			// Validating date time.
			if ( ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} 
			
			// Validating conditions.
			if ( ! $this->condition_validator->is_valid_conditions( $pricing, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			$this->cache['products_group'][ $pricing->id ] = array(
				'id'                       => absint( $pricing->id ),
				'name'                     => ! empty( $pricing->name ) ? sanitize_text_field( $pricing->name ) : '',
				'description'              => ! empty( $pricing->description ) ? sanitize_text_field( $pricing->description ) : '',
				'mode'                     => 'products_group',
				'apply_mode'               => ! empty( $pricing->apply_mode ) ? $pricing->apply_mode : 'all',
				'order'                    => (int) $pricing->ordering,
				'repeat'                   => $pricing->repeat,
				'discount'                 => floatval( $pricing->discount ),
				'discount_type'            => $pricing->discount_type,
				'groups'                   => array(),
				'exclude_items'            => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'date_time'                => $pricing->date_time,
				'date_times_match_mode'    => ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one',
				'conditions'               => $pricing->conditions,
				'message_type'             => ! empty( $pricing->message_type ) ? $pricing->message_type : 'text_message',
				'message_background_color' => ! empty( $pricing->message_background_color ) ? $pricing->message_background_color : '',
				'message_color'            => ! empty( $pricing->message_color ) ? $pricing->message_color : '',
				'receive_message'          => ! empty( $pricing->receive_message ) ? $pricing->receive_message : '',
			);

			// Setting products group.
			if ( ! empty( $pricing->items ) ) {
				foreach ( $pricing->items as $item ) {
					if ( empty( $item['quantity'] ) || 0 >= (int) $item['quantity'] ) {
						continue;
					}

					$this->cache['products_group'][ $pricing->id ]['groups'][] = array(
						'items'    => array( $item ),
						'quantity' => (int) $item['quantity'],
					);
				}
			}

			if ( empty( $this->cache['products_group'][ $pricing->id ]['groups'] ) ) {
				unset( $this->cache['products_group'][ $pricing->id ] );
			}
		}

		return $this->cache['products_group'] = apply_filters( 'wccs_pricing_products_groups', $this->cache['products_group'], $this );
	}

	public function is_auto_add_rule( $pricing ) {
		if ( empty( $pricing ) ) {
			return false;
		}

		// Discounted product price should be zero or free.
		if ( 'percentage_discount' !== $pricing['purchase']['discount_type'] && 'fixed_price' !== $pricing['purchase']['discount_type'] ) {
			return false;
		} elseif ( 'percentage_discount' === $pricing['purchase']['discount_type'] ) {
			// Discounted product should get 100% discount to become free.
			if ( 100 != $pricing['purchase']['discount'] ) {
				return false;
			}
		} elseif ( 'fixed_price' === $pricing['purchase']['discount_type'] ) {
			// Discounted value should be 0 to become free.
			if ( 0 != $pricing['purchase']['discount'] ) {
				return false;
			}
		}

		if ( isset( $pricing['mode_type'] ) && 'bogo' === $pricing['mode_type'] ) {
			return true;
		}

		$product = 0;

		if ( empty( $pricing['items'] ) || 1 < count( $pricing['items'] ) ) {
			return false;
		} elseif ( isset( $pricing['items'][0][0] ) && 1 < count( $pricing['items'][0] ) ) {
			return false;
		}

		// Find item
		$item = null;
		if ( isset( $pricing['items'][0][0] ) ) {
			$item = $pricing['items'][0][0];
		} elseif ( isset( $pricing['items'][0]['item'] ) ) {
			$item = $pricing['items'][0];
		}
		if ( empty( $item ) || empty( $item['item'] ) ) {
			return false;
		}

		// Pricing should exactly discount one product or one variation.
		if ( 'products_in_list' !== $item['item'] && 'product_variations_in_list' !== $item['item'] ) {
			return false;
		} elseif ( 'products_in_list' === $item['item'] ) {
			if ( empty( $item['products'] ) || 1 < count( $item['products'] ) ) {
				return false;
			}

			$product = $item['products'][0];
			$product = wc_get_product( $product );
			if ( ! $product || 'simple' !== $product->get_type() ) {
				return false;
			}
			$product = $product->get_id();
		} elseif ( 'product_variations_in_list' === $item['item'] ) {
			if ( empty( $item['variations'] ) || 1 < count( $item['variations'] ) ) {
				return false;
			}

			$product = $item['variations'][0];
		}

		return 0 < $product ? $product : false;
	}

	public function get_exclude_rules() {
		if ( false !== $this->cache['exclude'] ) {
			return $this->cache['exclude'];
		}

		$this->cache['exclude'] = array();
		if ( empty( $this->pricings ) ) {
			return $this->cache['exclude'] = apply_filters( 'wccs_pricing_excludes', $this->cache['exclude'], $this );
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'exclude' !== $pricing->mode || empty( $pricing->items ) ) {
				continue;
			} 
			
			// Validating date time.
			if ( ! empty( $pricing->date_time ) && ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} 
			
			// Validating conditions.
			if ( ! empty( $pricing->conditions ) && ! $this->condition_validator->is_valid_conditions( $pricing, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			$this->cache['exclude'][ $pricing->id ] = array(
				'id'            => absint( $pricing->id ),
				'mode'          => $pricing->mode,
				'items'         => $pricing->items,
				'exclude_items' => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'date_time'     => $pricing->date_time,
				'conditions'    => $pricing->conditions,
			);
		}

		return $this->cache['exclude'] = apply_filters( 'wccs_pricing_excludes', $this->cache['exclude'], $this );
	}

	/**
	 * Is given product in excluded rules.
	 *
	 * @since  1.1.0
	 *
	 * @param  int|WC_Product $product
	 * @param  int|WC_Product $variation
	 * @param  array          $variations
	 *
	 * @return boolean
	 */
	public function is_in_exclude_rules( $product, $variation = 0, array $variations = array() ) {
		$excludes = $this->get_exclude_rules();
		if ( empty( $excludes ) ) {
			return false;
		}

		foreach ( $excludes as $exclude ) {
			if ( WCCS()->WCCS_Product_Validator->is_valid_product( $exclude['items'], $product, $variation, $variations ) ) {
				if ( empty( $exclude['exclude_items'] ) || ! WCCS()->WCCS_Product_Validator->is_valid_product( $exclude['exclude_items'], $product, $variation, $variations ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public function get_pricings( array $pricing_types = array( 'simple', 'bulk', 'tiered', 'purchase', 'products_group' ) ) {
		$pricings = array();

		if ( in_array( 'simple', $pricing_types ) ) {
			$pricings['simple'] = $this->get_simple_pricings();
		}

		if ( in_array( 'bulk', $pricing_types ) ) {
			$pricings['bulk'] = $this->get_bulk_pricings();
		}

		if ( in_array( 'tiered', $pricing_types ) ) {
			$pricings['tiered'] = $this->get_tiered_pricings();
		}

		if ( in_array( 'purchase', $pricing_types ) ) {
			$pricings['purchase'] = $this->get_purchase_pricings();
		}

		if ( in_array( 'products_group', $pricing_types ) ) {
			$pricings['products_group'] = $this->get_products_group_pricings();
		}

		return apply_filters( 'wccs_pricing_pricings', $pricings, $pricing_types );
	}

	/**
	 * Reset cached pricings.
	 *
	 * @since  2.8.0
	 *
	 * @return void
	 */
	public function reset_cache() {
		$this->cache = array(
			'simple'         => false,
			'bulk'           => false,
			'tiered'         => false,
			'purchase'       => false,
			'products_group' => false,
			'exclude'        => false,
		);
	}

}

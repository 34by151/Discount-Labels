<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Shipping_Condition_Validator extends WCCS_Condition_Validator {

    public function is_valid_conditions( $rule, $match_mode = 'all', array $package = array() ) {
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
					if ( ! $this->is_valid( $condition, $rule, $package ) ) {
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
			if ( 'one' === $match_mode && $this->is_valid( $condition, $rule, $package ) ) {
				return true;
			} elseif ( 'all' === $match_mode && ! $this->is_valid( $condition, $rule, $package ) ) {
				return false;
			}
		}

		return 'all' === $match_mode;
    }

    public function is_valid( array $condition, $rule = null, array $package = array() ) {
        if ( empty( $condition ) ) {
			return false;
		}

		$is_valid = false;
		if ( is_callable( array( $this, $condition['condition'] ) ) ) {
            $is_valid = call_user_func_array( array( $this, $condition['condition'] ), array( $condition, $rule, $package ) );
		} elseif ( false !== strpos( $condition['condition'], 'taxonomy_' ) ) {
			$method = strpos( $condition['condition'], '__' );
			if ( false !== $method ) {
				$method = substr( $condition['condition'], 0, $method );
			}

			if ( ! empty( $method ) && is_callable( array( $this, $method ) ) ) {
				$is_valid = $this->{$method}( $condition );
			}
		}

		return apply_filters( 'wccs_shipping_condition_validator_is_valid_' . $condition['condition'], $is_valid, $condition, $rule, $package );
	}


	public function products_in_package( array $condition, $rule = null, array $package = [] ) {
		if ( empty( $condition['products'] ) ) {
			return true;
		}

		if ( empty( $package ) || empty( $package['contents'] ) ) {
			return false;
		}

		return WCCS()->WCCS_Cart_Items_Helpers->products_exists_in_items(
			$package['contents'],
			$condition['products'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_variations_in_package( array $condition, $rule = null, array $package = [] ) {
		if ( empty( $condition['variations'] ) ) {
			return true;
		}

		if ( empty( $package ) || empty( $package['contents'] ) ) {
			return false;
		}

		return WCCS()->WCCS_Cart_Items_Helpers->products_exists_in_items(
			$package['contents'],
			$condition['variations'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_categories_in_package( array $condition, $rule = null, array $package = [] ) {
		if ( empty( $condition['categories'] ) ) {
			return true;
		}

		if ( empty( $package ) || empty( $package['contents'] ) ) {
			return false;
		}

		return WCCS()->WCCS_Cart_Items_Helpers->categories_exists_in_items(
			$package['contents'],
			$condition['categories'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_attributes_in_package( array $condition, $rule = null, array $package = [] ) {
		if ( empty( $condition['attributes'] ) ) {
			return true;
		}

		if ( empty( $package ) || empty( $package['contents'] ) ) {
			return false;
		}

		return WCCS()->WCCS_Cart_Items_Helpers->attributes_terms_exists_in_items(
			$package['contents'],
			$condition['attributes'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function product_tags_in_package( array $condition, $rule = null, array $package = [] ) {
		if ( empty( $condition['tags'] ) ) {
			return true;
		}

		if ( empty( $package ) || empty( $package['contents'] ) ) {
			return false;
		}

		return WCCS()->WCCS_Cart_Items_Helpers->tags_exists_in_items(
			$package['contents'],
			$condition['tags'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function shipping_classes_in_package( array $condition, $rule = null, array $package = [] ) {
		if ( empty( $condition['shipping_classes'] ) ) {
			return true;
		}

		if ( empty( $package ) || empty( $package['contents'] ) ) {
			return false;
		}

		return WCCS()->WCCS_Cart_Items_Helpers->shipping_classes_exists_in_items(
			$package['contents'],
			$condition['shipping_classes'],
			$condition['union_type'],
			( ! empty( $condition['number_union_type'] ) ? (int) $condition['number_union_type'] : 2 )
		);
	}

	public function package_total_weight( array $condition, $rule = null, array $package = [] ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( WCCS()->WCCS_Shipping_Helpers->get_shipping_package_weight( $package ), $value, $condition['math_operation_type'] );
	}

	public function number_of_package_items( array $condition, $rule = null, array $package = [] ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( WCCS()->WCCS_Shipping_Helpers->get_shipping_package_contents_count( $package ), $value, $condition['math_operation_type'] );
	}

	public function quantity_of_package_items( array $condition, $rule = null, array $package = [] ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( empty( $package ) ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->quantities_compare(
			WCCS()->WCCS_Shipping_Helpers->get_shipping_package_item_quantities( $package ),
			$value,
			$condition['math_operation_type']
		);
	}

}

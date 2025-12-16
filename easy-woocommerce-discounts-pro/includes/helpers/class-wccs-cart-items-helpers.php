<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Cart_Items_Helpers {

    /**
     * Check is given products exists inside given cart items.
     *
     * @since  4.0.0
     *
     * @param  array   $cart_items
	 * @param  array   $products
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
    public static function products_exists_in_items( array $cart_items, array $products, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $products ) ) {
			return true;
		}

		$products = array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $products );

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $products, array(), $type, (int) $number );
		}

		$found_count = 0;
		foreach ( $products as $product ) {
			$found = false;
			foreach ( $cart_items as $cart_item ) {
				if ( $product == $cart_item['product_id'] || ( ! empty( $cart_item['variation_id'] ) && $product == $cart_item['variation_id'] ) ) {
					++$found_count;
					$found = true;
					break;
				}
			}

			if ( $found ) {
				if ( 'at_least_one_of' === $type ) {
					return true;
				} elseif ( 'at_least_number_of' === $type ) {
					if ( $found_count >= (int) $number ) {
						return true;
					}
				} elseif ( 'none_of' === $type ) {
					return false;
				}
			} elseif ( 'all_of' === $type || 'only' === $type ) {
				return false;
			}
		}

		if ( 'at_least_one_of' === $type || 'at_least_number_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type || 'all_of' === $type  ) {
			return true;
		} elseif ( 'only' === $type ) {
			foreach ( $cart_items as $cart_item ) {
				if ( ! in_array( $cart_item['product_id'], $products ) && ( empty( $cart_item['variation_id' ] ) || ! in_array( $cart_item['variation_id'], $products ) ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
    }

    /**
     * Check is given categories exists inside given cart items.
     *
     * @since  4.0.0
     *
     * @param  array   $cart_items
	 * @param  array   $categories
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
    public static function categories_exists_in_items( array $cart_items, array $categories, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $categories ) ) {
			return true;
		}

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $categories, array(), $type, (int) $number );
		}

		$categories = array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $categories );

		$cart_categories = array();

		foreach ( $cart_items as $item => $item_data ) {
			$product_categories = wc_get_product_cat_ids( $item_data['product_id'] );
			if ( 'at_least_one_of' === $type || 'none_of' === $type ) {
				if ( count( array_intersect( $categories, $product_categories ) ) ) {
					return 'at_least_one_of' === $type;
				}
			} elseif ( 'only' === $type ) {
				if ( ! count( array_intersect( $categories, $product_categories ) ) ) {
					return false;
				}
			} elseif ( 'at_least_number_of' === $type && count( array_intersect( $categories, $product_categories ) ) >= (int) $number ) {
				return true;
			}

			$cart_categories = array_merge( $cart_categories, $product_categories );
		}

		if ( 'at_least_one_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type ) {
			return true;
		}

		if ( ! empty( $cart_categories ) ) {
			if ( 'only' === $type ) {
				return true;
			}

			return WCCS()->WCCS_Comparison->union_compare( $categories, $cart_categories, $type, (int) $number );
		}

		return false;
    }

     /**
     * Check is given attribute terms exists inside given cart items.
     *
     * @since  4.0.0
     *
     * @param  array   $cart_items
	 * @param  array   $attributes_terms
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
	public static function attributes_terms_exists_in_items( array $cart_items, array $attributes_terms, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $attributes_terms ) ) {
			return true;
		}

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $attributes_terms, array(), $type, (int) $number );
		}

		$attributes_terms = WCCS_Helpers::maybe_get_exact_attributes( $attributes_terms );

		$terms = array();
		foreach ( $cart_items as $cart_item ) {
			$product_attributes = WCCS()->WCCS_Attribute_Helpers->get_product_simple_attributes( $cart_item['data'] );

			if ( ! empty( $cart_item['variation'] ) ) {
				foreach ( $cart_item['variation'] as $key => $value ) {
					if ( 0 === strpos( $key, 'attribute_' ) ) {
						$term = get_term_by( 'slug', $value, str_replace( 'attribute_', '', $key ) );
						if ( ! is_wp_error( $term ) && is_object( $term ) && $term->term_id ) {
							$product_attributes[] = $term->term_id;
						}
					}
				}
			}
			
			if ( 'at_least_one_of' === $type || 'none_of' === $type ) {
				if ( count( array_intersect( $attributes_terms, $product_attributes ) ) ) {
					return 'at_least_one_of' === $type;
				}
			} elseif ( 'only' === $type ) {
				if ( ! count( array_intersect( $attributes_terms, $product_attributes ) ) ) {
					return false;
				}
			} elseif ( 'at_least_number_of' === $type && count( array_intersect( $attributes_terms, $product_attributes ) ) >= (int) $number ) {
				return true;
			}

			$terms = array_merge( $terms, $product_attributes );
		}

		if ( 'at_least_one_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type ) {
			return true;
		}

		if ( ! empty( $terms ) ) {
			if ( 'only' === $type ) {
				return true;
			}

			return WCCS()->WCCS_Comparison->union_compare( $attributes_terms, $terms, $type, (int) $number );
		}

		return false;
    }

    /**
     * Check is given tags exists inside given cart items.
     *
     * @since  4.0.0
     *
     * @param  array   $cart_items
	 * @param  array   $tags
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
    public static function tags_exists_in_items( array $cart_items, array $tags, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $tags ) ) {
			return true;
		}

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $tags, array(), $type, (int) $number );
		}

		$tags = array_map( 'WCCS_Helpers::maybe_get_exact_tag_id', $tags );

		$product_helpers = WCCS()->product_helpers;

		$cart_tags = array();

		foreach ( $cart_items as $item => $item_data ) {
			$product_tags = $product_helpers->wc_get_product_term_ids( $item_data['product_id'], 'product_tag' );
			if ( 'at_least_one_of' === $type || 'none_of' === $type ) {
				if ( count( array_intersect( $tags, $product_tags ) ) ) {
					return 'at_least_one_of' === $type;
				}
			} elseif ( 'only' === $type ) {
				if ( ! count( array_intersect( $tags, $product_tags ) ) ) {
					return false;
				}
			} elseif ( 'at_least_number_of' === $type && count( array_intersect( $tags, $product_tags ) ) >= (int) $number ) {
				return true;
			}

			$cart_tags = array_merge( $cart_tags, $product_tags );
		}

		if ( 'at_least_one_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type ) {
			return true;
		}

		if ( ! empty( $cart_tags ) ) {
			if ( 'only' === $type ) {
				return true;
			}

			return WCCS()->WCCS_Comparison->union_compare( $tags, $cart_tags, $type, (int) $number );
		}

		return false;
    }

    /**
     * Check is given shipping classes exists inside given cart items.
     *
     * @since  4.0.0
     *
     * @param  array   $cart_items
	 * @param  array   $classes
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
    public static function shipping_classes_exists_in_items( array $cart_items, array $classes, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $classes ) ) {
			return true;
		}

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $classes, array(), $type, (int) $number );
		}

		$cart_classes = array();

		foreach ( $cart_items as $item => $item_data ) {
			if ( ! $product_class = $item_data['data']->get_shipping_class_id() ) {
				continue;
			}

			if ( 'at_least_one_of' === $type || 'none_of' === $type ) {
				if ( in_array( $product_class, $classes ) ) {
					return 'at_least_one_of' === $type;
				}
			} elseif ( 'only' === $type ) {
				if ( ! in_array( $product_class, $classes ) ) {
					return false;
				}
			}

			$cart_classes[] = $product_class;
		}

		if ( 'at_least_one_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type ) {
			return true;
		}

		if ( ! empty( $cart_classes ) ) {
			if ( 'only' === $type ) {
				return true;
			}

			return WCCS()->WCCS_Comparison->union_compare( $classes, $cart_classes, $type, (int) $number );
		}

		return false;
	}

	/**
     * Check is given product taxonomies exists inside given cart items.
     *
     * @since  5.2.0
     *
     * @param  array   $cart_items
	 * @param  array   $taxonomies
	 * @param  string  $taxonomy
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
    public static function taxonomies_exists_in_items( array $cart_items, array $taxonomies, $taxonomy, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $taxonomies ) || empty( $taxonomy ) ) {
			return true;
		}

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $taxonomies, array(), $type, (int) $number );
		}

		for ( $i = 0; $i < count( $taxonomies ); $i++ ) {
			$taxonomies[ $i ] = WCCS_Helpers::maybe_get_exact_item_id( $taxonomies[ $i ], $taxonomy );
		}

		$product_helpers = WCCS()->product_helpers;

		$cart_taxonomies = array();

		foreach ( $cart_items as $item => $item_data ) {
			$product_taxonomies = $product_helpers->wc_get_product_term_ids( $item_data['product_id'], $taxonomy );
			if ( 'at_least_one_of' === $type || 'none_of' === $type ) {
				if ( count( array_intersect( $taxonomies, $product_taxonomies ) ) ) {
					return 'at_least_one_of' === $type;
				}
			} elseif ( 'only' === $type ) {
				if ( ! count( array_intersect( $taxonomies, $product_taxonomies ) ) ) {
					return false;
				}
			} elseif ( 'at_least_number_of' === $type && count( array_intersect( $taxonomies, $product_taxonomies ) ) >= (int) $number ) {
				return true;
			}

			$cart_taxonomies = array_merge( $cart_taxonomies, $product_taxonomies );
		}

		if ( 'at_least_one_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type ) {
			return true;
		} 

		if ( ! empty( $cart_taxonomies ) ) {
			if ( 'only' === $type ) {
				return true;
			}

			return WCCS()->WCCS_Comparison->union_compare( $taxonomies, $cart_taxonomies, $type, (int) $number );
		}

		return false;
    }

	public static function get_product_ids( array $cart_items ) {
		if ( empty( $cart_items ) ) {
			return array();
		}

		$product_ids = array();
		foreach ( $cart_items as $cart_item ) {
			if ( $product_id = self::get_product_id( $cart_item ) ) {
				$product_ids[] = $product_id;
			}
		}
		return $product_ids;
	}

	public static function get_product_id( array $cart_item ) {
		if ( empty( $cart_item ) ) {
			return false;
		}

		$product_id = (int) $cart_item['product_id'];
		if ( isset( $cart_item['variation_id'] ) && 0 < (int) $cart_item['variation_id'] ) {
			$product_id = (int) $cart_item['variation_id'];
		}
		return $product_id;
	}

	public static function get_weight( array $cart_items ) {
		if ( empty( $cart_items ) ) {
			return 0;
		}

		$weight = 0;
        foreach ( $cart_items as $cart_item_key => $values ) {
			if ( $values['data']->has_weight() && $values['data']->needs_shipping() ) {
				$weight += (float) $values['data']->get_weight() * $values['quantity'];
			}
        }

		return apply_filters( 'wccs_cart_items_helpers_' . __FUNCTION__, $weight, $cart_items );
	}

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Product_Validator {

	protected $customer;

	protected $date_time;

	public function __construct( $customer = null, WCCS_Date_Time $date_time = null ) {
		$this->customer  = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
		$this->date_time = ! is_null( $date_time ) ? $date_time : new WCCS_Date_Time();
	}

	public function is_valid_product( array $items, $product, $variation = 0, array $variations = array(), $cart_item = array() ) {
		if ( empty( $items ) ) {
			return false;
		}

		if ( ! apply_filters( 'wccs_product_validator_is_valid_cart_item', true, $cart_item ) ) {
			return false;
		}

		// New structure conditions that supports OR conditions too.
		if ( is_array( $items[0] ) && ! isset( $items[0]['item'] ) && ! isset( $items[0]['condition'] ) ) {
			foreach ( $items as $group ) {
				if ( empty( $group ) ) {
					continue;
				}

				$valid = true;
				foreach ( $group as $item ) {
					if ( ! $this->is_valid( $item, $product, $variation, $variations ) ) {
						$valid = false;
						break;
					}
				}
				if ( $valid ) {
					return true;
				}
			}
			return false;
		}

		foreach ( $items as $item ) {
			if ( ! $this->is_valid( $item, $product, $variation, $variations ) ) {
				return false;
			}
		}

		return true;
	}

	public function is_valid( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item ) ) {
			return false;
		}

		$method = '';
		if ( isset( $item['item'] ) ) {
			$method = $item['item'];
		} elseif ( isset( $item['condition'] ) ) {
			$method = $item['condition'];
		}

		switch ( $method ) {
			case 'subtotal_of_products_include_tax':
			case 'subtotal_of_products_exclude_tax':
				$method = 'products_in_list';
				break;

			case 'subtotal_of_variations_include_tax':
			case 'subtotal_of_variations_exclude_tax':
				$method = 'product_variations_in_list';
				break;

			case 'subtotal_of_categories_include_tax':
			case 'subtotal_of_categories_exclude_tax':
				$method = 'categories_in_list';
				break;

			case 'subtotal_of_attributes_include_tax':
			case 'subtotal_of_attributes_exclude_tax':
				$method = 'product_attributes';
				break;

			case 'subtotal_of_tags_include_tax':
			case 'subtotal_of_tags_exclude_tax':
				$method = 'products_have_tags';
				break;

			case 'subtotal_of_regular_products_include_tax':
			case 'subtotal_of_regular_products_exclude_tax':
				$method = 'product_is_on_sale';
				$item['yes_no'] = 'no';
				break;

			case 'subtotal_of_onsale_products_include_tax':
			case 'subtotal_of_onsale_products_exclude_tax':
				$method = 'product_is_on_sale';
				$item['yes_no'] = 'yes';
				break;

			default:
				break;
		}

		if ( false !== strpos( $method, 'taxonomy_' ) ) {
			$method = 'product_taxonomies';
		}

		$method = apply_filters( 'wccs_product_validator_validate_method', $method, $item, $product, $variation, $variations );
		if ( empty( $method ) ) {
			return false;
		}

		$is_valid = false;
		if ( method_exists( $this, $method ) ) {
			$is_valid = $this->{$method}( $item, $product, $variation, $variations );
		}

		return apply_filters( 'wccs_product_validator_is_valid_' . $method, $is_valid, $item, $product, $variation, $variations );
	}

	public function all_products( $item, $product, $variation = 0, array $variations = array() ) {
		if ( is_object( $product ) ) {
			return 0 < $product->get_id();
		}
		return 0 < $product;
	}

	public function products_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['products'] ) ) {
			return false;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, array_filter( array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $item['products'] ) ) );
	}

	public function products_not_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['products'] ) ) {
			return false;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return ! in_array( $product, array_filter( array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $item['products'] ) ) );
	}

	public function product_variations_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['variations'] ) ) {
			return false;
		}

		$variation = is_numeric( $variation ) ? $variation : $variation->get_id();
		if ( 0 >= $variation ) {
			$variation = is_numeric( $product ) ? $product : $product->get_id();
		}

		return $variation > 0 &&
			in_array( $variation, array_filter( array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $item['variations'] ) ) );
	}

	public function product_variations_not_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['variations'] ) ) {
			return false;
		}

		$variation = is_numeric( $variation ) ? $variation : $variation->get_id();
		if ( 0 >= $variation ) {
			$variation = is_numeric( $product ) ? $product : $product->get_id();
		}

		return $variation === 0 ||
			! in_array( $variation, array_filter( array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $item['variations'] ) ) );
	}

	public function product_attributes( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['attributes'] ) ) {
			return false;
		}

		$terms = array();
		if ( ! empty( $variations ) ) {
			foreach ( $variations as $key => $value ) {
				if ( is_numeric( $key ) ) {
					$terms[] = $value;
				} else {
					$term = get_term_by( 'slug', $value, str_replace( 'attribute_', '', $key ) );
					if ( ! is_wp_error( $term ) && is_object( $term ) && $term->term_id ) {
						$terms[] = $term->term_id;
					}
				}
			}
		}

		$product_id = is_numeric( $variation ) ? absint( $variation ) : $variation->get_id();
		if ( 0 >= $product_id ) {
			$product_id = is_numeric( $product ) ? $product : $product->get_id();
		}

		// Merging product simple attributes.
		$terms = array_merge( $terms, WCCS()->WCCS_Attribute_Helpers->get_product_attributes( $product_id ) );

		$union_type = ! empty( $item['union_type'] ) ? $item['union_type'] : 'at_least_one_of';

		return WCCS()->WCCS_Comparison->union_compare(
			WCCS_Helpers::maybe_get_exact_attributes( $item['attributes'] ),
			array_unique( $terms ),
			$union_type
		);
	}

	public function products_have_tags( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['tags'] ) ) {
			return false;
		}

		$tags = WCCS()->product_helpers->wc_get_product_term_ids(
			is_numeric( $product ) ? $product : $product->get_id(),
			'product_tag'
		);

		$union_type = ! empty( $item['union_type'] ) ? $item['union_type'] : 'at_least_one_of';

		return WCCS()->WCCS_Comparison->union_compare(
			array_map( 'WCCS_Helpers::maybe_get_exact_tag_id', $item['tags'] ),
			array_unique( $tags ),
			$union_type
		);
	}

	public function product_taxonomies( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['taxonomies'] ) ) {
			return false;
		}

		$taxonomy = '';
		if ( isset( $item['item'] ) ) {
			$taxonomy = str_replace( 'taxonomy_', '', $item['item'] );
			if ( false !== strpos( $taxonomy, '__' ) ) {
				$taxonomy = substr( $taxonomy, strpos( $taxonomy, '__' ) + 2 );
			}
		} elseif ( isset( $item['condition'] ) ) {
			$taxonomy = str_replace( 'taxonomy_', '', $item['condition'] );
			if ( false !== strpos( $taxonomy, '__' ) ) {
				$taxonomy = substr( $taxonomy, strpos( $taxonomy, '__' ) + 2 );
			}
		}

		if ( empty( $taxonomy ) ) {
			return false;
		}

		$taxonomies = WCCS()->product_helpers->wc_get_product_term_ids(
			is_numeric( $product ) ? $product : $product->get_id(),
			sanitize_text_field( $taxonomy )
		);

		$union_type = ! empty( $item['union_type'] ) ? $item['union_type'] : 'at_least_one_of';

		$item_taxonomies = array();
		if ( ! empty( $item['taxonomies'] ) ) {
			foreach ( $item['taxonomies'] as $taxonomy_id ) {
				$item_taxonomies[] = WCCS_Helpers::maybe_get_exact_item_id( $taxonomy_id, $taxonomy );
			}
		}

		return WCCS()->WCCS_Comparison->union_compare(
			$item_taxonomies,
			array_unique( $taxonomies ),
			$union_type
		);
	}

	public function featured_products( $item, $product, $variation = 0, array $variations = array() ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$featured_products = wc_get_featured_product_ids();
		if ( $limit > 0 && ! empty( $featured_products ) ) {
			$featured_products = array_slice( $featured_products, 0, $limit );
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, $featured_products );
	}

	public function onsale_products( $item, $product, $variation = 0, array $variations = array() ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$on_sales = wc_get_product_ids_on_sale();
		if ( $limit > 0 && ! empty( $on_sales ) ) {
			$on_sales = array_slice( $on_sales, 0, $limit );
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, $on_sales );
	}

	public function regular_products( $item, $product, $variation = 0, array $variations = array() ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$on_sales = wc_get_product_ids_on_sale();
		if ( $limit > 0 && ! empty( $on_sales ) ) {
			$on_sales = array_slice( $on_sales, 0, $limit );
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return ! in_array( $product, $on_sales );
	}

	public function top_rated_products( $item, $product, $variation = 0, array $variations = array() ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, WCCS()->products->get_top_rated_products( $limit ) );
	}

	public function recently_viewed_products( $item, $product, $variation = 0, array $variations = array() ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, WCCS()->products->get_recently_viewed_products( $limit ) );
	}

	public function products_added( $item, $product, $variation = 0, array $variations = array() ) {
		$date_time_args = $this->date_time->get_date_time_args( $item );
		if ( empty( $date_time_args['date_after'] ) && empty( $date_time_args['date_before'] ) ) {
			return false;
		}

		$args = array(
			'status'     => 'publish',
			'limit'      => -1,
			'return'     => 'ids',
			'date_query' => array(),
		);

		if ( ! empty( $date_time_args['date_after'] ) ) {
			$args['date_query']['after'] = $date_time_args['date_after'];
		}

		if ( ! empty( $date_time_args['date_before'] ) ) {
			$args['date_query']['before'] = $date_time_args['date_before'];
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, array_map( 'WCCS_Helpers::maybe_get_exact_item_id', WCCS()->products->get_products( $args ) ) );
	}

	public function top_seller_products( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['top_filter_period'] ) ) {
			return false;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array(
			$product,
			array_map(
				'WCCS_Helpers::maybe_get_exact_item_id',
				WCCS()->WCCS_Report->get_top_sellers(
					array(
						'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
						'limit'  => $limit > 0 ? $limit : -1,
					)
				)
			)
		);
	}

	public function top_earner_products( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['top_filter_period'] ) ) {
			return false;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array(
			$product,
			array_map(
				'WCCS_Helpers::maybe_get_exact_item_id',
				WCCS()->WCCS_Report->get_top_earners(
					array(
						'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
						'limit'  => $limit > 0 ? $limit : -1,
					)
				)
			)
		);
	}

	public function top_free_products( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['top_filter_period'] ) ) {
			return false;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array(
			$product,
			array_map(
				'WCCS_Helpers::maybe_get_exact_item_id',
				WCCS()->WCCS_Report->get_top_freebies(
					array(
						'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
						'limit'  => $limit > 0 ? $limit : -1,
					)
				)
			)
		);
	}

	public function similar_products_to_customer_bought_products( $item, $product, $variation = 0, array $variations = array() ) {
		$bought_products = $this->customer->get_bought_products();
		if ( empty( $bought_products ) ) {
			return false;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = array();
		foreach ( $bought_products as $product ) {
			$products = array_merge( $products, WCCS()->product_helpers->get_related_products( $product, $limit ) );
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, $products );
	}

	public function similar_products_to_customer_cart_products( $item, $product, $variation = 0, array $variations = array() ) {
		if ( ! WC()->cart ) {
			return false;
		}

		$cart_products = WCCS()->cart->get_products();
		if ( empty( $cart_products ) ) {
			return false;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = array();
		foreach ( $cart_products as $product ) {
			$products = array_merge( $products, WCCS()->product_helpers->get_related_products( $product, $limit ) );
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, $products );
	}

	public function categories_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['categories'] ) ) {
			return false;
		}

		$item_categories    = array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $item['categories'] );
		$product            = is_numeric( $product ) ? $product : $product->get_id();
		$product_categories = wc_get_product_cat_ids( $product );
		foreach ( $product_categories as $category ) {
			if ( in_array( $category, $item_categories ) ) {
				return true;
			}
		}
		return false;
	}

	public function categories_not_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['categories'] ) ) {
			return false;
		}

		$item_categories    = array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $item['categories'] );
		$product            = is_numeric( $product ) ? $product : $product->get_id();
		$product_categories = wc_get_product_cat_ids( $product );
		foreach ( $product_categories as $category ) {
			if ( in_array( $category, $item_categories ) ) {
				return false;
			}
		}
		return true;
	}

	public function product_regular_price( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['math_operation_type'] ) ) {
			return false;
		}

		$value = ! empty( $item['number_value_2'] ) ? floatval( $item['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( is_numeric( $variation ) && 0 < $variation ) {
			$product = wc_get_product( $variation );
		} elseif ( is_object( $variation ) ) {
			$product = $variation;
		} elseif ( is_numeric( $product ) && 0 < $product ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		$price = WCCS()->product_helpers->wc_get_regular_price( $product );
		if ( '' === $price ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( (float) $price, $value, $item['math_operation_type'] );
	}

	public function product_display_price( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['math_operation_type'] ) ) {
			return false;
		}

		$value = ! empty( $item['number_value_2'] ) ? floatval( $item['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( is_numeric( $variation ) && 0 < $variation ) {
			$product = wc_get_product( $variation );
		} elseif ( is_object( $variation ) ) {
			$product = $variation;
		} elseif ( is_numeric( $product ) && 0 < $product ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( WCCS()->product_helpers->wc_get_price_to_display( $product ), $value, $item['math_operation_type'] );
	}

	public function product_is_on_sale( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['yes_no'] ) ) {
			return false;
		}

		if ( is_numeric( $variation ) && 0 < $variation ) {
			$product = wc_get_product( $variation );
		} elseif ( is_object( $variation ) ) {
			$product = $variation;
		} elseif ( is_numeric( $product ) && 0 < $product ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		$on_sale = WCCS()->product_helpers->is_on_sale( $product, 'edit' );
		if ( 'yes' === $item['yes_no'] ) {
			return $on_sale;
		} elseif ( 'no' === $item['yes_no'] ) {
			return ! $on_sale;
		}

		return false;
	}

	public function product_stock_quantity( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['math_operation_type'] ) ) {
			return false;
		}

		$value = ! empty( $item['number_value_2'] ) ? floatval( $item['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		if ( is_numeric( $variation ) && 0 < $variation ) {
			$product = wc_get_product( $variation );
		} elseif ( is_object( $variation ) ) {
			$product = $variation;
		} elseif ( is_numeric( $product ) && 0 < $product ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		$quantity = $product->get_stock_quantity();
		if ( null === $quantity || '' === $quantity ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( (float) $quantity, $value, $item['math_operation_type'] );
	}

	public function product_meta_field( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['meta_field_key'] ) || empty( $item['meta_field_condition'] ) ) {
			return true;
		}

		$product   = is_numeric( $product ) ? $product : $product->get_id();
		$variation = is_numeric( $variation ) ? $variation : $variation->get_id();

		// If meta data exists in product variation use product variation meta data otherwise use product meta data.
		$variation_meta = 0 < $variation ? get_post_meta( $variation, $item['meta_field_key'], false ) : array();
		$product_meta   = ! empty( $variation_meta ) ? $variation_meta : get_post_meta( $product, $item['meta_field_key'], false );

		if ( ! empty( $product_meta ) && 1 === count( $product_meta ) ) {
			$product_meta = $product_meta[0];
		}

		return WCCS()->WCCS_Comparison->meta_compare( $product_meta, $item['meta_field_condition'], $item['meta_field_value'] );
	}

}

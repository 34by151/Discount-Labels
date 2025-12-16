<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Discounted_Products_Selector {

	protected $customer;

	protected $date_time;

	protected $product_validator;

	protected $selected_products = array();

	protected $orders = array(
		'all_products' => '0',
		'products_in_list' => '1',
		'products_not_in_list' => '2',
		'product_variations_in_list' => '3',
		'products_have_tags' => '4',
		'featured_products' => '5',
		'onsale_products' => '6',
		'top_rated_products' => '7',
		'recently_viewed_products' => '8',
		'products_added' => '9',
		'top_seller_products' => '10',
		'top_earner_products' => '11',
		'top_free_products' => '12',
		'similar_products_to_customer_bought_products' => '13',
		'similar_products_to_customer_cart_products' => '14',
		'categories_in_list' => '15',
		'categories_not_in_list' => '16',
		'product_regular_price' => '17',
		'product_display_price' => '18',
		'product_stock_quantity' => '19',
		'product_is_on_sale' => '20',
		'product_meta_field' => '21',
		'product_variations_not_in_list' => '22',
		'product_attributes' => '23',
	);

	public function __construct( $customer = null ) {
		$this->customer          = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
		$this->date_time         = new WCCS_Date_Time();
		$this->product_validator = WCCS()->WCCS_Product_Validator;
	}

	public function get_products( array $items ) {
		if ( empty( $items ) ) {
			return array();
		}

		// OR-conditions support.
		if ( isset( $items[0][0] ) ) {
			$products = array();
			foreach ( $items as $group ) {
				$group_products = $this->get_group_products( $group );
				if ( ! empty( $group_products ) ) {
					$products = array_merge( $products, $group_products );
				}
			}
			return $products;
		}

		return $this->get_group_products( $items );
	}

	protected function get_group_products( array $items ) {
		if ( empty( $items ) ) {
			return array();
		}

		$items = $this->sort_items( $items );
		if ( empty( $items ) ) {
			return array();
		}

		$this->selected_products = array();
		foreach ( $items as $item ) {
			if ( method_exists( $this, $item['item'] ) ) {
				$products = $this->{$item['item']}( $item );
				if ( empty( $products ) ) {
					return array();
				}
			}
		}

		return $this->selected_products;
	}

	protected function sort_items( array $items ) {
		if ( empty( $items ) ) {
			return array();
		}

		$ret_items = array();
		foreach ( $items as $item ) {
			if ( isset( $this->orders[ $item['item'] ] ) ) {
				$ret_items[ $this->orders[ $item['item'] ] ] = $item;
			}
		}

		if ( ! empty( $ret_items ) ) {
			ksort( $ret_items );
		}

		return $ret_items;
	}

	protected function all_products( $item ) {
		return $this->selected_products = WCCS()->products->get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		);
	}

	protected function products_in_list( $item ) {
		if ( empty( $item['products'] ) ) {
			return true;
		}

		return $this->selected_products = array_merge( $this->selected_products, $item['products'] );
	}

	protected function products_not_in_list( $item ) {
		if ( empty( $item['products'] ) ) {
			return true;
		}

		if ( ! empty( $this->selected_products ) ) {
			return $this->selected_products = array_diff( $this->selected_products, $item['products'] );
		} else {
			return $this->selected_products = WCCS()->products->get_products(
				array(
					'exclude' => $item['products'],
					'status'  => 'publish',
					'limit'   => -1,
					'return'  => 'ids',
				)
			);
		}
	}

	protected function product_variations_in_list( $item ) {
		if ( empty( $item['variations'] ) ) {
			return true;
		}

		$parents = array();
		foreach ( $item['variations'] as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}

			$parent = WCCS()->product_helpers->get_parent_id( $variation );
			if ( 0 < $parent ) {
				$parents[] = $parent;
			}
		}

		if ( empty( $parents ) ) {
			return false;
		}

		if ( ! empty( $this->selected_products ) ) {
			$selected_products = array();
			foreach ( $this->selected_products as $product_id ) {
				if ( in_array( $product_id, $parents ) ) {
					$selected_products[] = $product_id;
				}
			}
			return $this->selected_products = $selected_products;
		} else {
			return $this->selected_products = $parents;
		}
	}

	protected function product_variations_not_in_list( $item ) {
		if ( empty( $item['variations'] ) ) {
			return true;
		}
	}

	protected function product_attributes( $item ) {
		if ( empty( $item['attributes'] ) ) {
			return true;
		}

		$products = WCCS()->products->get_attributes_products( $item['attributes'], $item['union_type'] );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function products_have_tags( $item ) {
		if ( empty( $item['union_type'] ) || empty( $item['tags'] ) ) {
			return true;
		}

		$products = WCCS()->products->get_products_have_tags( $item['tags'], $item['union_type'] );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function featured_products( $item ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$featured_products = wc_get_featured_product_ids();
		if ( $limit > 0 && ! empty( $featured_products ) ) {
			$featured_products = array_slice( $featured_products, 0, $limit );
		}

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $featured_products ) : $featured_products;
	}

	protected function onsale_products( $item ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$on_sales = wc_get_product_ids_on_sale();
		if ( $limit > 0 && ! empty( $on_sales ) ) {
			$on_sales = array_slice( $on_sales, 0, $limit );
		}

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $on_sales ) : $on_sales;
	}

	protected function top_rated_products( $item ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = WCCS()->products->get_top_rated_products( $limit );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function recently_viewed_products( $item ) {
		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = WCCS()->products->get_recently_viewed_products( $limit );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function products_added( $item ) {
		$date_time_args = $this->date_time->get_date_time_args( $item );
		if ( empty( $date_time_args['date_after'] ) && empty( $date_time_args['date_before'] ) ) {
			return true;
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

		$products = WCCS()->products->get_products( $args );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function top_seller_products( $item ) {
		if ( empty( $item['top_filter_period'] ) ) {
			return true;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = WCCS()->WCCS_Report->get_top_sellers( array(
			'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
			'limit'  => $limit > 0 ? $limit : -1,
		) );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function top_earner_products( $item ) {
		if ( empty( $item['top_filter_period'] ) ) {
			return true;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = WCCS()->WCCS_Report->get_top_earners( array(
			'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
			'limit'  => $limit > 0 ? $limit : -1,
		) );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function top_free_products( $item ) {
		if ( empty( $item['top_filter_period'] ) ) {
			return true;
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = WCCS()->WCCS_Report->get_top_freebies( array(
			'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
			'limit'  => $limit > 0 ? $limit : -1,
		) );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function similar_products_to_customer_bought_products( $item ) {
		$bought_products = $this->customer->get_bought_products();
		if ( empty( $bought_products ) ) {
			return $this->selected_products = array();
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = array();
		foreach ( $bought_products as $product ) {
			$products = array_merge( $products, WCCS()->product_helpers->get_related_products( $product, $limit ) );
		}

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function similar_products_to_customer_cart_products( $item ) {
		$cart_products = WCCS()->cart->get_products();
		if ( empty( $cart_products ) ) {
			return $this->selected_products = array();
		}

		$limit = 12;
		if ( ! empty( $item['limit'] ) ) {
			$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
		}

		$products = array();
		foreach ( $cart_products as $product ) {
			$products = array_merge( $products, WCCS()->product_helpers->get_related_products( $product, $limit ) );
		}

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function categories_in_list( $item ) {
		if ( empty( $item['categories'] ) ) {
			return true;
		}

		$products = WCCS()->products->get_categories_products( $item['categories'] );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function categories_not_in_list( $item ) {
		if ( empty( $item['categories'] ) ) {
			return true;
		}

		$categories_to_include = WCCS()->products->get_categories_not_in_list( $item['categories'] );
		if ( empty( $categories_to_include ) ) {
			return $this->selected_products = array();
		}

		$products = WCCS()->products->get_categories_products( $categories_to_include );

		return $this->selected_products = ! empty( $this->selected_products ) ?
			array_intersect( $this->selected_products, $products ) : $products;
	}

	protected function product_regular_price( $item ) {
		if ( ! empty( $this->selected_products ) ) {
			$selected_products = array();
			foreach ( $this->selected_products as $product_id ) {
				if ( $this->product_validator->product_regular_price( $item, $product_id ) ) {
					$selected_products[] = $product_id;
				}
			}
			return $this->selected_products = $selected_products;
		} else {
			if ( empty( $item['math_operation_type'] ) ) {
				return true;
			}

			$value = ! empty( $item['number_value_2'] ) ? floatval( $item['number_value_2'] ) : 0;
			if ( $value < 0 ) {
				return false;
			}

			$compare = '';
			switch ( $item['math_operation_type'] ) {
				case 'less_than' :
					$compare = '<';
					break;

				case 'less_equal_to' :
					$compare = '<=';
					break;

				case 'greater_than' :
					$compare = '>';
					break;

				case 'greater_equal_to' :
					$compare = '>=';
					break;

				case 'equal_to' :
					$compare = '=';
					break;

				case 'not_equal_to' :
					$compare = '!=';
					break;

				default :
					break;
			}

			if ( empty( $compare ) ) {
				return false;
			}

			return $this->selected_products = WCCS()->products->get_products_by_price( array(
				'value'      => $value,
				'compare'    => $compare,
				'price_type' => '_regular_price',
			) );
		}
	}

	protected function product_display_price( $item ) {
		if ( ! empty( $this->selected_products ) ) {
			$selected_products = array();
			foreach ( $this->selected_products as $product_id ) {
				if ( $this->product_validator->product_display_price( $item, $product_id ) ) {
					$selected_products[] = $product_id;
				}
			}
			return $this->selected_products = $selected_products;
		} else {
			if ( empty( $item['math_operation_type'] ) ) {
				return true;
			}

			$value = ! empty( $item['number_value_2'] ) ? floatval( $item['number_value_2'] ) : 0;
			if ( $value < 0 ) {
				return false;
			}

			$compare = '';
			switch ( $item['math_operation_type'] ) {
				case 'less_than' :
					$compare = '<';
					break;

				case 'less_equal_to' :
					$compare = '<=';
					break;

				case 'greater_than' :
					$compare = '>';
					break;

				case 'greater_equal_to' :
					$compare = '>=';
					break;

				case 'equal_to' :
					$compare = '=';
					break;

				case 'not_equal_to' :
					$compare = '!=';
					break;

				default :
					break;
			}

			if ( empty( $compare ) ) {
				return false;
			}

			return $this->selected_products = WCCS()->products->get_products_by_price( array(
				'value'   => $value,
				'compare' => $compare,
			) );
		}
	}

	protected function product_is_on_sale( $item ) {
		if ( ! empty( $this->selected_products ) ) {
			$selected_products = array();
			foreach ( $this->selected_products as $product_id ) {
				if ( $this->product_validator->product_is_on_sale( $item, $product_id ) ) {
					$selected_products[] = $product_id;
				}
			}
			return $this->selected_products = $selected_products;
		} else {
			if ( empty( $item['yes_no'] ) ) {
				return false;
			}

			if ( 'yes' === $item['yes_no'] ) {
				return $this->selected_products = wc_get_product_ids_on_sale();
			} elseif ( 'no' === $item['yes_no'] ) {
				return $this->selected_products = WCCS()->products->get_products(
					array(
						'limit'   => -1,
						'exclude' => wc_get_product_ids_on_sale(),
					)
				);
			}

			return false;
		}
	}

	protected function product_stock_quantity( $item ) {
		if ( ! empty( $this->selected_products ) ) {
			$selected_products = array();
			foreach ( $this->selected_products as $product_id ) {
				if ( $this->product_validator->product_stock_quantity( $item, $product_id ) ) {
					$selected_products[] = $product_id;
				}
			}
			return $this->selected_products = $selected_products;
		} else {
			if ( empty( $item['math_operation_type'] ) ) {
				return true;
			}

			$value = ! empty( $item['number_value_2'] ) ? floatval( $item['number_value_2'] ) : 0;
			if ( $value < 0 ) {
				return false;
			}

			$compare = '';
			switch ( $item['math_operation_type'] ) {
				case 'less_than' :
					$compare = '<';
					break;

				case 'less_equal_to' :
					$compare = '<=';
					break;

				case 'greater_than' :
					$compare = '>';
					break;

				case 'greater_equal_to' :
					$compare = '>=';
					break;

				case 'equal_to' :
					$compare = '=';
					break;

				case 'not_equal_to' :
					$compare = '!=';
					break;

				default :
					break;
			}

			if ( empty( $compare ) ) {
				return false;
			}

			return $this->selected_products = WCCS()->products->get_products_by_stock_quantity( array(
				'value'   => $value,
				'compare' => $compare,
			) );
		}
	}

	protected function product_meta_field( $item ) {
		if ( ! empty( $this->selected_products ) ) {
			$selected_products = array();
			foreach ( $this->selected_products as $product_id ) {
				if ( $this->product_validator->product_meta_field( $item, $product_id ) ) {
					$selected_products[] = $product_id;
				}
			}
			return $this->selected_products = $selected_products;
		} else {
			if ( empty( $item['meta_field_key'] ) || empty( $item['meta_field_condition'] ) ) {
				return true;
			}

			$args = array(
				'status'     => 'publish',
				'limit'      => -1,
				'return'     => 'ids',
				'meta_query' => array(),
			);
			switch ( $item['meta_field_condition'] ) {
				case 'empty' :
				case 'is_not_checked' :
					$args['meta_query'] = array(
						'relation' => 'OR',
						array(
							'key'     => $item['meta_field_key'],
							'value'   => '',
							'compare' => '=',
						),
						array(
							'key'     => $item['meta_field_key'],
							'value'   => false,
							'type'    => 'BOOLEAN',
							'compare' => '=',
						),
						array(
							'key'     => $item['meta_field_key'],
							'compare' => 'NOT EXISTS'
						)
					);
					break;

				case 'is_not_empty' :
				case 'is_checked' :
					$args['meta_query'] = array(
						'relation' => 'AND',
						array(
							'key'     => $item['meta_field_key'],
							'value'   => '',
							'compare' => '!=',
						),
						array(
							'key'     => $item['meta_field_key'],
							'value'   => false,
							'type'    => 'BOOLEAN',
							'compare' => '!=',
						),
					);
					break;

				case 'contains' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => 'LIKE',
					);
					break;

				case 'does_not_contain' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => 'NOT LIKE',
					);
					break;

				case 'begins_with' :
					// @todo adding meta query.
					break;

				case 'ends_with' :
					// @todo adding meta query.
					break;

				case 'equal_to' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => '=',
					);
					break;

				case 'not_equal_to' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => '!=',
					);
					break;

				case 'less_than' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => '<',
					);
					break;

				case 'less_equal_to' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => '<=',
					);
					break;

				case 'greater_than' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => '>',
					);
					break;

				case 'greater_equal_to' :
					$args['meta_query'] = array(
						'key'     => $item['meta_field_key'],
						'value'   => $item['meta_field_value'],
						'compare' => '>=',
					);
					break;
			}

			if ( empty( $args['meta_query'] ) ) {
				return false;
			}

			return $this->selected_products = WCCS()->products->get_products( $args );
		}
	}

}

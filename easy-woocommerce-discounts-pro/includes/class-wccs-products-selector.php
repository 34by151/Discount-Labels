<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Products_Selector {

	protected $customer;

	protected $products;

	protected $product_helpers;

	protected $cart;

	protected $report;

	protected $date_time;

	public function __construct( $customer = null ) {
		$wccs                  = WCCS();
		$this->customer        = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
		$this->products        = $wccs->products;
		$this->product_helpers = $wccs->product_helpers;
		$this->cart            = $wccs->cart;
		$this->report          = $wccs->report;
		$this->date_time       = new WCCS_Date_Time();
	}

	public function select_products( $items, $type = 'include' ) {
		$products = array(
			'include' => array(),
			'exclude' => array(),
		);

		if ( empty( $items ) ) {
			return $products;
		}

		$other_type = 'include' === $type ? 'exclude' : 'include';

		foreach ( $items as $item ) {
			if ( 'products_not_in_list' === $item['item'] && ! empty( $item['products'] ) ) {
				$products[ $other_type ] = array_merge( $products[ $other_type ], $item['products'] );
			} elseif ( 'product_variations_not_in_list' === $item['item'] ) {
				if ( ! empty( $item['variations'] ) ) {
					$products[ $other_type ] = array_merge( $products[ $other_type ], $item['variations'] );
				}
			}
		}

		$products[ $other_type ] = array_unique( $products[ $other_type ] );

		foreach ( $items as $item ) {
			$limit = 12;
			if ( ! empty( $item['limit'] ) ) {
				$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
			}

			if ( false !== strpos( $item['item'], 'taxonomy_' ) ) {
				if ( ! empty( $item['union_type'] ) && ! empty( $item['taxonomies'] ) ) {
					$products[ $type ] = array_merge( $products[ $type ], $this->products->get_products_have_taxonomies( $item['taxonomies'], str_replace( 'taxonomy_', '', $item['item'] ), $item['union_type'] ) );
				}
				continue;
			}

			switch ( $item['item'] ) {
				case 'all_products':
					if ( 'include' === $type ) {
						$products[ $type ] = array( 'all_products' );
						return $products;
					}
					break;

				case 'products_in_list' :
					if ( ! empty( $item['products'] ) ) {
						$products[ $type ] = array_merge( $products[ $type ], $item['products'] );
					}
					break;

				case 'product_variations_in_list' :
					if ( ! empty( $item['variations'] ) ) {
						$products[ $type ] = array_merge( $products[ $type ], $item['variations'] );
					}
					break;

				case 'discounted_products' :
					$discounted_products = $this->products->get_discounted_products();
					if ( 'all_products' === $discounted_products ) {
						$products[ $type ] = array( 'all_products' );
						return $products;
					} elseif ( ! empty( $discounted_products ) ) {
						$products[ $type ] = array_merge( $products[ $type ], $discounted_products );
					}
					break;

				case 'products_have_tags' :
					if ( ! empty( $item['union_type'] ) && ! empty( $item['tags'] ) ) {
						$products[ $type ] = array_merge( $products[ $type ], $this->products->get_products_have_tags( $item['tags'], $item['union_type'] ) );
					}
					break;

				case 'featured_products' :
					$featured_products = wc_get_featured_product_ids();
					if ( $limit > 0 && ! empty( $featured_products ) ) {
						$featured_products = array_slice( $featured_products, 0, $limit );
					}
					$products[ $type ] = array_merge( $products[ $type ], $featured_products );
					break;

				case 'onsale_products' :
					$on_sales = wc_get_product_ids_on_sale();
					if ( $limit > 0 && ! empty( $on_sales ) ) {
						$on_sales = array_slice( $on_sales, 0, $limit );
					}
					$products[ $type ] = array_merge( $products[ $type ], $on_sales );
					break;

				case 'top_rated_products' :
					$products[ $type ] = array_merge( $products[ $type ], $this->products->get_top_rated_products( $limit ) );
					break;

				case 'recently_viewed_products' :
					$products[ $type ] = array_merge( $products[ $type ], $this->products->get_recently_viewed_products( $limit ) );
					break;

				case 'products_added' :
					$date_time_args = $this->date_time->get_date_time_args( $item );
					if ( isset( $date_time_args['date_before'] ) || isset( $date_time_args['date_after'] ) ) {
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

						$products[ $type ] = array_merge( $products[ $type ], $this->products->get_products( $args ) );
					}
					break;

				case 'top_seller_products' :
					if ( ! empty( $item['top_filter_period'] ) ) {
						$products[ $type ] = array_merge(
							$products[ $type ],
							$this->report->get_top_sellers( array(
								'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
								'limit'  => $limit > 0 ? $limit : -1,
							) )
						);
					}
					break;

				case 'top_earner_products' :
					if ( ! empty( $item['top_filter_period'] ) ) {
						$products[ $type ] = array_merge(
							$products[ $type ],
							$this->report->get_top_earners( array(
								'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
								'limit'  => $limit > 0 ? $limit : -1,
							) )
						);
					}
					break;

				case 'top_free_products' :
					if ( ! empty( $item['top_filter_period'] ) ) {
						$products[ $type ] = array_merge(
							$products[ $type ],
							$this->report->get_top_freebies( array(
								'period' => 'today' == $item['top_filter_period'] ? '' : $item['top_filter_period'],
								'limit'  => $limit > 0 ? $limit : -1,
							) )
						);
					}
					break;

				case 'similar_products_to_customer_bought_products' :
					$bought_products = $this->customer->get_bought_products();
					if ( ! empty( $bought_products ) ) {
						foreach ( $bought_products as $product ) {
							$products[ $type ] = array_merge( $products[ $type ], $this->product_helpers->get_related_products( $product, $limit ) );
						}
					}
					break;

				case 'similar_products_to_customer_cart_products' :
					$cart_products = $this->cart->get_products();
					if ( ! empty( $cart_products ) ) {
						foreach ( $cart_products as $product ) {
							$products[ $type ] = array_merge( $products[ $type ], $this->product_helpers->get_related_products( $product, $limit ) );
						}
					}
					break;

				case 'categories_in_list' :
					if ( ! empty( $item['categories'] ) ) {
						$products[ $type ] = array_merge( $products[ $type ], $this->products->get_categories_products( $item['categories'] ) );
					}
					break;

				case 'categories_not_in_list' :
					if ( ! empty( $item['categories'] ) ) {
						$categories_to_include = $this->products->get_categories_not_in_list( $item['categories'] );
						if ( ! empty( $categories_to_include ) ) {
							$products[ $type ] = array_merge( $products[ $type ], $this->products->get_categories_products( $categories_to_include ) );
						}
					}
					break;

				default:
					break;
			}
		}

		$products[ $type ] = array_unique( $products[ $type ] );

		return $products;
	}

}

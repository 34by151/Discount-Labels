<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Order_Helpers {

	/**
	 * Wrapper for wc_get_orders.
	 *
	 * @since  2.0.0
	 *
	 * @param  array $args Array of args (above)
	 *
	 * @return array|stdClass Number of pages and an array of order objects if
	 *                             paginate is true, or just an array of values.
	 */
	public function wc_get_orders( array $args = array() ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return wc_get_orders( $args );
		}

		$args = wp_parse_args( $args, array(
			'status'     => array_keys( wc_get_order_statuses() ),
			'type'       => wc_get_order_types( 'view-orders' ),
			'parent'     => null,
			'customer'   => null,
			'email'      => '',
			'limit'      => get_option( 'posts_per_page' ),
			'offset'     => null,
			'page'       => 1,
			'exclude'    => array(),
			'orderby'    => 'date',
			'order'      => 'DESC',
			'return'     => 'objects',
			'paginate'   => false,
			'meta_query' => array(),
			'date_query' => array(),
		) );

		/**
		 * Generate WP_Query args. This logic will change if orders are moved to
		 * custom tables in the future.
		 */
		$wp_query_args = array(
			'post_type'      => $args['type'] ? $args['type'] : 'shop_order',
			'post_status'    => $args['status'],
			'posts_per_page' => $args['limit'],
			'meta_query'     => array(),
			'fields'         => 'ids',
			'orderby'        => $args['orderby'],
			'order'          => $args['order'],
			'meta_query'     => $args['meta_query'],
			'date_query'     => $args['date_query'],
		);

		if ( ! is_null( $args['parent'] ) ) {
			$wp_query_args['post_parent'] = absint( $args['parent'] );
		}

		if ( ! is_null( $args['offset'] ) ) {
			$wp_query_args['offset'] = absint( $args['offset'] );
		} else {
			$wp_query_args['paged'] = absint( $args['page'] );
		}

		if ( ! empty( $args['customer'] ) ) {
			$values = is_array( $args['customer'] ) ? $args['customer'] : array( $args['customer'] );
			$wp_query_args['meta_query'][] = _wc_get_orders_generate_customer_meta_query( $values );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$wp_query_args['post__not_in'] = array_map( 'absint', $args['exclude'] );
		}

		if ( ! $args['paginate' ] ) {
			$wp_query_args['no_found_rows'] = true;
		}

		// Get results.
		$orders = new WP_Query( $wp_query_args );

		if ( 'objects' === $args['return'] ) {
			$return = array_map( 'wc_get_order', $orders->posts );
		} else {
			$return = $orders->posts;
		}

		if ( $args['paginate' ] ) {
			return (object) array(
				'orders'        => $return,
				'total'         => $orders->found_posts,
				'max_num_pages' => $orders->max_num_pages,
			);
		} else {
			return $return;
		}
	}

	/**
	 * Get list of statuses which are consider 'paid'.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function wc_get_is_paid_statuses() {
		if ( WCCS_Helpers::wc_version_check() ) {
			return wc_get_is_paid_statuses();
		}

		return apply_filters( 'woocommerce_order_is_paid_statuses', array( 'processing', 'completed' ) );
	}

	/**
	 * Getting order created date.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order $order
	 *
	 * @return string
	 */
	public function get_order_date_created( $order ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return (string) $order->get_date_created();
		}

		return $order->order_date;
	}

	/**
	 * Getting order items quantities based on given quantity type.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order $order
	 * @param  string   $quantity_based_on
	 *
	 * @return array
	 */
	public function get_order_items_quantities_based_on( $order, $quantity_based_on = 'single_product' ) {
		$order_items = $order->get_items();
		if ( empty( $order_items ) ) {
			return array();
		}

		$quantities = array();

		switch ( $quantity_based_on ) {
			case 'single_product' :
				foreach ( $order_items as $order_item ) {
					$quantity = (float) WCCS_Order_Item_Product_Helpers::get_quantity( $order_item );
					if ( empty( $order_item['product_id'] ) || ! $quantity ) {
						continue;
					}

					$quantities[ $order_item['product_id'] ] = isset( $quantities[ $order_item['product_id'] ] ) ?
						$quantities[ $order_item['product_id'] ] + $quantity : $quantity;
				}
				break;

			case 'single_product_variation' :
				foreach ( $order_items as $order_item ) {
					$quantity = (float) WCCS_Order_Item_Product_Helpers::get_quantity( $order_item );
					if ( ! $quantity ) {
						continue;
					}

					if ( ! empty( $order_item['variation_id'] ) ) {
						$quantities[ $order_item['variation_id'] ] = isset( $quantities[ $order_item['variation_id'] ] ) ?
							$quantities[ $order_item['variation_id'] ] + $quantity : $quantity;
					} elseif ( ! empty( $order_item['product_id'] ) ) {
						$quantities[ $order_item['product_id'] ] = isset( $quantities[ $order_item['product_id'] ] ) ?
							$quantities[ $order_item['product_id'] ] + $quantity : $quantity;
					}
				}
				break;

			case 'category' :
				foreach ( $order_items as $order_item ) {
					$quantity = (float) WCCS_Order_Item_Product_Helpers::get_quantity( $order_item );
					if ( empty( $order_item['product_id'] ) || ! $quantity ) {
						continue;
					}

					$categories = array_unique( wc_get_product_cat_ids( $order_item['product_id'] ) );
					if ( ! empty( $categories ) ) {
						foreach ( $categories as $category ) {
							$quantities[ $category ] = isset( $quantities[ $category ] ) ?
								$quantities[ $category ] + $quantity : $quantity;
						}
					}
				}
				break;

			case 'attribute' :
				foreach ( $order_items as $order_item ) {
					$quantity = (float) WCCS_Order_Item_Product_Helpers::get_quantity( $order_item );
					if ( 0 >= $quantity ) {
						continue;
					}

					$product           = empty( $order_item['variation_id'] ) ? wc_get_product( $order_item['product_id'] ) : wc_get_product( $order_item['variation_id'] );
					$simple_attributes = WCCS()->WCCS_Attribute_Helpers->get_product_simple_attributes( $product );
					if ( ! empty( $simple_attributes ) ) {
						foreach ( $simple_attributes as $attribute_id ) {
							$quantities[ $attribute_id ] = isset( $quantities[ $attribute_id ] ) ?
								$quantities[ $attribute_id ] + $quantity : $quantity;
						}
					}

					if ( empty( $order_item['variation_id'] ) ) {
						continue;
					}

					$variation_attributes = WCCS_Order_Item_Product_Helpers::get_attributes( $order_item );
					foreach ( $variation_attributes as $attribute_id ) {
						$quantities[ $attribute_id ] = isset( $quantities[ $attribute_id ] ) ?
							$quantities[ $attribute_id ] + $quantity : $quantity;
					}
				}
				break;

			case 'tag' :
				foreach ( $order_items as $order_item ) {
					$quantity = (float) WCCS_Order_Item_Product_Helpers::get_quantity( $order_item );
					if ( empty( $order_item['product_id'] ) || ! $quantity ) {
						continue;
					}

					$tags = array_unique( WCCS()->product_helpers->wc_get_product_term_ids( $order_item['product_id'], 'product_tag' ) );
					if ( ! empty( $tags ) ) {
						foreach ( $tags as $tag ) {
							$quantities[ $tag ] = isset( $quantities[ $tag ] ) ?
								$quantities[ $tag ] + $quantity : $quantity;
						}
					}
				}
				break;

			case 'all_products' :
				foreach ( $order_items as $order_item ) {
					$quantity = (float) WCCS_Order_Item_Product_Helpers::get_quantity( $order_item );
					if ( empty( $order_item['product_id'] ) || ! $quantity ) {
						continue;
					}

					$quantities['all_products'] = isset( $quantities['all_products'] ) ?
						$quantities['all_products'] + $quantity : $quantity;
				}
				break;

			default :
				// Quantity based on product custom taxonomies.
				if ( false !== strpos( $quantity_based_on, 'taxonomy_' ) ) {
					$taxonomy = sanitize_text_field( str_replace( 'taxonomy_', '', $quantity_based_on ) );
					if ( ! empty( $taxonomy ) ) {
						foreach ( $order_items as $order_item ) {
							$quantity = (float) WCCS_Order_Item_Product_Helpers::get_quantity( $order_item );
							if ( empty( $order_item['product_id'] ) || ! $quantity ) {
								continue;
							}

							$taxonomies = array_unique( WCCS()->product_helpers->wc_get_product_term_ids( $order_item['product_id'], $taxonomy ) );
							if ( ! empty( $taxonomies ) ) {
								foreach ( $taxonomies as $key ) {
									$quantities[ $key ] = isset( $quantities[ $key ] ) ?
										$quantities[ $key ] + $quantity : $quantity;
								}
							}
						}
					}
				}
				break;
		}

		return $quantities;
	}

	/**
	 * Getting order items amounts based on given type.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Order $order
	 * @param  string   $based_on
	 * @param  boolean  $inc_tax
	 *
	 * @return void
	 */
	public function get_order_items_amounts_based_on( $order, $based_on = 'single_product', $inc_tax = true ) {
		$order_items = $order->get_items();
		if ( empty( $order_items ) ) {
			return array();
		}

		$amounts = array();
		switch ( $based_on ) {
			case 'single_product' :
				foreach ( $order_items as $order_item ) {
					$line_subtotal = $order->get_line_subtotal( $order_item, $inc_tax );
					if ( 0 < $line_subtotal && ! empty( $order_item['product_id'] ) ) {
						$amounts[ $order_item['product_id'] ] = isset( $amounts[ $order_item['product_id'] ] ) ?
							$amounts[ $order_item['product_id'] ] + $line_subtotal : $line_subtotal;
					}
				}
				break;

			case 'single_product_variation' :
				foreach ( $order_items as $order_item ) {
					$line_subtotal = $order->get_line_subtotal( $order_item, $inc_tax );
					if ( 0 < $line_subtotal ) {
						if ( ! empty( $order_item['variation_id'] ) ) {
							$amounts[ $order_item['variation_id'] ] = isset( $amounts[ $order_item['variation_id'] ] ) ?
								$amounts[ $order_item['variation_id'] ] + $line_subtotal : $line_subtotal;
						} elseif ( ! empty( $order_item['product_id'] ) ) {
							$amounts[ $order_item['product_id'] ] = isset( $amounts[ $order_item['product_id'] ] ) ?
								$amounts[ $order_item['product_id'] ] + $line_subtotal : $line_subtotal;
						}
					}
				}
				break;

			case 'category' :
				foreach ( $order_items as $order_item ) {
					$line_subtotal = $order->get_line_subtotal( $order_item, $inc_tax );
					if ( 0 < $line_subtotal && ! empty( $order_item['product_id'] ) ) {
						$categories = array_unique( wc_get_product_cat_ids( $order_item['product_id'] ) );
						if ( ! empty( $categories ) ) {
							foreach ( $categories as $category ) {
								$amounts[ $category ] = isset( $amounts[ $category ] ) ?
									$amounts[ $category ] + $line_subtotal : $line_subtotal;
							}
						}
					}
				}
				break;

			case 'attribute' :
				foreach ( $order_items as $order_item ) {
					$line_subtotal     = $order->get_line_subtotal( $order_item, $inc_tax );
					$product           = empty( $order_item['variation_id'] ) ? wc_get_product( $order_item['product_id'] ) : wc_get_product( $order_item['variation_id'] );
					$simple_attributes = WCCS()->WCCS_Attribute_Helpers->get_product_simple_attributes( $product );
					if ( ! empty( $simple_attributes ) ) {
						foreach ( $simple_attributes as $attribute_id ) {
							$amounts[ $attribute_id ] = isset( $amounts[ $attribute_id ] ) ?
								$amounts[ $attribute_id ] + $line_subtotal : $line_subtotal;
						}
					}

					if ( empty( $order_item['variation_id'] ) ) {
						continue;
					}

					$variation_attributes = WCCS_Order_Item_Product_Helpers::get_attributes( $order_item );
					foreach ( $variation_attributes as $attribute_id ) {
						$amounts[ $attribute_id ] = isset( $amounts[ $attribute_id ] ) ?
							$amounts[ $attribute_id ] + $line_subtotal : $line_subtotal;
					}
				}
				break;

			case 'tag' :
				foreach ( $order_items as $order_item ) {
					$line_subtotal = $order->get_line_subtotal( $order_item, $inc_tax );
					if ( 0 < $line_subtotal && ! empty( $order_item['product_id'] ) ) {
						$tags = array_unique( WCCS()->product_helpers->wc_get_product_term_ids( $order_item['product_id'], 'product_tag' ) );
						if ( ! empty( $tags ) ) {
							foreach ( $tags as $tag ) {
								$amounts[ $tag ] = isset( $amounts[ $tag ] ) ?
									$amounts[ $tag ] + $line_subtotal : $line_subtotal;
							}
						}
					}
				}
				break;

			case 'all_products' :
				foreach ( $order_items as $order_item ) {
					$line_subtotal = $order->get_line_subtotal( $order_item, $inc_tax );
					if ( 0 < $line_subtotal && ! empty( $order_item['product_id'] ) ) {
						$amounts['all_products'] = isset( $amounts['all_products'] ) ?
							$amounts['all_products'] + $line_subtotal : $line_subtotal;
					}
				}
				break;

			default :
				// Amounts based on product custom taxonomies.
				if ( false !== strpos( $based_on, 'taxonomy_' ) ) {
					$taxonomy = sanitize_text_field( str_replace( 'taxonomy_', '', $based_on ) );
					if ( ! empty( $taxonomy ) ) {
						foreach ( $order_items as $order_item ) {
							$line_subtotal = $order->get_line_subtotal( $order_item, $inc_tax );
							if ( 0 < $line_subtotal && ! empty( $order_item['product_id'] ) ) {
								$taxonomies = array_unique( WCCS()->product_helpers->wc_get_product_term_ids( $order_item['product_id'], $taxonomy ) );
								if ( ! empty( $taxonomies ) ) {
									foreach ( $taxonomies as $key ) {
										$amounts[ $key ] = isset( $amounts[ $key ] ) ?
											$amounts[ $key ] + $line_subtotal : $line_subtotal;
									}
								}
							}
						}
					}
				}
				break;
		}

		return $amounts;
	}

	public function get_order_products( $order ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return array();
		}

		$products = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$products[] = $item['product_id'];
			}
		}

		return array_unique( $products );
	}

	public function get_order_variations( $order ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return array();
		}

		$variations = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['variation_id'] ) ) {
				$variations[] = $item['variation_id'];
			}
		}

		return array_unique( $variations );
	}

	public function get_order_categories( $order ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return array();
		}

		$categories = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$categories = array_merge( $categories, wc_get_product_cat_ids( $item['product_id'] ) );
			}
		}

		return array_unique( $categories );
	}

	public function get_order_attributes_terms( $order ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return array();
		}

		$attributes_terms = array();
		foreach ( $items as $item ) {
			$product           = empty( $item['variation_id'] ) ? wc_get_product( $item['product_id'] ) : wc_get_product( $item['variation_id'] );
			$simple_attributes = WCCS()->WCCS_Attribute_Helpers->get_product_simple_attributes( $product );
			if ( ! empty( $simple_attributes ) ) {
				$attributes_terms = array_merge( $attributes_terms, $simple_attributes );
			}

			if ( empty( $item['variation_id'] ) ) {
				continue;
			}

			$variation_attributes = WCCS_Order_Item_Product_Helpers::get_attributes( $item );
			if ( ! empty( $variation_attributes ) ) {
				$attributes_terms = array_merge( $attributes_terms, $variation_attributes );
			}
		}

		return array_unique( $attributes_terms );
	}

	public function get_order_tags( $order ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return array();
		}

		$tags = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$tags = array_merge( $tags, WCCS()->product_helpers->wc_get_product_term_ids( $item['product_id'], 'product_tag' ) );
			}
		}

		return array_unique( $tags );
	}

	public function get_order_taxonomies( $order, $taxonomy ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return array();
		}

		$taxonomies = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$taxonomies = array_merge( $taxonomies, WCCS()->product_helpers->wc_get_product_term_ids( $item['product_id'], $taxonomy ) );
			}
		}

		return array_unique( $taxonomies );
	}

}

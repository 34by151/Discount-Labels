<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Products {

	protected $custom_taxonomies;

	public function get_products( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'status'         => array( 'draft', 'pending', 'private', 'publish' ),
			'type'           => array_merge( array_keys( wc_get_product_types() ) ),
			'parent'         => null,
			'sku'            => '',
			'category'       => array(),
			'tag'            => array(),
			'tag_tax_opts'   => array(
				'field'    => 'slug',
				'operator' => 'IN',
			),
			'limit'          => get_option( 'posts_per_page' ),
			'offset'         => null,
			'page'           => 1,
			'include'        => array(),
			'exclude'        => array(),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'return'         => 'objects',
			'paginate'       => false,
			'shipping_class' => array(),
			'meta_query'     => array(),
			'tax_query'      => array(),
			'date_query'     => array(),
			'post_title'     => '',
			'post_id'        => '',
		) );

		/**
		 * Generate WP_Query args.
		 */
		$wp_query_args = array(
			'post_type'      => 'variation' === $args['type'] ? 'product_variation' : 'product',
			'post_status'    => $args['status'],
			'posts_per_page' => $args['limit'],
			'meta_query'     => $args['meta_query'],
			'orderby'        => $args['orderby'],
			'order'          => $args['order'],
			'tax_query'      => $args['tax_query'],
			'date_query'     => $args['date_query'],
		);
		// Do not load unnecessary post data if the user only wants IDs.
		if ( 'ids' === $args['return'] ) {
			$wp_query_args['fields'] = 'ids';
		}

		if ( 'variation' !== $args['type'] ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $args['type'],
			);
		}

		if ( ! empty( $args['sku'] ) ) {
			$wp_query_args['meta_query'][] = array(
				'key'     => '_sku',
				'value'   => $args['sku'],
				'compare' => 'LIKE',
			);
		}

		if ( ! empty( $args['category'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'   => $args['category'],
			);
		}

		if ( ! empty( $args['tag'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => isset( $args['tag_tax_opts']['field'] ) ? $args['tag_tax_opts']['field'] : 'slug',
				'terms'    => $args['tag'],
				'operator' => isset( $args['tag_tax_opts']['operator'] ) ? $args['tag_tax_opts']['operator'] : 'IN',
			);
		}

		if ( ! empty( $args['shipping_class'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_shipping_class',
				'field'    => 'slug',
				'terms'    => $args['shipping_class'],
			);
		}

		// Custom product taxonomies.
		if ( ! empty( $args['taxonomy'] ) && ! empty( $args['taxonomies'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => sanitize_text_field( $args['taxonomy'] ),
				'field'    => 'term_id',
				'terms'    => $args['taxonomies'],
				'operator' => isset( $args['tax_opts']['operator'] ) ? $args['tax_opts']['operator'] : 'IN',
			);
		}

		if ( ! is_null( $args['parent'] ) ) {
			$wp_query_args['post_parent'] = absint( $args['parent'] );
		}

		if ( ! is_null( $args['offset'] ) ) {
			$wp_query_args['offset'] = absint( $args['offset'] );
		} else {
			$wp_query_args['paged'] = absint( $args['page'] );
		}

		if ( ! empty( $args['include'] ) ) {
			$wp_query_args['post__in'] = array_map( 'absint', $args['include'] );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$wp_query_args['post__not_in'] = array_map( 'absint', $args['exclude'] );
		}

		if ( ! $args['paginate'] ) {
			$wp_query_args['no_found_rows'] = true;
		}

		if ( ! empty( $args['meta_key'] ) ) {
			$wp_query_args['meta_key'] = $args['meta_key'];
		}

		if ( ! empty( $args['post_title'] ) ) {
			$wp_query_args['wccs_post_title'] = $args['post_title'];
		}

		if ( ! empty( $args['post_id'] ) ) {
			$wp_query_args['wccs_post_id'] = $args['post_id'];
		}

		$wp_query_args = apply_filters( 'wccs_products_query', $wp_query_args, $args );

		// Get results.
		$products = new WP_Query( $wp_query_args );

		if ( 'wp_query' === strtolower( $args['return'] ) ) {
			$return = $products;
		} elseif ( 'objects' === $args['return'] ) {
			$return = array_map( 'wc_get_product', $products->posts );
		} else {
			$return = $products->posts;
		}

		if ( $args['paginate'] ) {
			return (object) array(
				'products'      => $return,
				'total'         => $products->found_posts,
				'max_num_pages' => $products->max_num_pages,
			);
		} else {
			return $return;
		}
	}

	public function get_top_rated_products( $limit = 12, $return = 'ids' ) {
		$limit = intval( $limit );

		$products = $this->get_products(
			array(
				'return'     => $return,
				'limit'      => $limit > 0 ? $limit : -1,
				'status'     => 'publish',
				'meta_key'   => '_wc_average_rating',
				'orderby'    => 'meta_value_num',
				'order'      => 'DESC',
				'meta_query' => WC()->query->get_meta_query(),
				'tax_query'  => WC()->query->get_tax_query(),
			)
		);

		return $products;
	}

	public function get_recently_viewed_products( $limit = 12 ) {
		$limit = intval( $limit );

		$viewed_products = ! empty( $_COOKIE['woocommerce_recently_viewed'] ) ? (array) explode( '|', $_COOKIE['woocommerce_recently_viewed'] ) : array();

		if ( empty( $viewed_products ) ) {
			return array();
		}

		$viewed_products = array_reverse( array_filter( array_map( 'absint', $viewed_products ) ) );

		if ( $limit > 0 ) {
			return array_slice( $viewed_products, 0, $limit );
		}

		return $viewed_products;
	}

	public function get_categories( array $args = array() ) {
		$defaults = array(
			'separator'          => '/',
			'nicename'           => false,
			'pad_counts'         => 1,
			'show_count'         => 1,
			'hierarchical'       => 1,
			'hide_empty'         => 0,
			'show_uncategorized' => 0,
			'orderby'            => 'name',
			'menu_order'         => false,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( 'order' === $args['orderby'] ) {
			$args['menu_order'] = 'asc';
			$args['orderby']    = 'name';
		}

		$terms = get_terms( 'product_cat', apply_filters( 'wccs_wc_products_get_categories_args', $args ) );

		if ( empty( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $category ) {
			$categories[] = (object) array(
				'id'   => $category->term_id,
				'text' => rtrim( WCCS_Helpers::get_term_hierarchy_name( $category->term_id, 'product_cat', $args['separator'], $args['nicename'] ), $args['separator'] ),
				'slug' => $category->slug,
				'name' => $category->name,
			);
		}

		return $categories;
	}

	public function get_categories_not_in_list( array $categories ) {
		$all_categories = $this->get_categories();
		$all_categories = ! empty( $all_categories ) ? array_column( $all_categories, 'id' ) : array();
		if ( empty( $all_categories ) ) {
			return array();
		}

		return array_diff( $all_categories, $categories );
	}

	public function get_tags( array $args = array() ) {
		$args = wp_parse_args( $args, array( 'hide_empty' => 0, 'nicename' => false ) );

		$terms = get_terms( 'product_tag', apply_filters( 'wccs_wc_products_get_tags_args', $args ) );

		if ( empty( $terms ) ) {
			return array();
		}

		$ret_terms = array();

		foreach ( $terms as $term ) {
			$ret_terms[] = (object) array(
				'id'   => $term->term_id,
				'text' => $args['nicename'] ? $term->slug : $term->name,
				'slug' => $term->slug,
				'name' => $term->name,
			);
		}

		return $ret_terms;
	}

	public function get_products_have_tags( array $tags, $have = 'at_least_one_of', $return_only_ids = true ) {
		if ( empty( $tags ) ) {
			return array();
		}

		$args = array(
			'status'       => 'publish',
			'tag'          => $tags,
			'tag_tax_opts' => array(
				'field'    => 'term_id',
				'operator' => 'IN',
			),
			'limit'        => -1,
		);

		if ( 'all_of' === $have || 'only' === $have ) {
			$args['tag_tax_opts']['operator'] = 'AND';
		} elseif ( 'none_of' === $have ) {
			$args['tag_tax_opts']['operator'] = 'NOT IN';
		}

		if ( $return_only_ids ) {
			$args['return'] = 'ids';
		}

		$products = $this->get_products( $args );
		if ( empty( $products ) ) {
			return array();
		}

		$product_helpers = WCCS()->product_helpers;

		// Filtering products that have only given tags.
		if ( 'only' === $have ) {
			foreach ( $products as $index => $product ) {
				if ( $return_only_ids ) {
					$product_tags = $product_helpers->wc_get_product_term_ids( $product, 'product_tag' );
				} else {
					$product_tags = $product_helpers->wc_get_product_term_ids( $product->get_id(), 'product_tag' );
				}

				if ( count( $tags ) != count( $product_tags ) ) {
					unset( $products[ $index ] );
				}
			}
		}

		return $products;
	}

	public function get_categories_products( array $categories, $return_only_ids = true ) {
		if ( empty( $categories ) ) {
			return array();
		}

		$all_categories = $this->get_categories();

		$categories_slug = array();

		foreach ( $categories as $category ) {
			foreach ( $all_categories as $cat ) {
				if ( $category == $cat->id ) {
					$categories_slug[] = $cat->slug;
					break;
				}
			}
		}

		if ( empty( $categories_slug ) ) {
			return array();
		}

		$args = array(
			'status'   => 'publish',
			'category' => $categories_slug,
			'limit'    => -1,
		);

		if ( $return_only_ids ) {
			$args['return'] = 'ids';
		}

		$products = $this->get_products( $args );

		if ( empty( $products ) ) {
			return array();
		}

		return $products;
	}

	public function get_attributes_products( array $attributes, $have = 'at_least_one_of', $return_only_ids = true ) {
		if ( empty( $attributes ) ) {
			return array();
		}

		$tax_attributes = array();
		foreach ( $attributes as $value ) {
			if ( empty( $value ) || is_numeric( $value ) ) {
				continue;
			}

			$attribute = explode( ',', $value );
			if (
				2 === count( $attribute ) &&
				! empty( $attribute[0] ) &&
				! empty( $attribute[1] ) &&
				is_numeric( $attribute[1] ) &&
				0 < (int) $attribute[1]
			) {
				if ( ! isset( $tax_attributes[ sanitize_title( $attribute[0] ) ] ) ) {
					$tax_attributes[ sanitize_title( $attribute[0] ) ] = array();
				}
				$tax_attributes[ sanitize_title( $attribute[0] ) ][] = absint( $attribute[1] );
			}
		}
		if ( empty( $tax_attributes ) ) {
			return array();
		}

		$operator = 'IN';
		$relation = 'OR';
		if ( 'all_of' === $have || 'only' === $have ) {
			$operator = $relation = 'AND';
		} elseif ( 'none_of' === $have ) {
			$operator = 'NOT IN';
			$relation = 'AND';
		}

		$tax_query = array();
		foreach ( $tax_attributes as $key => $value ) {
			$tax_query[] = array(
				'taxonomy' => $key,
				'field'    => 'term_id',
				'terms'    => $value,
				'operator' => $operator,
			);
		}

		if ( 1 < count( $tax_query ) ) {
			$tax_query['relation'] = $relation;
		}

		$args = array(
			'status'    => 'publish',
			'tax_query' => array( $tax_query ),
			'return'    => $return_only_ids ? 'ids' : 'objects',
			'limit'     => -1,
		);

		$products = $this->get_products( $args );
		if ( empty( $products ) ) {
			return array();
		}

		if ( 'only' !== $have ) {
			return $products;
		}

		foreach ( $products as $index => $product ) {
			if ( $return_only_ids ) {
				$product_atts = WCCS()->WCCS_Attribute_Helpers->get_product_attributes( $product );
			} else {
				$product_atts = WCCS()->WCCS_Attribute_Helpers->get_product_attributes( $product->get_id() );
			}

			if ( count( $attributes ) != count( $product_atts ) ) {
				unset( $products[ $index ] );
			}
		}

		return $products;
	}

	/**
	 * Get products by specified price value and type.
	 *
	 * @since  2.0.0
	 *
	 * @param  array $args
	 *
	 * @return array
	 */
	public function get_products_by_price( array $args ) {
		$args = wp_parse_args( $args, array(
			'value'      => 0,
			'compare'    => '=',
			'price_type' => '_price',
			'return'     => 'ids',
		) );
		if ( $args['value'] < 0 ) {
			return array();
		}

		if ( ! in_array( $args['price_type'], array( '_price', '_regular_price', '_sale_price' ) ) ) {
			return array();
		}

		$query_args = array(
			'status'     => 'publish',
			'limit'      => -1,
			'return'     => $args['return'],
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => $args['price_type'],
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => $args['price_type'],
					'value'   => false,
					'type'    => 'BOOLEAN',
					'compare' => '!=',
				),
				array(
					'key'     => $args['price_type'],
					'value'   => (float) $args['value'],
					'compare' => $args['compare'],
				),
			)
		);

		return $this->get_products( $query_args );
	}

	/**
	 * Get products by stock quantity value.
	 *
	 * @since  2.0.0
	 *
	 * @param  array $args
	 *
	 * @return array
	 */
	public function get_products_by_stock_quantity( array $args ) {
		$args = wp_parse_args( $args, array(
			'value'   => 0,
			'compare' => '=',
			'return'  => 'ids',
		) );
		if ( $args['value'] < 0 ) {
			return array();
		}

		$query_args = array(
			'status'     => 'publish',
			'limit'      => -1,
			'return'     => $args['return'],
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => '_stock',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => '_stock',
					'value'   => false,
					'type'    => 'BOOLEAN',
					'compare' => '!=',
				),
				array(
					'key'     => '_stock',
					'value'   => (float) $args['value'],
					'compare' => $args['compare'],
				),
			)
		);

		return $this->get_products( $query_args );
	}

	/**
	 * Getting discounted products.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $args
	 *
	 * @return array|string all_products string when all of products discounted.
	 */
	public function get_discounted_products( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'status'          => 'publish',
			'return'          => 'ids',
			'limit'           => -1,
			'include'         => array(),
			'exclude'         => array(),
			'onsale_products' => true,
		) );

		if ( 'ids' === $args['return'] ) {
			$discounted_products = get_transient( 'wccs_discounted_products' );
			if ( false !== $discounted_products ) {
				$discounted_products = array_map( 'WCCS_Helpers::maybe_get_exact_product', $discounted_products );
				if ( ! empty( $args['onsale_products'] ) ) {
					$onsale = wc_get_product_ids_on_sale();
					$onsale = array_map( 'WCCS_Helpers::maybe_get_exact_product', $onsale );
					$discounted_products = ! empty( $onsale ) ? array_merge( $discounted_products, $onsale ) : $discounted_products;
				}
				return $discounted_products;
			}
		}

		$onsale = array();
		if ( ! empty( $args['onsale_products'] ) ) {
			$onsale = wc_get_product_ids_on_sale();
			$onsale = array_map( 'WCCS_Helpers::maybe_get_exact_product', $onsale );
		}

		$wccs_pricing = WCCS()->pricing;
		$pricings     = array();

		if ( ! empty( $args['pricing_type'] ) ) {
			if ( 'simple' === $args['pricing_type'] ) {
				$pricings['simple'] = $wccs_pricing->get_simple_pricings();
			} elseif ( 'bulk' === $args['pricing_type'] ) {
				$pricings['bulk'] = $wccs_pricing->get_bulk_pricings();
			} elseif ( 'tiered' === $args['pricing_type'] ) {
				$pricings['tiered'] = $wccs_pricing->get_tiered_pricings();
			} elseif ( 'purchase' === $args['pricing_type'] ) {
				$pricings['purchase'] = $wccs_pricing->get_purchase_pricings();
			}
		} else {
			$pricings = $wccs_pricing->get_pricings( array( 'simple', 'bulk', 'tiered', 'purchase' ) );
		}

		if ( empty( $pricings['simple'] ) && empty( $pricings['bulk'] ) && empty( $pricings['tiered'] ) && empty( $pricings['purchase'] ) ) {
			return $onsale;
		}

		$product_selector = new WCCS_Discounted_Products_Selector();

		foreach ( $pricings as $type => $discounts ) {
			if ( empty( $discounts ) ) {
				continue;
			}

			foreach ( $discounts as $discount ) {
				if ( empty( $discount['items'] ) ) {
					continue;
				}

				$products = $product_selector->get_products( $discount['items'] );
				if ( empty( $products ) ) {
					continue;
				}

				foreach ( $products as $product_id ) {
					if ( ! empty( $discount['exclude_items'] ) ) {
						if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $discount['exclude_items'], $product_id ) ) {
							$args['include'][] = $product_id;
						}
					} else {
						$args['include'][] = $product_id;
					}
				}
			}
		}

		// Excluding products that are in exclude rules.
		if ( ! empty( $args['include'] ) ) {
			foreach ( $args['include'] as $product_id ) {
				if ( $wccs_pricing->is_in_exclude_rules( $product_id ) ) {
					$args['exclude'][] = $product_id;
				}
			}
		}
		if ( ! empty( $args['include'] ) && ! empty( $args['exclude'] ) ) {
			$args['include'] = array_diff( $args['include'], $args['exclude'] );
			if ( empty( $args['include'] ) ) {
				return $onsale;
			}
		}

		if ( empty( $args['include'] ) && empty( $args['exclude'] ) ) {
			return $onsale;
		}

		$args['include'] = ! empty( $args['include'] ) ? array_map( 'WCCS_Helpers::maybe_get_exact_product', $args['include'] ) : array();
		$args['exclude'] = ! empty( $args['exclude'] ) ? array_map( 'WCCS_Helpers::maybe_get_exact_product', $args['exclude'] ) : array();

		$discounted_products = $this->get_products( $args );

		if ( 'ids' === $args['return'] ) {
			set_transient( 'wccs_discounted_products', $discounted_products, DAY_IN_SECONDS * 30 );
		}

		$discounted_products = ! empty( $onsale ) ? array_merge( $discounted_products, $onsale ) : $discounted_products;

		return $discounted_products;
	}

	/**
     * Get product custom taxonomies.
	 *
	 * @since  5.2.0
	 *
     * @return array|null
     */
    public function get_custom_taxonomies() {
        if ( isset( $this->custom_taxonomies ) ) {
            return $this->custom_taxonomies;
        }

        $taxonomies = get_taxonomies( array(
			'show_ui'      => true,
			'show_in_menu' => true,
			'object_type'  => array( 'product' ),
		), 'objects' );

		$this->custom_taxonomies = array();
		foreach ( (array) $taxonomies as $key => $taxonomy ) {
			if ( ! in_array( $taxonomy->name, array( 'product_cat', 'product_tag' ) ) ) {
				$this->custom_taxonomies[ $key ] = $taxonomy;
			}
		}

		$this->custom_taxonomies = apply_filters( 'wccs_product_helpers_' . __FUNCTION__, $this->custom_taxonomies );

		return $this->custom_taxonomies;
    }

	public function get_product_taxonomies( array $args ) {
		if ( empty( $args['taxonomy'] ) ) {
			return array();
		}

		$args = wp_parse_args( $args, array( 'hide_empty' => 0, 'nicename' => false ) );

		$args['taxonomy'] = sanitize_text_field( $args['taxonomy'] );

		if ( isset( $args['search'] ) ) {
			$args['name__like'] = sanitize_text_field( $args['search'] );
			unset( $args['search'] );
		}

		$terms = get_terms( apply_filters( 'wccs_wc_products_get_' . $args['taxonomy'] . '_args', $args ) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$ret_terms = array();

		foreach ( $terms as $term ) {
			$parent = '';
			if ( ! empty( $term->parent ) ) {
				$parent = get_the_category_by_ID( (int) $term->parent );
				$parent = ! is_wp_error( $parent ) ? $parent .= ' — ' : '';
			}

			$ret_terms[] = (object) array(
				'id'   => $term->term_id,
				'text' => $args['nicename'] ? $parent . $term->slug : $parent . $term->name,
				'slug' => $term->slug,
				'name' => $term->name,
			);
		}

		return $ret_terms;
	}

	public function get_products_have_taxonomies( array $taxonomies, $taxonomy, $have = 'at_least_one_of', $return_only_ids = true ) {
		if ( empty( $taxonomies ) || empty( $taxonomy ) ) {
			return array();
		}

		$args = array(
			'status'     => 'publish',
			'taxonomy'   => $taxonomy,
			'taxonomies' => $taxonomies,
			'tax_opts' => array(
				'field'    => 'term_id',
				'operator' => 'IN',
			),
			'limit'     => -1,
		);

		if ( 'all_of' === $have || 'only' === $have ) {
			$args['tax_opts']['operator'] = 'AND';
		} elseif ( 'none_of' === $have ) {
			$args['tax_opts']['operator'] = 'NOT IN';
		}

		if ( $return_only_ids ) {
			$args['return'] = 'ids';
		}

		$products = $this->get_products( $args );
		if ( empty( $products ) ) {
			return array();
		}

		$product_helpers = WCCS()->product_helpers;

		// Filtering products that have only given taxonomies.
		if ( 'only' === $have ) {
			foreach ( $products as $index => $product ) {
				if ( $return_only_ids ) {
					$product_taxonomies = $product_helpers->wc_get_product_term_ids( $product, $taxonomy );
				} else {
					$product_taxonomies = $product_helpers->wc_get_product_term_ids( $product->get_id(), $taxonomy );
				}

				if ( count( $taxonomies ) != count( $product_taxonomies ) ) {
					unset( $products[ $index ] );
				}
			}
		}

		return $products;
	}

}

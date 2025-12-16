<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Product_Helpers {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WCCS_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Constructor.
	 *
	 * @since  1.0.0
	 *
	 * @param  WCCS_Loader $loader
	 */
	public function __construct( WCCS_Loader $loader ) {
		$this->loader = $loader;
	}

	/**
	 * Defining hooks related to product.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function define_hooks() {
		$this->loader->add_action( 'woocommerce_delete_product_transients', $this, 'delete_product_transients' );
		$this->loader->add_filter( 'woocommerce_available_variation', $this, 'woocommerce_available_variation', 10, 3 );
	}

	/**
	 * Get product parent id.
	 *
	 * @since  1.0.0
	 *
	 * @param  WC_Product $product
	 *
	 * @return int
	 */
	public function get_parent_id( WC_Product $product ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return $product->get_parent_id();
		}

		return $product->get_parent();
	}

	/**
	 * Retrieves product term ids for a taxonomy.
	 *
	 * @since  1.0.0
	 *
	 * @param  int    $product_id Product ID.
	 * @param  string $taxonomy   Taxonomy slug.
	 *
	 * @return array
	 */
	public function wc_get_product_term_ids( $product_id, $taxonomy ) {
		if ( WCCS_Helpers::wc_version_check() ) {
			return wc_get_product_term_ids( $product_id, $taxonomy );
		}

		$terms = get_the_terms( $product_id, $taxonomy );
		return ( empty( $terms ) || is_wp_error( $terms ) ) ? array() : wp_list_pluck( $terms, 'term_id' );
	}

	/**
	 * Returns the price including or excluding tax, based on the 'woocommerce_tax_display_shop' setting.
	 *
	 * @since  1.0.0
	 *
	 * @param  WC_Product|int $product
	 * @param  array          $args
	 * @param  boolean        $wc_price if true will return product main price without plugin applied discounts on it.
	 *
	 * @return float
	 */
	public function wc_get_price_to_display( $product, $args = array(), $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		}

		if ( WCCS_Helpers::wc_version_check() ) {
			$price_to_display = (float) wc_get_price_to_display( $product, $args );
			if ( true === $wc_price ) {
				// Enable plugin price replacer hooks.
				WCCS()->WCCS_Product_Price_Replace->enable_hooks();
			}

			return apply_filters( 'wccs_wc_get_price_to_display', $price_to_display, $product, $args, $wc_price );
		}

		$args = wp_parse_args( $args, array(
			'qty'   => 1,
			'price' => $product->get_price(),
		) );

		$price_to_display = (float) $product->get_display_price( $args['price'], $args['qty'] );
		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		}

		return apply_filters( 'wccs_wc_get_price_to_display', $price_to_display, $product, $args, $wc_price );
	}

	/**
	 * Returns product price.
	 *
	 * @since  3.0.0
	 *
	 * @param  WC_Product|int $product
	 * @param  boolean        $wc_price if true will return product main price without plugin applied discounts on it.
	 *
	 * @return float
	 */
	public function wc_get_price( $product, $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		}

		$price = $product->get_price();

		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		}

		return $price;
	}

	/**
	 * Returns product regular price.
	 *
	 * @since  3.0.0
	 *
	 * @param  WC_Product|int $product
	 * @param  boolean        $wc_price if true will return product main price without plugin applied discounts on it.
	 *
	 * @return float
	 */
	public function wc_get_regular_price( $product, $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		}

		$regular_price = $product->get_regular_price();

		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		}

		return $regular_price;
	}

	/**
	 * Returns product sale price.
	 *
	 * @since  3.0.0
	 *
	 * @param  WC_Product|int $product
	 * @param  boolean        $wc_price if true will return product main price without plugin applied discounts on it.
	 *
	 * @return float
	 */
	public function wc_get_sale_price( $product, $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		}

		$sale_price = $product->get_sale_price();

		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		}

		return $sale_price;
	}

	public function wc_get_price_html( $product, $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
			WCCS()->WCCS_Public_Pricing_Hooks->disable_price_hooks();
		}

		$price_html = $product->get_price_html();

		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
			WCCS()->WCCS_Public_Pricing_Hooks->enable_price_hooks();
		}

		return $price_html;
	}

	public function wc_get_variation_prices( $product, $for_display = false, $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		}

		$variation_prices = $product->get_variation_prices( $for_display );

		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		}

		return $variation_prices;
	}

	/**
	 * Get related products based on product category and tags.
	 *
	 * @since  1.0.0
	 *
	 * @param  int   $product_id  Product ID.
	 * @param  int   $limit       Limit of results.
	 * @param  array $exclude_ids Exclude IDs from the results.
	 *
	 * @return array
	 */
	public function get_related_products( $product_id, $limit = 5, $exclude_ids = array() ) {
		$product_id     = absint( $product_id );
		$exclude_ids    = array_map( 'absint', array_merge( array( 0, $product_id ), $exclude_ids ) );
		$transient_name = 'wccs_related_' . $product_id;
		$related_posts  = get_transient( $transient_name );
		$limit          = $limit > 0 ? $limit : 0;

		if ( false === $related_posts || ( $limit && count( $related_posts ) < $limit ) ) {
			$cats_array = apply_filters( 'woocommerce_conditions_product_related_posts_relate_by_category', true, $product_id ) ? apply_filters( 'woocommerce_conditions_get_related_product_cat_terms', $this->wc_get_product_term_ids( $product_id, 'product_cat' ), $product_id ) : array();
			$tags_array = apply_filters( 'woocommerce_conditions_product_related_posts_relate_by_tag', true, $product_id ) ? apply_filters( 'woocommerce_conditions_get_related_product_tag_terms', $this->wc_get_product_term_ids( $product_id, 'product_tag' ), $product_id ) : array();

			// Don't bother if none are set, unless woocommerce_conditions_product_related_posts_force_display is set to true in which case all products are related.
			if ( empty( $cats_array ) && empty( $tags_array ) && ! apply_filters( 'woocommerce_conditions_product_related_posts_force_display', false, $product_id ) ) {
				$related_posts = array();
			} else {
				if ( WCCS_Helpers::wc_version_check() ) {
					$data_store    = WC_Data_Store::load( 'product' );
					$related_posts = $data_store->get_related_products( $cats_array, $tags_array, $exclude_ids, ( $limit > 0 ? $limit + 10 : 9999999 ), $product_id );
				} else {
					global $wpdb;

					$product = wc_get_product( $product_id );

					// Generate query - but query an extra 10 results to give the appearance of random results
					$query = $product->build_related_query( $cats_array, $tags_array, $exclude_ids, ( $limit > 0 ? $limit + 10 : 9999999 ) );

					// Get the posts
					$related_posts = $wpdb->get_col( implode( ' ', $query ) );
				}
			}

			set_transient( $transient_name, $related_posts, DAY_IN_SECONDS );
		}

		shuffle( $related_posts );

		if ( $limit ) {
			return array_slice( $related_posts, 0, $limit );
		}

		return $related_posts;
	}

	/**
	 * Clear all transients cache for product data.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $post_id
	 *
	 * @return void
	 */
	public function delete_product_transients( $post_id ) {
		$transients_to_clear = apply_filters( 'wccs_product_transients_to_clear', array() );

		// Transient names that include an ID
		$post_transient_names = apply_filters( 'wccs_product_post_transient_names', array(
			'wccs_related_',
		) );

		if ( $post_id > 0 ) {
			foreach ( $post_transient_names as $transient ) {
				$transients_to_clear[] = $transient . $post_id;
			}
		}

		// Delete transients.
		foreach ( $transients_to_clear as $transient ) {
			delete_transient( $transient );
		}
	}

	/**
	 * Returns whether or not the product is on sale.
	 *
	 * @since  1.0.0
	 *
	 * @param  WC_Product|int $product What the value is for. Valid values are view and edit.
	 * @param  string         $context
	 *
	 * @return boolean
	 */
	public function is_on_sale( $product, $context = 'view' ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( WCCS_Helpers::wc_version_check() ) {
			return $product->is_on_sale( $context );
		}

		if ( '' !== (string) $this->wc_get_sale_price( $product ) && $this->wc_get_regular_price( $product ) > $this->wc_get_sale_price( $product ) ) {
			$on_sale = true;

			if ( $product->sale_price_dates_from && strtotime( date( 'Y-m-d', $product->sale_price_dates_from ) ) > current_time( 'timestamp', true ) ) {
				$on_sale = false;
			}

			if ( $product->sale_price_dates_to && strtotime( date( 'Y-m-d', $product->sale_price_dates_to ) ) < current_time( 'timestamp', true ) ) {
				$on_sale = false;
			}
		} else {
			$on_sale = false;
		}
		return 'view' === $context ? apply_filters( 'woocommerce_product_is_on_sale', $on_sale, $product ) : $on_sale;
	}

	/**
	 * Filter hook to filtering woocommerce_available_variation data.
	 *
	 * @since  1.0.0
	 *
	 * @param  array      $data
	 * @param  WC_Product $variable
	 * @param  WC_Product $variation
	 *
	 * @return array
	 */
	public function woocommerce_available_variation( $data, $variable, $variation ) {
		if ( ! isset( $data['wccs_is_on_sale'] ) ) {
			$data['wccs_is_on_sale'] = $this->is_on_sale( $variation, 'edit' );
		}

		return $data;
	}

	/**
	 * For a given product, and optionally price/qty, work out the price with tax included, based on store settings.
	 *
	 * @since  2.2.4
	 *
	 * @param  WC_Product|int $product WC_Product object.
	 * @param  array          $args Optional arguments to pass product quantity and price.
	 * @param  boolean        $wc_price if true will return product main price without plugin applied discounts on it.
	 *
	 * @return float
	 */
	public function wc_get_price_including_tax( $product, $args = array(), $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		}

		if ( function_exists( 'wc_get_price_including_tax' ) ) {
			$price_including_tax = wc_get_price_including_tax( $product, $args );
			if ( true === $wc_price ) {
				// Enable plugin price replacer hooks.
				WCCS()->WCCS_Product_Price_Replace->enable_hooks();
			}
			return $price_including_tax;
		}

		$args = wp_parse_args(
			$args, array(
				'qty'   => 1,
				'price' => '',
			)
		);

		$price_including_tax = (float) $product->get_price_including_tax( $args['qty'], $args['price'] );
		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		}
		return $price_including_tax;
	}

	/**
	 * For a given product, and optionally price/qty, work out the price with tax excluded, based on store settings.
	 *
	 * @since  2.2.4
	 *
	 * @param  WC_Product|int $product WC_Product object.
	 * @param  array          $args Optional arguments to pass product quantity and price.
	 * @param  boolean        $wc_price if true will return product main price without plugin applied discounts on it.
	 *
	 * @return float
	 */
	public function wc_get_price_excluding_tax( $product, $args = array(), $wc_price = true ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( true === $wc_price ) {
			// Disable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		}

		if ( function_exists( 'wc_get_price_excluding_tax' ) ) {
			$price_excluding_tax = wc_get_price_excluding_tax( $product, $args );
			if ( true === $wc_price ) {
				// Enable plugin price replacer hooks.
				WCCS()->WCCS_Product_Price_Replace->enable_hooks();
			}
			return $price_excluding_tax;
		}

		$args = wp_parse_args(
			$args, array(
				'qty'   => 1,
				'price' => '',
			)
		);

		$price_excluding_tax = (float) $product->get_price_excluding_tax( $args['qty'], $args['price'] );
		if ( true === $wc_price ) {
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		}
		return $price_excluding_tax;
	}

	/**
	 * Get a product available variations.
	 *
	 * @since  3.4.0
	 *
	 * @param  WC_Product|int $product
	 *
	 * @return array
	 */
	public function get_available_variations( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product || 'variable' !== $product->get_type() ) {
			return array();
		}

		// Disable plugin price replacer hooks to get variations main price.
		WCCS()->WCCS_Product_Price_Replace->disable_hooks();
		add_filter( 'woocommerce_show_variation_price', '__return_false', 100 );
		$variations = $product->get_available_variations();
		// Enable plugin price replacer hooks.
		WCCS()->WCCS_Product_Price_Replace->enable_hooks();
		remove_filter( 'woocommerce_show_variation_price', '__return_false', 100 );

		return $variations;
	}

	/**
	 * Get product categories based on given items.
	 * Product categories should match in the given items categories.
	 *
	 * @since  3.4.0
	 *
	 * @param  int   $product_id
	 * @param  array $items
	 *
	 * @return array
	 */
	public function get_product_categories( $product_id, $items = array() ) {
		if ( ! $product_id ) {
			return array();
		}

		$product_categories = array_unique( wc_get_product_cat_ids( $product_id ) );

		if ( empty( $product_categories ) || empty( $items ) ) {
			return $product_categories;
		}

		$method = '';
		foreach ( $items as $group ) {
			if ( is_array( $group ) && ! isset( $group['item'] ) && ! isset( $group['condition'] ) ) {
				foreach ( $group as $item ) {
					$method = isset( $item['item'] ) ? $item['item'] : $item['condition'];
					if ( 'categories_in_list' === $method && ! empty( $item['categories'] ) ) {
						$product_categories = array_intersect( $product_categories, $item['categories'] );
					} elseif ( 'categories_not_in_list' === $method && ! empty( $item['categories'] ) ) {
						$product_categories = array_diff( $product_categories, $item['categories'] );
					}
				}
			} else {
				$method = isset( $group['item'] ) ? $group['item'] : $group['condition'];
				if ( 'categories_in_list' === $method && ! empty( $group['categories'] ) ) {
					$product_categories = array_intersect( $product_categories, $group['categories'] );
				} elseif ( 'categories_not_in_list' === $method && ! empty( $group['categories'] ) ) {
					$product_categories = array_diff( $product_categories, $group['categories'] );
				}
			}
		}

		return $product_categories;
	}

	public function get_percentage_badge_value( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			$prices         = $product->get_variation_prices();
			$max_percentage = 0;
			foreach( $prices['price'] as $key => $price ) {
				// Only on sale variations
				if ( $prices['regular_price'][ $key ] > $price ) {
					$percentage = ( floatval( $prices['regular_price'][ $key ] ) - floatval( $price ) ) / floatval( $prices['regular_price'][ $key ] ) * 100;
					if ( $percentage > $max_percentage ) {
						$max_percentage = $percentage;
					}
				}
			}
			if ( 0 < $max_percentage ) {
				return $max_percentage;
			}
		} else {
			$regular_price = $product->get_regular_price();
			$sale_price    = $product->get_sale_price();
			if ( '' !== $sale_price && $sale_price < $regular_price ) {
				return ( floatval( $regular_price ) - floatval( $sale_price ) ) / floatval( $regular_price ) * 100;
			}
		}

		return false;
	}

	public function is_variable_product( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product ) {
			return false;
		}

		$type = $product->get_type();

		return 'variable' === $type || false !== strpos( $type, 'variable' );
	}

	public function is_variation_product( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product ) {
			return false;
		}

		$type = $product->get_type();

		return 'variation' === $type || false !== strpos( $type, 'variation' );
	}

}

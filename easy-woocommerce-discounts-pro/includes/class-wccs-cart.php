<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Cart {

	public $cart;

	public function __construct( $cart = null ) {
		$this->cart = null !== $cart ? $cart : WC()->cart;
	}

	public function __get( $key ) {
		if ( property_exists( $this, $key ) ) {
			return $this->$key;
		} elseif ( $this->cart_initialized() ) {
			return $this->cart->$key;
		}

		return null;
	}

	public function __call( $name, $arguments ) {
		if ( method_exists( $this, $name ) ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		} elseif ( $this->cart_initialized() && is_callable( array( $this->cart, $name ) ) ) {
			return call_user_func_array( array( $this->cart, $name ), $arguments );
		}
	}

	public function cart_initialized( $force_init_cart = true ) {
		if ( isset( $this->cart ) ) {
			return true;
		} elseif ( $force_init_cart && isset( WC()->cart ) ) {
			$this->cart = WC()->cart;
			return true;
		}

		return false;
	}

	public function get_cart() {
		return $this->cart_initialized() ? $this->cart->get_cart() : array();
	}

	/**
	 * Return whether or not the cart is displaying prices including tax, rather than excluding tax.
	 *
	 * @since  2.2.4
	 *
	 * @return bool
	 */
	public function display_prices_including_tax() {
		if ( ! $this->cart_initialized() ) {
			return false;
		}

		if ( is_callable( array( $this->cart, 'display_prices_including_tax' ) ) ) {
			return $this->cart->display_prices_including_tax();
		}

		return apply_filters( 'woocommerce_cart_' . __FUNCTION__, 'incl' === $this->cart->tax_display_cart );
	}

	public function get_product_price( $product, $args = array() ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( $this->display_prices_including_tax() ) {
			$product_price = wc_get_price_including_tax( $product, $args );
		} else {
			$product_price = wc_get_price_excluding_tax( $product, $args );
		}
		return apply_filters( 'woocommerce_cart_product_price', wc_price( $product_price ), $product );
	}

	public function get_products() {
		if ( ! $this->cart_initialized() ) {
			return array();
		}

		$cart_contents = $this->get_cart();

		if ( empty( $cart_contents ) ) {
			return array();
		}

		$cart_products = array();

		foreach ( $cart_contents as $cart_item ) {
			if ( isset( $cart_item['product_id'] ) ) {
				$cart_products[] = $cart_item['product_id'];
			} elseif ( ! empty( $cart_item['data'] ) ) {
				$cart_products[] = $cart_item['data']->get_id();
			}
		}

		return $cart_products;
	}

	public function products_exists_in_cart( array $products, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $products ) ) {
			return true;
		}

		if ( ! $this->cart_initialized() ) {
			return false;
		}

		return WCCS_Cart_Items_Helpers::products_exists_in_items(
			$this->get_cart(),
			$products,
			$type,
			$number
		);
	}

	public function categories_exists_in_cart( array $categories, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $categories ) ) {
			return true;
		}

		if ( ! $this->cart_initialized() ) {
			return false;
		}

		return WCCS_Cart_Items_Helpers::categories_exists_in_items(
			$this->get_cart(),
			$categories,
			$type,
			$number
		);
	}

	/**
	 * Is given attributes terms exists in the cart.
	 *
	 * @since  2.0.0
	 *
	 * @param  array  $attributes_terms
	 * @param  string $type
	 *
	 * @return boolean
	 */
	public function attributes_terms_exists_in_cart( array $attributes_terms, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $attributes_terms ) ) {
			return true;
		}

		if ( ! $this->cart_initialized() ) {
			return false;
		}

		return WCCS_Cart_Items_Helpers::attributes_terms_exists_in_items(
			$this->get_cart(),
			$attributes_terms,
			$type,
			$number
		);
	}

	public function tags_exists_in_cart( array $tags, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $tags ) ) {
			return true;
		}

		if ( ! $this->cart_initialized() ) {
			return false;
		}

		return WCCS_Cart_Items_Helpers::tags_exists_in_items(
			$this->get_cart(),
			$tags,
			$type,
			$number
		);
	}

	public function taxonomies_exists_in_cart( array $taxonomies, $taxonomy, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $taxonomies ) || empty( $taxonomy ) ) {
			return true;
		}

		if ( ! $this->cart_initialized() ) {
			return false;
		}

		return WCCS_Cart_Items_Helpers::taxonomies_exists_in_items(
			$this->get_cart(),
			$taxonomies,
			$taxonomy,
			$type,
			$number
		);
	}

	public function shipping_classes_exists_in_cart( array $classes, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $classes ) ) {
			return true;
		}

		if ( ! $this->cart_initialized() ) {
			return false;
		}

		return WCCS_Cart_Items_Helpers::shipping_classes_exists_in_items(
			$this->get_cart(),
			$classes,
			$type,
			$number
		);
	}

	/**
	 * Get items quantities.
	 *
	 * @param array   $items
	 * @param string  $quantity_based_on
	 * @param boolean $exclude_excluded_products
	 * @param string  $sort                      Possible value is 'price'
	 * @param string  $order                     Possible values are 'asc', 'desc'
	 * @param array   $exclude_items
	 *
	 * @return array
	 */
	public function get_items_quantities(
		array $items,
		$quantity_based_on = 'single_product',
		$exclude_excluded_products = false,
		$sort = '',
		$order = 'desc',
		array $exclude_items = array(),
		$filter_auto_added_products = false
	) {
		if ( empty( $items ) ) {
			return array();
		}

		$cart_items = $this->filter_cart_items( $items, $exclude_excluded_products, $exclude_items, $filter_auto_added_products );
		if ( empty( $cart_items ) ) {
			return array();
		}

		return $this->get_cart_quantities_based_on( $quantity_based_on, $cart_items, $sort, $order );
	}

	public function get_regular_products_subtotal( $include_tax = true ) {
		$subtotal = $this->get_items_subtotal(
			array(
				'item'  => 'onsale_products',
				'limit' => -1,
			),
			$include_tax
		);
		return apply_filters( 'wccs_cart_' . __FUNCTION__, $subtotal, $include_tax );
	}

	public function get_onsale_products_subtotal( $include_tax = true ) {
		$subtotal = $this->get_items_subtotal(
			array(
				'item'  => 'regular_products',
				'limit' => -1,
			),
			$include_tax
		);
		return apply_filters( 'wccs_cart_' . __FUNCTION__, $subtotal, $include_tax );
	}

	/**
	 * Get items subtotal.
	 *
	 * @since  2.5.0
	 *
	 * @param  array   $items
	 * @param  boolean $include_tax
	 * @param  boolean $exclude_excluded_products
	 *
	 * @return float
	 */
	public function get_items_subtotal( array $items, $include_tax = true, $exclude_excluded_products = false ) {
		if ( empty( $items ) ) {
			return 0;
		}

		$cart_items = $this->filter_cart_items( $items, $exclude_excluded_products );
		if ( empty( $cart_items ) ) {
			return 0;
		}

		$subtotal = 0;
		foreach ( $cart_items as $cart_item ) {
			$subtotal += $include_tax ? $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] : $cart_item['line_subtotal'];
		}

		return apply_filters( 'wccs_cart_' . __FUNCTION__, (float) $subtotal, $items, $include_tax, $exclude_excluded_products );
	}

	public function filter_cart_items(
		array $items,
		$exclude_excluded_products = false,
		array $exclude_items = array(),
		$filter_auto_added_products = false
	) {
		if ( ! $this->cart_initialized() || empty( $items ) ) {
			return array();
		}

		$cart_contents = $this->get_cart();
		if ( empty( $cart_contents ) ) {
			return array();
		}

		$cart_items = array();
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			if ( $filter_auto_added_products && WCCS_Helpers::is_auto_added_product( $cart_item ) ) {
				continue;
			}

			$product   = $cart_item['data'];
			$variation = (int) $cart_item['variation_id'];
			if ( 0 < $variation ) {
				$product   = (int) $cart_item['product_id'];
				$variation = $cart_item['data'];
			}

			if ( WCCS()->WCCS_Product_Validator->is_valid_product( $items, $product, $variation, ( ! empty( $cart_item['variation'] ) ? $cart_item['variation'] : array() ), $cart_item ) ) {
				if ( empty( $exclude_items ) || ! WCCS()->WCCS_Product_Validator->is_valid_product( $exclude_items, $product, $variation, ( ! empty( $cart_item['variation'] ) ? $cart_item['variation'] : array() ), $cart_item ) ) {
					if ( ! $exclude_excluded_products || ! WCCS()->pricing->is_in_exclude_rules( $product, $variation, ( ! empty( $cart_item['variation'] ) ? $cart_item['variation'] : array() ) ) ) {
						$cart_items[ $cart_item_key ] = $cart_item;
					}
				}
			}
		}

		return $cart_items;
	}

	/**
	 * Sort cart items.
	 *
	 * @param  null|array $cart_items
	 * @param  string     $sort
	 * @param  string     $order
	 *
	 * @return array
	 */
	public function sort_cart_items( $cart_items = null, $sort = 'price', $order = 'asc' ) {
		if ( null === $cart_items && $this->cart_initialized() ) {
			$cart_items = $this->get_cart();
		}

		if ( empty( $cart_items ) ) {
			return $cart_items;
		}

		switch ( $sort ) {
			case 'price':
				// Setting cart item price.
				foreach ( $cart_items as $cart_item_key => &$cart_item ) {
					if ( isset( $cart_item['_wccs_main_price'] ) ) {
						$cart_item['item_price'] = (float) $cart_item['_wccs_main_price'];
					} else {
						$cart_item['item_price'] = (float) WCCS()->product_helpers->wc_get_price( $cart_item['data'] );
					}
				}
				unset( $cart_item );

				if ( 'asc' === strtolower( $order ) ) {
					uasort( $cart_items, array( WCCS()->WCCS_Sorting, 'sort_by_item_price_asc' ) );
				} else {
					uasort( $cart_items, array( WCCS()->WCCS_Sorting, 'sort_by_item_price_desc' ) );
				}

				break;
		}

		return $cart_items;
	}

	/**
	 * Getting products quantities in the cart.
	 *
	 * @since  1.0.0
	 *
	 * @param  $include                   array   An array of included items. product ids or array( 'all_products' ) or array( 'all_categories' ) to getting all cart items quantities.
	 * @param  $exclude                   array   An array of excluded items. product ids
	 * @param  $quantity_based_on         string
	 * @param  $exclude_excluded_products boolean Exclude products that are excluded by pricing exclude rules.
	 *
	 * @return array
	 */
	public function get_products_quantities( array $include, array $exclude = array(), $quantity_based_on = 'single_product', $exclude_excluded_products = false ) {
		if ( empty( $include ) && empty( $exclude ) ) {
			return array();
		}

		$cart_items = $this->filter_cart_products( $include, $exclude, $exclude_excluded_products );
		if ( empty( $cart_items ) ) {
			return array();
		}

		return $this->get_cart_quantities_based_on( $quantity_based_on, $cart_items );
	}

	/**
	 * Filtering cart items based on given products.
	 *
	 * @since  1.0.0
	 *
	 * @param  $include                   array   An array of included items. product ids or array( 'all_products' ) or array( 'all_categories' ) to getting all cart items quantities.
	 * @param  $exclude                   array   An array of excluded items. product ids
	 * @param  $exclude_excluded_products boolean Exclude products that are excluded by pricing exclude rules.
	 *
	 * @return array
	 */
	public function filter_cart_products( array $include, array $exclude = array(), $exclude_excluded_products = false ) {
		if ( empty( $include ) && empty( $exclude ) ) {
			return array();
		} elseif ( ! $this->cart_initialized() ) {
			return array();
		}

		$cart_contents = $this->get_cart();
		if ( empty( $cart_contents ) ) {
			return array();
		}

		$pricing          = WCCS()->pricing;
		$valid_cart_items = array();

		if ( array( 'all_products' ) === $include || array( 'all_categories' ) === $include ) {
			if ( ! $exclude_excluded_products && empty( $exclude ) ) {
				return $cart_contents;
			}
		}

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			if ( ! empty( $include ) ) {
				if ( isset( $cart_item['product_id'] ) && ! isset( $valid_cart_items[ $cart_item_key ] ) ) {
					if ( array( 'all_products' ) === $include || array( 'all_categories' ) === $include || in_array( $cart_item['product_id'], $include ) ) {
						if ( $exclude_excluded_products && ! $pricing->is_in_exclude_rules( $cart_item['product_id'], $cart_item['variation_id'], ( ! empty( $cart_item['variation'] ) ? $cart_item['variation'] : array() ) ) ) {
							if ( empty( $exclude ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							} elseif ( ! in_array( $cart_item['product_id'], $exclude ) && ( empty( $cart_item['variation_id'] ) || ! in_array( $cart_item['variation_id'], $exclude ) ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							}
						} elseif ( ! $exclude_excluded_products ) {
							if ( empty( $exclude ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							} elseif ( ! in_array( $cart_item['product_id'], $exclude ) && ( empty( $cart_item['variation_id'] ) || ! in_array( $cart_item['variation_id'], $exclude ) ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							}
						}
					}
				}

				if ( ! empty( $cart_item['variation_id'] ) && ! isset( $valid_cart_items[ $cart_item_key ] ) ) {
					if ( array( 'all_products' ) === $include || array( 'all_categories' ) === $include || in_array( $cart_item['variation_id'], $include ) ) {
						if ( $exclude_excluded_products && ! $pricing->is_in_exclude_rules( $cart_item['product_id'], $cart_item['variation_id'], ( ! empty( $cart_item['variation'] ) ? $cart_item['variation'] : array() ) ) ) {
							if ( empty( $exclude ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							} elseif ( ! in_array( $cart_item['product_id'], $exclude ) && ( empty( $cart_item['variation_id'] ) || ! in_array( $cart_item['variation_id'], $exclude ) ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							}
						} elseif ( ! $exclude_excluded_products ) {
							if ( empty( $exclude ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							} elseif ( ! in_array( $cart_item['product_id'], $exclude ) && ( empty( $cart_item['variation_id'] ) || ! in_array( $cart_item['variation_id'], $exclude ) ) ) {
								$valid_cart_items[ $cart_item_key ] = $cart_item;
							}
						}
					}
				}
			} elseif ( ! empty( $exclude ) ) {
				if ( ! in_array( $cart_item['product_id'], $exclude ) && ( empty( $cart_item['variation_id'] ) || ! in_array( $cart_item['variation_id'], $exclude ) ) ) {
					if ( $exclude_excluded_products && ! $pricing->is_in_exclude_rules( $cart_item['product_id'], $cart_item['variation_id'], ( ! empty( $cart_item['variation'] ) ? $cart_item['variation'] : array() ) ) ) {
						$valid_cart_items[ $cart_item_key ] = $cart_item;
					} elseif ( ! $exclude_excluded_products ) {
						$valid_cart_items[ $cart_item_key ] = $cart_item;
					}
				}
			}
		}

		return $valid_cart_items;
	}

	/**
	 * Getting cart items quantities based on given type.
	 *
	 * @since  2.0.0
	 *
	 * @param  string     $quantity_based_on
	 * @param  array|null $cart_items        When it is null it use default cart items.
	 * @param  string     $sort              Possible values is price
	 * @param  string     $order             Possible values are asc, desc
	 * @param  boolean    $include_hierarchy Include hierarchy is usefull when items has hierarchy like product categories.
	 *
	 * @return array
	 */
	public function get_cart_quantities_based_on(
		$quantity_based_on = 'single_product',
		array $cart_items = null,
		$sort = '',
		$order = 'desc',
		$include_hierarchy = false
	) {
		if ( empty( $quantity_based_on ) ) {
			return array();
		} elseif ( ! $this->cart_initialized() ) {
			return array();
		}

		$cart_items = null !== $cart_items ? $cart_items : $this->get_cart();
		if ( empty( $cart_items ) ) {
			return array();
		}

		if ( ! empty( $sort ) && ! empty( $order ) ) {
			$cart_items = $this->sort_cart_items( $cart_items, $sort, $order );
		}

		$cart_quantities = array();

		switch ( $quantity_based_on ) {
			case 'single_product' :
				foreach ( $cart_items as $cart_item_key => $cart_item ) {
					if ( ! isset( $cart_quantities[ $cart_item['product_id'] ] ) ) {
						$cart_quantities[ $cart_item['product_id'] ] = array(
							'count' => 0,
							'items' => array()
						);
					}

					$cart_quantities[ $cart_item['product_id'] ]['count'] += $cart_item['quantity'];
					$cart_quantities[ $cart_item['product_id'] ]['items'][ $cart_item_key ] = $cart_item['quantity'];
				}
				break;

			case 'single_product_variation' :
				foreach ( $cart_items as $cart_item_key => $cart_item ) {
					if ( ! empty( $cart_item['variation_id'] ) ) {
						if ( ! isset( $cart_quantities[ $cart_item['variation_id'] ] ) ) {
							$cart_quantities[ $cart_item['variation_id'] ] = array(
								'count' => 0,
								'items' => array()
							);
						}

						$cart_quantities[ $cart_item['variation_id'] ]['count'] += $cart_item['quantity'];
						$cart_quantities[ $cart_item['variation_id'] ]['items'][ $cart_item_key ] = $cart_item['quantity'];
					} else {
						if ( ! isset( $cart_quantities[ $cart_item['product_id'] ] ) ) {
							$cart_quantities[ $cart_item['product_id'] ] = array(
								'count' => 0,
								'items' => array()
							);
						}

						$cart_quantities[ $cart_item['product_id'] ]['count'] += $cart_item['quantity'];
						$cart_quantities[ $cart_item['product_id'] ]['items'][ $cart_item_key ] = $cart_item['quantity'];
					}
				}
				break;

			case 'cart_line_item' :
				foreach ( $cart_items as $cart_item_key => $cart_item ) {
					if ( ! isset( $cart_quantities[ $cart_item_key ] ) ) {
						$cart_quantities[ $cart_item_key ] = array(
							'count' => 0,
							'items' => array()
						);
					}

					$cart_quantities[ $cart_item_key ]['count'] += $cart_item['quantity'];
					$cart_quantities[ $cart_item_key ]['items'][ $cart_item_key ] = $cart_item['quantity'];
				}
				break;

			case 'category' :
				foreach ( $cart_items as $cart_item_key => $cart_item ) {
					$categories = array_unique( $include_hierarchy ? wc_get_product_cat_ids( $cart_item['product_id'] ) : wc_get_product_term_ids( $cart_item['product_id'], 'product_cat' ) );
					if ( ! empty( $categories ) ) {
						foreach ( $categories as $category ) {
							if ( ! isset( $cart_quantities[ $category ] ) ) {
								$cart_quantities[ $category ] = array(
									'count' => 0,
									'items' => array()
								);
							}

							$cart_quantities[ $category ]['count'] += $cart_item['quantity'];
							$cart_quantities[ $category ]['items'][ $cart_item_key ] = $cart_item['quantity'];
						}
					}
				}
				break;

			case 'attribute' :
				foreach ( $cart_items as $cart_item_key => $cart_item ) {
					$simple_attributes = WCCS()->WCCS_Attribute_Helpers->get_product_simple_attributes( $cart_item['data'] );
					if ( ! empty( $simple_attributes ) ) {
						foreach ( $simple_attributes as $attribute_id ) {
							if ( ! isset( $cart_quantities[ $attribute_id ] ) ) {
								$cart_quantities[ $attribute_id ] = array(
									'count' => 0,
									'items' => array()
								);
							}
							$cart_quantities[ $attribute_id ]['count'] += $cart_item['quantity'];
							$cart_quantities[ $attribute_id ]['items'][ $cart_item_key ] = $cart_item['quantity'];
						}
					}

					if ( empty( $cart_item['variation_id'] ) || empty( $cart_item['variation'] ) ) {
						continue;
					}

					foreach ( $cart_item['variation'] as $key => $value ) {
						if ( 0 === strpos( $key, 'attribute_' ) ) {
							$term = get_term_by( 'slug', $value, str_replace( 'attribute_', '', $key ) );
							if ( ! is_wp_error( $term ) && is_object( $term ) && $term->term_id ) {
								if ( ! isset( $cart_quantities[ $term->term_id ] ) ) {
									$cart_quantities[ $term->term_id ] = array(
										'count' => 0,
										'items' => array()
									);
								}

								$cart_quantities[ $term->term_id ]['count'] += $cart_item['quantity'];
								$cart_quantities[ $term->term_id ]['items'][ $cart_item_key ] = $cart_item['quantity'];
							}
						}
					}
				}
				break;

			case 'tag' :
				foreach ( $cart_items as $cart_item_key => $cart_item ) {
					$tags = array_unique( WCCS()->product_helpers->wc_get_product_term_ids( $cart_item['product_id'], 'product_tag' ) );
					if ( ! empty( $tags ) ) {
						foreach ( $tags as $tag ) {
							if ( ! isset( $cart_quantities[ $tag ] ) ) {
								$cart_quantities[ $tag ] = array(
									'count' => 0,
									'items' => array()
								);
							}

							$cart_quantities[ $tag ]['count'] += $cart_item['quantity'];
							$cart_quantities[ $tag ]['items'][ $cart_item_key ] = $cart_item['quantity'];
						}
					}
				}
				break;

			case 'all_products' :
				foreach ( $cart_items as $cart_item_key => $cart_item ) {
					if ( ! isset( $cart_quantities['all_products'] ) ) {
						$cart_quantities['all_products'] = array(
							'count' => 0,
							'items' => array()
						);
					}

					$cart_quantities['all_products']['count'] += $cart_item['quantity'];
					$cart_quantities['all_products']['items'][ $cart_item_key ] = $cart_item['quantity'];
				}
				break;

			default :
				// Quantity based on product custom taxonomies.
				if ( false !== strpos( $quantity_based_on, 'taxonomy_' ) ) {
					$taxonomy = sanitize_text_field( str_replace( 'taxonomy_', '', $quantity_based_on ) );
					if ( ! empty( $taxonomy ) ) {
						foreach ( $cart_items as $cart_item_key => $cart_item ) {
							$taxonomies = array_unique( WCCS()->product_helpers->wc_get_product_term_ids( $cart_item['product_id'], $taxonomy ) );
							if ( ! empty( $taxonomies ) ) {
								foreach ( $taxonomies as $key ) {
									if ( ! isset( $cart_quantities[ $key ] ) ) {
										$cart_quantities[ $key ] = array(
											'count' => 0,
											'items' => array()
										);
									}

									$cart_quantities[ $key ]['count'] += $cart_item['quantity'];
									$cart_quantities[ $key ]['items'][ $cart_item_key ] = $cart_item['quantity'];
								}
							}
						}
					}
				}
				break;
		}

		return $cart_quantities;
	}

	/**
	 * Getting cart applied coupons ids.
	 *
	 * @since  2.0.0
	 *
	 * @return array Array of coupons id
	 */
	public function get_applied_coupons_ids() {
		$applied_coupons = $this->get_applied_coupons();
		if ( empty( $applied_coupons ) ) {
			return array();
		}

		$ids = array();
		foreach ( $applied_coupons as $applied_coupon ) {
			$id = WCCS_Coupon_Helpers::get_coupon_id_by_code( $applied_coupon );
			if ( ! empty( $id ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Get items maximum length.
	 *
	 * @since  4.0.0
	 *
	 * @return float
	 */
	public function get_items_max_length() {
		if ( ! $this->cart_initialized() ) {
			return apply_filters( 'wccs_cart_' . __FUNCTION__, 0 );
		}

		$cart_contents = $this->get_cart();
		if ( empty( $cart_contents ) ) {
			return apply_filters( 'wccs_cart_' . __FUNCTION__, 0 );
		}

		$max = 0;
		foreach ( $cart_contents as $cart_item ) {
			$length = $cart_item['data']->get_length();
			if ( (float) $length > $max ) {
				$max = (float) $length;
			}
		}

		return apply_filters( 'wccs_cart_' . __FUNCTION__, $max );
	}

	/**
	 * Get items maximum width.
	 *
	 * @since  4.0.0
	 *
	 * @return float
	 */
	public function get_items_max_width() {
		if ( ! $this->cart_initialized() ) {
			return apply_filters( 'wccs_cart_' . __FUNCTION__, 0 );
		}

		$cart_contents = $this->get_cart();
		if ( empty( $cart_contents ) ) {
			return apply_filters( 'wccs_cart_' . __FUNCTION__, 0 );
		}

		$max = 0;
		foreach ( $cart_contents as $cart_item ) {
			$width = $cart_item['data']->get_width();
			if ( (float) $width > $max ) {
				$max = (float) $width;
			}
		}

		return apply_filters( 'wccs_cart_' . __FUNCTION__, $max );
	}

	/**
	 * Get items maximum height.
	 *
	 * @since  4.0.0
	 *
	 * @return float
	 */
	public function get_items_max_height() {
		if ( ! $this->cart_initialized() ) {
			return apply_filters( 'wccs_cart_' . __FUNCTION__, 0 );
		}

		$cart_contents = $this->get_cart();
		if ( empty( $cart_contents ) ) {
			return apply_filters( 'wccs_cart_' . __FUNCTION__, 0 );
		}

		$max = 0;
		foreach ( $cart_contents as $cart_item ) {
			$height = $cart_item['data']->get_height();
			if ( (float) $height > $max ) {
				$max = (float) $height;
			}
		}

		return apply_filters( 'wccs_cart_' . __FUNCTION__, $max );
	}

	/**
	 * Get items minimum stock quantity.
	 *
	 * @since  4.0.0
	 *
	 * @return float|false
	 */
	public function get_items_min_stock_quantity() {
		$min = false;
		if ( ! $this->cart_initialized() ) {
			return apply_filters( 'wccs_cart_' . __FUNCTION__, $min );
		}

		foreach ( $this->get_cart() as $cart_item ) {
			// We only need to check products managing stock, with a limited stock qty.
			if ( ! $cart_item['data']->managing_stock() || $cart_item['data']->backorders_allowed() ) {
				continue;
			}

			$stock_quantity = $cart_item['data']->get_stock_quantity();
			if ( false === $min || $min > $stock_quantity ) {
				$min = $stock_quantity;
			}
		}

		return apply_filters( 'wccs_cart_' . __FUNCTION__, $min );
	}

}

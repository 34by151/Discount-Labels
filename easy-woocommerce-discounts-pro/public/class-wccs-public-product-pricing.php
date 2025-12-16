<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Public_Product_Pricing extends WCCS_Public_Controller {

	protected $pricing;

	protected $apply_method;

	protected $simple_discounts;

	protected $simple_fees;

	protected $bulk_pricings;

	protected $tiered_pricings;

	protected $purchase_pricings;

	protected $is_in_excludes;

	public $product;

	public $product_type;

	public $product_id;

	public $parent_id;

	public function __construct( $product, WCCS_Pricing $pricing, $apply_method = '' ) {
		if ( is_numeric( $product ) ) {
			$this->product = wc_get_product( $product );
		} else {
			$this->product = $product;
		}

		$wccs = WCCS();

		$this->product_type = $this->product->get_type();
		$this->product_id   = $this->product->get_id();
		$this->parent_id    = 'variation' === $this->product_type ? $wccs->product_helpers->get_parent_id( $this->product ) : $this->product_id;
		$this->pricing      = $pricing;
		$this->apply_method = ! empty( $apply_method ) ? $apply_method : $wccs->settings->get_setting( 'product_pricing_discount_apply_method', 'sum' );
	}

	public function get_price_html( $price = '' ) {
		do_action( 'wccs_public_product_pricing_before_get_price_html', $this, $price );

		if ( 
			'all' === WCCS()->settings->get_setting( 'change_display_price', 'all' ) &&
			! is_single( $this->parent_id )
		) {
			$bulk = $this->get_bulk_price_html( $price );
			if ( ! empty( $bulk ) ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return apply_filters( 'wccs_public_product_pricing_' . __FUNCTION__, $bulk, $this->product, $price );
			}
		}

		if ( WCCS()->product_helpers->is_variable_product( $this->product ) ) {
			$product_discounted_price = WCCS()->product_helpers->wc_get_variation_prices( $this->product, true, false );
			if ( empty( $product_discounted_price['price'] ) ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			$min_product_discounted_price = apply_filters(
				'wccs_public_product_pricing_get_price_html_min_variation_price',
				current( $product_discounted_price['price'] ),
				key( $product_discounted_price['price'] ),
				$price,
				$this
			);
			$max_product_discounted_price = apply_filters(
				'wccs_public_product_pricing_get_price_html_max_variation_price',
				end( $product_discounted_price['price'] ),
				key( $product_discounted_price['price'] ),
				$price,
				$this
			);

			$prices = WCCS()->product_helpers->wc_get_variation_prices( $this->product, true );
			if ( empty( $prices['regular_price'] ) ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			$min_price = current( $prices['regular_price'] );
			$max_price = end( $prices['regular_price'] );

			if ( (float) $min_price == $min_product_discounted_price && (float) $max_price == $max_product_discounted_price ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			if ( $min_price !== $max_price ) {
				$display_price = WCCS_Helpers::wc_format_price_range( $min_price, $max_price );
			} else {
				$display_price = wc_price( $min_price );
			}

			if ( $min_product_discounted_price !== $max_product_discounted_price ) {
				$discounted_price = WCCS_Helpers::wc_format_price_range( $min_product_discounted_price, $max_product_discounted_price );
			} else {
				$discounted_price = wc_price( $min_product_discounted_price );
			}

			if ( (float) $min_price > $min_product_discounted_price || (float) $max_price > $max_product_discounted_price ) {
				$discounted_price = '<del aria-hidden="true">' . $display_price . '</del> <ins>' . $discounted_price . '</ins>' . $this->product->get_price_suffix();
			} else {
				$discounted_price = $discounted_price . $this->product->get_price_suffix();
			}
		} else {
			$display_price            = WCCS()->product_helpers->wc_get_price_to_display( $this->product, $this->product->is_on_sale( 'edit' ) ? array( 'price' => WCCS()->product_helpers->wc_get_regular_price( $this->product ) ) : array() );
			$product_discounted_price = WCCS()->product_helpers->wc_get_price_to_display( $this->product, array(), false );
			if ( $product_discounted_price < 0 || $product_discounted_price == $display_price || false === $product_discounted_price ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			if ( $product_discounted_price < $display_price ) {
				$discounted_price = wc_format_sale_price( wc_price( $display_price ), wc_price( $product_discounted_price ) ) . $this->product->get_price_suffix();
			} else {
				$discounted_price = wc_price( $product_discounted_price ) . $this->product->get_price_suffix();
			}
		}

		do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );

		return apply_filters( 'wccs_product_pricing_get_price_html', $discounted_price, $this->product, $price );
	}

	protected function get_bulk_price_html( $price = '' ) {
		if ( WCCS()->product_helpers->is_variation_product( $this->product ) ) {
			return false;
		}

		if ( $this->is_in_exclude_rules() ) {
			return false;
		}

		$rule = null;

		if (
			! $this->product->get_manage_stock() ||
			! (int) WCCS()->settings->get_setting( 'quantity_table_stock_management', 0 ) ||
			1 < $this->product->get_stock_quantity()
		) {
			$rule = $this->get_bulk_pricings();
			$rule = ! empty( $rule ) ? current( $rule ) : null;
		}

		if ( empty( $rule ) || empty( $rule['quantities'] ) ) {
			return false;
		}

		$cached_content = WCCS()->WCCS_Product_Price_Cache->get_cached_price( $this->product, array( 'rule' => absint( $rule['id'] ), 'price' => $price ) );
		if ( false !== $cached_content ) {
			return $cached_content;
		}

		$min = $rule['quantities'][ count( $rule['quantities'] ) - 1 ];
		$max = $rule['quantities'][0];

		$regular_price = '';
		$prices        = array();

		if ( WCCS()->product_helpers->is_variable_product( $this->product ) ) {
			$variable_prices = WCCS()->product_helpers->wc_get_variation_prices( $this->product, true );
			if ( empty( $variable_prices['price'] ) ) {
				return false;
			}

			if ( ! isset( $max['min'] ) || 1 != $max['min'] ) {
				$prices[] = current( $variable_prices['price'] );
				$prices[] = end( $variable_prices['price'] );
			} elseif ( isset( $max['min'] ) && 1 == $max['min'] && 0 == $max['discount'] ) {
				if ( 'fixed_price' !== $max['discount_type'] ) {
					$prices[] = current( $variable_prices['price'] );
					$prices[] = end( $variable_prices['price'] );
				}
			}
			
			$v_price = $this->calculate_discounted_price( $min['discount'], $min['discount_type'] );
			if ( isset( $v_price['min'] ) ) {
				$prices[] = $v_price['min'];
			}
			if ( isset( $v_price['max'] ) ) {
				$prices[] = $v_price['max'];
			}

			$v_price = $this->calculate_discounted_price( $max['discount'], $max['discount_type'] );
			if ( isset( $v_price['min'] ) ) {
				$prices[] = $v_price['min'];
			}
			if ( isset( $v_price['max'] ) ) {
				$prices[] = $v_price['max'];
			}

			$min_reg = current( $variable_prices['regular_price'] );
			$max_reg = end( $variable_prices['regular_price'] );
			if ( $min_reg !== $max_reg ) {
				$regular_price = WCCS_Helpers::wc_format_price_range( $min_reg, $max_reg );
			} else {
				$regular_price = wc_price( $min_reg );
			}
		} else {
			$prices[] = $this->calculate_discounted_price( $min['discount'], $min['discount_type'] );
			$prices[] = $this->calculate_discounted_price( $max['discount'], $max['discount_type'] );

			if ( ! isset( $max['min'] ) || 1 != $max['min'] ) {
				$prices[] = apply_filters( 
					'wccs_get_bulk_price_html_display_price', 
					WCCS()->product_helpers->wc_get_price_to_display( $this->product ), 
					$this->product, 
					$price, 
					$this 
				);
			} elseif ( isset( $max['min'] ) && 1 == $max['min'] && 0 == $max['discount'] ) {
				if ( 'fixed_price' !== $max['discount_type'] ) {
					$prices[] = apply_filters( 
						'wccs_get_bulk_price_html_display_price', 
						WCCS()->product_helpers->wc_get_price_to_display( $this->product ), 
						$this->product, 
						$price, 
						$this 
					);
				}
			}

			$regular_price = WCCS()->product_helpers->wc_get_price_to_display( $this->product, array( 'price' => WCCS()->product_helpers->wc_get_regular_price( $this->product ) ) );
		}
		
		$prices = array_filter( $prices );
		if ( empty( $prices ) ) {
			return false;
		}

		$from = min( $prices );
		$to   = max( $prices );

		$content = '';
		if ( isset( $from ) && isset( $to ) && $from != $to ) {
			$content = wc_format_price_range( $from, $to );
		} elseif ( isset( $from ) ) {
			$content = wc_price( $from );
		} elseif ( isset( $to ) ) {
			$content = wc_price( $to );
		}

		if ( '' !== $regular_price ) {
			if ( $this->product->is_type( 'variable' ) ) {
				if ( $min_reg < $from || $max_reg > $to ) {
					$content = '<del aria-hidden="true">' . $regular_price . '</del> <ins>' . $content . '</ins>';
				}
			} elseif ( $regular_price < $from || $regular_price > $to ) {
				$content = '<del aria-hidden="true">' . wc_price( $regular_price ) . '</del> <ins>' . $content . '</ins>';
			}
		}

		$content .= $this->product->get_price_suffix();

		$content = apply_filters( 'wccs_public_product_pricing_' . __FUNCTION__, $content, $prices, $this, $price );

		WCCS()->WCCS_Product_Price_Cache->cache_price( $this->product, $content, array( 'rule' => absint( $rule['id'] ), 'price' => $price ) );

		return $content;
	}

	/**
	 * Getting price.
	 *
	 * @since  1.0.0
	 *
	 * @return float
	 */
	public function get_price( $base_price = null ) {
		if ( WCCS()->product_helpers->is_variable_product( $this->product ) ) {
			return false;
		}

		if ( $this->is_in_exclude_rules() ) {
			return false;
		}

		// Fix #13 and using get_base_price instead of get_base_price_to_display that caused issues.
		$base_price     = null === $base_price ? $this->get_base_price() : $base_price;
		$adjusted_price = $this->apply_simple_discounts( $base_price );
		$adjusted_price = $this->apply_simple_fees( $adjusted_price );

		if ( $base_price != $adjusted_price ) {
			if ( apply_filters( 'wccs_public_product_pricing_apply_adjusted_price', true, $adjusted_price, $this->product ) ) {
				return $adjusted_price;
			}
		}

		return false;
	}

	public function get_base_price( $product = null ) {
		$product = null === $product ? $this->product : $product;

		do_action( 'wccs_public_product_pricing_before_get_base_price', $this );

		$base_price = (float) $product->get_price( 'edit' );
		if ( $product->is_on_sale( 'edit' ) ) {
			if ( 'regular_price' === WCCS()->settings->get_setting( 'on_sale_products_price', 'on_sale_price' ) ) {
				$base_price = (float) $product->get_regular_price( 'edit' );
			}
		}

		do_action( 'wccs_public_product_pricing_after_get_base_price', $this );

		return apply_filters(
			'wccs_public_product_pricing_' . __FUNCTION__,
			$base_price,
			$product,
			$this
		);
	}

	public function get_base_price_to_display( $product = null ) {
		$product = null === $product ? $this->product : $product;

		$args = array( 'price' => $product->get_price( 'edit' ) );
		if ( $product->is_on_sale( 'edit' ) ) {
			if ( 'regular_price' === WCCS()->settings->get_setting( 'on_sale_products_price', 'on_sale_price' ) ) {
				$args['price'] = $product->get_regular_price( 'edit' );
			}
		}

		return apply_filters(
			'wccs_public_product_pricing_' . __FUNCTION__,
			wc_get_price_to_display( $product, $args ),
			$product,
			$this
		);
	}

	/**
	 * Getting product price based on given discount and discount_type.
	 *
	 * @since  1.0.0
	 *
	 * @param  $discount      float
	 * @param  $discount_type string
	 *
	 * @return string
	 */
	public function get_discounted_price( $discount, $discount_type ) {
		$discount = (float) $discount;
		if ( $discount <= 0 || empty( $discount_type ) ) {
			$price = WCCS()->product_helpers->wc_get_price_to_display( $this->product );
			return wc_price( $price );
		}

		do_action( 'wccs_public_product_pricing_before_get_discounted_price', $discount, $discount_type, $this );
		$price = $this->calculate_discounted_price( $discount, $discount_type );
		do_action( 'wccs_public_product_pricing_after_get_discounted_price', $discount, $discount_type, $this );

		if ( false === $price ) {
			$price = WCCS()->product_helpers->wc_get_price_to_display( $this->product );
			return wc_price( $price );
		}

		if ( is_array( $price ) ) {
			if ( $price['min'] !== $price['max'] ) {
				$price = WCCS_Helpers::wc_format_price_range( $price['min'], $price['max'] );
				return $price;
			} else {
				$price = $price['min'];
			}
		}

		return wc_price( $price );
	}

	public function calculate_discounted_price( $discount, $discount_type ) {
		$discount = (float) $discount;
		if ( $discount <= 0 || empty( $discount_type ) ) {
			return false;
		}

		if ( WCCS()->product_helpers->is_variable_product( $this->product ) ) {
			$variation_ids = $this->product->get_visible_children();
			if ( empty( $variation_ids ) ) {
				return false;
			}

			$variable_prices = array();
			foreach ( $variation_ids as $variation_id ) {
				$variation  = wc_get_product( $variation_id );
				$base_price = $this->get_base_price( $variation );
				if ( $base_price < 0 ) {
					continue;
				}

				$discount_limit = WCCS_Helpers::get_pricing_discount_limit( $base_price );

				$discount_amount = 0;
				if ( 'percentage_discount' === $discount_type ) {
					if ( $discount / 100 * $base_price > 0 ) {
						$discount_amount = $discount / 100 * $base_price;
						// Limit discount amount if limit exists.
						if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
							$discount_amount = (float) $discount_limit;
						}
					}
				} elseif ( 'price_discount' === $discount_type ) {
					if ( $discount > 0 ) {
						$discount_amount = $discount;
						// Limit discount amount if limit exists.
						if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
							$discount_amount = (float) $discount_limit;
						}
					}
				} elseif ( 'fixed_price' === $discount_type ) {
					if ( $discount > 0 ) {
						$discount_amount = $base_price - floatval( $discount );
						// Limit discount amount if limit exists.
						if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
							$discount_amount = (float) $discount_limit;
						}
					}
				} elseif ( 'percentage_fee' === $discount_type ) {
					$discount_amount = $discount / 100 * $base_price * -1;
				} elseif ( 'price_fee' === $discount_type ) {
					$discount_amount = $discount * -1;
				}

				$variation_price = WCCS()->product_helpers->wc_get_price_to_display( $variation );
				if ( $base_price - $discount_amount >= 0 ) {
					$variation_price = WCCS()->product_helpers->wc_get_price_to_display(
						$variation,
						array(
							'qty'   => 1,
							'price' => $base_price - $discount_amount,
						)
					);
					$variation_price = WCCS_Helpers::maybe_exchange_price( $variation_price );
				}

				$variable_prices[ $variation_id ] = apply_filters(
					'wccs_public_product_pricing_get_discounted_price_variation',
					$variation_price,
					$variation_id,
					$variation,
					$discount,
					$discount_type,
					$this
				);
			}

			if ( ! empty( $variable_prices ) ) {
				$min_price = min( $variable_prices );
				$max_price = max( $variable_prices );

				return array(
					'min' => $min_price,
					'max' => $max_price,
				);
			}
		} // End if().
		// Simple and Variation product.
		else {
			$base_price      = $this->get_base_price();
			$discount_limit  = WCCS_Helpers::get_pricing_discount_limit( $base_price );
			$discount_amount = 0;
			if ( 'percentage_discount' === $discount_type ) {
				if ( $discount / 100 * $base_price > 0 ) {
					$discount_amount = $discount / 100 * $base_price;
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			} elseif ( 'price_discount' === $discount_type ) {
				if ( $discount > 0 ) {
					$discount_amount = $discount;
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			} elseif ( 'fixed_price' === $discount_type ) {
				if ( $discount > 0 ) {
					$discount_amount = $base_price - floatval( $discount );
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			} elseif ( 'percentage_fee' === $discount_type ) {
				$discount_amount = $discount / 100 * $base_price * -1;
			} elseif ( 'price_fee' === $discount_type ) {
				$discount_amount = $discount * -1;
			}

			$price = WCCS()->product_helpers->wc_get_price_to_display( $this->product );
			if ( $base_price - $discount_amount >= 0 ) {
				$price = WCCS()->product_helpers->wc_get_price_to_display(
					$this->product,
					array(
						'qty'   => 1,
						'price' => $base_price - $discount_amount,
					)
				);
				$price = WCCS_Helpers::maybe_exchange_price( $price );
			}

			return apply_filters(
				'wccs_public_product_pricing_get_discounted_price_product',
				$price,
				$this->product,
				$discount,
				$discount_type,
				$this
			);

		}

		return false;
	}

	/**
	 * Get discount value html.
	 *
	 * @since  2.8.0
	 *
	 * @param  float  $discount
	 * @param  string $discount_type
	 *
	 * @return string
	 */
	public function get_discount_value_html( $discount, $discount_type ) {
		$discount = (float) $discount;
		if ( $discount < 0 || empty( $discount_type ) ) {
			return apply_filters( 'wccs_product_pricing_discount_value_html', '' );
		}

		if ( 'percentage_discount' === $discount_type ) {
			return apply_filters( 'wccs_product_pricing_discount_value_html', $discount . '%' );
		} elseif ( 'price_discount' === $discount_type ) {
			return apply_filters( 'wccs_product_pricing_discount_value_html', wc_price( WCCS_Helpers::maybe_exchange_price( $discount ) ) );
		} elseif ( 'fixed_price' === $discount_type ) {
			$base_price = $this->get_base_price_to_display();
			if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
				$discount_amount = $base_price - (float) WCCS()->product_helpers->wc_get_price_including_tax( $this->product, array( 'price' => $discount, 'qty' => 1 ) );
			} else {
				$discount_amount = $base_price - (float) $discount;
			}

			return apply_filters( 'wccs_product_pricing_discount_value_html', wc_price( WCCS_Helpers::maybe_exchange_price( $discount_amount ) ) );
		}

		return apply_filters( 'wccs_product_pricing_discount_value_html', '' );
	}

	public function bulk_pricing_table() {
		$settings = WCCS()->settings;
		$bulks    = array();

		if (
			! $this->product->get_manage_stock() ||
			! (int) $settings->get_setting( 'quantity_table_stock_management', 0 ) ||
			1 < $this->product->get_stock_quantity()
		) {
			$bulks = $this->get_bulk_pricings();
		}

		if ( ! empty( $bulks ) ) {
			do_action( 'asnp_wccs_before_bulk_pricing_table', $bulks, $this );

            $view           = $settings->get_setting( 'quantity_table_layout', 'bulk-pricing-table-horizontal' );
			$cache_enabled  = (int) $settings->get_setting( 'cache_quantity_table', 1 );
			$exclude_rules  = $this->pricing->get_exclude_rules();
			$table_title    = __( 'Discount per Quantity', 'easy-woocommerce-discounts' );
			$price_label    = __( 'Price', 'easy-woocommerce-discounts' );
			$discount_label = __( 'Discount', 'easy-woocommerce-discounts' );
			$quantity_label = __( 'Quantity', 'easy-woocommerce-discounts' );
			if ( (int) $settings->get_setting( 'localization_enabled', 1 ) ) {
				$table_title    = $settings->get_setting( 'quantity_table_title', $table_title );
				$price_label    = $settings->get_setting( 'price_label', $price_label );
				$discount_label = $settings->get_setting( 'discount_label', $discount_label );
				$quantity_label = $settings->get_setting( 'quantity_label', $quantity_label );
			}

			$cache = false;
			if ( $cache_enabled ) {
				$cache_args = array(
					'product_id'     => $this->product_id,
					'parent_id'      => $this->parent_id,
					'price_html'     => WCCS()->product_helpers->wc_get_price_html( $this->product ),
					'rules'          => $bulks,
					'exclude_rules'  => $exclude_rules,
					'view'           => $view,
					'table_title'    => $table_title,
					'quantity_label' => $quantity_label,
					'price_label'    => $price_label,
					'discount_label' => $discount_label,
					'variation'      => 'variation' === $this->product_type ? $this->product_id : '',
				);
				$cache = WCCS()->WCCS_Product_Quantity_Table_Cache->get_quantity_table( $cache_args );

				if ( false !== $cache ) {
					if ( ! empty( $cache ) ) {
						echo apply_filters( 'wccs_product_pricing_bulk_pricing_table', $cache, $this );
					}
				}
			} 
			
			if ( false === $cache ) {
				if ( $this->is_in_exclude_rules() ) {
					if ( $cache_enabled ) {
						WCCS()->WCCS_Product_Quantity_Table_Cache->set_quantity_table( $cache_args, '' );
					}
					return;
				}
	
				$table = '';
				foreach ( $bulks as $discount ) {
					ob_start();
					$this->render_view(
						"product-pricing.$view",
						array(
							'controller'     => $this,
							'discount'       => $discount,
							'table_title'    => $table_title,
							'quantity_label' => $quantity_label,
							'price_label'    => $price_label,
							'discount_label' => $discount_label,
							'product_id'     => $this->product_id,
							'variation'      => 'variation' === $this->product_type ? $this->product_id : '',
						)
					);
					$table .= ob_get_clean();
				}
	
				if ( $cache_enabled ) {
					WCCS()->WCCS_Product_Quantity_Table_Cache->set_quantity_table( $cache_args, $table );
				}
	
				echo apply_filters( 'wccs_product_pricing_bulk_pricing_table', $table, $this );
			}
		}

		if ( WCCS()->product_helpers->is_variable_product( $this->product ) ) {
			// Disable plugin price repacer hooks to get variations main price.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
			add_filter( 'woocommerce_show_variation_price', '__return_false', 100 );
			$variations = $this->product->get_available_variations();
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
			remove_filter( 'woocommerce_show_variation_price', '__return_false', 100 );
			if ( ! empty( $variations ) ) {
				foreach ( $variations as $variation ) {
					$variation_pricing = new WCCS_Public_Product_Pricing( $variation['variation_id'], $this->pricing, $this->apply_method );
					$variation_pricing->bulk_pricing_table();
				}
			}
		}
	}

	public function set_min_quantity( $args ) {
		$min = $this->get_min_quantity( $this->get_bulk_pricings() );
		if ( ! $min ) {
			$min = $this->get_min_quantity( $this->get_tiered_pricings() );
		}
		if ( ! $min ) {
			return $args;
		}

		$min_prop = 'variation' === $this->product_type ? 'min_qty' : 'min_value';
		if ( $this->product->managing_stock() && ! $this->product->backorders_allowed() && absint( $min ) > $this->product->get_stock_quantity() ) {
			$args[ $min_prop ] = $this->product->get_stock_quantity();
		} else {
			$args[ $min_prop ] = $min;
		}

		if ( ! is_cart() && ! is_checkout() ) {
			$args['input_value'] = $args[ $min_prop ];
		}

		return $args;
	}

	public function purchase_message() {
		if ( $this->is_in_exclude_rules() ) {
			return;
		}

		$rules         = $this->pricing->get_pricings();
		$exclude_rules = $this->pricing->get_exclude_rules();

		foreach ( $rules as $type => $pricings ) {
			if ( empty( $pricings ) ) {
				continue;
			}

			foreach ( $pricings as $pricing ) {
				if ( empty( $pricing['purchased_message'] ) && empty( $pricing['receive_message'] ) ) {
					continue;
				}

				if ( ! empty( $pricing['purchased_message'] ) ) {
					$cache = $this->get_pricing_purchase_message_from_cache( $pricing, $exclude_rules, 'purchased_message' );
					if ( false !== $cache ) {
						echo $cache;
					} else {
						echo $this->get_pricing_purchase_message( $pricing, $exclude_rules, 'purchased_message' );
					}
				}

				if ( ! empty( $pricing['receive_message'] ) ) {
					$cache = $this->get_pricing_purchase_message_from_cache( $pricing, $exclude_rules, 'receive_message' );
					if ( false !== $cache ) {
						echo $cache;
					} else {
						echo $this->get_pricing_purchase_message( $pricing, $exclude_rules, 'receive_message' );
					}
				}
			}
		}

		if ( WCCS()->product_helpers->is_variable_product( $this->product ) ) {
			// Disable plugin price repacer hooks to get variations main price.
			WCCS()->WCCS_Product_Price_Replace->disable_hooks();
			add_filter( 'woocommerce_show_variation_price', '__return_false', 100 );
			$variations = $this->product->get_available_variations();
			// Enable plugin price replacer hooks.
			WCCS()->WCCS_Product_Price_Replace->enable_hooks();
			remove_filter( 'woocommerce_show_variation_price', '__return_false', 100 );
			if ( ! empty( $variations ) ) {
				foreach ( $variations as $variation ) {
					$variation_pricing = new WCCS_Public_Product_Pricing( $variation['variation_id'], $this->pricing, $this->apply_method );
					$variation_pricing->purchase_message();
				}
			}
		}
	}

	protected function get_pricing_purchase_message_from_cache( $pricing, $exclude_rules, $type ) {
		if ( empty( $pricing ) || empty( $type ) ) {
			return '';
		}

		if ( empty( $pricing[ $type ] ) ) {
			return '';
		}

		if ( 0 === (int) WCCS()->settings->get_setting( 'cache_messages', 1 ) ) {
			return false;
		}

		$cache_args = array(
			'product_id'    => $this->product_id,
			'parent_id'     => $this->parent_id,
			'pricing'       => $pricing,
			'exclude_rules' => $exclude_rules,
			'type'          => $type,
		);
		$cache = WCCS()->WCCS_Product_Purchase_Message_Cache->get_purchase_message( $cache_args );
		if ( false !== $cache ) {
			if ( ! empty( $cache ) ) {
				if ( ! empty( $pricing['message_type'] ) && 'shortcode' === $pricing['message_type'] ) {
					$message = '<div class="wccs-shortcode-purchase-message wccs-shortcode-' . esc_attr( $type ) . '"' .
						( 'variation' === $this->product_type ? "data-variation='{$this->product_id}'" : '') .
						( ! empty( 'variation' === $this->product_type ) ? 'style="display: none;"' : '' ) . '>' .
						do_shortcode( $cache ) .
						'</div>';
					return apply_filters( "wccs_purchase_{$type}", $message, $pricing, $this );
				} else {
					return apply_filters( "wccs_purchase_{$type}", $cache, $pricing, $this );
				}
			}

			return '';
		}

		return false;
	}

	protected function get_pricing_purchase_message( $pricing, $exclude_rules, $type ) {
		$cache_enabled = WCCS()->settings->get_setting( 'cache_messages', 1 );
		$cache_args = array(
			'product_id'    => $this->product_id,
			'parent_id'     => $this->parent_id,
			'pricing'       => $pricing,
			'exclude_rules' => $exclude_rules,
			'type'          => $type,
		);

		if ( $this->is_in_exclude_rules() ) {
			if ( $cache_enabled ) {
				WCCS()->WCCS_Product_Purchase_Message_Cache->set_purchase_message( $cache_args, '' );
			}
			return '';
		}

		$attributes = $this->get_attributes();

		if ( 'purchased_message' === $type ) {
			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['purchased_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				if ( $cache_enabled ) {
					WCCS()->WCCS_Product_Purchase_Message_Cache->set_purchase_message( $cache_args, '' );
				}
				return '';
			}
		} elseif ( 'receive_message' === $type ) {
			if ( 'products_group' === $pricing['mode'] ) {
				$is_valid = false;
				if ( ! empty( $pricing['groups'] ) ) {
					foreach ( $pricing['groups'] as $group ) {
						if ( ! empty( $group['items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $group['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
							$is_valid = true;
							break;
						}
					}
				}
				
				if ( ! $is_valid ) {
					if ( $cache_enabled ) {
						WCCS()->WCCS_Product_Purchase_Message_Cache->set_purchase_message( $cache_args, '' );
					}
					return '';
				}
			} elseif ( empty( $pricing['items'] ) || ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				if ( $cache_enabled ) {
					WCCS()->WCCS_Product_Purchase_Message_Cache->set_purchase_message( $cache_args, '' );
				}
				return '';
			}
		}

		if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
			if ( $cache_enabled ) {
				WCCS()->WCCS_Product_Purchase_Message_Cache->set_purchase_message( $cache_args, '' );
			}
			return '';
		}

		if ( ! empty( $pricing['message_type'] ) && 'shortcode' === $pricing['message_type'] ) {
			if ( $cache_enabled ) {
				WCCS()->WCCS_Product_Purchase_Message_Cache->set_purchase_message( $cache_args, $pricing[ $type ] );
			}
			
			$message = '<div class="wccs-shortcode-purchase-message wccs-shortcode-' . esc_attr( $type ) . '"' .
				( 'variation' === $this->product_type ? "data-variation='{$this->product_id}'" : '') .
				( ! empty( 'variation' === $this->product_type ) ? 'style="display: none;"' : '' ) . '>' .
				do_shortcode( $pricing[ $type ] ) .
				'</div>';
			return apply_filters( "wccs_purchase_{$type}", $message, $pricing, $this );
		}

		$default_background_color = WCCS()->settings->get_setting( 'purchase_message_background_color', '' );
		$default_color            = WCCS()->settings->get_setting( 'purchase_message_color', '' );

		$style = '';
		if ( ! empty( $pricing['message_background_color'] ) ) {
			$style .= 'background-color: ' . $pricing['message_background_color'] . ';';
		} elseif ( ! empty( $default_background_color ) ) {
			$style .= 'background-color: ' . $default_background_color . ';';
		}

		if ( ! empty( $pricing['message_color'] ) ) {
			$style .= 'color: ' . $pricing['message_color'] . ';';
		} elseif ( ! empty( $default_color ) ) {
			$style .= 'color: ' . $default_color . ';';
		}

		if ( 'variation' === $this->product_type ) {
			$style .= 'display: none;';
		}

		$message = '<div class="wccs-purchase-message wccs-' . esc_attr( $type ) . '"' .
			( 'variation' === $this->product_type ? "data-variation='{$this->product_id}'" : '') .
			( ! empty( $style ) ? "style='$style'" : '' ) . '>' . __( wp_kses_post( wp_unslash( $pricing[ $type ] ) ), 'easy-woocommerce-discounts' ) . '</div>';

		if ( $cache_enabled ) {
			WCCS()->WCCS_Product_Purchase_Message_Cache->set_purchase_message( $cache_args, $message );
		}

		return apply_filters( "wccs_purchase_{$type}", $message, $pricing, $this );
	}

	public function get_simple_fees() {
		if ( isset( $this->simple_fees ) ) {
			return $this->simple_fees;
		}

		$simples = $this->pricing->get_simple_pricings();
		if ( empty( $simples ) ) {
			$this->simple_fees = array();
			return array();
		}

		$fees = array();
		foreach ( $simples as $pricing_id => $pricing ) {
			if ( ! in_array( $pricing['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
				continue;
			}

			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ) ) ) {
				continue;
			}

			if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ) ) ) {
				continue;
			}

			$fees[ $pricing_id ] = $pricing;
		}

		$this->simple_fees = $fees;
		return $fees;
	}

	public function get_simple_discounts() {
		if ( isset( $this->simple_discounts ) ) {
			return $this->simple_discounts;
		}

		$simples = $this->pricing->get_simple_pricings();
		if ( empty( $simples ) ) {
			$this->simple_discounts = array();
			return array();
		}

		$discounts = array();

		foreach ( $simples as $pricing_id => $pricing ) {
			if ( in_array( $pricing['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
				continue;
			}

			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ) ) ) {
				continue;
			}

			if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ) ) ) {
				continue;
			}

			$discounts[ $pricing_id ] = $pricing;
		}

		if ( ! empty( $discounts ) ) {
			usort( $discounts, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$discounts = $this->pricing->rules_filter->by_apply_mode( $discounts );
		}

		$this->simple_discounts = $discounts;
		return $discounts;
	}

	public function get_bulk_pricings() {
		if ( isset( $this->bulk_pricings ) ) {
			return $this->bulk_pricings;
		}

		$bulks = $this->pricing->get_bulk_pricings();
		if ( empty( $bulks ) ) {
			$this->bulk_pricings = array();
			return array();
		}

		$attributes = $this->get_attributes();

		$pricings = array();
		foreach ( $bulks as $pricing_id => $pricing ) {
			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				continue;
			}

			if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				continue;
			}

			$pricings[ $pricing_id ] = $pricing;
		}

		if ( ! empty( $pricings ) ) {
			usort( $pricings, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$pricings = $this->pricing->rules_filter->by_apply_mode( $pricings );
		}

		$this->bulk_pricings = $pricings;
		return $pricings;
	}

	public function get_tiered_pricings() {
		if ( isset( $this->tiered_pricings ) ) {
			return $this->tiered_pricings;
		}

		$bulks = $this->pricing->get_tiered_pricings();
		if ( empty( $bulks ) ) {
			$this->tiered_pricings = array();
			return array();
		}

		$attributes = $this->get_attributes();

		$pricings = array();
		foreach ( $bulks as $pricing_id => $pricing ) {
			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				continue;
			}

			if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				continue;
			}

			$pricings[ $pricing_id ] = $pricing;
		}

		if ( ! empty( $pricings ) ) {
			usort( $pricings, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$pricings = $this->pricing->rules_filter->by_apply_mode( $pricings );
		}

		$this->tiered_pricings = $pricings;
		return $pricings;
	}

	public function get_purchase_pricings() {
		if ( isset( $this->purchase_pricings ) ) {
			return $this->purchase_pricings;
		}

		$purchases = $this->pricing->get_purchase_pricings();
		if ( empty( $purchases ) ) {
			$this->purchase_pricings = array();
			return array();
		}

		$attributes = $this->get_attributes();

		$pricings = array();
		foreach ( $purchases as $pricing_id => $pricing ) {
			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				continue;
			}

			if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $attributes ) ) {
				continue;
			}

			$pricings[ $pricing_id ] = $pricing;
		}

		if ( ! empty( $pricings ) ) {
			usort( $pricings, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$pricings = $this->pricing->rules_filter->by_apply_mode( $pricings );
		}

		$this->purchase_pricings = $pricings;
		return $pricings;
	}

	protected function apply_simple_discounts( $base_price ) {
		$discounts = $this->get_simple_discounts();
		if ( empty( $discounts ) ) {
			return $base_price;
		}

		// Get discount limit.
		$discount_limit = WCCS_Helpers::get_pricing_discount_limit( $base_price );

		$discount_amounts = array();
		foreach ( $discounts as $discount ) {
			$discount_amount = false;
			if ( '' !== $discount_limit && 0 >= $discount_limit ) {
				break;
			}

			if ( 'percentage_discount' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] / 100 * $base_price > 0 ) {
					$discount_amount = (float) $discount['discount'] / 100 * $base_price;
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			} elseif ( 'price_discount' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] > 0 ) {
					$discount_amount = (float) $discount['discount'];
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			} elseif ( 'fixed_price' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] > 0 ) {
					$discount_amount = $base_price - (float) $discount['discount'];
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			}

			if ( false !== $discount_amount ) {
				if ( '' !== $discount_limit ) {
					$discount_limit -= $discount_amount;
				}

				$discount_amounts[] = $discount_amount;
			}
		}

		if ( ! empty( $discount_amounts ) ) {
			$discount_amount = 0;
			if ( 'first' === $this->apply_method ) {
				$discount_amount = $discount_amounts[0];
			} elseif ( 'max' === $this->apply_method ) {
				$discount_amount = max( $discount_amounts );
			} elseif ( 'min' === $this->apply_method ) {
				$discount_amount = min( $discount_amounts );
			} elseif ( 'sum' === $this->apply_method ) {
				$discount_amount = array_sum( $discount_amounts );
			}

			if ( $base_price - $discount_amount >= 0 ) {
				return $base_price - $discount_amount;
			}
			return 0;
		}

		return $base_price;
	}

	protected function apply_simple_fees( $base_price ) {
		$fees = $this->get_simple_fees();
		if ( empty( $fees ) ) {
			return $base_price;
		}

		$fee_amounts = array();
		foreach ( $fees as $fee ) {
			if ( 'percentage_fee' === $fee['discount_type'] ) {
				if ( (float) $fee['discount'] / 100 * $base_price > 0 ) {
					$fee_amounts[] = (float) $fee['discount'] / 100 * $base_price;
				}
			} elseif ( 'price_fee' === $fee['discount_type'] ) {
				if ( (float) $fee['discount'] > 0 ) {
					$fee_amounts[] = (float) $fee['discount'];
				}
			}
		}

		if ( ! empty( $fee_amounts ) ) {
			$fee_amount = 0;
			if ( 'first' === $this->apply_method ) {
				$fee_amount = $fee_amounts[0];
			} elseif ( 'max' === $this->apply_method ) {
				$fee_amount = max( $fee_amounts );
			} elseif ( 'min' === $this->apply_method ) {
				$fee_amount = min( $fee_amounts );
			} elseif ( 'sum' === $this->apply_method ) {
				$fee_amount = array_sum( $fee_amounts );
			}

			if ( $base_price + $fee_amount >= 0 ) {
				return $base_price + $fee_amount;
			}
		}

		return $base_price;
	}

	protected function is_in_exclude_rules() {
		if ( isset( $this->is_in_excludes ) ) {
			return $this->is_in_excludes;
		}

		if ( $this->pricing->is_in_exclude_rules( $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), $this->get_attributes() ) ) {
			$this->is_in_excludes = true;
			return true;
		}

		$this->is_in_excludes = false;
		return false;
	}

	protected function get_attributes() {
		$attributes = array();
		if ( $this->product->is_type( 'variation' ) ) {
			$attributes = WCCS()->WCCS_Attribute_Helpers->get_product_attributes( $this->product_id );
		}

		return $attributes;
	}

	protected function get_min_quantity( $discounts ) {
		if ( empty( $discounts ) ) {
			return false;
		}

		foreach ( $discounts as $discount ) {
			if ( empty( $discount['set_min_quantity'] ) || 'true' !== $discount['set_min_quantity'] ) {
				continue;
			}

			if ( ! empty( $discount['quantities'] )
				&& $discount['quantities'][0]
				&& ! empty( $discount['quantities'][0]['min'] )
				&& 0 < (int) $discount['quantities'][0]['min']
			) {
				return absint( $discount['quantities'][0]['min'] );
			}
		}

		return false;
	}

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Attribute_Helpers {

	/**
	 * An array contains product_id => attributes.
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * An array contains product_id => attributes.
	 * Exclude variation attributes.
	 *
	 * @var array
	 */
	protected $simple_attributes = array();

	public function get_attributes( array $args = array() ) {
		global $wc_product_attributes;
		if ( empty( $wc_product_attributes ) ) {
			return array();
		}

		$args = wp_parse_args( $args, array(
			'separator'          => '/',
			'nicename'           => false,
			'pad_counts'         => 1,
			'show_count'         => 1,
			'hierarchical'       => 1,
			'hide_empty'         => 0,
			'show_uncategorized' => 0,
			'orderby'            => 'name',
			'menu_order'         => false,
		) );

		$attributes = array();
		foreach ( $wc_product_attributes as $taxonomy => $attribute ) {
			$terms = get_terms( $taxonomy, $args );
			if ( empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$attributes[] = (object) array(
					'id'       => absint( $term->term_id ),
					'text'     => ( ! empty( $attribute->attribute_label ) ? sanitize_text_field( $attribute->attribute_label ) : sanitize_text_field( $attribute->attribute_name ) ) . ': ' . rtrim( WCCS_Helpers::get_term_hierarchy_name( $term->term_id, $taxonomy, $args['separator'], $args['nicename'] ), $args['separator'] ),
					'slug'     => sanitize_text_field( $term->slug ),
					'name'     => sanitize_text_field( $term->name ),
					'taxonomy' => sanitize_text_field( $taxonomy ),
				);
			}
		}

		return $attributes;
	}

	/**
	 * Getting product attributes.
	 * For variation attributes if they have default values, default value will add otherwise all of options will add.
	 *
	 * @since  2.0.0
	 *
	 * @param  int $product_id
	 *
	 * @return array
	 */
	public function get_product_attributes( $product_id ) {
		if ( isset( $this->attributes[ $product_id ] ) ) {
			return $this->attributes[ $product_id ];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$is_variable          = WCCS()->product_helpers->is_variable_product( $product );
		$is_variation         = WCCS()->product_helpers->is_variation_product( $product );
		$attributes           = $this->get_product_raw_attributes( $product );
		$variation_attributes = $is_variable ? $product->get_variation_attributes() : array();
		if ( $is_variation ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( ! $parent ) {
				$this->attributes[ $product_id ] = array();
				return array();
			}

			$variation_attributes = $product->get_variation_attributes( false );
		}

		if ( empty( $attributes ) ) {
			$this->attributes[ $product_id ] = array();
			return array();
		}

		$attribute_ids = array();
		foreach ( $attributes as $attribute ) {
			if ( ! $attribute['is_taxonomy'] || ! taxonomy_exists( $attribute['name'] ) ) {
				continue;
			}

			if ( ! $attribute['is_variation'] || ( ! $is_variable && ! $is_variation ) ) {
				if ( isset( $attribute['options'] ) ) {
					$attribute_ids = array_merge( $attribute_ids, $attribute['options'] );
				} else {
					$attribute_ids = array_merge( $attribute_ids, wc_get_product_terms( $product_id, $attribute['name'], array( 'fields' => 'ids' ) ) );
				}
			} else {
				if ( ! isset( $variation_attributes[ $attribute['name'] ] ) ) {
					continue;
				}

				if ( is_array( $variation_attributes[ $attribute['name'] ] ) ) {
					if ( isset( $attribute['options'] ) ) {
						$attribute_ids = array_merge( $attribute_ids, $attribute['options'] );
					} elseif ( isset( $attributes[ $attribute['name'] ] ) && $attributes[ $attribute['name'] ]['is_variation'] ) {
						$attribute_ids = array_merge( $attribute_ids, $attributes[ $attribute['name'] ]['options'] );
					}
				} elseif ( '' === $variation_attributes[ $attribute['name'] ] ) {
					if ( isset( $attribute['options'] ) ) {
						$attribute_ids = array_merge( $attribute_ids, $attribute['options'] );
					} // Get options in WooCommerce 2.6
					else {
						if ( ! empty( $attribute['is_taxonomy'] ) ) {
							$attribute_ids = array_merge(
								$attribute_ids,
								WCCS()->WCCS_Term_Helpers->wc_get_object_terms( $parent->get_id(), $attribute['name'], 'term_id' )
							);
						} else {
							$attribute_ids = array_merge(
								$attribute_ids,
								array_filter( wc_get_text_attributes( $attribute['value'] ), array( $this, 'wc_get_text_attributes_filter_callback' ) )
							);
						}
					}
				} else {
					$term = get_term_by( 'slug', $variation_attributes[ $attribute['name'] ], $attribute['name'] );
					if ( ! is_wp_error( $term ) && is_object( $term ) && $term->term_id ) {
						$attribute_ids[] = $term->term_id;
					}
				}
			}
		}

		$this->attributes[ $product_id ] = $attribute_ids;

		return $attribute_ids;
	}

	public function get_product_simple_attributes( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product ) {
			return array();
		}

		if ( isset( $this->simple_attributes[ $product->get_id() ] ) ) {
			return $this->simple_attributes[ $product->get_id() ];
		}

		$attributes = $this->get_product_raw_attributes( $product );

		if ( empty( $attributes ) ) {
			$this->simple_attributes[ $product->get_id() ] = array();
			return array();
		}

		$is_variable  = WCCS()->product_helpers->is_variable_product( $product );
		$is_variation = WCCS()->product_helpers->is_variation_product( $product );

		$attribute_ids = array();
		foreach ( $attributes as $attribute ) {
			if ( ! $attribute['is_taxonomy'] || ! taxonomy_exists( $attribute['name'] ) ) {
				continue;
			}

			if ( ! $attribute['is_variation'] || ( ! $is_variable && ! $is_variation ) ) {
				if ( isset( $attribute['options'] ) ) {
					$attribute_ids = array_merge( $attribute_ids, $attribute['options'] );
				} else {
					$attribute_ids = array_merge( $attribute_ids, wc_get_product_terms( $product->get_id(), $attribute['name'], array( 'fields' => 'ids' ) ) );
				}
			}
		}

		$this->simple_attributes[ $product->get_id() ] = $attribute_ids;

		return $attribute_ids;
	}

	public function get_product_raw_attributes( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product ) {
			return array();
		}

		$is_variation = WCCS()->product_helpers->is_variation_product( $product );
		$attributes   = ! $is_variation ? $product->get_attributes() : array();
		if ( $is_variation ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( ! $parent ) {
				return array();
			}

			$attributes = $parent->get_attributes();
		}

		return $attributes;
	}

	/**
	 * See if an attribute is actually valid.
	 *
	 * @since  2.2.1
	 *
	 * @param  string $value Value.
	 *
	 * @return bool
	 */
	public function wc_get_text_attributes_filter_callback( $value ) {
		if ( function_exists( 'wc_get_text_attributes_filter_callback' ) ) {
			return wc_get_text_attributes_filter_callback( $value );
		}

		return '' !== $value;
	}

}

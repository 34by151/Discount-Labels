<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager.
 *
 * @since      1.0.0
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Settings_Manager {

	/**
	 * Getting tabs of settings menu.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_settings_tabs() {
		return apply_filters( 'wccs_settings_tabs',
			array(
				'general'      => __( 'General', 'easy-woocommerce-discounts' ),
				'promotion'    => __( 'Promotion', 'easy-woocommerce-discounts' ),
				'localization' => __( 'Localization', 'easy-woocommerce-discounts' ),
				'performance'  => __( 'Performance', 'easy-woocommerce-discounts' ),
				'licenses'     => __( 'Enable Updates', 'easy-woocommerce-discounts' ),
			)
		);
	}

	/**
	 * Retrieve settings tabs
	 *
	 * @since  1.0.0
	 * @param  string|boolean $tab
	 * @return array          $section
	 */
	public function get_settings_tab_sections( $tab = false ) {
		$tabs     = false;
		$sections = $this->get_registered_settings_sections();

		if ( $tab && ! empty( $sections[ $tab ] ) ) {
			$tabs = $sections[ $tab ];
		} else if ( $tab ) {
			$tabs = false;
		}

		return $tabs;
	}

	/**
	 * Get the settings sections for each tab
	 * Uses a static to avoid running the filters on every request to this function
	 *
	 * @since  1.0.0
	 * @return array Array of tabs and sections
	 */
	public function get_registered_settings_sections() {
		static $sections = false;

		if ( false !== $sections ) {
			return $sections;
		}

		$sections = array(
			'general' => apply_filters( 'wccs_settings_sections_general', array(
				'cart-discount'   => __( 'Cart Discount', 'easy-woocommerce-discounts' ),
				'product-pricing' => __( 'Product Pricing', 'easy-woocommerce-discounts' ),
				'checkout-fee'    => __( 'Checkout Fee', 'easy-woocommerce-discounts' ),
				'shipping'        => __( 'Shipping', 'easy-woocommerce-discounts' ),
			) ),
			'promotion' => apply_filters( 'wccs_settings_sections_promotion', array(
				'quantity-table'  => __( 'Quantity Table', 'easy-woocommerce-discounts' ),
				'live-price'      => __( 'Live Price', 'easy-woocommerce-discounts' ),
				'messages'        => __( 'Messages', 'easy-woocommerce-discounts' ),
				'total-discounts' => __( 'Total Discounts', 'easy-woocommerce-discounts' ),
				'countdown-timer' => __( 'Countdown Timer', 'easy-woocommerce-discounts' ),
				'discount-page'   => __( 'Discount Page', 'easy-woocommerce-discounts' ),
			) ),
			'localization' => apply_filters( 'wccs_settings_sections_localization', array(
				'main' => __( 'Main', 'easy-woocommerce-discounts' ),
			) ),
			'performance' => apply_filters( 'wccs_settings_sections_performance', array(
				'main' => __( 'Main', 'easy-woocommerce-discounts' ),
			) ),
			'licenses' => apply_filters( 'wccs_settings_sections_licenses', array(
				'main' => __( 'Main', 'easy-woocommerce-discounts' ),
			) ),
		);

		$sections = apply_filters( 'wccs_settings_sections', $sections );

		return $sections;
	}

	/**
	 * Retrieve array of plugin settings
	 *
	 * @since   1.0.0
	 * @return  array
	 */
	public function get_registered_settings() {
		$pages = get_pages();
		$pages = ! empty( $pages ) ? wp_list_pluck( $pages, 'post_title', 'ID' ) : array();

		return apply_filters( 'wccs_registered_settings', array(
				// General Settings
				'general' => apply_filters( 'wccs_settings_general',
					array(
						'cart-discount' => apply_filters( 'wccs_settings_general_cart_discount_section',
							array(
								'cart_discount_apply_method' => array(
									'id'      => 'cart_discount_apply_method',
									'name'    => __( 'Discount Apply Method', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'sum',
									'options' => array(
										'first' => __( 'Apply first matched rule discount', 'easy-woocommerce-discounts' ),
										'max'   => __( 'Apply maximum discount', 'easy-woocommerce-discounts' ),
										'min'   => __( 'Apply minimum discount', 'easy-woocommerce-discounts' ),
										'sum'   => __( 'Apply sum of discounts', 'easy-woocommerce-discounts' ),
									),
								),
								'cart_discount_with_individual_coupons' => array(
									'id'      => 'cart_discount_with_individual_coupons',
									'name'    => __( 'Apply with individual use coupons', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Apply cart discounts with cart individual use coupons.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'cart_discount_with_regular_coupons' => array(
									'id'      => 'cart_discount_with_regular_coupons',
									'name'    => __( 'Apply with regular coupons', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Apply cart discounts with cart regular coupons.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'cart_discount_limit_type' => array(
									'id'      => 'cart_discount_limit_type',
									'name'    => __( 'Discount Limit Type', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'no_limit',
									'optgroups' => array(
										array(
											'optgroup' => __( 'No Limit', 'easy-woocommerce-discounts' ),
											'options'  => array(
												'no_limit' => __( 'No discount limit', 'easy-woocommerce-discounts' ),
											),
										),
										array(
											'optgroup' => __( 'Total Discount Limit', 'easy-woocommerce-discounts' ),
											'options'  => array(
												'total_price_limit'      => sprintf( __( 'Total discount limit %s', 'easy-woocommerce-discounts' ), get_woocommerce_currency_symbol() ),
												'total_percentage_limit' => sprintf( __( 'Total discount limit %s', 'easy-woocommerce-discounts' ), '%' ),
											),
										),
									),
								),
								'cart_discount_limit' => array(
									'id'   => 'cart_discount_limit',
									'name' => __( 'Discount Limit', 'easy-woocommerce-discounts' ),
									'type' => 'number',
									'step' => '0.1',
									'std'  => '',
								),
								'cart_discount_display_multiple_discounts' => array(
									'id'      => 'cart_discount_display_multiple_discounts',
									'name'    => __( 'Display Multiple Discounts', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'separate',
									'options' => array(
										'separate' => __( 'Display all discounts separately', 'easy-woocommerce-discounts' ),
										'combine'  => __( 'Combine to one total discount', 'easy-woocommerce-discounts' ),
									),
								),
								'coupon_label' => array(
									'id'   => 'coupon_label',
									'name' => __( 'Coupon Label', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Name of the discount showed in the cart totals block as a discount title when using the combine mode for display multiple discounts.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Discount', 'easy-woocommerce-discounts' ),
								),
								'remove_coupons_zero_value' => array(
									'id'      => 'remove_coupons_zero_value',
									'name'    => __( 'Remove Zero', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Remove zero value from the zero amount coupons.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
							)
						),
						'product-pricing' => apply_filters( 'wccs_settings_general_product_pricing_section',
							array(
								'change_display_price' => array(
									'id'      => 'change_display_price',
									'name'    => __( 'Change Display Price', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'all',
									'options' => array(
										'none'           => __( 'Do not change', 'easy-woocommerce-discounts' ),
										'all'            => __( 'Change price - simple and bulk prices', 'easy-woocommerce-discounts' ),
										'simple'         => __( 'Change price - simple prices', 'easy-woocommerce-discounts' ),
										'simple_ex_html' => __( 'Change price - simple prices without strikethrough price', 'easy-woocommerce-discounts' ),
									),
								),
								'sale_badge' => array(
									'id'      => 'sale_badge',
									'name'    => __( 'Show Sale Badge', 'easy-woocommerce-discounts' ),
									'type'    => 'multicheck',
									'options' => array(
										'simple' => array(
											'std'  => '1',
											'id'   => 'simple',
											'name' => __( 'Show sale badge on simple pricing products.', 'easy-woocommerce-discounts' ),
										),
										'bulk' => array(
											'id'   => 'bulk',
											'name' => __( 'Show sale badge on bulk pricing products.', 'easy-woocommerce-discounts' ),
										),
										'tiered' => array(
											'id'   => 'tiered',
											'name' => __( 'Show sale badge on tiered pricing products.', 'easy-woocommerce-discounts' ),
										),
										'purchase' => array(
											'id'   => 'purchase',
											'name' => __( 'Show sale badge on purchase pricing products.', 'easy-woocommerce-discounts' ),
										),
										'products_group' => array(
											'id'   => 'products_group',
											'name' => __( 'Show sale badge on products group pricing products.', 'easy-woocommerce-discounts' ),
										),
									),
								),
								'sale_badge_type' => array(
									'id'   => 'sale_badge_type',
									'name' => __( 'Sale Badge Type', 'easy-woocommerce-discounts' ),
									'desc' => sprintf( __( 'Sale: Displays "Sale" inside the badge.%1$s Discount: Displays discount value inside the badge.%1$s Note: It will only applies to simple pricing rules.', 'easy-woocommerce-discounts' ), '<br>' ),
									'type' => 'select',
									'std'  => 'sale',
									'options' => array(
										'sale'     => __( 'Sale', 'easy-woocommerce-discounts' ),
										'discount' => __( 'Discount', 'easy-woocommerce-discounts' ),
									),
								),
								'loop_sale_badge_position' => array(
									'id'   => 'loop_sale_badge_position',
									'name' => __( 'Sale Badge Position On Archive Page', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Sale badge position for bulk, tiered, purchase, products group rules in the archive page.', 'easy-woocommerce-discounts' ),
									'type' => 'select',
									'std'  => 'before_shop_loop_item_thumbnail',
									'options' => array(
										'before_shop_loop_item_thumbnail' => __( 'Before product thumbnail', 'easy-woocommerce-discounts' ),
										'after_shop_loop_item_thumbnail'  => __( 'After product thumbnail', 'easy-woocommerce-discounts' ),
										'before_shop_loop_item_title'     => __( 'Before product title', 'easy-woocommerce-discounts' ),
										'after_shop_loop_item_title'      => __( 'After product title', 'easy-woocommerce-discounts' ),
										'before_shop_loop_item_rating'    => __( 'Before product rating', 'easy-woocommerce-discounts' ),
										'after_shop_loop_item_rating'     => __( 'After product rating', 'easy-woocommerce-discounts' ),
										'before_shop_loop_item_price'     => __( 'Before product price', 'easy-woocommerce-discounts' ),
										'after_shop_loop_item_price'      => __( 'After product price', 'easy-woocommerce-discounts' ),
									),
								),
								'single_sale_badge_position' => array(
									'id'   => 'single_sale_badge_position',
									'name' => __( 'Sale Badge Position On Product Page', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Sale badge position for bulk, tiered, purchase, products group rules in the product page.', 'easy-woocommerce-discounts' ),
									'type' => 'select',
									'std'  => 'before_single_item_images',
									'options' => array(
										'before_single_item_images' => __( 'Before product images', 'easy-woocommerce-discounts' ),
										'after_single_item_images'  => __( 'After product images', 'easy-woocommerce-discounts' ),
										'before_single_item_title'  => __( 'Before product title', 'easy-woocommerce-discounts' ),
										'after_single_item_title'   => __( 'After product title', 'easy-woocommerce-discounts' ),
										'before_single_item_price'  => __( 'Before product price', 'easy-woocommerce-discounts' ),
										'after_single_item_price'   => __( 'After product price', 'easy-woocommerce-discounts' ),
									),
								),
								'product_pricing_discount_apply_method' => array(
									'id'      => 'product_pricing_discount_apply_method',
									'name'    => __( 'Discount Apply Method', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'sum',
									'options' => array(
										'first' => __( 'Apply first matched rule discount', 'easy-woocommerce-discounts' ),
										'max'   => __( 'Apply maximum discount', 'easy-woocommerce-discounts' ),
										'min'   => __( 'Apply minimum discount', 'easy-woocommerce-discounts' ),
										'sum'   => __( 'Apply sum of discounts', 'easy-woocommerce-discounts' ),
									),
								),
								'product_pricing_consider_sale_price' => array(
									'id'      => 'product_pricing_consider_sale_price',
									'name'    => __( 'Consider Product Sale Price', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf(
										__( 'If product is on sale with a WooCommerce onsale price consider it when the %1$s is %2$s or %3$s.%4$s
											e.g: If the product discounted price by the plugin is 20$ and product onsale price in WooCommerce is 10$
											and the %1$s is %2$s the price will be 10$ because it is the maximum discount.', 'easy-woocommerce-discounts'
										),
										'<code>' . __( 'Discount Apply Method', 'easy-woocommerce-discounts' ) . '</code>',
										'<code>' . __( 'Apply maximum discount', 'easy-woocommerce-discounts' ) . '</code>',
										'<code>' . __( 'Apply minimum discount', 'easy-woocommerce-discounts' ) . '</code>', '<br/>'
									),
									'type'    => 'select',
									'std'     => 0,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'pricing_product_base_price' => array(
									'id'      => 'pricing_product_base_price',
									'name'    => __( 'Product Base Price', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf( __( 'The base price of the product that discounts should apply to it.%1$s Product Price: Discounts will apply to the product regular or sale price.%1$s Cart Item Price: Discounts will apply to the cart item price. Useful when a custom pricing plugin is enabled on your site.', 'easy-woocommerce-discounts' ), '<br>' ),
									'type'    => 'select',
									'std'     => 'cart_item_price',
									'options' => array(
										'product_price'   => __( 'Product price', 'easy-woocommerce-discounts' ),
										'cart_item_price' => __( 'Cart item price', 'easy-woocommerce-discounts' ),
									),
								),
								'on_sale_products_price' => array(
									'id'      => 'on_sale_products_price',
									'name'    => __( 'On-Sale Products Price', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Base price that used for on-sale products in pricing rules to applying discount on that price.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'on_sale_price',
									'options' => array(
										'regular_price' => __( 'Regular price', 'easy-woocommerce-discounts' ),
										'on_sale_price' => __( 'On-sale price', 'easy-woocommerce-discounts' ),
										// 'exclude'       => 'Exclude on-sale products from pricing rules',
									),
								),
								'product_pricing_limit_type' => array(
									'id'      => 'product_pricing_limit_type',
									'name'    => __( 'Discount Limit Type', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'no_limit',
									'optgroups' => array(
										array(
											'optgroup' => __( 'No Limit', 'easy-woocommerce-discounts' ),
											'options'  => array(
												'no_limit' => __( 'No discount limit', 'easy-woocommerce-discounts' ),
											),
										),
										array(
											'optgroup' => __( 'Price Discount Limit', 'easy-woocommerce-discounts' ),
											'options'  => array(
												'price_price_limit'      => sprintf( __( 'Price discount limit %s', 'easy-woocommerce-discounts' ), get_woocommerce_currency_symbol() ),
												'price_percentage_limit' => sprintf( __( 'Price discount limit %s', 'easy-woocommerce-discounts' ), '%' ),
											),
										),
									),
								),
								'round_product_adjustment' => array(
									'id'      => 'round_product_adjustment',
									'name'    => __( 'Round Adjustment', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf( __( 'If set to yes it will round product adjusted price.%1$s exp: Subtotal of 2 quantities of a product with price %2$s and %3$s discount will be %4$s when round is enabled otherwise it will be %5$s', 'easy-woocommerce-discounts' ), '<br>', '0.99$', '2.5%', '1.94$', '1.93$' ),
									'type'    => 'select',
									'std'     => 'no',
									'options' => array(
										'yes' => __( 'Yes', 'easy-woocommerce-discounts' ),
										'no'  => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'product_pricing_discount_limit' => array(
									'id'   => 'product_pricing_discount_limit',
									'name' => __( 'Discount Limit', 'easy-woocommerce-discounts' ),
									'type' => 'number',
									'step' => '0.1',
									'std'  => '',
								),
								'update_cart_on_shipping_change' => array(
									'id'      => 'update_cart_on_shipping_change',
									'name'    => __( 'Update Cart On Shipping Change', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Enable this option to update cart in the cart page when you used one of Shipping Method, Shipping Country, Shipping State, Shipping Postcode or Shipping Zone in your product pricing rules.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'disabled',
									'options' => array(
										'enabled'   => __( 'Enabled', 'easy-woocommerce-discounts' ),
										'disabled'  => __( 'Disabled', 'easy-woocommerce-discounts' ),
									),
								),
								'auto_add_free_to_cart' => array(
									'id'      => 'auto_add_free_to_cart',
									'name'    => __( 'Automatically add free products to cart', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf( __( 'Automatically add free products to the cart for "purchase X receive Y" rules.%1$s To use this feature just add one product or one variation to the "Discounted products" of "purchase X receive Y" rule.%1$s Check out %2$s.', 'easy-woocommerce-discounts' ), '<br>', '<a href="http://www.asanaplugins.com/automatically-add-free-products-to-cart/" target="_blank">this toturial</a>' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'set_min_quantity' => array(
									'id'      => 'set_min_quantity',
									'name'    => __( 'Set Min Quantity', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Setting min quantity of a product based on applied bulk or tiered pricing rule min value.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 0,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'show_free_gift_badge' => array(
									'id'      => 'show_free_gift_badge',
									'name'    => __( 'Show Free Gift Emoji', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Show a free gift emoji for free gift products in the cart and checkout.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
							)
						),
						'checkout-fee' => apply_filters( 'wccs_settings_general_checkout_fee_section',
							array(
								'checkout_fee_apply_method' => array(
									'id'      => 'checkout_fee_apply_method',
									'name'    => __( 'Checkout Fee Apply Method', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'all',
									'options' => array(
										'first' => __( 'Apply first matched rule fee', 'easy-woocommerce-discounts' ),
										'max'   => __( 'Apply maximum fee', 'easy-woocommerce-discounts' ),
										'min'   => __( 'Apply minimum fee', 'easy-woocommerce-discounts' ),
										'all'   => __( 'Apply all of fees', 'easy-woocommerce-discounts' ),
									),
								),
								'checkout_fee_limit_type' => array(
									'id'      => 'checkout_fee_limit_type',
									'name'    => __( 'Checkout Fee Limit Type', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'no_limit',
									'optgroups' => array(
										array(
											'optgroup' => __( 'No Limit', 'easy-woocommerce-discounts' ),
											'options'  => array(
												'no_limit' => __( 'No fee limit', 'easy-woocommerce-discounts' ),
											),
										),
										array(
											'optgroup' => __( 'Total Fee Limit', 'easy-woocommerce-discounts' ),
											'options'  => array(
												'total_price_limit'      => sprintf( __( 'Total fee limit %s', 'easy-woocommerce-discounts' ), get_woocommerce_currency_symbol() ),
												'total_percentage_limit' => sprintf( __( 'Total fee limit %s', 'easy-woocommerce-discounts' ), '%' ),
											),
										),
									),
								),
								'checkout_fee_limit' => array(
									'id'   => 'checkout_fee_limit',
									'name' => __( 'Checkout Fee Limit', 'easy-woocommerce-discounts' ),
									'type' => 'number',
									'step' => '0.1',
									'std'  => '',
								),
								'checkout_fee_tax_class' => array(
									'id'      => 'checkout_fee_tax_class',
									'name'    => __( 'Checkout Fee Tax Class', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'not_taxable',
									'options' => array(
										'not_taxable' => __( 'Is not taxable', 'easy-woocommerce-discounts' ),
										'inherit'     => __( 'Checkout fee tax class based on cart items', 'easy-woocommerce-discounts' ),
									) + wc_get_product_tax_class_options(),
								),
								'checkout_fee_display_multiple_fees' => array(
									'id'      => 'checkout_fee_display_multiple_fees',
									'name'    => __( 'Display Multiple Fees', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'separate',
									'options' => array(
										'separate' => __( 'Display all fees separately', 'easy-woocommerce-discounts' ),
										'combine'  => __( 'Combine to one total fee', 'easy-woocommerce-discounts' ),
									),
								),
								'checkout_fee_label' => array(
									'id'   => 'checkout_fee_label',
									'name' => __( 'Checkout Fee Label', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Name of the fee showed in the cart totals block as a checkout fee title when using the combine mode for display multiple fees.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Fee', 'easy-woocommerce-discounts' ),
								),
							)
						),
						'shipping' => apply_filters( 'wccs_settings_general_shipping_section',
							array(
								'shipping_enabled' => array(
									'id'      => 'shipping_enabled',
									'name'    => __( 'Enabled', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Enables WooCommerce Dynamic Shipping.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'yes',
									'options' => array(
										'yes' => __( 'Yes', 'easy-woocommerce-discounts' ),
										'no'  => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'hide_on_free_shipping' => array(
									'id'      => 'hide_on_free_shipping',
									'name'    => __( 'Hide On Free Shipping', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'When a free shipping method is available hide other shipping methods.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'no',
									'options' => array(
										'yes' => __( 'Yes', 'easy-woocommerce-discounts' ),
										'no'  => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
							)
						),
					)
				),
				'promotion' => apply_filters( 'wccs_settings_promotion',
					array(
						'quantity-table' => apply_filters( 'wccs_settings_promotion_quantity_table_section',
							array(
								'display_quantity_table' => array(
									'id'      => 'display_quantity_table',
									'name'    => __( 'Display Quantity Table', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'quantity_table_title' => array(
									'id'   => 'quantity_table_title',
									'name' => __( 'Title of Quantity Table', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Discount per Quantity', 'easy-woocommerce-discounts' ),
								),
								'quantity_table_position' => array(
									'id' => 'quantity_table_position',
									'name' => __( 'Quantity Table Position', 'easy-woocommerce-discounts' ),
									'type' => 'select',
									'std'  => 'before_add_to_cart_button',
									'options' => array(
										'before_add_to_cart_button' => __( 'Before "Add to cart" button', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_button'  => __( 'After "Add to cart" button', 'easy-woocommerce-discounts' ),
										'before_add_to_cart_form'   => __( 'Before "Add to cart" form', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_form'    => __( 'After "Add to cart" form', 'easy-woocommerce-discounts' ),
										'before_excerpt'            => __( 'Before product excerpt', 'easy-woocommerce-discounts' ),
										'after_excerpt'             => __( 'After product excerpt', 'easy-woocommerce-discounts' ),
										'after_product_meta'        => __( 'After product meta', 'easy-woocommerce-discounts' ),
										'none'                      => __( 'None', 'easy-woocommerce-discounts' ),
										// 'in_modal'                  => __( 'In a modal', 'easy-woocommerce-discounts' ),
									),
								),
								'quantity_table_layout' => array(
									'id'      => 'quantity_table_layout',
									'name'    => __( 'Quantity Table Layout', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'bulk-pricing-table-horizontal',
									'options' => array(
										'bulk-pricing-table-horizontal' => __( 'Horizontal', 'easy-woocommerce-discounts' ),
										'bulk-pricing-table-vertical'   => __( 'Vertical', 'easy-woocommerce-discounts' ),
									),
								),
								'quantity_table_stock_management' => array(
									'id'      => 'quantity_table_stock_management',
									'name'    => __( 'Stock Management', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Do not display quantity table when the product available stock quantity is less or equal to 1.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 0,
									'options' => array(
										1 => __( 'Enabled', 'easy-woocommerce-discounts' ),
										0 => __( 'Disabled', 'easy-woocommerce-discounts' ),
									),
								),
								'bulk_pricing_change_price' => array(
									'id'      => 'bulk_pricing_change_price',
									'name'    => __( 'Change Price', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'The client side may be faster, but the server side is more accurate, especially when considering the quantities within the cart.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'client',
									'options' => array(
										'client' => __( 'Client side', 'easy-woocommerce-discounts' ),
										'server' => __( 'Server side', 'easy-woocommerce-discounts' ),
									),
								),
								'variation_pirce_update' => array(
									'id'      => 'variation_pirce_update',
									'name'    => __( 'Variation Price Update', 'easy-woocommerce-discounts' ),
									'desc'    => __( "When enabled, the product price will automatically update to reflect the selected variation's price when a variation is chosen on the product page.", 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 0,
									'options' => array(
										1 => __( 'Enabled', 'easy-woocommerce-discounts' ),
										0 => __( 'Disabled', 'easy-woocommerce-discounts' ),
									),
								),
							)
						),
						'live-price' => apply_filters( 'wccs_settings_promotion_live_price_section',
							array(
								'live_pricing_display' => array(
									'id'      => 'live_pricing_display',
									'name'    => __( 'Display Live Price', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'If enabled displays product discounted price in the product page.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'live_pricing_total_display' => array(
									'id'      => 'live_pricing_total_display',
									'name'    => __( 'Display Live Total Price', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf( __( 'If enabled displays product discounted total price in the product page.%1$s Note: It calculates total price based on %2$slive price * quantity%3$s.', 'easy-woocommerce-discounts' ), '<br>', '<span class="wccs-settings-notice-message">', '</span>' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'live_pricing_display_type' => array(
									'id'      => 'live_pricing_display_type',
									'name'    => __( 'Display Type', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf( __( 'Discount Available: Displays live price when a discount available for the product.%1$s Always: Displays live price always.', 'easy-woocommerce-discounts' ), '<br>' ),
									'type'    => 'select',
									'std'     => 'discount_available',
									'options' => array(
										'discount_available' => __( 'Discount Available', 'easy-woocommerce-discounts' ),
										'always'             => __( 'Always', 'easy-woocommerce-discounts' ),
									),
								),
								'live_pricing_position' => array(
									'id'      => 'live_pricing_position',
									'name'    => __( 'Live Price Position', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'before_add_to_cart_button',
									'options' => array(
										'before_add_to_cart_button' => __( 'Before "Add to cart" button', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_button'  => __( 'After "Add to cart" button', 'easy-woocommerce-discounts' ),
										'before_add_to_cart_form'   => __( 'Before "Add to cart" form', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_form'    => __( 'After "Add to cart" form', 'easy-woocommerce-discounts' ),
										'before_excerpt'            => __( 'Before product excerpt', 'easy-woocommerce-discounts' ),
										'after_excerpt'             => __( 'After product excerpt', 'easy-woocommerce-discounts' ),
										'after_product_meta'        => __( 'After product meta', 'easy-woocommerce-discounts' ),
										'none'                      => __( 'None', 'easy-woocommerce-discounts' ),
										// 'in_modal'                  => __( 'In a modal', 'easy-woocommerce-discounts' ),
									),
								),
								'live_pricing_label' => array(
									'id'   => 'live_pricing_label',
									'name' => __( 'Live Pricing Label', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Label of the live price.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Your Price', 'easy-woocommerce-discounts' ),
								),
								'live_pricing_total_label' => array(
									'id'   => 'live_pricing_total_label',
									'name' => __( 'Live Total Price Label', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Label of the live total price.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Total Price', 'easy-woocommerce-discounts' ),
								),
								'live_pricing_calculation' => array(
									'id'      => 'live_pricing_calculation',
									'name'    => __( 'Live Price Calculation', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf( __( 'Calculate in the cart price: considers product quantities that are already in the cart when calculating live price value.%1$s Calculate current quantity price: Does not consider product quantities that are already in the cart when calculating live price value.', 'easy-woocommerce-discounts' ), '<br>' ),
									'type'    => 'select',
									'std'     => 'cart_price',
									'options' => array(
										'cart_price'             => __( 'Calculate in the cart price', 'easy-woocommerce-discounts' ),
										'current_quantity_price' => __( 'Calculate current quantity price', 'easy-woocommerce-discounts' ),
									),
								),
							)
						),
						'messages' => apply_filters( 'wccs_settings_promotion_messages_section',
							array(
								'purchase_x_receive_y_message_display' => array(
									'id'      => 'purchase_x_receive_y_message_display',
									'name'    => __( 'Display Message', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Display pricing rule message in the product page.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'purchase_x_receive_y_message_position' => array(
									'id'      => 'purchase_x_receive_y_message_position',
									'name'    => __( 'Message Position', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'before_add_to_cart_button',
									'options' => array(
										'before_add_to_cart_button' => __( 'Before "Add to cart" button', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_button'  => __( 'After "Add to cart" button', 'easy-woocommerce-discounts' ),
										'before_add_to_cart_form'   => __( 'Before "Add to cart" form', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_form'    => __( 'After "Add to cart" form', 'easy-woocommerce-discounts' ),
										'before_excerpt'            => __( 'Before product excerpt', 'easy-woocommerce-discounts' ),
										'after_excerpt'             => __( 'After product excerpt', 'easy-woocommerce-discounts' ),
										'after_product_meta'        => __( 'After product meta', 'easy-woocommerce-discounts' ),
										'none'                      => __( 'None', 'easy-woocommerce-discounts' ),
										// 'in_modal'                  => __( 'In a modal', 'easy-woocommerce-discounts' ),
									),
								),
								'purchase_message_background_color' => array(
									'id'   => 'purchase_message_background_color',
									'name' => __( 'Message Background Color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Default background color of messages in the message box.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#0f834d',
								),
								'purchase_message_color' => array(
									'id'   => 'purchase_message_color',
									'name' => __( 'Message Color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Default color of messages in the message box.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#fff',
								),
							)
						),
						'total-discounts' => apply_filters( 'wccs_settings_promotion_total_discounts_section',
							array(
								'display_total_discounts' => array(
									'id'      => 'display_total_discounts',
									'name'    => __( 'Display Total Discounts', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'If enabled displays total discounts that the user get for the current order in the cart and checkout pages.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 0,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'total_discounts_label' => array(
									'id'   => 'total_discounts_label',
									'name' => __( 'Total Discounts Label', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Label of the total discounts.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Total Discounts', 'easy-woocommerce-discounts' ),
								),
								'total_discounts_position_cart' => array(
									'id'      => 'total_discounts_position_cart',
									'name'    => __( 'Position In Cart', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'woocommerce_cart_totals_before_order_total',
									'options' => array(
										'woocommerce_cart_totals_before_order_total' => __( 'Before order total', 'easy-woocommerce-discounts' ),
										'woocommerce_cart_totals_after_order_total'  => __( 'After order total', 'easy-woocommerce-discounts' ),
										'none'                                       => __( 'None', 'easy-woocommerce-discounts' ),
									),
								),
								'total_discounts_position_checkout' => array(
									'id'      => 'total_discounts_position_checkout',
									'name'    => __( 'Position In Checkout', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'woocommerce_review_order_before_order_total',
									'options' => array(
										'woocommerce_review_order_before_order_total' => __( 'Before order total', 'easy-woocommerce-discounts' ),
										'woocommerce_review_order_after_order_total'  => __( 'After order total', 'easy-woocommerce-discounts' ),
										'none'                                        => __( 'None', 'easy-woocommerce-discounts' ),
									),
								),
								'total_discounts_include_cart_discounts' => array(
									'id'      => 'total_discounts_include_cart_discounts',
									'name'    => __( 'Include Cart Discounts And Coupons', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'If enabled cart discounts and cart coupons amount will be included in the total discounts.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
							)
						),
						'countdown-timer' => apply_filters( 'wccs_settings_promotion_countdown_timer_section',
							array(
								'display_countdown_timer' => array(
									'id'      => 'display_countdown_timer',
									'name'    => __( 'Display Countdown Timer', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf(
										__(
											'If enabled displays Countdown Timer in the product page.%1$s' .
											'When a discount available: If the product has a pricing rule with a date-time condition then show the countdown timer based on <strong>Threshold Time Value</strong>.%1$s' .
											'When a discount can apply: If the product has a pricing rule with a date-time condition then check all of that rule conditions and if all conditions are true then show the countdown timer based on <strong>Threshold Time Value</strong>.',
											'easy-woocommerce-discounts'
										),
										'<br>'
									),
									'type'    => 'select',
									'std'     => 0,
									'options' => array(
										1 => __( 'Yes - When a discount available', 'easy-woocommerce-discounts' ),
										2 => __( 'Yes - When a discount can apply', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'countdown_timer_position' => array(
									'id'      => 'countdown_timer_position',
									'name'    => __( 'Countdown Timer Position', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'before_add_to_cart_button',
									'options' => array(
										'before_add_to_cart_button' => __( 'Before "Add to cart" button', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_button'  => __( 'After "Add to cart" button', 'easy-woocommerce-discounts' ),
										'before_add_to_cart_form'   => __( 'Before "Add to cart" form', 'easy-woocommerce-discounts' ),
										'after_add_to_cart_form'    => __( 'After "Add to cart" form', 'easy-woocommerce-discounts' ),
										'before_excerpt'            => __( 'Before product excerpt', 'easy-woocommerce-discounts' ),
										'after_excerpt'             => __( 'After product excerpt', 'easy-woocommerce-discounts' ),
										'after_product_meta'        => __( 'After product meta', 'easy-woocommerce-discounts' ),
										'none'                      => __( 'None', 'easy-woocommerce-discounts' ),
										// 'in_modal'                  => __( 'In a modal', 'easy-woocommerce-discounts' ),
									),
								),
								'countdown_timer_threshold_time' => array(
									'id'   => 'countdown_timer_threshold_time',
									'name' => __( 'Threshold Time Value', 'easy-woocommerce-discounts' ),
									'desc' => sprintf( __( 'Display Countdown Timer when its close to the end time of the rule.%1$s e.g: If its value is 1 and "Threshold Time Type" is "Day" Countdown Timer will display before 1day to the end time of the rule.', 'easy-woocommerce-discounts' ), '<br>' ),
									'type' => 'number',
									'std'  => '1',
								),
								'countdown_timer_threshold_time_type' => array(
									'id'   => 'countdown_timer_threshold_time_type',
									'name' => __( 'Threshold Time Type', 'easy-woocommerce-discounts' ),
									'desc' => sprintf( __( 'e.g: If its value is "Day" and "Threshold Time Value" is "1" Countdown Timer will display before 1day to the end time of the rule.%1$s Note: Please note that when its value is "No Limit" Countdown Timer will display always if pricing rule has date/time condition.', 'easy-woocommerce-discounts' ), '<br>' ),
									'type' => 'select',
									'std'  => 'no_limit',
									'options' => array(
										'no_limit' => __( 'No Limit', 'easy-woocommerce-discounts' ),
										'minute'   => __( 'Minute', 'easy-woocommerce-discounts' ),
										'hour'     => __( 'Hour', 'easy-woocommerce-discounts' ),
										'day'      => __( 'Day', 'easy-woocommerce-discounts' ),
										'week'     => __( 'Week', 'easy-woocommerce-discounts' ),
										'month'    => __( 'Month', 'easy-woocommerce-discounts' ),
									),
								),
								'countdown_timer_title' => array(
									'id'   => 'countdown_timer_title',
									'name' => __( 'Title', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Title of Countdown Timer.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Limited-time offer! Sale ends in', 'easy-woocommerce-discounts' ),
								),
								'countdown_timer_days_label' => array(
									'id'   => 'countdown_timer_days_label',
									'name' => __( 'Days', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Days label in Countdown Timer heading.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Days', 'easy-woocommerce-discounts' ),
								),
								'countdown_timer_hours_label' => array(
									'id'   => 'countdown_timer_hours_label',
									'name' => __( 'Hours', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Hours label in Countdown Timer heading.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Hours', 'easy-woocommerce-discounts' ),
								),
								'countdown_timer_minutes_label' => array(
									'id'   => 'countdown_timer_minutes_label',
									'name' => __( 'Minutes', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Minutes label in Countdown Timer heading.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Minutes', 'easy-woocommerce-discounts' ),
								),
								'countdown_timer_seconds_label' => array(
									'id'   => 'countdown_timer_seconds_label',
									'name' => __( 'Seconds', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Seconds label in Countdown Timer heading.', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Seconds', 'easy-woocommerce-discounts' ),
								),
								'countdown_timer_title_fontsize' => array(
									'id'   => 'countdown_timer_title_fontsize',
									'name' => __( 'Title Font Size', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Countdown Timer title font-size.', 'easy-woocommerce-discounts' ),
									'type' => 'number',
									'std'  => '20',
								),
								'countdown_timer_title_color' => array(
									'id'   => 'countdown_timer_title_color',
									'name' => __( 'Title color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Countdown Timer title color.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#000000',
								),
								'countdown_timer_heading_color' => array(
									'id'   => 'countdown_timer_heading_color',
									'name' => __( 'Heading color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Days, Hours, Minutes, Seconds text color.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#000000',
								),
								'countdown_timer_rotor_top_color' => array(
									'id'   => 'countdown_timer_rotor_top_color',
									'name' => __( 'Rotor top color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Color of rotor top.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#FFFFFF',
								),
								'countdown_timer_rotor_top_background_color' => array(
									'id'   => 'countdown_timer_rotor_top_background_color',
									'name' => __( 'Rotor top background color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Background color of rotor top.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#151515',
								),
								'countdown_timer_rotor_bottom_color' => array(
									'id'   => 'countdown_timer_rotor_bottom_color',
									'name' => __( 'Rotor bottom color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Color of rotor bottom.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#EFEFEF',
								),
								'countdown_timer_rotor_bottom_background_color' => array(
									'id'   => 'countdown_timer_rotor_bottom_background_color',
									'name' => __( 'Rotor bottom background color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Background color of rotor bottom.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#202020',
								),
								'countdown_timer_hinge_color' => array(
									'id'   => 'countdown_timer_hinge_color',
									'name' => __( 'Hinge color', 'easy-woocommerce-discounts' ),
									'desc' => __( 'Hinge color of rotor.', 'easy-woocommerce-discounts' ),
									'type' => 'color',
									'std'  => '#151515',
								),
							)
						),
						'discount-page' => apply_filters( 'wccs_settings_promotion_discount_page_section',
							array(
								'show_discount_page' => array(
									'id'      => 'show_discount_page',
									'name'    => __( 'Show Discount Page', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Showing discount page in cart and checkout pages as a notice to user.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 'discounts_available',
									'options' => array(
										'always'              => __( 'Always', 'easy-woocommerce-discounts' ),
										'discounts_available' => __( 'Discounts available', 'easy-woocommerce-discounts' ),
										'never'               => __( 'Never', 'easy-woocommerce-discounts' ),
									),
								),
								'discount_page' => array(
									'id'      => 'discount_page',
									'name'    => __( 'Discount Page', 'easy-woocommerce-discounts' ),
									'desc'    => __( 'Discount page that shows discounted products.', 'easy-woocommerce-discounts' ),
									'type'    => 'select',
									'std'     => 0,
									'options' => array( 0 => 'Not Selected' ) + $pages,
								),
								'cart_discount_page_message' => array(
									'id'   => 'cart_discount_page_message',
									'name' => __( 'Discount Page Message', 'easy-woocommerce-discounts' ),
									'desc' => __( 'This message will show in the cart page.', 'easy-woocommerce-discounts' ) . '</br>' .
												sprintf( __( 'Example Message : Visit our %s to view new discounted products based on your cart.', 'easy-woocommerce-discounts' ), '"[discount_page]"' ) . '</br>' .
												sprintf( __( '%s is a placeholder for discount page url and should be exists in the message.', 'easy-woocommerce-discounts' ), '"[discount_page]"' ),
									'type' => 'textarea',
									'std'  => sprintf( __( 'Visit our %1$s to view new discounted products based on your cart.', 'easy-woocommerce-discounts' ), '[discount_page]' ),
								),
								'discount_page_title' => array(
									'id'   => 'discount_page_title',
									'name' => __( 'Discount Page Title', 'easy-woocommerce-discounts' ),
									'desc' => sprintf( __( 'This title will replace with %s placeholder in discount page message.', 'easy-woocommerce-discounts' ), '"[discount_page]"' ),
									'type' => 'text',
									'std'  => __( 'Discount Page', 'easy-woocommerce-discounts' ),
								),
							)
						)
					)
				),
				'localization' => apply_filters( 'wccs_settings_localization',
					array(
						'main' => apply_filters( 'wccs_settings_localization_main_section',
							array(
								'localization_enabled' => array(
									'id'      => 'localization_enabled',
									'name'    => __( 'Enabled', 'easy-woocommerce-discounts' ),
									'desc'    => sprintf( __( 'Enable it to use local texts or labels.%1$s Note: Disable it on a multilingual site and just translate texts that appears in front-end of your site which are exists in the langulage file of the plugin.', 'easy-woocommerce-discounts' ), '</br>' ),
									'type'    => 'select',
									'std'     => 1,
									'options' => array(
										1 => __( 'Yes', 'easy-woocommerce-discounts' ),
										0 => __( 'No', 'easy-woocommerce-discounts' ),
									),
								),
								'price_label' => array(
									'id'   => 'price_label',
									'name' => __( 'Price', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Price', 'easy-woocommerce-discounts' ),
								),
								'quantity_label' => array(
									'id'   => 'quantity_label',
									'name' => __( 'Quantity', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Quantity', 'easy-woocommerce-discounts' ),
								),
								'discount_label' => array(
									'id'   => 'discount_label',
									'name' => __( 'Discount', 'easy-woocommerce-discounts' ),
									'type' => 'text',
									'std'  => __( 'Discount', 'easy-woocommerce-discounts' ),
								),
							)
						),
					)
				),
				'performance' => apply_filters( 'wccs_settings_performance',
					array(
						'main' => apply_filters( 'wccs_settings_performance_main_section', array(
							'update_products_price' => array(
								'id'      => 'update_products_price',
								'name'    => __( 'Update Products Price', 'easy-woocommerce-discounts' ),
								'desc'    => __( 'Cache products price based on simple pricing rules to improve performance in the front-end.', 'easy-woocommerce-discounts' ),
								'type'    => 'link',
								'url'     => esc_url( wp_nonce_url( add_query_arg( 'do_update_products_price_asnp_wccs', '1' ), 'wccs_update_products_price_nonce', '_wccs_update_products_price_nonce' ) ),
								'classes' => 'button',
							),
							'auto_update_products_price' => array(
								'id'      => 'auto_update_products_price',
								'name'    => __( 'Auto Update Products Price', 'easy-woocommerce-discounts' ),
								'desc'    => __( 'Automatically update products price in the background when a new rule added or the plugin settings updated or WooCommerce settings updated or a product added or updated.', 'easy-woocommerce-discounts' ),
								'type'    => 'select',
								'std'     => 0,
								'options' => array(
									1 => __( 'Yes', 'easy-woocommerce-discounts' ),
									0 => __( 'No', 'easy-woocommerce-discounts' ),
								),
							),
							'enable_analytics' => array(
								'id'      => 'enable_analytics',
								'name'    => __( 'Enable Analytics', 'easy-woocommerce-discounts' ),
								'desc'    => __( 'Enable/Disable Analytics', 'easy-woocommerce-discounts' ),
								'type'    => 'select',
								'std'     => 1,
								'options' => array(
									1 => __( 'Yes', 'easy-woocommerce-discounts' ),
									0 => __( 'No', 'easy-woocommerce-discounts' ),
								),
							),
							'cache_prices' => array(
								'id'      => 'cache_prices',
								'name'    => __( 'Cache Prices', 'easy-woocommerce-discounts' ),
								'desc'    => sprintf( __( "Enable/Disable price caching.%s <strong>Note:</strong> Disabling this feature can potentially reduce your website's loading speed.", 'easy-woocommerce-discounts' ), '<br>' ),
								'type'    => 'select',
								'std'     => 1,
								'options' => array(
									1 => __( 'Yes', 'easy-woocommerce-discounts' ),
									0 => __( 'No', 'easy-woocommerce-discounts' ),
								),
							),
							'cache_quantity_table' => array(
								'id'      => 'cache_quantity_table',
								'name'    => __( 'Cache Quantity Table', 'easy-woocommerce-discounts' ),
								'desc'    => __( 'Enable/Disable quantity table caching.', 'easy-woocommerce-discounts' ),
								'type'    => 'select',
								'std'     => 1,
								'options' => array(
									1 => __( 'Yes', 'easy-woocommerce-discounts' ),
									0 => __( 'No', 'easy-woocommerce-discounts' ),
								),
							),
							'cache_messages' => array(
								'id'      => 'cache_messages',
								'name'    => __( 'Cache Messages', 'easy-woocommerce-discounts' ),
								'desc'    => __( 'Enable/Disable pricing messages caching.', 'easy-woocommerce-discounts' ),
								'type'    => 'select',
								'std'     => 1,
								'options' => array(
									1 => __( 'Yes', 'easy-woocommerce-discounts' ),
									0 => __( 'No', 'easy-woocommerce-discounts' ),
								),
							),
							'cache_onsale_badge' => array(
								'id'      => 'cache_onsale_badge',
								'name'    => __( 'Cache Onsale Badge', 'easy-woocommerce-discounts' ),
								'desc'    => __( 'Enable/Disable onsale badge caching.', 'easy-woocommerce-discounts' ),
								'type'    => 'select',
								'std'     => 1,
								'options' => array(
									1 => __( 'Yes', 'easy-woocommerce-discounts' ),
									0 => __( 'No', 'easy-woocommerce-discounts' ),
								),
							),
							'disable_calculate_cart_totals' => array(
								'id'      => 'disable_calculate_cart_totals',
								'name'    => __( 'Disable Calculate Cart Totals', 'easy-woocommerce-discounts' ),
								'desc'    => sprintf( __( 'Disable calculate cart totals option to fix compatibility issues with some plugins.%1$s %2$sOnly use it when necessary because it can cause issues for mini-cart prices and pricing rules that are using subtotal conditions.%3$s', 'easy-woocommerce-discounts' ), '<br>', '<span style="color: red;">', '</span>' ),
								'type'    => 'select',
								'std'     => 0,
								'options' => array(
									1 => __( 'Yes', 'easy-woocommerce-discounts' ),
									0 => __( 'No', 'easy-woocommerce-discounts' ),
								),
							),
							'search_items_limit' => array(
								'id'   => 'search_items_limit',
								'name' => __( 'Search Items Limit', 'easy-woocommerce-discounts' ),
								'desc' => sprintf( __( 'Limit search result of search inputs in the admin.%1$s Set it to %2$s-1%3$s for no limit.', 'easy-woocommerce-discounts' ), '<br>', '<code>', '</code>' ),
								'type' => 'number',
								'std'  => '20',
								'min'  => '-1',
							),
						) ),
					)
				),
				'licenses' => apply_filters( 'wccs_settings_licenses',
					array(
						'main' => apply_filters( 'wccs_settings_licenses_main_section', array(
                            'license_key' => array(
                                'id'      => 'license_key',
                                'name'    => __( 'License/Purchase Key', 'easy-woocommerce-discounts' ),
                                'desc'    => __( 'Enter your license key to enable and receive automatic updates.', 'easy-woocommerce-discounts' ),
                                'type'    => 'text',
                                'size'    => 'regular',
                            ),
                            'license_desc' => array(
                                'id' => 'license_desc',
                                'name' => 'Notice',
                                'desc' => '<p><strong>Each website using this plugin needs a legal license (1 license = 1 website).</strong><br>
                                           You can find more information on <a href="https://www.asanaplugins.com/software-license-agreement/" target="_blank">software license agreement</a>.<br>
                                           If you need to buy a new license of this plugin, <a href="https://www.asanaplugins.com/product/advanced-woocommerce-dynamic-pricing-discounts/" target="_blank">click here</a>.</p>
                                           <p><a href="http://asanaplugins.com/knowledgebase/enable-updates/" target="_blank">Where can I find my license key?</a></p>',
                                'type' => 'info',
                            ),
                        ) ),
					)
				),
			)
		);
	}

}

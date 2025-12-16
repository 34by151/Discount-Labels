<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * @link       taher.atashbar@gmail.com
 * @since      1.0.0
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/public
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WCCS_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	private $loader;

	/**
	 * Service container of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var   WCCS_Service_Manager
	 */
	private $services;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string      $plugin_name The name of the plugin.
	 * @param string      $version     The version of this plugin.
	 * @param WCCS_Loader $loader
	 */
	public function __construct( $plugin_name, $version, WCCS_Loader $loader, WCCS_Service_Manager $services ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->loader = $loader;
		$this->services = $services;

		$this->load_dependencies();
	}

	/**
	 * Load dependencies required in admin area.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function load_dependencies() {
		/**
		 * The controller class of public area.
		 */
		require_once dirname( __FILE__ ) . '/class-wccs-public-controller.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-products-list.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-cart-discount-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-pricing-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-checkout-fee-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-cart-item-pricing.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-product-pricing.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-shipping-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-total-discounts-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-auto-add-to-cart.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-order-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-public-analytics-hooks.php';

		// Shortcodes.
		require_once dirname( __FILE__ ) . '/shortcodes/class-wccs-shortcode-products-list.php';
		require_once dirname( __FILE__ ) . '/shortcodes/class-wccs-shortcode-discounted-products.php';
		require_once dirname( __FILE__ ) . '/shortcodes/class-wccs-shortcode-bulk-table.php';
		require_once dirname( __FILE__ ) . '/shortcodes/class-wccs-shortcode-live-price.php';
		require_once dirname( __FILE__ ) . '/shortcodes/class-wccs-shortcode-purchase-message.php';
		require_once dirname( __FILE__ ) . '/shortcodes/class-wccs-shortcode-countdown-timer.php';
		require_once dirname( __FILE__ ) . '/shortcodes/class-wccs-shortcode-sale-flash.php';
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function define_hooks() {
		if ( WCCS()->is_request( 'frontend' ) ) {
			$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_styles' );
			$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_scripts' );
			$this->loader->add_action( 'wp_head', $this, 'custom_styles' );

			$pricing_hooks = new WCCS_Public_Pricing_Hooks( $this->loader );
			$pricing_hooks->init();

			$this->services->set( 'WCCS_Public_Cart_Discount_Hooks', new WCCS_Public_Cart_Discount_Hooks( $this->loader ) );
			$this->services->set( 'WCCS_Public_Pricing_Hooks', $pricing_hooks );
			$this->services->set( 'WCCS_Public_Checkout_Fee_Hooks', new WCCS_Public_Checkout_Fee_Hooks( $this->loader ) );
			$this->services->set( 'WCCS_Public_Auto_Add_To_Cart', new WCCS_Public_Auto_Add_To_Cart( $this->loader ) );

			if ( (int) WCCS()->settings->get_setting( 'display_total_discounts', 0 ) ) {
				$this->services->set( 'WCCS_Public_Total_Discounts_Hooks', new WCCS_Public_Total_Discounts_Hooks( $this->loader ) );
			}

			if ( (int) WCCS()->settings->get_setting( 'enable_analytics', 1 ) ) {
				$analytics = new WCCS_Pubilc_Analytics_Hooks();
				$this->services->set( 'WCCS_Pubilc_Analytics_Hooks', $analytics );
				$analytics->init();
			}

			WCCS_Public_Order_Hooks::init();

			// Shortcodes.
			$this->loader->add_shortcode( 'wccs_products_list', new WCCS_Shortcode_Products_List(), 'output' );
			$this->loader->add_shortcode( 'wccs_discounted_products', new WCCS_Shortcode_Discounted_Products(), 'output' );
			$this->loader->add_shortcode( 'wccs_bulk_table', new WCCS_Shortcode_Bulk_Table(), 'output' );
			$this->loader->add_shortcode( 'wccs_live_price', new WCCS_Shortcode_Live_Price(), 'output' );
			$this->loader->add_shortcode( 'wccs_purchase_message', new WCCS_Shortcode_Purchase_Message(), 'output' );
			$this->loader->add_shortcode( 'wccs_countdown_timer', new WCCS_Shortcode_Countdown_Timer(), 'output' );
			$this->loader->add_shortcode( 'wccs_sale_flash', new WCCS_Shortcode_Sale_Flash(), 'output' );
		}

		$this->services->set( 'WCCS_Public_Shipping_Hooks', new WCCS_Public_Shipping_Hooks( $this->loader ) );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( WCCS_Helpers::is_product_page() || is_cart() ) {
			wp_enqueue_style( 'wccs-public', plugin_dir_url( __FILE__ ) . 'css/wccs-public' . $suffix . '.css' );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		global $post;

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( WCCS_Helpers::is_product_page() ) {
			$display_countdown_timer = (int) WCCS()->settings->get_setting( 'display_countdown_timer', 0 );
			if ( $display_countdown_timer ) {
				wp_enqueue_script( 'flipdown', plugin_dir_url( __FILE__ ) . 'js/flipdown/flipdown.js', array(), '0.2.2', true );
				wp_enqueue_style( 'flipdown', plugin_dir_url( __FILE__ ) . 'css/flipdown/flipdown.css', array(), '0.2.2' );
			}

			$live_price_label = __( 'Your Price', 'easy-woocommerce-discounts' );
			$total_price_label = __( 'Total Price', 'easy-woocommerce-discounts' );
			$days_label = __( 'Days', 'easy-woocommerce-discounts' );
			$hours_label = __( 'Hours', 'easy-woocommerce-discounts' );
			$minutes_label = __( 'Minutes', 'easy-woocommerce-discounts' );
			$seconds_label = __( 'Seconds', 'easy-woocommerce-discounts' );
			if ( (int) WCCS()->settings->get_setting( 'localization_enabled', 1 ) ) {
				$live_price_label = WCCS()->settings->get_setting( 'live_pricing_label', $live_price_label );
				$total_price_label = WCCS()->settings->get_setting( 'live_pricing_total_label', $total_price_label );
				$days_label = WCCS()->settings->get_setting( 'countdown_timer_days_label', $days_label );
				$hours_label = WCCS()->settings->get_setting( 'countdown_timer_hours_label', $hours_label );
				$minutes_label = WCCS()->settings->get_setting( 'countdown_timer_minutes_label', $minutes_label );
				$seconds_label = WCCS()->settings->get_setting( 'countdown_timer_seconds_label', $seconds_label );
			}

			wp_enqueue_script( 'wccs-product-pricing', plugin_dir_url( __FILE__ ) . 'js/wccs-product-pricing' . $suffix . '.js', array( 'jquery' ), $this->version, true );
			wp_localize_script(
				'wccs-product-pricing',
				'wccs_product_pricing_params',
				apply_filters(
					'wccs_product_pricing_params',
					array(
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
						'nonce' => wp_create_nonce( 'wccs_single_product_nonce' ),
						'product_id' => $post->ID,
						'display_live_price' => WCCS()->settings->get_setting( 'live_pricing_display', 1 ),
						'live_price_display_type' => WCCS()->settings->get_setting( 'live_pricing_display_type', 'discount_available' ),
						'live_pricing_label' => apply_filters( 'wccs_live_price_label', $live_price_label ),
						'display_live_total_price' => WCCS()->settings->get_setting( 'live_pricing_total_display', 1 ),
						'live_pricing_total_label' => apply_filters( 'wccs_live_total_price_label', $total_price_label ),
						'display_countdown_timer' => $display_countdown_timer,
						'countdown_timer_days' => apply_filters( 'wccs_countdown_timer_days_label', $days_label ),
						'countdown_timer_hours' => apply_filters( 'wccs_countdown_timer_hours_label', $hours_label ),
						'countdown_timer_minutes' => apply_filters( 'wccs_countdown_timer_minutes_label', $minutes_label ),
						'countdown_timer_seconds' => apply_filters( 'wccs_countdown_timer_seconds_label', $seconds_label ),
						'set_min_quantity' => (int) WCCS()->settings->get_setting( 'set_min_quantity', 0 ),
						'bulk_pricing_change_price' => WCCS()->settings->get_setting( 'bulk_pricing_change_price', 'client' ),
						'variation_pirce_update' => WCCS()->settings->get_setting( 'variation_pirce_update', 0 ),
						'analytics' => (int) WCCS()->settings->get_setting( 'enable_analytics', 1 ),
					)
				)
			);
		} elseif ( is_checkout() ) {
			wp_enqueue_script( 'wccs-checkout', plugin_dir_url( __FILE__ ) . 'js/wccs-checkout' . $suffix . '.js', array( 'jquery' ), $this->version, true );
		} elseif ( is_cart() ) {
			wp_enqueue_script( 'wccs-cart', plugin_dir_url( __FILE__ ) . 'js/wccs-cart' . $suffix . '.js', array( 'jquery' ), $this->version, true );
			wp_localize_script(
				'wccs-cart',
				'wccs_cart_params',
				array(
					'update_cart_on_shipping_change' => WCCS()->settings->get_setting( 'update_cart_on_shipping_change', 'disabled' ),
				)
			);
		}
	}

	public function custom_styles() {
		$custom_styles = $this->get_product_custom_styles();
		$custom_styles = apply_filters( 'wccs_custom_styles', $custom_styles );

		if ( ! empty( $custom_styles ) ) {
			echo "\n<style>\n" . $custom_styles . "\n</style>\n";
		}
	}

	protected function get_product_custom_styles() {
		if ( ! is_product() ) {
			return '';
		}

		$styles = '';
		$settings = WCCS()->settings;

		$countdown_timer_title_color = $settings->get_setting( 'countdown_timer_title_color', '' );
		$countdown_timer_title_fontsize = $settings->get_setting( 'countdown_timer_title_fontsize', '' );
		$countdown_timer_heading_color = $settings->get_setting( 'countdown_timer_heading_color', '' );
		$countdown_timer_rotor_top_color = $settings->get_setting( 'countdown_timer_rotor_top_color', '' );
		$countdown_timer_rotor_top_background_color = $settings->get_setting( 'countdown_timer_rotor_top_background_color', '' );
		$countdown_timer_rotor_bottom_color = $settings->get_setting( 'countdown_timer_rotor_bottom_color', '' );
		$countdown_timer_rotor_bottom_background_color = $settings->get_setting( 'countdown_timer_rotor_bottom_background_color', '' );
		$countdown_timer_hinge_color = $settings->get_setting( 'countdown_timer_hinge_color', '' );

		if ( ! empty( $countdown_timer_title_color ) ) {
			$styles .= '.wccs-countdown-timer-title {';
			$styles .= ' color: ' . $countdown_timer_title_color . '; ';
			$styles .= '}';
		}

		if ( ! empty( $countdown_timer_title_fontsize ) ) {
			$styles .= '.wccs-countdown-timer-title {';
			$styles .= ' font-size: ' . $countdown_timer_title_fontsize . 'px; ';
			$styles .= '}';
		}

		if ( ! empty( $countdown_timer_heading_color ) ) {
			$styles .= '.flipdown.flipdown__theme-dark .rotor-group-heading:before {';
			$styles .= ' color: ' . $countdown_timer_heading_color . '; ';
			$styles .= '}';
		}

		if ( ! empty( $countdown_timer_rotor_top_color ) ) {
			$styles .= '.flipdown.flipdown__theme-dark .rotor,
			.flipdown.flipdown__theme-dark .rotor-top,
			.flipdown.flipdown__theme-dark .rotor-leaf-front {';
			$styles .= ' color: ' . $countdown_timer_rotor_top_color . '; ';
			$styles .= '}';
		}

		if ( ! empty( $countdown_timer_rotor_top_background_color ) ) {
			$styles .= '.flipdown.flipdown__theme-dark .rotor,
			.flipdown.flipdown__theme-dark .rotor-top,
			.flipdown.flipdown__theme-dark .rotor-leaf-front {';
			$styles .= ' background-color: ' . $countdown_timer_rotor_top_background_color . '; ';
			$styles .= '}';
		}

		if ( ! empty( $countdown_timer_rotor_bottom_color ) ) {
			$styles .= '.flipdown.flipdown__theme-dark .rotor-bottom,
			.flipdown.flipdown__theme-dark .rotor-leaf-rear {';
			$styles .= ' color: ' . $countdown_timer_rotor_bottom_color . '; ';
			$styles .= '}';
		}

		if ( ! empty( $countdown_timer_rotor_bottom_background_color ) ) {
			$styles .= '.flipdown.flipdown__theme-dark .rotor-bottom,
			.flipdown.flipdown__theme-dark .rotor-leaf-rear {';
			$styles .= ' background-color: ' . $countdown_timer_rotor_bottom_background_color . '; ';
			$styles .= '}';
		}

		if ( ! empty( $countdown_timer_hinge_color ) ) {
			$styles .= '.flipdown.flipdown__theme-dark .rotor:after {';
			$styles .= ' border-color: ' . $countdown_timer_hinge_color . '; ';
			$styles .= '}';
		}

		return apply_filters( 'wccs_product_custom_styles', $styles );
	}

}

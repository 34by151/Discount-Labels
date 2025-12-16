<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Admin_Assets {

	protected $loader;

	protected $scripts = array();

	protected $styles = array();

	protected $localize_scripts = array();

	protected $menu;

	public function __construct( WCCS_Loader $loader, WCCS_Admin_Menu $menu ) {
		$this->loader = $loader;
		$this->menu = $menu;
	}

	public function init_hooks() {
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'load_scripts' );
	}

	protected function get_asset_url( $path ) {
		return apply_filters( 'wccs_get_admin_asset_url', plugins_url( $path, WCCS_PLUGIN_FILE ), $path );
	}

	protected function register_script( $handle, $path, $deps = array( 'jquery' ), $version = WCCS_VERSION, $in_footer = true ) {
		$this->scripts[] = $handle;
		wp_register_script( $handle, $path, $deps, $version, $in_footer );
	}

	protected function enqueue_script( $handle, $path = '', $deps = array( 'jquery' ), $version = WCCS_VERSION, $in_footer = true ) {
		if ( ! in_array( $handle, $this->scripts ) && $path ) {
			$this->register_script( $handle, $path, $deps, $version, $in_footer );
		}
		wp_enqueue_script( $handle );
	}

	protected function register_style( $handle, $path, $deps = array(), $version = WCCS_VERSION, $media = 'all', $has_rtl = false ) {
		$this->styles[] = $handle;
		wp_register_style( $handle, $path, $deps, $version, $media );

		if ( $has_rtl ) {
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}
	}

	protected function enqueue_style( $handle, $path = '', $deps = array(), $version = WCCS_VERSION, $media = 'all', $has_rtl = false ) {
		if ( ! in_array( $handle, $this->styles ) && $path ) {
			$this->register_style( $handle, $path, $deps, $version, $media, $has_rtl );
		}
		wp_enqueue_style( $handle );
	}

	public function load_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$screen = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$menus = $this->menu->get_menus();

		if ( in_array( $screen_id, array_values( $menus ) ) ) {
			$this->enqueue_style( 'wccs-admin', $this->get_asset_url( 'admin/css/wccs-admin' . $suffix . '.css' ) );
			$this->enqueue_style( 'wp-color-picker' );
		}

		if ( isset( $menus['wc_conditions'] ) && $menus['wc_conditions'] === $screen_id && ! WCCS_Updates::update_required() ) {
			$this->enqueue_style( 'select2', $this->get_asset_url( 'admin/css/select2/select2.css' ), array(), '4.0.3' );
			$this->enqueue_style( 'easy-woocommerce-discounts', $this->get_asset_url( 'admin/css/conditions/style.css' ) );
			$this->enqueue_style( 'wccs-font-awesome', $this->get_asset_url( 'admin/css/font-awesome.css' ), array(), '4.6.3' );
			$this->register_script( 'vue', $this->get_asset_url( 'admin/js/vendor/vue/vue.js' ), array(), '2.6.12' );
			$this->register_script( 'vue-router', $this->get_asset_url( 'admin/js/vendor/vue-router/vue-router.js' ), array( 'vue' ), '3.4.9' );
			$this->register_script( 'vuex', $this->get_asset_url( 'admin/js/vendor/vuex/vuex.js' ), array( 'vue' ), '3.6.0' );
			$this->register_script( 'sortable', $this->get_asset_url( 'admin/js/vendor/sortable/sortable.js' ), array(), '1.13.0' );
			$this->register_script( 'vuelidate', $this->get_asset_url( 'admin/js/vendor/vuelidate/vuelidate.js' ), array(), '0.7.6' );
			$this->register_script( 'ewd-shared', $this->get_asset_url( 'admin/js/shared/index.js' ), array(), WCCS_VERSION );
			$this->register_script( 'ewd-pages', $this->get_asset_url( 'admin/js/pages/index.js' ), array( 'vuex' ), WCCS_VERSION );
			$this->register_script( 'ewd-validators', $this->get_asset_url( 'admin/js/validators/index.js' ), array( 'vuelidate' ), WCCS_VERSION );

			WCCS_Helpers::register_polyfills();
			$this->enqueue_script(
				'easy-woocommerce-discounts',
				$this->get_asset_url( 'admin/js/conditions/index.js' ),
				array(
					'vue-router',
					'vuelidate',
					'moment',
					'lodash',
					version_compare( WC()->version, '10.3.0', '>=' ) ? 'wc-select2' : 'select2',
					'sortable',
					'wp-color-picker',
					'wp-hooks',
					'wp-i18n',
					'wp-api-fetch',
					'ewd-shared',
					'ewd-pages',
					'ewd-validators',
					version_compare( WC()->version, '10.3.0', '>=' ) ? 'wc-jquery-tiptip' : 'jquery-tiptip',
				),
				WCCS_VERSION,
				true
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'easy-woocommerce-discounts', 'easy-woocommerce-discounts', WCCS_PLUGIN_DIR . 'languages' );
			}
		} elseif ( isset( $menus['settings'] ) && $menus['settings'] === $screen_id ) {
			$this->enqueue_script( 'wccs-admin', $this->get_asset_url( 'admin/js/wccs-admin' . $suffix . '.js' ), array( 'wp-color-picker' ), WCCS_VERSION, true );
		} elseif ( 'dashboard' === $screen_id ) {
			$this->show_review();
		}

		$this->localize_scripts();
	}

	protected function localize_script( $handle ) {
		if ( ! in_array( $handle, $this->localize_scripts ) && wp_script_is( $handle ) && ( $data = $this->get_script_data( $handle ) ) ) {
			$name = 'easy-woocommerce-discounts' === $handle ? 'wcConditions' : str_replace( '-', '_', $handle ) . '_params';
			$this->localize_scripts[] = $handle;
			wp_localize_script( $handle, $name, apply_filters( $name, $data ) );
		}
	}

	protected function get_script_data( $handle ) {
		switch ( $handle ) {
			case 'easy-woocommerce-discounts':
				$wccs = WCCS();
				$wc_products = $wccs->products;

				return array(
					'nonce' => wp_create_nonce( 'wccs_conditions_nonce' ),
					'taxonomies' => $wc_products->get_custom_taxonomies(),
					'roles' => WCCS_Admin_Select_Data_Provider::get_roles(),
					'capabilities' => WCCS_Admin_Select_Data_Provider::get_capabilities(),
					'countries' => WCCS_Admin_Select_Data_Provider::get_countries(),
					'states' => WCCS_Admin_Select_Data_Provider::get_states(),
					'zones' => WCCS_Admin_Select_Data_Provider::get_shipping_zones(),
					'shippingClasses' => WCCS_Admin_Select_Data_Provider::get_shipping_classes(),
					'paymentMethods' => WCCS_Admin_Select_Data_Provider::get_payment_methods(),
					'productsList' => WCCS_Conditions_Provider::get_products_lists(),
					'discountList' => WCCS_Conditions_Provider::get_cart_discounts(),
					'pricingList' => WCCS_Conditions_Provider::get_pricings(),
					'checkoutFeeList' => WCCS_Conditions_Provider::get_checkout_fees(),
					'shippingList' => WCCS_Conditions_Provider::get_shippings(),
					'settings' => array(
						'set_min_quantity' => $wccs->settings->get_setting( 'set_min_quantity', 0 ),
						'coupon_url' => home_url( '/' ) . $wccs->settings->get_setting( 'url_coupons_url_prefix', 'coupon' ),
						'url_coupon_redirect_url' => $wccs->settings->get_setting( 'url_coupons_redirect_url', home_url( '/' ) . 'cart/' ),
						'url_coupon_success_message' => $wccs->settings->get_setting( 'url_coupons_success_message', 'Coupon applied successfully' ),
					),
					'urlCouponsEnabled' => $wccs->is_addon_active( 'url_coupons' ),
					'dateTime' => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
					'saleBadgesAdv' => ! defined( 'ASNP_WESB_VERSION' ),
					'wcSubscriptions' => class_exists( 'WC_Subscriptions' ),
				);
		}

		return false;
	}

	protected function show_review() {
		if ( ! WCCS_Helpers::maybe_show_review() ) {
			return;
		}

		WCCS_Helpers::register_polyfills( true );

		wp_enqueue_style(
			'asnp-ewd-review',
			$this->get_asset_url( 'admin/css/review/style.css' )
		);
		wp_enqueue_script(
			'asnp-ewd-review',
			$this->get_asset_url( 'admin/js/review/index.js' ),
			array(
				'react-dom',
				'wp-i18n',
				'wp-api-fetch',
			),
			WCCS_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'asnp-ewd-review', 'easy-woocommerce-discounts', WCCS_PLUGIN_DIR . 'languages' );
		}
	}

	protected function localize_scripts() {
		foreach ( $this->scripts as $handle ) {
			$this->localize_script( $handle );

		}
	}

}

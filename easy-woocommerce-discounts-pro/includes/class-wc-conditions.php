<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       taher.atashbar@gmail.com
 * @since      1.0.0
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
final class WC_Conditions {

	/**
	 * @var WC_Conditions The one true WC_Conditions
	 * @since 1.0.0
	 */
	private static $instance;

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
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	public $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      string    $version    The current version of the plugin.
	 */
	public $version;

	/**
	 * Admin side of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var   WCCS_Admin
	 */
	public $admin;

	/**
	 * Public side of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var   WCCS_Public
	 */
	public $public;

	/**
	 * The plugin cart instance.
	 *
	 * @var WCCS_Cart
	 */
	public $cart;

	/**
	 * Apply custom props on objects.
	 *
	 * @var WCCS_Custom_Props
	 */
	public $custom_props;

	/**
	 * Service container of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var   WCCS_Service_Manager
	 */
	private $services;

	/**
	 * Is live price running currently.
	 *
	 * @since 4.3.0
	 *
	 * @var   boolean
	 */
	private $live_price_running = false;

	private $compatibilities;

	/**
	 * Main WC_Conditions Instance.
	 *
	 * Insures that only one instance of WC_Conditions exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 1.0.0
	 *
	 * @see WCCS()
	 *
	 * @return object|WC_Conditions The one true WC_Conditions
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WC_Conditions ) ) {
			self::$instance = new WC_Conditions();
			self::$instance->setup_constants();
			self::$instance->load_dependencies();

			self::$instance->plugin_name = 'easy-woocommerce-discounts-pro';
			self::$instance->version     = WCCS_VERSION;

			self::$instance->custom_props = new WCCS_Custom_Props();
			self::$instance->loader       = new WCCS_Loader();

			self::$instance->define_services();

			self::$instance->admin  = new WCCS_Admin( self::$instance->plugin_name, self::$instance->version, self::$instance->loader, self::$instance->services );
			self::$instance->public = new WCCS_Public( self::$instance->plugin_name, self::$instance->version, self::$instance->loader, self::$instance->services );

			self::$instance->set_locale();
			self::$instance->define_hooks();

			self::$instance->compatibilities = new WCCS_Compatibilities( self::$instance->loader, self::$instance->services );
			self::$instance->compatibilities->init();
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'easy-woocommerce-discounts' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since 1.6
	 *
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'easy-woocommerce-discounts' ), '1.0.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	private function setup_constants() {
		// Plugin Folder Path.
		if ( ! defined( 'WCCS_PLUGIN_DIR' ) ) {
			define( 'WCCS_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'WCCS_PLUGIN_URL' ) ) {
			define( 'WCCS_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
		}

		// Plugin Root File.
		if ( ! defined( 'WCCS_PLUGIN_FILE' ) ) {
			define( 'WCCS_PLUGIN_FILE', plugin_dir_path( dirname( __FILE__ ) ) . 'easy-woocommerce-discounts.php' );
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - WCCS_Loader. Orchestrates the hooks of the plugin.
	 * - WCCS_i18n. Defines internationalization functionality.
	 * - WCCS_Admin. Defines all hooks for the admin area.
	 * - WCCS_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		require_once dirname( __FILE__ ) . '/class-wccs-updates.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-activator.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-i18n.php';

		/**
		 * The class responsible for services of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-service-manager.php';

		/**
		 * Base controller class of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-controller.php';

		/**
		 * The class responsible for plugin settings.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-settings.php';

		/**
		 * The class responsible for WooCommerce Products.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-products.php';

		/**
		 * The class responsible for WooCommerce Customer.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-customer.php';

		/**
		 * Base DB class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-db.php';

		/**
		 * Conditions DB Class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-db-conditions.php';

		/**
		 * Condition Meta DB Class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-db-condition-meta.php';

		require_once dirname( __FILE__ ) . '/class-wccs-db-cache.php';
		// Usage logs.
		require_once dirname( __FILE__ ) . '/class-wccs-db-user-usage-logs.php';
		require_once dirname( __FILE__ ) . '/class-wccs-db-rule-usage-logs.php';
		// Analytics.
		require_once dirname( __FILE__ ) . '/class-wccs-db-analytics.php';
		require_once dirname( __FILE__ ) . '/class-wccs-background-analytics.php';

		/**
		 * The class responsible for providing conditions.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-conditions-provider.php';

		/**
		 * The class responsible for cart.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-cart.php';

		/**
		 * The class responsible for cart totals.
		 */
		require_once dirname( __FILE__ ) . '/class-wccs-cart-totals.php';

		/**
		 * The class responsible for report.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-report.php';
		require_once dirname( __FILE__ ) . '/class-wccs-reports.php';

		/**
		 * The class responsible for validating condition.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/validator/class-wccs-condition-validator.php';

		/**
		 * The class responsible for validating pricing condition.
		 */
		require_once dirname( __FILE__ ) . '/validator/class-wccs-pricing-condition-validator.php';

		/**
		 * The class responsible for validating shipping condition.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/validator/class-wccs-shipping-condition-validator.php';

		/**
		 * The class responsible for validating date time.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/validator/class-wccs-date-time-validator.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/validator/class-wccs-product-validator.php';
		require_once dirname( __FILE__ ) . '/validator/class-wccs-usage-validator.php';

		/**
		 * The class responsible for filtering rules.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-rules-filter.php';

		/**
		 * The class responsible for cart discount.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-cart-discount.php';

		/**
		 * The class responsible for pricing.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-pricing.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-checkout-fee.php';

		/**
		 * The class responsible for date time.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-date-time.php';

		/**
		 * The class responsible for finding products from selected products.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-products-selector.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-discounted-products-selector.php';

		/**
		 * The class responsible for sorting.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-sorting.php';

		/**
		 * The class responsible for comparison.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-comparison.php';

		/**
		 * Classes responsible for live pricing.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/abstracts/abstract-wccs-virtual-cart.php';
		if ( version_compare( WC_VERSION, '3.2.0', '>=' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-virtual-cart.php';
		} else {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-virtual-cart-legacy.php';
		}
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-live-price-handler.php';
		require_once dirname( __FILE__ ) . '/class-wccs-cart-pricing-cache.php';

		/**
		 * Cache Classes.
		 */
		require_once dirname( __FILE__ ) . '/abstracts/abstract-wccs-cache.php';
		require_once dirname( __FILE__ ) . '/cache/class-wccs-product-price-cache.php';
		require_once dirname( __FILE__ ) . '/cache/class-wccs-product-quantity-table-cache.php';
		require_once dirname( __FILE__ ) . '/cache/class-wccs-product-purchase-message-cache.php';
		require_once dirname( __FILE__ ) . '/cache/class-wccs-product-onsale-cache.php';

		require_once dirname( __FILE__ ) . '/class-wccs-clear-cache.php';
		require_once dirname( __FILE__ ) . '/class-wccs-background-batch-price-updater.php';
		require_once dirname( __FILE__ ) . '/class-wccs-product-price-replace.php';
		require_once dirname( __FILE__ ) . '/class-wccs-countdown-timer.php';
		require_once dirname( __FILE__ ) . '/class-wccs-cart-item-pricing-discounts.php';
		require_once dirname( __FILE__ ) . '/class-wccs-shipping-method.php';
		require_once dirname( __FILE__ ) . '/class-wccs-total-discounts.php';
		require_once dirname( __FILE__ ) . '/class-wccs-price-calculator.php';
		require_once dirname( __FILE__ ) . '/class-wccs-custom-props.php';

		// Rest API.
		require_once dirname( __FILE__ ) . '/api/class-wccs-rest-base-controller.php';
		require_once dirname( __FILE__ ) . '/api/class-wccs-rest-api.php';
		require_once dirname( __FILE__ ) . '/api/class-wccs-rest-review.php';
		require_once dirname( __FILE__ ) . '/api/class-wccs-rest-analytics.php';

		/**
		 * Plugin helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-helpers.php';

		/**
		 * Product helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-product-helpers.php';

		/**
		 * Order helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-order-helpers.php';

		/**
		 * Order Item Product helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-order-item-product-helpers.php';

		/**
		 * Coupon helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-coupon-helpers.php';

		/**
		 * Roles helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-roles-helpers.php';

		/**
		 * Attribute helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-attribute-helpers.php';

		/**
		 * Term helpers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wccs-term-helpers.php';

		/**
		 * Rules helpers.
		 */
		require_once dirname( __FILE__ ) . '/helpers/class-wccs-rules-helpers.php';

		/**
		 * Cart items helpers.
		 */
		require_once dirname( __FILE__ ) . '/helpers/class-wccs-cart-items-helpers.php';

		/**
		 * Cart item helpers.
		 */
		require_once dirname( __FILE__ ) . '/helpers/class-wccs-cart-item-helpers.php';

		/**
		 * Shipping helpres.
		 */
		require_once dirname( __FILE__ ) . '/helpers/class-wccs-shipping-helpers.php';

		/**
		 * Checkout helpers.
		 */
		require_once dirname( __FILE__ ) . '/helpers/class-wccs-checkout-helpers.php';

		/**
		 * Email helpers.
		 */
		require_once dirname( __FILE__ ) . '/helpers/class-wccs-email-helpers.php';

		/**
		 * Plugin compatibilities manager.
		 */
		require_once dirname( __FILE__ ) . '/class-wccs-compatibilities.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wccs-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wccs-public.php';

		/**
		 * The class responsible for plugin activation.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-activator.php';

		/**
		 * The class responsible for plugin deactivation.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-deactivator.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the WCCS_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new WCCS_i18n();
		$plugin_i18n->load_plugin_textdomain();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_hooks() {
		$this->loader->add_action( 'wpmu_new_blog', $this, 'new_blog_created', 10, 6 );
		$this->loader->add_action( 'woocommerce_init', $this, 'woocommerce_init' );
		$this->loader->add_action( 'before_woocommerce_init', $this, 'hpos_support' );

		$this->loader->add_filter( 'wpmu_drop_tables', $this, 'wpmu_drop_tables', 10, 2 );
		$this->loader->add_filter( 'posts_where', $this, 'posts_where', 10, 2 );

		WCCS_Activator::init();

		$this->admin->define_hooks();
		$this->public->define_hooks();

		if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) && version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), '7.2.0', 'ge' ) ) {
			include_once dirname( __FILE__ ) . '/class-wccs-store-api.php';
			include_once dirname( __FILE__ ) . '/class-wccs-blocks-hooks.php';
			include_once dirname( __FILE__ ) . '/class-wccs-checkout-integration.php';
			WCCS_Blocks_Hooks::init();
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	public function container() {
		return $this->services;
	}

	/**
	 * Magic method to getting services.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->services->get( $key );
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    WCCS_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * When a new Blog is created in multisite, see if WC_Conditions is network activated, and run the installer
	 *
	 * @since  1.0.0
	 *
	 * @param  int    $blog_id The Blog ID created.
	 * @param  int    $user_id The User ID set as the admin.
	 * @param  string $domain  The URL.
	 * @param  string $path    Site Path.
	 * @param  int    $site_id The Site ID.
	 * @param  array  $meta    Blog Meta.
	 *
	 * @return void
	 */
	public function new_blog_created( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		if ( is_plugin_active_for_network( plugin_basename( WCCS_PLUGIN_FILE ) ) ) {
			switch_to_blog( $blog_id );
			WCCS_Activator::activate();
			restore_current_blog();
		}
	}

	/**
	 * Drop our custom tables when a mu site is deleted.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $tables  The tables to drop.
	 * @param  int   $blog_id The Blog ID being deleted.
	 *
	 * @return array          The tables to drop.
	 */
	public function wpmu_drop_tables( $tables, $blog_id ) {
		switch_to_blog( $blog_id );

		$conditions_db     = new WCCS_DB_Conditions();
		$condition_meta_db = new WCCS_DB_Condition_Meta();

		if ( $conditions_db->installed() ) {
			$tables[] = $conditions_db->table_name;
			$tables[] = $condition_meta_db->table_name;
		}

		restore_current_blog();

		return $tables;
	}

	/**
	 * Filter hook to filtering posts_where.
	 *
	 * @since  2.4.0
	 *
	 * @param  string $where
	 * @param  object $wp_query
	 *
	 * @return string
	 */
	public function posts_where( $where, $wp_query ) {
		global $wpdb;

		$post_title = $wp_query->get( 'wccs_post_title' );
		$post_id    = $wp_query->get( 'wccs_post_id' );

		if ( $post_title ) {
			$post_title = $wpdb->posts . '.post_title LIKE \'%' . esc_sql( $wpdb->esc_like( $post_title ) ) . '%\'';
		}

		if ( $post_id ) {
			$post_id = $wpdb->posts . '.ID LIKE \'' . esc_sql( $wpdb->esc_like( $post_id ) ) . '%\'';
		}

		if ( $post_title && $post_id ) {
			$where .= ( strlen( $where ) ? ' AND (' : '(' ) . $post_title . ' OR ' . $post_id . ')';
		} elseif ( $post_title ) {
			$where .= ( strlen( $where ) ? ' AND ' : '' ) . $post_title;
		} elseif ( $post_id ) {
			$where .= ( strlen( $where ) ? ' AND ' : '' ) . $post_id;
		}

		return $where;
	}

	/**
	 * Hook function called after woocommerce init.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function woocommerce_init() {
		if ( $this->is_request( 'frontend' ) || WCCS_Helpers::wc_is_rest_api_request() ) {
			$this->cart = new WCCS_Cart();
			$pricing = new WCCS_Pricing(
				WCCS_Conditions_Provider::get_pricings( array( 'status' => 1 ) )
			);
			$this->services->set( 'cart_discount', new WCCS_Cart_Discount(
				WCCS_Conditions_Provider::get_cart_discounts( array( 'status' => 1 ) )
			) );
			$this->services->set( 'pricing', $pricing );
			$this->services->set( 'WCCS_Checkout_Fee', new WCCS_Checkout_Fee(
				WCCS_Conditions_Provider::get_checkout_fees( array( 'status' => 1 ) )
			) );
			$this->services->set( 'WCCS_Product_Price_Cache', new WCCS_Product_Price_Cache( $pricing ) );
		}
	}

	/**
	 * WooCommerce HOPS support.
	 *
	 * @return void
	 */
	public function hpos_support() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCCS_PLUGIN_FILE, true );
		}
	}

	/**
	 * Defining services of the plugin.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	private function define_services() {
		$this->services = new WCCS_Service_Manager();

		$this->services->set( 'settings', new WCCS_Settings() );

		$product_helpers = new WCCS_Product_Helpers( $this->loader );
		$product_helpers->define_hooks();

		$batch_price_updater = new WCCS_Background_Batch_Price_Updater();
		$batch_price_updater->init();

		$this->services->set( 'WCCS_DB_Cache', new WCCS_DB_Cache() );
		$this->services->set( 'conditions', new WCCS_DB_Conditions() );
		$this->services->set( 'condition_meta', new WCCS_DB_Condition_Meta() );
		$this->services->set( 'products', new WCCS_Products() );
		$this->services->set( 'report', new WCCS_Report() );
		$this->services->set( 'order_helpers', new WCCS_Order_Helpers() );
		$this->services->set( 'product_helpers', $product_helpers );
		$this->services->set( 'WCCS_Background_Batch_Price_Updater', $batch_price_updater );
		$this->services->set( 'WCCS_Rest_Api', new WCCS_Rest_Api() );
		$this->services->set( WCCS_DB_User_Usage_Logs::class, new WCCS_DB_User_Usage_Logs() );
		$this->services->set( WCCS_DB_Rule_Usage_Logs::class, new WCCS_DB_Rule_Usage_Logs() );
		$this->services->set( WCCS_DB_Analytics::class, new WCCS_DB_Analytics() );
	}

	/**
	 * What type of request is this?
	 *
	 * @since  1.0.0
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 *
	 * @return bool
	 */
	public function is_request( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
			case 'ajax' :
				return defined( 'DOING_AJAX' );
			case 'cron' :
				return defined( 'DOING_CRON' );
			case 'frontend' :
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	/**
	 * Set a value for live price running.
	 *
	 * @since  4.3.0
	 *
	 * @return void
	 */
	public function set_live_price_running( $value ) {
		$this->live_price_running = $value;
	}

	/**
	 * Is live price running?
	 *
	 * @since  4.3.0
	 *
	 * @return boolean
	 */
	public function is_live_price_running() {
		return $this->live_price_running;
	}

	/**
	 * Checking is the plugin specific addon active or no.
	 *
	 * @param  string $addon
	 *
	 * @return boolean
	 */
	public function is_addon_active( $addon ) {
		if ( empty( $addon ) ) {
			return false;
		}

		return apply_filters( 'wccs_is_addon_active_' . $addon, false );
	}

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       taher.atashbar@gmail.com
 * @since      1.0.0
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin {

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
	 * @param string      $plugin_name The name of this plugin.
	 * @param string      $version     The version of this plugin.
	 * @param WCCS_Loader $loader
	 */
	public function __construct( $plugin_name, $version, WCCS_Loader $loader, WCCS_Service_Manager $services ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->loader      = $loader;
		$this->services    = $services;

		$this->load_dependencies();
		$this->update_checker();
	}

	/**
	 * Load dependencies required in admin area.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function load_dependencies() {
		/**
		 * The class responsible for Ajax operations.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-ajax.php';
		/**
		 * The controller class of admin area.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-controller.php';
		/**
		 * The class responsible for outputting html elements in pages.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-html-element.php';
		/**
		 * The class responsible for creating all admin menus of the plugin.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-menu.php';
		/**
		 * The class responsible for admin assets.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-assets.php';
		/**
		 * The class responsible for showing admin notices.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-notices.php';

		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-select-data-provider.php';
		require_once dirname( __FILE__ ) . '/class-wccs-admin-conditions-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-admin-order-hooks.php';

		include_once dirname( __FILE__ ) . '/class-wccs-admin-update-checker.php';
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function define_hooks() {
		// Menu hooks.
		$menu = new WCCS_Admin_Menu( $this->loader );
		// Admin notices.
		WCCS()->WCCS_Admin_Notices->init();
		// Ajax Operations.
		new WCCS_Admin_Ajax( $this->loader );
		// Admin Assets.
		$admin_assets = new WCCS_Admin_Assets( $this->loader, $menu );
		$admin_assets->init_hooks();

		$conditions_hooks = new WCCS_Admin_Conditions_Hooks( $this->loader );
		$conditions_hooks->enable_hooks();

		$order_hooks = new WCCS_Admin_Order_Hooks( $this->loader );
		$order_hooks->enable_hooks();

		// Cache Clear Hooks.
		WCCS()->WCCS_Clear_Cache->enable_hooks();

		$this->loader->add_action( 'in_plugin_update_message-easy-woocommerce-discounts-pro/easy-woocommerce-discounts.php', $this, 'in_plugin_update_message' );
	}

	/**
	 * Checking for plugin updates.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	private function update_checker() {
		$update_checker = WCCS_Admin_Update_Checker::buildUpdateChecker(
			'https://wpupdate.asanaplugins.com/?action=get_metadata&slug=easy-woocommerce-discounts-pro',
			WCCS_PLUGIN_FILE,
			'easy-woocommerce-discounts-pro'
		);
		$update_checker->addQueryArgFilter( array( &$this, 'filter_update_checks' ) );
	}

	/**
	 * Filtering update checks.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $query_args
	 *
	 * @return array
	 */
	public function filter_update_checks( array $query_args ) {
		$license = WCCS()->settings->get_setting( 'license_key', '' );
		if ( ! empty( $license ) ) {
			$query_args['license_key'] = $license;
		}
		$query_args['host'] = preg_replace( '#^\w+://#', '', trim( get_option( 'siteurl' ) ) );

		return $query_args;
	}

	/**
	 * Showing message in plugin update message area.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function in_plugin_update_message() {
		$license = WCCS()->settings->get_setting( 'license_key', '' );
		if ( empty( $license ) ) {
			$url      = esc_url( admin_url( 'admin.php?page=wccs-settings&tab=licenses' ) );
			$redirect = sprintf( '<a href="%s" target="_blank">%s</a>', $url, __( 'settings', 'easy-woocommerce-discounts' ) );

			echo sprintf( ' ' . __( 'To receive automatic updates, license activation is required. Please visit %s to activate your plugin.', 'easy-woocommerce-discounts' ), $redirect );
		}
	}

}

<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu manager of the plugin.
 *
 * @package    Easy_Property_Compare
 * @subpackage Easy_Property_Compare/public
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin_Menu {

	/**
	 * Menus of the plugin.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private $menus;

    /**
     * Conditions menu of the plugin.
     *
     * @since 1.0.0
     * @var   WCCS_Admin_Conditions_Menu
     */
    private $conditions_menu;

	/**
	 * Settings menu of the plugin.
	 *
	 * @since 1.0.0
	 * @var   WCCS_Admin_Settings_Menu
	 */
	private $settings_menu;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param WCCS_Loader $loader
	 */
	public function __construct( WCCS_Loader $loader ) {
		$this->menus = array();

		$this->load_dependencies();

        $this->conditions_menu = new WCCS_Admin_Conditions_Menu();
		$this->settings_menu   = new WCCS_Admin_Settings_Menu( $loader, new WCCS_Settings_Manager() );

		// Actions for creating menus of the plugin.
		$loader->add_action( 'admin_menu', $this, 'menus' );
	}

	/**
	 * Loading dependencies of the class.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function load_dependencies() {
		if ( ! class_exists( 'WCCS_Settings_Manager' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-settings-manager.php';
		}
		require_once plugin_dir_path( __FILE__ ) . 'menus/class-wccs-admin-settings-menu.php';
        require_once plugin_dir_path( __FILE__ ) . 'menus/class-wccs-admin-conditions-menu.php';
	}

	/**
	 * Creating plugin menus.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function menus() {
	    $this->menus['wc_conditions'] = add_menu_page(
	        __( 'WooCommerce Dynamic Pricing & Discounts', 'easy-woocommerce-discounts' ),
            __( 'Woo Pricing & Discounts', 'easy-woocommerce-discounts' ),
            apply_filters( 'wccs_conditions_menu_capability', 'manage_woocommerce' ),
            'wccs-conditions',
            array( $this->conditions_menu, 'create_menu' ),
            'dashicons-cart'
        );
        $this->menus['settings'] = add_submenu_page(
		    'wccs-conditions',
            __( 'WooCommerce Dynamic Pricing & Discounts', 'easy-woocommerce-discounts' ),
            __( 'Settings', 'easy-woocommerce-discounts' ),
            apply_filters( 'wccs_settings_menu_capability', 'manage_woocommerce' ),
            'wccs-settings',
            array( $this->settings_menu, 'create_menu' )
        );
	}

	/**
	 * Getting all of admin-face menus of plugin.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_menus() {
		return $this->menus;
	}

}

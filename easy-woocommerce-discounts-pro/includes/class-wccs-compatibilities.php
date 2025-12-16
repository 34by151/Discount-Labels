<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The plugin compatibilities manager.
 *
 * @since 3.5.0
 */
class WCCS_Compatibilities {

    /**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    3.5.0
	 * @access   protected
	 * @var      WCCS_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	private $loader;

	/**
	 * Service container of the plugin.
	 *
	 * @since 3.5.0
	 *
	 * @var   WCCS_Service_Manager
	 */
    private $services;

    public function __construct( WCCS_Loader $loader, WCCS_Service_Manager $services ) {
        $this->loader   = $loader;
        $this->services = $services;
    }

    public function init() {
		// Extra Product Options compatibility.
        if ( class_exists( 'TM_Extra_Product_Options' ) || class_exists( 'THEMECOMPLETE_Extra_Product_Options' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-tm-epo.php';
			$compatibility = new WCCS_Compatibility_TM_EPO( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_TM_EPO', $compatibility );
		}

		// YITH WooCommerce Product Add-ons compatibility.
		if ( function_exists( 'YITH_WAPO' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-yith-wapo.php';
			$compatibility = new WCCS_Compatibility_Yith_WAPO( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_Yith_WAPO', $compatibility );
		}

		// WooCommerce Product Add-Ons compatibility.
		if ( class_exists( 'WC_Product_Addons' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-product-addons.php';
			$compatibility = new WCCS_Compatibility_Product_Addons( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_Product_Addons', $compatibility );
		}

		// WooCommerce Subscriptions compatibility.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wc-subscriptions.php';
			$compatibility = new WCCS_Compatibility_WC_Subscriptions( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_WC_Subscriptions', $compatibility );
		}

		// Booster for WooCommerce compatibility.
		if ( function_exists( 'WCJ' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-booster-wc.php';
			$compatibility = new WCCS_Compatibility_Booster_WC( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_Booster_WC', $compatibility );
		}

		// WooCommerce Currency Switcher(WOOCS) compatibility.
		if ( class_exists( 'WOOCS_STARTER' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-woocs.php';
			$compatibility = new WCCS_Compatibility_WOOCS( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_WOOCS', $compatibility );
		}

		// WooCommerce Bookings compatibility.
		if ( class_exists( 'WC_Bookings' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wc-bookings.php';
			$compatibility = new WCCS_Compatibility_WC_Bookings( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_WC_Bookings', $compatibility );
		}

		// WooCommerce Product Bundles compatibility.
		if ( class_exists( 'WC_Bundles' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wc-product-bundles.php';
			WCCS_Compatibility_WC_Product_Bundles::init();
		}

		// WPC Product Bundles compatibility.
		if ( defined( 'WOOSB_FILE' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wpc-product-bundles.php';
			WCCS_Compatibility_WPC_Product_Bundles::init();
		}

		// Product Feed PRO for WooCommerce compatibility.
		if ( defined( 'WOOCOMMERCESEA_FILE' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-product-feed-pro.php';
			$compatibility = new WCCS_Compatibility_Product_Feed_Pro( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_Product_Feed_Pro', $compatibility );
		}

		if ( class_exists( 'WCS_ATT' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wcs-all-products.php';
			$compatibility = new WCCS_Compatibility_WCS_All_Products( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_WCS_All_Products', $compatibility );
		}

		// WooCommerce Payments compatibility.
		if ( defined( 'WCPAY_PLUGIN_FILE' ) && WC_Payments_Features::is_customer_multi_currency_enabled() ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wc-payments.php';
			WCCS_Compatibility_WC_Payments::init();
		}

		// CURCY - Multi Currency for WooCommerce compatibility.
		if (
			is_callable( array( 'WOOMULTI_CURRENCY_Data', 'get_ins' ) ) ||
			is_callable( array( 'WOOMULTI_CURRENCY_F_Data', 'get_ins' ) )
		) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-curcy.php';
			$compatibility = new WCCS_Compatibility_Curcy(
				$this->loader,
				class_exists( 'WOOMULTI_CURRENCY_Data' ) ? WOOMULTI_CURRENCY_Data::get_ins() : WOOMULTI_CURRENCY_F_Data::get_ins()
			);
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_Curcy', $compatibility );
		}

		// WPML compatibility.
		if ( function_exists( 'wpml_loaded' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wpml.php';
			$compatibility = new WCCS_Compatibility_WPML( $this->loader );
			$compatibility->init();
			$this->services->set( 'WCCS_Compatibility_WPML', $compatibility );
		}

		if ( defined( 'ASNP_WEPB_VERSION' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-product-bundles.php';
			WCCS_Compatibility_Product_Bundles::init();
		}

		if ( class_exists( 'WooCommerceWholeSalePrices' ) ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-wholesale-prices.php';
			WCCS_Compatibility_WholeSale_Prices::init();
		}

		if ( 'woodmart' === get_stylesheet() || 'woodmart' === get_template() ) {
			require_once dirname( __FILE__ ) . '/compatibility/class-wccs-compatibility-woodmart.php';
			$compatibility = new WCCS_Compatibility_Woodmart( $this->loader );
			$compatibility->init();
			$this->services->set( WCCS_Compatibility_Woodmart::class, $compatibility );
		}
    }

}

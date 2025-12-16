<?php
/**
 * @wordpress-plugin
 * Plugin Name: Discount Rules and Dynamic Pricing for WooCommerce
 * Plugin URI: https://www.asanaplugins.com/product/woocommerce-dynamic-pricing-and-discounts-plugin/?utm_source=easy-woocommerce-discounts-free&utm_campaign=easy-woocommerce-discounts&utm_medium=link
 * Description: All purpose WooCommerce discounts, pricing, shipping and promotion tool.
 * Tags: woocommerce, woocommerce discounts, woocommerce pricing deals, woocommerce Buy One Get One Free, woocommerce bulk discounts, woocommerce pricing, shipping, woocommerce price rules, woocommerce advanced discounts, woocommerce pricing deals, woocommerce bulk discounts, woocommerce cart discounts, woocommerce pricing deals, woocommerce discount rules, discount rules for woocommerce, woocommerce shipping
 * Version: 9.2.1
 * Author: Discount Team
 * Author URI: http://www.asanaplugins.com/
 * License: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-woocommerce-discounts
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 10.3.6
 *
 * Copyright 2025 Asana Plugins (https://www.asanaplugins.com/)
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin version.
if ( ! defined( 'WCCS_VERSION' ) ) {
	define( 'WCCS_VERSION', '9.2.1' );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wccs-activator.php
 */
function activate_wc_conditions( $network_wide = false ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wccs-activator.php';
	WCCS_Activator::activate( $network_wide );
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wccs-deactivator.php
 */
function deactivate_wc_conditions() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wccs-deactivator.php';
	WCCS_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wc_conditions' );
register_deactivation_hook( __FILE__, 'deactivate_wc_conditions' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-conditions.php';

/**
 * The main function for that returns WC_Conditions
 *
 * The main function responsible for returning the one true WC_Conditions
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $wccs = WCCS(); ?>
 *
 * @since  1.0.0
 * @return object|WC_Conditions The one true WC_Conditions Instance.
 */
function WCCS() {
	return WC_Conditions::instance();
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wc_conditions() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		if ( ! class_exists( 'WCCS_WC_Extension_Activation' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-wccs-wc-extension-activation.php';
		}
		$activation = new WCCS_WC_Extension_Activation( plugin_basename( __FILE__ ) );
		$activation->run();
	} else {
		WCCS()->run();
	}
}
add_action( 'plugins_loaded', 'run_wc_conditions', 100 );

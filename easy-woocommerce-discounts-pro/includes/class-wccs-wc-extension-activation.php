<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCCS_WC_Extension_Activation' ) ) :

	/**
	 * WC Extension Activation Handler Class
	 *
	 * @since      1.0.0
	 * @package    WCCS
	 * @subpackage WCCS/includes
	 * @author     Taher Atashbar <taher.atashbar@gmail.com>
	 */
	class WCCS_WC_Extension_Activation {

		private $plugin_name;
		private $has_wc;
		private $wc_base;

		/**
		 * Setup the activation class
		 *
		 * @access      public
		 * @since       1.0.0
		 */
		public function __construct( $plugin_basename ) {
			// We need plugin.php!
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			$plugins = get_plugins();

			// Set plugin name
			if ( isset( $plugins[ $plugin_basename ]['Name'] ) ) {
				$this->plugin_name = $plugins[ $plugin_basename ]['Name'];
			} else {
				$this->plugin_name = __( 'WooCommerce Extension', 'wc-extension-activation' );
			}

			// Is WooCommerce installed?
			foreach ( $plugins as $plugin_path => $plugin ) {
				if ( $plugin['Name'] == 'WooCommerce' ) {
					$this->has_wc = true;
					$this->wc_base = $plugin_path;
					break;
				}
			}
		}

		/**
		 * Process plugin deactivation
		 *
		 * @access      public
		 * @since       1.0.0
		 * @return      void
		 */
		public function run() {
			// Display notice
			add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
		}

		/**
		 * Display notice if WooCommerce isn't installed/activated
		 *
		 * @access      public
		 * @since       1.0.0
		 * @return      string The notice to display
		 */
		public function missing_wc_notice() {
			if ( $this->has_wc ) {
				$url  = esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $this->wc_base ), 'activate-plugin_' . $this->wc_base ) );
				$link = '<a href="' . $url . '">' . __( 'activate it', 'wc-extension-activation' ) . '</a>';
			} else {
				$url  = esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' ) );
				$link = '<a href="' . $url . '">' . __( 'install it', 'wc-extension-activation' ) . '</a>';
			}

			echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires WooCommerce! Please %s to continue!', 'wc-extension-activation' ), $link ) . '</p></div>';
		}

	}

endif;

<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-facing controller of the plugin.
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin_Controller extends WCCS_Controller {

	/**
	 * Rendering requested view.
	 *
	 * @since 1.0.0
	 * @param string  $view
	 * @param array   $variables
	 */
	public function render_view( $view, array $variables = array() ) {
		$view = trim( $view );
		if ( strlen( $view ) ) {
			if ( strpos( $view, '.' ) !== false ) {
				$view = str_replace( '.', '/', $view );
			}
			$this->get_template_part( $view, null, true, $variables );
		}
	}

	/**
	 * Returns the template directory name.
	 * Themes can filter this by using the wccs_admin_templates_dir filter.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_theme_template_dir_name() {
		return trailingslashit( apply_filters( 'wccs_admin_templates_dir', 'wccs_templates/admin' ) );
	}

	/**
	 * Returns the path to the WooCommerce Conditions admin templates directory
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_templates_dir() {
		return plugin_dir_path( __FILE__ ) . 'partials';
	}

	/**
	 * Getting admin-side js directory url.
	 *
	 * @since  1.0.0
	 * @return string url of js directory.
	 */
	public function get_js_url() {
		return plugin_dir_url( __FILE__ ) . 'js/';
	}

	/**
	 * Getting admin-side css directory url.
	 *
	 * @since  1.0.0
	 * @return string url of css directory
	 */
	public function get_css_url() {
		return plugin_dir_url( __FILE__ ) . 'css/';
	}

	/**
	 * Getting admin-side images directory url.
	 *
	 * @since  1.0.0
	 * @return string url of images directory
	 */
	public function get_images_url() {
		return plugin_dir_url( __FILE__ ) . 'images/';
	}

	/**
	 * Getting path of admin area.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_path() {
		return plugin_dir_path( __FILE__ );
	}

}

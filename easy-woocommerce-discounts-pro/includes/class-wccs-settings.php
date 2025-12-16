<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The class responsible for plugin settings.
 *
 * @since      1.0.0
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Settings {

	/**
	 * Plugin settings
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $plugin_settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->plugin_settings = get_option( 'wccs_settings' );
		if ( empty( $this->plugin_settings ) ) {
			$this->plugin_settings = array();
		}
	}

	/**
	 * Getting plugin settings.
	 *
	 * @since  1.0.0
	 * @return array $plugin_settings
	 */
	public function get_settings() {
		return apply_filters( 'wccs_get_settings', $this->plugin_settings );
	}

	/**
	 * Getting a setting of the plugin.
	 *
	 * @since  1.0.0
	 * @param  string  $key
	 * @param  boolean $default
	 * @return mixed
	 */
	public function get_setting( $key = '', $default = false ) {
		$value = isset( $this->plugin_settings[ $key ] ) ? $this->plugin_settings[ $key ] : $default;
		$value = apply_filters( 'wccs_get_setting', $value, $key, $default );
		return apply_filters( 'wccs_get_setting_' . $key, $value, $key, $default );
	}

	/**
	 * Getting a setting with it's key from plugin settings.
	 *
	 * @since  1.0.0
	 * @param  string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		return isset( $this->plugin_settings[ $key ] ) ? $this->plugin_settings[ $key ] : false;
	}

	/**
	 * Set a value to plugin settings.
	 *
	 * @since 1.0.0
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set( $key, $value ) {
		$this->plugin_settings[ $key ] = $value;
	}

	/**
	 * Deleting a setting from plugin settings.
	 *
	 * @since  1.0.0
	 * @param  string $key
	 * @return void
	 */
	public function delete_setting( $key ) {
		if ( array_key_exists( $key, $this->plugin_settings ) ) {
			unset( $this->plugin_settings[ $key ] );
		}
	}

	/**
	 * Updating plugin settings.
	 *
	 * @since  1.0.0
	 * @return boolean
	 */
	public function update() {
		return update_option( 'wccs_settings', $this->plugin_settings );
	}

}

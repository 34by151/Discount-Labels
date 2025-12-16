<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings menu controller of the plugin.
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin/menus
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin_Settings_Menu extends WCCS_Admin_Controller {

	/**
	 * @since 1.0.0
	 * @var   WCCS_Settings_Manager $settings_manager
	 */
	public $settings_manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param WCCS_Loader $loader
     * @param WCCS_Settings_Manager $settings_manager
	 */
	public function __construct( WCCS_Loader $loader, WCCS_Settings_Manager $settings_manager ) {
		$this->settings_manager = $settings_manager;

		$loader->add_action( 'admin_init', $this, 'register_settings' );
		$loader->add_action( 'update_option_wccs_settings', $this, 'settings_updated', 10, 3 );
		// Sanitize settings fields.
		$loader->add_filter( 'wccs_settings_sanitize_text', $this, 'sanitize_text_field' );
	}

	/**
	 * Outputting menu content.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function create_menu() {
		$this->render_view( 'menu.settings-menu',
			array(
				'controller' => $this,
				'tabs'       => $this->settings_manager->get_settings_tabs(),
			)
		);
	}

	/**
	 * Registering settings of the plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_settings() {
		if ( false === get_option( 'wccs_settings' ) ) {
			add_option( 'wccs_settings' );
		}

		$html_element = new WCCS_Admin_Html_Element();

		foreach ( $this->settings_manager->get_registered_settings() as $tab => $sections ) {
			foreach ( $sections as $section => $settings ) {
				// Check for backwards compatibility
				$section_tabs = $this->settings_manager->get_settings_tab_sections( $tab );
				if ( ! is_array( $section_tabs ) || ! array_key_exists( $section, $section_tabs ) ) {
					$section = 'main';
					$settings = $sections;
				}

				add_settings_section(
					'wccs_settings_' . $tab . '_' . $section,
					null,
					'__return_false',
					'wccs_settings_' . $tab . '_' . $section
				);

				foreach ( $settings as $option ) {
					// For backwards compatibility
					if ( empty( $option['id'] ) ) {
						continue;
					}

					$name = isset( $option['name'] ) ? $option['name'] : '';

					add_settings_field(
						'wccs_settings[' . $option['id'] . ']',
						$name,
						method_exists( $html_element, $option['type'] ) ?
							array( $html_element, $option['type'] ) : array( $html_element, 'missing' ),
						'wccs_settings_' . $tab . '_' . $section,
						'wccs_settings_' . $tab . '_' . $section,
						array(
							'section'     => $section,
							'id'          => isset( $option['id'] ) ? $option['id'] : null,
							'desc'        => ! empty( $option['desc'] ) ? $option['desc'] : '',
							'desc_tip'    => isset( $option['desc_tip'] ) ? $option['desc_tip'] : false,
							'name'        => isset( $option['name'] ) ? $option['name'] : null,
							'size'        => isset( $option['size'] ) ? $option['size'] : null,
							'options'     => isset( $option['options'] ) ? $option['options'] : '',
							'optgroups'   => isset( $option['optgroups'] ) ? $option['optgroups'] : '',
							'std'         => isset( $option['std'] ) ? $option['std'] : '',
							'min'         => isset( $option['min'] ) ? $option['min'] : null,
							'max'         => isset( $option['max'] ) ? $option['max'] : null,
							'step'        => isset( $option['step'] ) ? $option['step'] : null,
							'chosen'      => isset( $option['chosen'] ) ? $option['chosen'] : null,
							'placeholder' => isset( $option['placeholder'] ) ? $option['placeholder'] : null,
							'allow_blank' => isset( $option['allow_blank'] ) ? $option['allow_blank'] : true,
							'readonly'    => isset( $option['readonly'] ) ? $option['readonly'] : false,
							'faux'        => isset( $option['faux'] ) ? $option['faux'] : false,
                            'classes'     => isset( $option['classes'] ) ? $option['classes'] : '',
							'style'       => isset( $option['style'] ) ? $option['style'] : '',
							'url'         => isset( $option['url'] ) ? $option['url'] : '',
						)
					);
				}
			}
		}

		// Creates our settings in the options table
		register_setting( 'wccs_settings', 'wccs_settings', array( $this, 'settings_sanitize' ) );
	}

	/**
	 * Settings Sanitization
	 *
	 * Adds a settings error (for the updated message)
	 * At some point this will validate input
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The value inputted in the field
	 *
	 * @return string $input Sanitizied value
	 */
	public function settings_sanitize( $input = array() ) {
		$wccs_settings = WCCS()->settings->get_settings();

		if ( empty( $_POST['_wp_http_referer'] ) ) {
			return $input;
		}

		parse_str( $_POST['_wp_http_referer'], $referrer );

		$settings = $this->settings_manager->get_registered_settings();
		$tab      = isset( $referrer['tab'] ) ? $referrer['tab'] : 'general';
		$section  = isset( $referrer['section'] ) ? $referrer['section'] : 'main';

		$input    = $input ? $input : array();

		$input    = apply_filters( 'wccs_settings_' . $tab . '_sanitize', $input );
		if ( 'main' === $section )  {
			// Check for extensions that aren't using new sections
			$input = apply_filters( 'wccs_settings_' . $tab . '_sanitize', $input );
		}

		// Loop through each setting being saved and pass it through a sanitization filter
		foreach ( $input as $key => $value ) {

			// Get the setting type (checkbox, select, etc)
            $type = isset( $settings[ $tab ][ $section ][ $key ]['type'] ) ? $settings[ $tab ][ $section ][ $key ]['type'] : false;

			if ( $type ) {
				// Field type specific filter
				$input[ $key ] = apply_filters( 'wccs_settings_sanitize_' . $type, $value, $key );
			}

			// General filter
			$input[ $key ] = apply_filters( 'wccs_settings_sanitize', $input[ $key ], $key );
		}

		// Loop through the whitelist and unset any that are empty for the tab being saved
		$main_settings    = $section == 'main' ? $settings[ $tab ] : array(); // Check for extensions that aren't using new sections
		$section_settings = ! empty( $settings[ $tab ][ $section ] ) ? $settings[ $tab ][ $section ] : array();

		$found_settings   = array_merge( $main_settings, $section_settings );

		if ( ! empty( $found_settings ) ) {
			foreach ( $found_settings as $key => $value ) {

				// settings used to have numeric keys, now they have keys that match the option ID. This ensures both methods work
				if ( is_numeric( $key ) ) {
					$key = $value['id'];
				}

				if ( empty( $input[ $key ] ) && isset( $wccs_settings[ $key ] ) ) {
					unset( $wccs_settings[ $key ] );
				}
			}
		}

		// Merge our new settings with the existing
		$output = array_merge( $wccs_settings, $input );

		add_settings_error( 'wccs-notices', '', __( 'Settings updated.', 'easy-woocommerce-discounts' ), 'updated' );

		return $output;
	}

	/**
	 * Sanitizing text fields.
	 *
	 * @since  1.0.0
	 * @param  string $input
	 * @return string
	 */
	public function sanitize_text_field( $input ) {
		return trim( $input );
	}

	/**
	 * Hook method that execute after plugin settings updated.
	 *
	 * @param  mixed  $old_value
	 * @param  mixed  $value
	 * @param  string $option
	 *
	 * @return void
	 */
	public function settings_updated( $old_value, $value, $option ) {
		WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
	}

}

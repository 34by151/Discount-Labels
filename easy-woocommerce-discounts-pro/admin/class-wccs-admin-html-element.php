<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-facing HTML element renderer.
 *
 * @since      1.0.0
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin_Html_Element extends WCCS_Admin_Controller {

	/**
	 * Plugin settings.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private $wccs_settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->wccs_settings = WCCS()->settings->get_settings();
	}

	/**
	 * Callback that called when rendering callback not found for element type.
	 *
	 * @since   1.0.0
	 * @param   $args
	 */
	public function missing( $args ) {
		printf( __( 'The callback function used for the <strong>%s</strong> setting is missing.', 'easy-woocommerce-discounts' ), $args['id'] );
	}

	/**
	 * Radio Callback
	 *
	 * Renders radio boxes.
	 *
	 * @since   1.0.0
	 * @param   array $args Arguments passed by the setting
	 * @return  void
	 */
	public function radio( $args ) {
		if ( ! empty( $args['options'] ) ) {
			if ( true === $args['desc_tip'] ) {
				echo '<img class="help_tip" data-tip="' . esc_attr( $args['desc'] ) . '" src="' . esc_url( $this->get_images_url() ) . 'help.png" height="16" width="16" />';
			}
			foreach ( $args['options'] as $key => $option ) {
				$checked = false;

				if ( isset( $this->wccs_settings[ $args['id'] ] ) && $this->wccs_settings[ $args['id'] ] == $key ) {
					$checked = true;
				} else if ( isset( $args['std'] ) && $args['std'] == $key && ! isset( $this->wccs_settings[ $args['id'] ] ) ) {
					$checked = true;
				}

				echo '<input name="wccs_settings[' . $args['id'] . ']"" id="wccs_settings[' . $args['id'] . '][' . $key . ']" type="radio" value="' . $key . '" ' . checked( true, $checked, false ) . '/>&nbsp;' .
					'<label for="wccs_settings[' . $args['id'] . '][' . $key . ']">' . $option . '</label><br/>';
			}
			if ( false === $args['desc_tip'] ) {
				echo '<p class="description">' . $args['desc'] . '</p>';
			}
		}
	}

	/**
	 * Text Callback
	 *
	 * Renders text fields.
	 *
	 * @since  1.0.0
	 * @param  array $args Arguments passed by the setting
	 * @return void
	 */
	public function text( $args ) {
		if ( isset( $this->wccs_settings[ $args['id'] ] ) ) {
			$value = $this->wccs_settings[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		if ( isset( $args['faux'] ) && true === $args['faux'] ) {
			$args['readonly'] = true;
			$value = isset( $args['std'] ) ? $args['std'] : '';
			$name  = '';
		} else {
			$name = 'name="wccs_settings[' . esc_attr( $args['id'] ) . ']"';
		}

		$readonly = isset( $args['readonly'] ) && $args['readonly'] === true ? ' readonly="readonly"' : '';
		$size     = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html     = '<input type="text" class="' . sanitize_html_class( $size ) . '-text" id="wccs_settings[' . esc_attr( $args['id'] ) . ']" ' . $name . ' value="' . esc_attr( stripslashes( $value ) ) . '"' . $readonly . '/>';
		$html    .= '<label for="wccs_settings[' . esc_attr( $args['id'] ) . ']"> '  . wp_kses_post( $args['desc'] ) . '</label>';

		echo $html;
	}

	/**
	 * Number Callback
	 *
	 * Renders number fields.
	 *
	 * @since   1.0.0
	 * @param   array $args Arguments passed by the setting
	 * @return  void
	 */
	public function number( $args ) {
		if ( isset( $this->wccs_settings[ $args['id'] ] ) ) {
			$value = $this->wccs_settings[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$max  = isset( $args['max'] ) ? $args['max'] : 999999;
		$min  = isset( $args['min'] ) ? $args['min'] : 0;
		$step = isset( $args['step'] ) ? $args['step'] : 1;
		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';

		if ( true === $args['desc_tip'] ) {
			echo '<img class="help_tip" data-tip="' . esc_attr( $args['desc'] ) . '" src="' . esc_url( $this->get_images_url() ) . 'help.png" height="16" width="16" />';
		}
		echo '<input type="number" step="' . esc_attr( $step ) . '" max="' . esc_attr( $max ) . '" min="' . esc_attr( $min ) . '" class="' . esc_attr( $size ) . '-text" id="wccs_settings[' . esc_attr( $args['id'] ) . ']" name="wccs_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
		if ( false === $args['desc_tip'] ) {
			echo '<label for="wccs_settings[' . esc_attr( $args['id'] ) . ']"> '  . $args['desc'] . '</label>';
		}
	}

	/**
	 * Color Callback
	 *
	 * Renders color fields.
	 *
	 * @since   1.0.0
	 * @param   array $args Arguments passed by the setting
	 * @return  void
	 */
	public function color( $args ) {
		if ( isset( $this->wccs_settings[ $args['id'] ] ) ) {
			$value = $this->wccs_settings[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		if ( true === $args['desc_tip'] ) {
			echo '<img class="help_tip" data-tip="' . esc_attr( $args['desc'] ) . '" src="' . esc_url( $this->get_images_url() ) . 'help.png" height="16" width="16" />';
		}

		echo '<input type="text" class="wccs-colorpick-setting" id="wccs_settings[' . esc_attr( $args['id'] ) . ']" name="wccs_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';

		if ( false === $args['desc_tip'] ) {
			echo '<label for="wccs_settings[' . esc_attr( $args['id'] ) . ']"> '  . $args['desc'] . '</label>';
		}
	}

	/**
	 * Select Callback
	 *
	 * Renders select fields.
	 *
	 * @since  1.0.0
	 * @param  array $args Arguments passed by the setting
	 * @return void
	 */
	public function select( $args ) {
		if ( isset( $this->wccs_settings[ $args['id'] ] ) ) {
			$value = $this->wccs_settings[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		if ( true === $args['desc_tip'] ) {
			echo '<img class="help_tip" data-tip="' . esc_attr( $args['desc'] ) . '" src="' . esc_url( $this->get_images_url() ) . 'help.png" height="16" width="16" />';
		}
		echo '<select id="wccs_settings[' . esc_attr( $args['id'] ) . ']" name="wccs_settings[' . esc_attr( $args['id'] ) . ']" class="' . ( ! empty( $args['classes'] ) ? esc_attr( $args['classes'] ) : '' ) . '" ' . ( ! empty( $args['style'] ) ? 'style="' . $args['style'] . '"' : '' ) . '/>';

		if ( ! empty( $args['optgroups'] ) ) {
			foreach ( $args['optgroups'] as $optgroup ) {
				if ( empty( $optgroup ) || empty( $optgroup['optgroup'] ) || empty( $optgroup['options'] ) ) {
					continue;
				}

				echo '<optgroup label="' . esc_attr( $optgroup['optgroup'] ) . '">';
				foreach ( $optgroup['options'] as $option => $name ) {
					$selected = selected( $option, $value, false );
					echo '<option value="' . esc_attr( $option ) . '" ' . $selected . '>' . esc_html( $name ) . '</option>';
				}
				echo '</optgroup>';
			}
		} elseif ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option => $name ) {
				$selected = selected( $option, $value, false );
				echo '<option value="' . esc_attr( $option ) . '" ' . $selected . '>' . esc_html( $name ) . '</option>';
			}
		}

		echo '</select>';
		if ( false === $args['desc_tip'] ) {
			echo '<label for="wccs_settings[' . esc_attr( $args['id'] ) . ']"> '  . $args['desc'] . '</label>';
		}
	}

    /**
     * Multiple Select Callback
     *
     * Renders multiple select fields.
     *
     * @since  1.0.0
     * @param  $args array
     * @return void
     */
	public function multiple_select( $args ) {
	    $value = array();
        if ( isset( $this->wccs_settings[ $args['id'] ] ) ) {
            $value = is_array( $this->wccs_settings[ $args['id'] ] ) ? $this->wccs_settings[ $args['id'] ] : explode( ' ', $this->wccs_settings[ $args['id'] ] );
        }

        if ( true === $args['desc_tip'] ) {
            echo '<img class="help_tip" data-tip="' . esc_attr( $args['desc'] ) . '" src="' . esc_url( $this->get_images_url() ) . 'help.png" height="16" width="16" />';
        }
        echo '<select multiple id="wccs_settings[' . esc_attr( $args['id'] ) . ']" name="wccs_settings[' . esc_attr( $args['id'] ) . '][]" class="' . ( ! empty( $args['classes'] ) ? esc_attr( $args['classes'] ) : '' ) . '" ' . ( ! empty( $args['style'] ) ? 'style="' . $args['style'] . '"' : '' ) . '/>';
        if ( ! empty( $args['options'] ) ) {
            foreach ( $args['options'] as $option => $name ) {
                $selected = in_array( $option, $value );
                echo '<option value="' . esc_attr( $option ) . '" ' . ( true === $selected ? 'selected="selected"' : '' ) . '>' . esc_attr( $name ) . '</option>';
            }
        }
        echo '</select>';
        if ( false === $args['desc_tip'] ) {
            echo '<label for="wccs_settings[' . esc_attr( $args['id'] ) . ']"> '  . esc_attr( $args['desc'] ) . '</label>';
        }
    }

	/**
	 * Checkbox Callback
	 *
	 * Renders checkboxes.
	 *
	 * @since  1.0.0
	 * @param  array $args Arguments passed by the setting
	 * @return void
	 */
	public function checkbox( $args ) {
		$checked = isset( $this->wccs_settings[ $args['id'] ] ) ? checked( 1, $this->wccs_settings[ $args['id'] ], false ) : '';
		if ( true === $args['desc_tip'] ) {
			echo '<img class="help_tip" data-tip="' . esc_attr( $args['desc'] ) . '" src="' . esc_url( $this->get_images_url() ) . 'help.png" height="16" width="16" />';
		}
		echo '<input type="checkbox" id="wccs_settings[' . $args['id'] . ']" name="wccs_settings[' . $args['id'] . ']" value="1" ' . $checked . '/>';
		echo '<label for="wccs_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';
	}

	/**
	 * Multicheck Callback
	 *
	 * Renders multiple checkboxes.
	 *
	 * @since 1.0.0
	 * @param array $args Arguments passed by the setting
	 * @return void
	 */
	public function multicheck( $args ) {
		if ( ! empty( $args['options'] ) ) {
			if ( true === $args['desc_tip'] ) {
				echo '<img class="help_tip" data-tip="' . esc_attr( $args['desc'] ) . '" src="' . esc_url( $this->get_images_url() ) . 'help.png" height="16" width="16" />';
			}
			foreach ( $args['options'] as $key => $option ) {
				$enabled = null;
				if ( isset( $this->wccs_settings[ $args['id'] ][ $key ] ) ) {
					$enabled = $this->wccs_settings[ $args['id'] ][ $key ];
				}

				echo '<input name="wccs_settings[' . $args['id'] . '][' . $key . ']"" id="wccs_settings[' . $args['id'] . '][' . $key . ']" type="checkbox" value="1" ' . checked( 1, $enabled, false ) . '/>&nbsp;' .
					'<label for="wccs_settings[' . $args['id'] . '][' . $key . ']">' . esc_attr( $option['name'] ) . '</label><br/>';
			}
			if ( false === $args['desc_tip'] ) {
				echo '<p class="description">' . $args['desc'] . '</p>';
			}
		}
	}

	/**
	 * Sortable Multicheck Callback
	 *
	 * Renders multiple checkboxes.
	 *
	 * @since 1.0.0
	 * @param array $args Arguments passed by the setting
	 * @return void
	 */
	public function sortable_multicheck( $args ) {
		if ( ! empty( $args['options'] ) ) {
			// Sorting options.
			$pos_arr   = array_keys( $args['options'] );
			$positions = implode( ',', $pos_arr );
			if ( isset( $this->wccs_settings[ $args['id'] . '_sortable_positions' ] ) ) {
				$positions = $this->wccs_settings[ $args['id'] . '_sortable_positions' ];
				$pos_arr   = array_map( 'trim', explode( ',', $positions ) );
			}
			// Finding intersection between sorted values and options.
			$pos_arr = array_intersect_key( array_flip( $pos_arr ), array_flip( array_keys( $args['options'] ) ) );
			// Filling sorted keys by values from options.
			$options = array_merge( $pos_arr, $args['options'] );

			echo '<ul id="' . esc_attr( $args['id'] ) . '-sortable" class="ui-sortable">';
			foreach ( $options as $key => $option ) {
				$enabled = null;
				if ( isset( $this->wccs_settings[ $args['id'] ][ $key ] ) ) {
					$enabled = $option['id'];
				}
				echo '<li class="ui-sortable-handle">';
					echo '<input name="wccs_settings[' . $args['id'] . '][' . $key . ']"" id="wccs_settings[' . $args['id'] . '][' . $key . ']" type="checkbox" value="' . esc_attr( $option['id'] ) . '" ' . checked( $option['id'], $enabled, false ) . '/>&nbsp;' .
						'<label for="wccs_settings[' . $args['id'] . '][' . $key . ']">' . esc_attr( $option['name'] ) . '</label>';
				echo '</li>';
			}
			echo '</ul>';
			if ( ! empty( $args['desc'] ) ) {
				echo '<p class="description">' . $args['desc'] . '</p>';
			}
			echo '<input type="hidden" name="wccs_settings[' . esc_attr( $args['id'] ) . '_sortable_positions]" id="' . esc_attr( $args['id'] ) . '_sortable_positions" value="' . esc_attr( $positions ) . '"/>';
		}
	}

	/**
	 * Textarea Callback
	 *
	 * Renders textarea fields.
	 *
	 * @since  1.0.0
	 * @param  array $args Arguments passed by the setting
	 * @return void
	 */
	public function textarea( array $args ) {
		if ( isset( $this->wccs_settings[ $args['id'] ] ) ) {
			$value = $this->wccs_settings[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$html = '<textarea class="large-text" cols="50" rows="5" id="wccs_settings[' . $args['id'] . ']" name="wccs_settings[' . $args['id'] . ']">' . esc_textarea( stripslashes( $value ) ) . '</textarea>';
		$html .= '<label for="wccs_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * Header Callback
	 *
	 * Renders the header.
	 *
	 * @since  1.0.0
	 * @param  array $args Arguments passed by the setting
	 * @return void
	 */
	public function header( $args ) {
		echo '';
	}

	/**
     * Information Callback
     *
     * Renders the info text.
     *
     * @since  1.0.0
     * @param  array $args Arguments passed by the setting
     * @return void
     */
	public function info( $args ) {
	    if ( ! empty( $args['desc'] ) ) {
	        echo $args['desc'];
        }
	}
	
	public function link( array $args ) {
		echo '<a href="' . 
			( ! empty( $args['url'] ) ? esc_url( $args['url'] ) : '' ) . '"' . 
			( ! empty( $args['classes'] ) ? 'class="' . esc_attr( $args['classes'] ) . '"' : '' ) . '>' . 
			esc_html( $args['name'] ) . 
		'</a>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<label for="wccs_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';
		}
	} 

}

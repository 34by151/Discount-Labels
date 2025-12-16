<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $view_args[
 *      @type array tabs
 * ]
 */

$active_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $view_args['tabs'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
$sections   = $view_args['controller']->settings_manager->get_settings_tab_sections( $active_tab );
$key        = 'main';

if ( is_array( $sections ) ) {
	$key = key( $sections );
}

$section    = isset( $_GET['section'] ) && ! empty( $sections ) && array_key_exists( $_GET['section'], $sections ) ? sanitize_text_field( $_GET['section'] ) : $key;

// Unset 'main' if it's empty and default to the first non-empty if it's the chosen section
$all_settings = $view_args['controller']->settings_manager->get_registered_settings();

// Let's verify we have a 'main' section to show
ob_start();
do_settings_sections( 'wccs_settings_' . $active_tab . '_main' );
$has_main_settings = strlen( ob_get_contents() ) > 0;
ob_end_clean();

if ( false === $has_main_settings ) {
	unset( $sections['main'] );

	if ( 'main' === $section ) {
		foreach ( $sections as $section_key => $section_title ) {
			if ( ! empty( $all_settings[ $active_tab ][ $section_key ] ) ) {
				$section = $section_key;
				break;
			}
		}
	}
}
?>
<div id="wccs-settings" class="wrap">
	<h2 class="nav-tab-wrapper">
		<?php
		foreach ( $view_args['tabs'] as $tab_id => $tab_name ) {
			$tab_url = add_query_arg( array(
				'settings-updated' => false,
				'tab'              => $tab_id,
			) );

			// Remove the section from the tabs so we always end up at the main section
			$tab_url = remove_query_arg( 'section', $tab_url );

			$active = $active_tab == $tab_id ? ' nav-tab-active' : '';

			echo '<a href="' . esc_url( $tab_url ) . '" title="' . esc_attr( $tab_name ) . '" class="nav-tab' . $active . '">' .
					esc_html( $tab_name ) . '</a>';
		}
		?>
	</h2>
	<?php
	$number_of_sections = count( $sections );
	$number = 0;
	if ( $number_of_sections > 1 ) {
		echo '<div><ul class="subsubsub">';
		foreach( $sections as $section_id => $section_name ) {
			echo '<li>';
			$number++;
			$tab_url = add_query_arg( array(
				'settings-updated' => false,
				'tab'              => $active_tab,
				'section'          => $section_id,
			) );
			$class = '';
			if ( $section == $section_id ) {
				$class = 'current';
			}
			echo '<a class="' . $class . '" href="' . esc_url( $tab_url ) . '">' . $section_name . '</a>';

			if ( $number != $number_of_sections ) {
				echo ' | ';
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}
	?>
	<div id="tab-container">
		<form method="post" action="options.php">
			<table class="form-table">
			<?php
			settings_fields( 'wccs_settings' );

			if ( 'main' === $section ) {
				do_action( 'wccs_settings_tab_top', $active_tab );
			}

			do_action( 'wccs_settings_tab_top_' . $active_tab . '_' . $section );

			do_settings_sections( 'wccs_settings_' . $active_tab . '_' . $section );

			do_action( 'wccs_settings_tab_bottom_' . $active_tab . '_' . $section  );

			// For backwards compatibility
			if ( 'main' === $section ) {
				do_action( 'wccs_settings_tab_bottom', $active_tab );
			}

			?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
</div>

<?php
// Exit if accessed directly.
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
class WCCS_Admin_Conditions_Menu extends WCCS_Admin_Controller {

	/**
	 * Outputting menu content.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function create_menu() {
		if ( WCCS_Updates::update_required() ) {
			return $this->render_view( 'menu.update-required',
				array(
					'controller' => $this,
				)
			);
		}

		$this->render_view( 'menu.conditions-menu',
			array(
				'controller' => $this,
			)
		);
	}

}

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Roles_Helpers {

	public function get_capabilities() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			get_role( 'administrator' );
		}

		$capabilities = array();
		foreach ( $wp_roles->roles as $role => $data ) {
			if ( empty( $data['capabilities'] ) ) {
				continue;
			}

			foreach ( $data['capabilities'] as $capability => $value ) {
				if ( ! isset( $capabilities[ $capability ] ) ) {
					$capabilities[ $capability ] = true;
				}
			}
		}

		return array_keys( $capabilities );
	}

}

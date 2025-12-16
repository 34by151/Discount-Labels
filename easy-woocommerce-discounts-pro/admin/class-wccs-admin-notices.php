<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-facing notices of the plugin.
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin_Notices extends WCCS_Admin_Controller {

	protected $notices = array();

	private $core_notices = array(
		'update'       => 'update_notice',
		'update_price' => 'update_price_notice',
	);

	public function init() {
		$this->notices = get_option( 'woocommerce_conditions_admin_notices', array() );

		add_action( 'admin_notices', array( &$this, 'settings_notices' ) );
		add_action( 'wp_loaded', array( &$this, 'hide_notices' ) );
		add_action( 'shutdown', array( &$this, 'store_notices' ) );

		if ( current_user_can( 'manage_woocommerce' ) ) {
			add_action( 'admin_print_styles', array( &$this, 'add_notices' ) );
		}
	}

	/**
	 * Store notices to DB
	 *
	 * @since  1.1.0
	 *
	 * @return void
	 */
	public function store_notices() {
		update_option( 'woocommerce_conditions_admin_notices', $this->get_notices() );
	}

	public function get_notices() {
		return $this->notices;
	}

	public function remove_all_notices() {
		$this->notices = array();
	}

	/**
	 * Show a notice.
	 *
	 * @since  1.1.0
	 * @param  string $name
	 *
	 * @return void
	 */
	public function add_notice( $name ) {
		$this->notices = array_unique( array_merge( $this->get_notices(), array( $name ) ) );
	}

	public function add_notices() {
		$notices = $this->get_notices();
		if ( empty( $notices ) ) {
			return;
		}

		wp_enqueue_style( 'woocommerce-activation', plugins_url( '/assets/css/activation.css', WC_PLUGIN_FILE ), array(), WC_VERSION );

		foreach ( $notices as $notice ) {
			if ( ! empty( $this->core_notices[ $notice ] ) && apply_filters( 'wccs_show_admin_notice', true, $notice ) ) {
				add_action( 'admin_notices', array( &$this, $this->core_notices[ $notice ] ) );
			}
		}
	}

	/**
	 * Remove a notice from being displayed.
	 *
	 * @since  1.1.0
	 *
	 * @param  string $name
	 *
	 * @return void
	 */
	public function remove_notice( $name ) {
		$this->notices = array_diff( $this->get_notices(), array( $name ) );
		// delete_option( 'woocommerce_conditions_admin_notice_' . $name );
	}

	public function hide_notices() {
		if ( isset( $_GET['wccs-hide-notice'] ) && isset( $_GET['_wccs_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key ( wp_unslash( $_GET['_wccs_notice_nonce'] ) ), 'woocommerce_conditions_hide_notices_nonce' ) ) {
				wp_die( __( 'Action failed. Please refresh the page and retry.', 'easy-woocommerce-discounts' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'Cheatin&#8217; huh?', 'easy-woocommerce-discounts' ) );
			}

			$hide_notice = sanitize_text_field( wp_unslash( $_GET['wccs-hide-notice'] ) );
			$this->remove_notice( $hide_notice );
			update_user_meta( get_current_user_id(), 'dismissed_' . $hide_notice . '_notice', true );
			do_action( 'wccs_hide_' . $hide_notice . '_notice' );
		}
	}

	public function update_notice() {
		if ( version_compare( get_option( 'woocommerce_conditions_db_version' ), WCCS_VERSION, '<' ) ) {
			$updater = new WCCS_Background_Updater();
			if ( $updater->is_updating() || ! empty( $_GET['do_update_asnp_wccs'] ) ) {
				$this->render_view( 'admin_notices/updating' );
			} else {
				$this->render_view( 'admin_notices/update' );
			}
		} else {
			$this->render_view( 'admin_notices/updated' );
		}
	}

	public function update_price_notice() {
		$this->render_view( 'admin_notices/updating-prices' );
	}

	/**
	 * Settings notices of the plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function settings_notices() {
		settings_errors( 'wccs-notices' );
	}

}

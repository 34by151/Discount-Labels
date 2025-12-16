<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation
 *
 * @link       taher.atashbar@gmail.com
 * @since      1.0.0
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Activator {

	/**
	 * Background update class.
	 *
	 * @var object
	 */
	private static $background_updater;

	/**
	 * Hook in tabs.
	 *
	 * @since  1.1.0
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
		add_action( 'init', array( __CLASS__, 'init_background_updater' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'install_actions' ) );
	}

	/**
	 * Init background updates.
	 *
	 * @since  1.1.0
	 *
	 * @return void
	 */
	public static function init_background_updater() {
		include_once dirname( __FILE__ ) . '/class-wccs-background-updater.php';
		self::$background_updater = new WCCS_Background_Updater();
	}

	/**
	 * Check WooCommerce version and run the updater is required.
	 *
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public static function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) && get_option( 'wccs_version' ) !== WCCS()->version ) {
			self::run_install();
			do_action( 'woocommerce_conditions_updated' );
		}
	}

	/**
	 * Install actions when a update button is clicked within the admin area.
	 *
	 * This function is hooked into admin_init to affect admin only.
	 */
	public static function install_actions() {
		if ( ! empty( $_GET['do_update_asnp_wccs'] ) ) {
			check_admin_referer( 'wccs_db_update', 'wccs_db_update_nonce' );
			self::update();
			WCCS()->WCCS_Admin_Notices->add_notice( 'update' );
		} elseif ( ! empty( $_GET['force_update_asnp_wccs'] ) ) {
			check_admin_referer( 'wccs_db_update', 'wccs_db_update_nonce' );
			do_action( 'wp_' . get_current_blog_id() . '_wccs_updater_cron' );
			wp_safe_redirect( admin_url( 'admin.php?page=wccs-settings' ) );
			exit;
		}
	}

	/**
	 * Activating plugin.
	 *
	 * @since  1.0.0
	 *
	 * @param  $network_wide boolean
	 *
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		self::load_dependencies();

		global $wpdb;

		if ( is_multisite() && $network_wide ) {
			foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs LIMIT 100" ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::run_install();
				restore_current_blog();
			}
		} else {
			self::run_install();
		}
	}

	/**
	 * Loading dependencies.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	private static function load_dependencies() {
		require_once dirname( __FILE__ ) . '/class-wccs-db.php';
		require_once dirname( __FILE__ ) . '/class-wccs-db-conditions.php';
		require_once dirname( __FILE__ ) . '/class-wccs-db-condition-meta.php';
		require_once dirname( __FILE__ ) . '/class-wccs-db-cache.php';
		require_once dirname( __FILE__ ) . '/class-wccs-db-user-usage-logs.php';
		require_once dirname( __FILE__ ) . '/class-wccs-db-rule-usage-logs.php';
		require_once dirname( __FILE__ ) . '/class-wccs-db-analytics.php';
	}

	/**
	 * Running installation.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public static function run_install() {
		if ( ! is_blog_installed() ) {
			return;
		}

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'wccs_installing' ) ) {
			return;
		}

		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient( 'wccs_installing', 'yes', MINUTE_IN_SECONDS * 10 );

		$current_version = get_option( 'wccs_version' );
		if ( ! empty( $current_version ) ) {
			update_option( 'wccs_version_upgraded_from', $current_version );
		}

		// Use add_option() here to avoid overwriting this value with each
		// plugin version update. We base plugin age off of this value.
		add_option( 'wccs_install_timestamp', time() );

		global $wpdb;

		$wpdb->hide_errors();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$conditions = new WCCS_DB_Conditions();
		$conditions->create_table();

		$condition_meta = new WCCS_DB_Condition_Meta();
		$condition_meta->create_table();

		$cache = new WCCS_DB_Cache();
		$cache->create_table();

		$rule_usage_logs = new WCCS_DB_Rule_Usage_Logs();
		$rule_usage_logs->create_table();

		$user_usage_logs = new WCCS_DB_User_Usage_Logs();
		$user_usage_logs->create_table();

		$analytics = new WCCS_DB_Analytics();
		$analytics->create_table();

		self::create_options();
		self::update_version();
		self::maybe_update_db_version();
		self::notices();

		delete_transient( 'wccs_installing' );

		do_action( 'woocommerce_conditions_installed' );
	}

	/**
	 * Default options.
	 *
	 * Sets up the default options used on the settings page.
	 *
	 * @return void
	 */
	private static function create_options() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wccs-settings-manager.php';

		$plugin_settings = new WCCS_Settings();
		$plugin_options  = $plugin_settings->get_settings();

		// Setup some default options
		$options = array();

		$settings_manager = new WCCS_Settings_Manager();
		// Populate some default values
		foreach( $settings_manager->get_registered_settings() as $tab => $sections ) {
			foreach( $sections as $section => $settings) {
				// Check for backwards compatibility
				$tab_sections = $settings_manager->get_settings_tab_sections( $tab );
				if( ! is_array( $tab_sections ) || ! array_key_exists( $section, $tab_sections ) ) {
					$section = 'main';
					$settings = $sections;
				}
				foreach ( $settings as $option ) {
					if ( empty( $option['type'] ) ) {
						continue;
					}

					if( 'checkbox' == $option['type'] && ! empty( $option['std'] ) ) {
						$options[ $option['id'] ] = '1';
					} elseif ( in_array( $option['type'], array( 'multicheck', 'sortable_multicheck' ) ) && ! empty( $option['options'] ) ) {
						foreach ( $option['options'] as $key => $value ) {
							if ( ! empty( $value['std'] ) ) {
								$options[ $option['id'] ][ $key ] = '1';
							}
						}
					} elseif ( 'multiple_select' === $option['type'] && ! empty( $option['std'] ) ) {
						$options[ $option['id'] ] = is_array( $option['std'] ) ? $option['std'] : explode( ',', $option['std'] );
					}
				}
			}
		}

		$options = array_merge( $options, $plugin_options );

		update_option( 'wccs_settings', $options );
	}

	/**
	 * Update WooCommerce Conditions version to current.
	 *
	 * @since  1.1.0
	 *
	 * @return void
	 */
	private static function update_version() {
		delete_option( 'wccs_version' );
		add_option( 'wccs_version', WCCS()->version );
	}

	/**
	 * Get list of DB update callbacks.
	 *
	 * @since  1.1.0
	 *
	 * @return array
	 */
	public static function get_db_update_callbacks() {
		if ( ! class_exists( 'WCCS_Updates' ) ) {
			include_once( dirname( __FILE__ ) . '/class-wccs-updates.php' );
		}

		return array(
			'1.1.0' => array(
				'WCCS_Updates::update_110_conditions',
				'WCCS_Updates::update_110_db_version',
			),
			'3.0.1' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'4.3.0' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'4.4.0' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'4.6.0' => array(
				'WCCS_Updates::update_460',
			),
			'6.7.1' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'7.0.0' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'8.0.0' => array(
				'WCCS_Updates::update_800',
			),
			'8.5.0' => array(
				'WCCS_Updates::update_850',
			),
			'8.5.1' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'8.6.0' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'8.8.0' => array(
				'WCCS_Updates::update_880',
			),
			'8.18.2' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
			'8.18.3' => array(
				'WCCS_Updates::clear_pricing_caches',
			),
		);
	}

	/**
	 * Is a DB update needed?
	 *
	 * @since  1.1.0
	 *
	 * @return boolean
	 */
	private static function needs_db_update() {
		$current_db_version = get_option( 'woocommerce_conditions_db_version' );
		$upgraded_from      = get_option( 'wccs_version_upgraded_from' );
		$updates            = self::get_db_update_callbacks();

		return false !== $upgraded_from && ( false === $current_db_version || version_compare( $current_db_version, max( array_keys( $updates ) ), '<' ) );
	}

	/**
	 * See if we need to show or run database updates during install.
	 *
	 * @since  1.1.0
	 *
	 * @return void
	 */
	private static function maybe_update_db_version() {
		if ( self::needs_db_update() ) {
			if ( apply_filters( 'woocommerce_conditions_enable_auto_update_db', false ) ) {
				self::init_background_updater();
				self::update();
			} else {
				WCCS()->WCCS_Admin_Notices->add_notice( 'update' );
			}
		} else {
			self::update_db_version();
		}
	}

	/**
	 * Push all needed DB updates to the queue for processing.
	 */
	private static function update() {
		$current_db_version = get_option( 'woocommerce_conditions_db_version' );
		$logger             = WCCS_Helpers::wc_get_logger();
		$update_queued      = false;

		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					if ( WCCS_Helpers::wc_version_check() ) {
						$logger->info(
							sprintf( 'Queuing %s - %s', $version, $update_callback ),
							array( 'source' => 'wccs_db_updates' )
						);
					} else {
						$logger->add( 'wccs_db_updates', sprintf( 'Queuing %s - %s', $version, $update_callback ) );
					}
					self::$background_updater->push_to_queue( $update_callback );
					$update_queued = true;
				}
			}
		}

		if ( $update_queued ) {
			self::$background_updater->save()->dispatch();
		}
	}

	private static function notices() {
		$upgraded_from = get_option( 'wccs_version_upgraded_from' );
		if ( $upgraded_from ) {
			if ( version_compare( $upgraded_from, '6.0.0', '<' ) ) {
				WC_Admin_Notices::add_custom_notice(
					'wccs_new_addons',
					'<p><strong>Discount Rules and Dynamic Pricing for WooCommerce:</strong> ' .
						sprintf(
							__( 'New discount addons added to the plugin, please visit the %s for more information.', 'easy-woocommerce-discounts' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wccs-conditions#/addons' ) ) . '">' . __( 'Addons Page', 'easy-woocommerce-discounts' ) . '</a>'
						) . '</p>'
				);
			} elseif ( version_compare( $upgraded_from, '6.2.0', '<' ) ) {
				WC_Admin_Notices::add_custom_notice(
					'wccs_new_addons',
					'<p><strong>Discount Rules and Dynamic Pricing for WooCommerce:</strong> ' .
						sprintf(
							__( 'URL Coupons for WooCommerce addon added to the plugin, please visit the %s for more information.', 'easy-woocommerce-discounts' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wccs-conditions#/addons' ) ) . '">' . __( 'Addons Page', 'easy-woocommerce-discounts' ) . '</a>'
						) . '</p>'
				);
			}
		}

		if ( 
			! defined( 'ASNP_WESB_PRO_VERSION' ) && 
			! WC_Admin_Notices::has_notice( 'ewd_sale_badges' ) &&
			! get_user_meta( get_current_user_id(), 'dismissed_ewd_sale_badges_notice', true )
		) {
			WC_Admin_Notices::add_custom_notice(
				'ewd_sale_badges',
				'<p><a href="https://www.asanaplugins.com/product/woocommerce-sale-badges-and-product-labels/" target="_blank"><strong>Sale Badges and Product Labels for WooCommerce</strong></a> Add beautiful sale badges and product labels or countdown timers to your products, categories, and discount or pricing rules.</p>'
			);
		}
	}

	/**
	 * Update DB version to current.
	 *
	 * @since  1.1.0
	 *
	 * @param  string|null $version New WooCommerce DB version or null.
	 *
	 * @return void
	 */
	public static function update_db_version( $version = null ) {
		update_option( 'woocommerce_conditions_db_version', is_null( $version ) ? WCCS()->version : $version );
	}

}

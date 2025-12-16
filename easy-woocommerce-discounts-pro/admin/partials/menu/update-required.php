<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$update_url = wp_nonce_url(
	add_query_arg( 'do_update_asnp_wccs', 'true', admin_url( 'admin.php?page=wccs-settings' ) ),
	'wccs_db_update',
	'wccs_db_update_nonce'
);
?>
<div id="asnp-ewd-update-required" class="updated woocommerce-message">
	<p><?php _e( 'Please update the plugin database then reload the page!', 'easy-woocommerce-discounts' ); ?></p>
	<p class="submit"><a href="<?php echo esc_url( $update_url ); ?>" class="wccs-update-now button-primary"><?php _e( 'Run the updater', 'easy-woocommerce-discounts' ); ?></a></p>
</div>

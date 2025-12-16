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
<div id="message" class="updated woocommerce-message wc-connect asnp-ewd-update-message">
	<p><strong><?php _e( 'Easy WooCommerce Discounts Pro data update', 'easy-woocommerce-discounts' ); ?></strong> &#8211; <?php _e( 'We need to update database to the latest version.', 'easy-woocommerce-discounts' ); ?></p>
	<p class="submit"><a href="<?php echo esc_url( $update_url ); ?>" class="wccs-update-now button-primary"><?php _e( 'Run the updater', 'easy-woocommerce-discounts' ); ?></a></p>
</div>
<script type="text/javascript">
	jQuery( '.wccs-update-now' ).click( 'click', function() {
		return window.confirm( '<?php echo esc_js( __( 'It is strongly recommended that you backup your database before proceeding. Are you sure you wish to run the updater now?', 'easy-woocommerce-discounts' ) ); ?>' ); // jshint ignore:line
	});
</script>

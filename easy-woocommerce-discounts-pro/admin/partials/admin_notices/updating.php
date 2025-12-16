<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$update_url = wp_nonce_url(
	add_query_arg( 'force_update_asnp_wccs', 'true', admin_url( 'admin.php?page=wccs-settings' ) ),
	'wccs_db_update',
	'wccs_db_update_nonce'
);
?>
<div id="message" class="updated woocommerce-message wc-connect asnp-ewd-updating-message">
	<p><strong><?php _e( 'Easy WooCommerce Discounts Pro data update', 'easy-woocommerce-discounts' ); ?></strong> &#8211; <?php _e( 'Your database is being updated in the background.', 'easy-woocommerce-discounts' ); ?> <a href="<?php echo esc_url( $update_url ); ?>"><?php _e( 'Taking a while? Click here to run it now.', 'easy-woocommerce-discounts' ); ?></a></p>
</div>

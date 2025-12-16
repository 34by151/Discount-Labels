<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="message" class="updated woocommerce-message wc-connect woocommerce-message--success">
	<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wccs-hide-notice', 'update', remove_query_arg( 'do_update_asnp_wccs' ) ), 'woocommerce_conditions_hide_notices_nonce', '_wccs_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'easy-woocommerce-discounts' ); ?></a>

	<p><?php _e( 'Easy WooCommerce Discounts Pro data update complete. Thank you for updating to the latest version!', 'easy-woocommerce-discounts' ); ?></p>
</div>

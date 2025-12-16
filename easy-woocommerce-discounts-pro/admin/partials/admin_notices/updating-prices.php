<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="message" class="updated woocommerce-message">
	<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wccs-hide-notice', 'updating_prices' ), 'woocommerce_conditions_hide_notices_nonce', '_wccs_notice_nonce' ) ); ?>"><?php _e( 'Cancel updating prices', 'easy-woocommerce-discounts' ); ?></a>

	<p><?php esc_html_e( 'Products price updating is running in the background. Depending on the amount of products in your store this may take a while.', 'easy-woocommerce-discounts' ); ?></p>
</div>

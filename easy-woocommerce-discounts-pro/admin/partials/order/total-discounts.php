<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<tr>
    <td class="label"><?php echo esc_html( $view_args['label'] ); ?>:</td>
    <td width="1%"></td>
    <td class="total">
        <?php echo apply_filters( 'wccs_cart_total_discounts_html_prefix', '-' ) . ' ' . wc_price( $view_args['discount'], array( 'currency' => $view_args['order']->get_currency() ) ); // WPCS: XSS ok. ?>
    </td>
</tr>

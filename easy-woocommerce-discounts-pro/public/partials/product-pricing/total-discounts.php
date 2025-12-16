<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<tr class="wccs-order-total-discounts">
    <th><?php echo esc_html( $view_args['label'] ); ?></th>
    <td data-title="<?php echo esc_attr( $view_args['label'] ); ?>"><?php echo $view_args['discount_html']; ?></td>
</tr>

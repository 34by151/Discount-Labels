<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $view_args['table_title'] ) ) {
	echo '<p class="wccs-bulk-pricing-table-title"' . ( ! empty( $view_args['variation'] ) ? ' data-variation="' . esc_attr( $view_args['variation'] ) . '"' : '' ) . ' style="' . ( ! empty( $view_args['variation'] ) ? 'display:none' : '' ) . '"><strong>' . esc_html( $view_args['table_title'] ) . '</strong></p>';
}
?>
<div class="wccs-bulk-pricing-table-container" <?php echo ! empty( $view_args['product_id'] ) ? 'data-product="' . esc_attr( $view_args['product_id'] ) . '"' : '' ?> <?php echo ! empty( $view_args['variation'] ) ? 'data-variation="' . esc_attr( $view_args['variation'] ) . '"' : '' ?> style="<?php echo ! empty( $view_args['variation'] ) ? 'display:none' : ''; ?>">
	<table class="wccs-bulk-pricing-table wccs-vertical-table" <?php echo ! empty( $view_args['variation'] ) ? 'data-variation="' . esc_attr( $view_args['variation'] ) . '"' : '' ?>>
		<thead>
			<tr>
				<?php
				if ( 'yes' === $view_args['discount']['display_quantity'] ) {
					echo '<th>' . esc_html( $view_args['quantity_label'] ) . '</th>';
				}
				if ( 'yes' === $view_args['discount']['display_discount'] ) {
					echo '<th>' . esc_html( $view_args['discount_label'] ) . '</th>';
				}
				if ( 'yes' === $view_args['discount']['display_price'] ) {
					echo '<th>' . esc_html( $view_args['price_label'] ) . '</th>';
				}
				?>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $view_args['discount']['quantities'] as $discount ) {
			echo '<tr>';
			if ( 'yes' === $view_args['discount']['display_quantity'] ) {
				echo '<td data-type="quantity" data-quantity-min="' . esc_attr( $discount['min'] ) . '" data-quantity-max="' . ( ! empty( $discount['max'] ) ? esc_attr( $discount['max'] ) : '' ) . '">' . esc_html( apply_filters( 'wccs_quantity_table_quantity', $discount['min'] . ( ! empty( $discount['max'] ) ? ( $discount['min'] != $discount['max'] ? ' - ' . $discount['max'] : '' ) : ' +' ) ) ) . '</td>';
			}
			if ( 'yes' === $view_args['discount']['display_discount'] ) {
				echo '<td data-type="discount" data-quantity-min="' . esc_attr( $discount['min'] ) . '" data-quantity-max="' . ( ! empty( $discount['max'] ) ? esc_attr( $discount['max'] ) : '' ) . '">' . apply_filters( 'wccs_quantity_table_discount', $view_args['controller']->get_discount_value_html( $discount['discount'], $discount['discount_type'] ) ) . '</td>';
			}
			if ( 'yes' === $view_args['discount']['display_price'] ) {
				echo '<td data-type="price" data-quantity-min="' . esc_attr( $discount['min'] ) . '" data-quantity-max="' . ( ! empty( $discount['max'] ) ? esc_attr( $discount['max'] ) : '' ) . '">' . apply_filters( 'wccs_quantity_table_price', $view_args['controller']->get_discounted_price( $discount['discount'], $discount['discount_type'] ) ) . '</td>';
			}
			echo '</tr>';
		}
		?>
		</tbody>
	</table>
</div>

<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title = __( 'Limited-time offer! Sale ends in', 'easy-woocommerce-discounts' );
if ( (int) WCCS()->settings->get_setting( 'localization_enabled', 1 ) ) {
    $title = WCCS()->settings->get_setting( 'countdown_timer_title', $title );
}
$title = apply_filters( 'wccs_countdown_timer_title', $title );
?>
<div class="wccs-countdown-timer-container" id="wccs-countdown-timer-container" style="display: none;">
    <?php
    if ( ! empty( $title ) ) {
        echo '<div class="wccs-countdown-timer-title">' . $title . '</div>';
    }
    ?>
    <div class="wccs-countdown-timer-content" id="wccs-countdown-timer-content"></div>
</div>

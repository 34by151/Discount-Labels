<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Checkout_Helpers {

    public static function get_billing_email() {
        if ( empty( $_GET['wc-ajax'] ) || ! in_array( $_GET['wc-ajax'], array( 'checkout', 'update_order_review' ) ) ) {
            return '';
        }

        $email = '';
        if ( ! empty( $_POST['billing_email'] ) ) {
            $email = strtolower( sanitize_email( $_POST['billing_email'] ) );
        } elseif ( ! empty( $_POST['post_data'] ) ) {
            parse_str( $_POST['post_data'], $post );
            if ( ! empty( $post['billing_email'] ) ) {
                $email = strtolower( sanitize_email( $post['billing_email'] ) );
            }
        }
        return is_email( $email ) ? $email : '';
    }

}

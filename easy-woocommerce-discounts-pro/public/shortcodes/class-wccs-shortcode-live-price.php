<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Shortcode_Live_Price extends WCCS_Public_Controller {

    public function output( $atts, $content = null ) {
        ob_start();
        $this->render_view( 'product-pricing.live-price', array( 'controller' => $this ) );
        return ob_get_clean();
    }

}

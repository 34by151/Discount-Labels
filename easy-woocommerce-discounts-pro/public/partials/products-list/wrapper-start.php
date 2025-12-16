<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'wc_get_default_products_per_row' ) ) {
	echo '<div class="woocommerce-conditions-products-list woocommerce columns-' . wc_get_default_products_per_row() . '">';
} else {
	echo '<div class="woocommerce-conditions-products-list woocommerce woocommerce-page">';

	$template = get_option( 'template' );

	switch ( $template ) {
		case 'twentyeleven' :
			echo '<div id="primary"><div id="content" role="main" class="twentyeleven">';
			break;
		case 'twentytwelve' :
			echo '<div id="primary" class="site-content"><div id="content" role="main" class="twentytwelve">';
			break;
		case 'twentythirteen' :
			echo '<div id="primary" class="site-content"><div id="content" role="main" class="entry-content twentythirteen">';
			break;
		case 'twentyfourteen' :
			echo '<div id="primary"><div id="content" role="main" class="site-content twentyfourteen"><div class="tfwc">';
			break;
		case 'twentyfifteen' :
			echo '<div id="primary" role="main" class="twentyfifteen"><div id="main" class="site-main t15wc">';
			break;
		case 'twentysixteen' :
			echo '<div id="primary" class="twentysixteen"><main id="main" class="site-main" role="main">';
			break;
		default :
			echo '<div id="container"><div id="content" role="main">';
			break;
	}
}

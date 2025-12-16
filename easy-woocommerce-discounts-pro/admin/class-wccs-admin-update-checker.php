<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5p5\PucFactory' ) ) {
    require_once dirname( dirname( __FILE__ ) ) . '/includes/vendor/plugin-update-checker/plugin-update-checker.php';
}

class WCCS_Admin_Update_Checker extends \YahnisElsts\PluginUpdateChecker\v5p5\PucFactory {}

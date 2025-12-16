<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Rest_Api {
    /**
	 * Constructor
	 */
    public function __construct() {
        $this->init();
    }

    /**
	 * Initialize class features.
	 */
    protected function init() {
        // REST API was included starting WordPress 4.4.
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
        }

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
    }

    /**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
        $controllers = array(
			'review'    => WCCS_Rest_Review::class,
            'analytics' => WCCS_Rest_Analytics::class,
		);

        foreach ( $controllers as $name => $class ) {
			$instance = new $class();
			$instance->register_routes();
		}
    }
}

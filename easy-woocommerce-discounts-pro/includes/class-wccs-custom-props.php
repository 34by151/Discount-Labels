<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Custom_Props {

    protected $map;

    public function __construct( $map = null ) {
        if ( null !== $map ) {
            $this->map = $map;
        } else {
            $this->map = version_compare( PHP_VERSION, '8.2', '>=' ) ? new WeakMap() : null;
        }
    }

    public function set_prop( &$object, $key, $value ) {
        if ( isset( $this->map ) ) {
            if ( ! isset( $this->map[ $object ] ) ) {
                $this->map[ $object ] = new stdClass();
            }
            $this->map[ $object ]->{ $key } = $value;
        } else {
            $object->{ $key } = $value;
        }
    }

    public function get_prop( $object, $key, $default = null ) {
        if ( isset( $this->map ) ) {
            return isset( $this->map[ $object ]->{ $key } ) ? $this->map[ $object ]->{ $key } : $default;
        }

        return isset( $object->{ $key } ) ? $object->{ $key } : $default;
    }

}

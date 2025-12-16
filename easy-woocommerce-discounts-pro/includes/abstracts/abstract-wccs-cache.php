<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Abstract_Cache {

    const TYPE = '';

    protected $cache_prefix;
    protected $cache_group;

    public function __construct( $cache_prefix, $cache_group ) {
        $this->cache_prefix = $cache_prefix;
        $this->cache_group  = $cache_group;
    }

    public function get_cache_prefix() {
        return $this->cache_prefix;
    }

    public function get_cache_group() {
        return $this->cache_group;
    }

    public function clear_cache() {
        if ( empty( static::TYPE ) ) {
            return false;
        }

        WCCS()->WCCS_DB_Cache->delete_items_by_type( static::TYPE );
    }

    public function clear_cache_deprecated() {
        $this->delete_transient_group();
        // WC_Cache_Helper::get_transient_version( $this->cache_group, true );
    }

    public function get_transient_version( $refresh = false ) {
        return WC_Cache_Helper::get_transient_version( $this->cache_group, $refresh );
    }

    public function get_transient_name( array $args = array() ) {
        if ( empty( $args ) ) {
            return false;
        }

        return $this->cache_prefix . 
            md5( 
                wp_json_encode( $args ) . 
                WC_Cache_Helper::get_transient_version( $this->cache_group ) 
            );
    }

    public function delete_transient_group() {
        if ( wp_using_ext_object_cache() ) {
            return;
        }

        do_action( 'wccs_cache_' . __FUNCTION__, $this->cache_group, $this->cache_prefix );

        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . $this->cache_group ) . '%',
                $wpdb->esc_like( '_transient_timeout_' . $this->cache_group ) . '%',
                $wpdb->esc_like( '_transient_' . $this->cache_group . '-transient-version' ) . '%'
            )
        );
    }

    public function delete_transient( array $args = array() ) {
        $transient_name = $this->get_transient_name( $args );
        if ( $transient_name ) {
            delete_transient( $transient_name );
        }
    }

}

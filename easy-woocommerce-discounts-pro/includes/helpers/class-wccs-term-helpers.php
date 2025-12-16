<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Term_Helpers {

    /**
     * Helper to get cached object terms and filter by field using wp_list_pluck().
     * Works as a cached alternative for wp_get_post_terms() and wp_get_object_terms().
     *
     * @since  2.2.1
     *
     * @param  int    $object_id Object ID.
     * @param  string $taxonomy  Taxonomy slug.
     * @param  string $field     Field name.
     * @param  string $index_key Index key name.
     *
     * @return array
     */
    public function wc_get_object_terms( $object_id, $taxonomy, $field = null, $index_key = null ) {
        if ( function_exists( 'wc_get_object_terms' ) ) {
            return wc_get_object_terms( $object_id, $taxonomy, $field, $index_key );
        }

        // Test if terms exists. get_the_terms() return false when it finds no terms.
        $terms = get_the_terms( $object_id, $taxonomy );

        if ( ! $terms || is_wp_error( $terms ) || is_null( $field ) ) {
            return array();
        }

        return wp_list_pluck( $terms, $field, $index_key );
    }

}

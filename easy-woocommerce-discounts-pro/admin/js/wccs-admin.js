(function( $, window ) {
    'use strict';

    /**
	 * WCCS Admin class.
	 *
	 * @since 2.7.0
	 */
    window.WCCS_Admin = function() {};

    /**
	 * Using color picker for color picker elements.
	 *
	 * @since   2.7.0
     *
	 * @returns void
	 */
	WCCS_Admin.prototype.colorPicker = function() {
		if ( ! jQuery().wpColorPicker ) {
			return;
		}

		// Using color picker in setting page.
		jQuery( 'input[type=text].wccs-colorpick-setting' ).wpColorPicker();
    };

    $( function() {
        var wccsAmin = new WCCS_Admin();
        wccsAmin.colorPicker();
    });

})( jQuery, window );

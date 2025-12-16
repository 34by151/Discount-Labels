(function( $ ) {
    'use strict';

    var Cart = function() {
        // Methods.
        this.init                              = this.init.bind( this );
        this.updateCartContent                 = this.updateCartContent.bind( this );
        this.shippingCalculatorSubmit          = this.shippingCalculatorSubmit.bind( this );
        this.updateCartContentOnShippingSubmit = this.updateCartContentOnShippingSubmit.bind( this );

        this.init();
    };

    Cart.prototype.init = function() {
        this.$cartForm               = $( '.woocommerce-cart-form' );
        this.$shippingCalculatorForm = $( 'from.woocommerce-shipping-calculator' );
    }

    Cart.prototype.shippingListeners = function() {
        if ( ! this.$cartForm.length || ! wccs_cart_params || 'disabled' === wccs_cart_params.update_cart_on_shipping_change ) {
            return;
        }

        $( document.body ).on( 'updated_shipping_method', this.updateCartContent );

        if ( this.$shippingCalculatorForm ) {
            $( document.body ).on( 'updated_wc_div', this.updateCartContentOnShippingSubmit );
            $( document ).on(
                'submit',
                this.$shippingCalculatorForm,
                this.shippingCalculatorSubmit
            );
        }
    };

    Cart.prototype.updateCartContent = function() {
        $( document.body ).trigger( 'wc_update_cart', false );
    };

    Cart.prototype.updateCartContentOnShippingSubmit = function() {
        if ( this.shippingCalculatorSubmited ) {
            this.updateCartContent();
            this.shippingCalculatorSubmited = false;
        }
    }

    Cart.prototype.shippingCalculatorSubmit = function() {
        this.shippingCalculatorSubmited = true;
    }

    /**
	 * Creating a singleton instance of Cart.
	 */
	var Singleton = (function() {
		var instance;

		return {
			getInstance: function() {
				if ( ! instance ) {
					instance = new Cart();
				}
				return instance;
			}
		}
    })();
    
    $.fn.wccs_get_cart = function() {
		return Singleton.getInstance();
	}

    $(function() {
        var cart = $().wccs_get_cart();
        cart.shippingListeners();
    });
})( jQuery );

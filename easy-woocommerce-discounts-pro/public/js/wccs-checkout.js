(function( $ ) {
    'use strict';

    var Checkout = function() {
        // Methods.
        this.init                  = this.init.bind( this );
        this.triggerUpdateCheckout = this.triggerUpdateCheckout.bind( this );
        this.onPaymentMethodChange = this.onPaymentMethodChange.bind( this );

        this.init();
    };

    Checkout.prototype.init = function() {
        this.$checkoutForm = $( 'form.checkout' );
        this.listeners();
    }

    Checkout.prototype.listeners = function() {
        if ( ! this.$checkoutForm.length ) {
            return;
        }

        $( document.body ).on( 'updated_checkout', this.onPaymentMethodChange );

        this.onPaymentMethodChange();
    }

    Checkout.prototype.onPaymentMethodChange = function() {
        if ( ! this.$checkoutForm.length ) {
            return;
        }

        var that = this;
        $( 'input[name="payment_method"], input[name="billing_email"]', this.$checkoutForm ).each(function() {
            $( this ).on( 'change', that.triggerUpdateCheckout );
        });
    };

    Checkout.prototype.triggerUpdateCheckout = function() {
        $( document.body ).trigger( 'update_checkout' );
    }

    /**
	 * Creating a singleton instance of Checkout.
	 */
	var Singleton = (function() {
		var instance;

		return {
			getInstance: function() {
				if ( ! instance ) {
					instance = new Checkout();
				}
				return instance;
			}
		}
    })();
    
    $.fn.wccs_get_checkout = function() {
		return Singleton.getInstance();
	}

    $(function() {
        $().wccs_get_checkout();
    });
})( jQuery );

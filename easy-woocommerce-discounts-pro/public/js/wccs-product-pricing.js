( function ( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	/**
	 * ProductPricing class which handles discounted prices.
	 *
	 * @since  1.0.0
	 */
	var ProductPricing = function () {
		this.prices = {};
		this.changedPrice = false;
		// Contains productIds that countdown timer is initialized for them.
		this.productTimer = [];

		// Methods.
		this.init = this.init.bind( this );
		this.onFoundVariation = this.onFoundVariation.bind( this );
		this.onHideVariation = this.onHideVariation.bind( this );
		this.listeners = this.listeners.bind( this );
		this.delayLivePricing = this.delayLivePricing.bind( this );
		this.livePricing = this.livePricing.bind( this );
		this.countDownTimer = this.countDownTimer.bind( this );
		this.stopCountdownTimer = this.stopCountdownTimer.bind( this );
		this.changePrice = this.changePrice.bind( this );
		this.changePriceByBulk = this.changePriceByBulk.bind( this );
		this.setProductPrice = this.setProductPrice.bind( this );
		this.setVariationPrice = this.setVariationPrice.bind( this );
		this.getProductPrice = this.getProductPrice.bind( this );
		this.getProductRegularPrice = this.getProductRegularPrice.bind( this );
		this.getVariationPrice = this.getVariationPrice.bind( this );
		this.getVariationRegularPrice = this.getVariationRegularPrice.bind(
			this
		);
		this.tracks = this.tracks.bind( this );

		this.init();
	};

	/**
	 * Initialize.
	 */
	ProductPricing.prototype.init = function () {
		this.$bulkTables = $( '.wccs-bulk-pricing-table-container' );
		this.$bulkTitles = $( '.wccs-bulk-pricing-table-title' );
		this.$messages = $(
			'.wccs-purchase-message, .wccs-shortcode-purchase-message'
		);

		if ( $( '.variations_form' ).length ) {
			this.$parentTable = this.$bulkTables.not( '[data-variation]' );
			this.$parentTableTitle = this.$bulkTitles.not( '[data-variation]' );
			this.$parentMessage = this.$messages.not( '[data-variation]' );
			this.$variationForm = $( '.variations_form' ).first();
			$( document.body ).on(
				'found_variation.wccs_product_pricing',
				this.$variationForm,
				this.onFoundVariation
			);
			$( document.body ).on(
				'hide_variation.wccs_product_pricing',
				this.$variationForm,
				this.onHideVariation
			);
		}

		this.$cartForm = $( '.product form.cart' ).first();
		this.$livePriceContainer = $( '.wccs-live-price-container' );
		this.$countdownTimer = $( '.wccs-countdown-timer-container' );

		this.changePrice();
		if ( ! this.$variationForm ) {
			this.delayLivePricing( 500 );
		}

		this.listeners();
		this.tracks();
	};

	/**
	 * Listeners.
	 *
	 * @since   2.2.0
	 *
	 * @returns void
	 */
	ProductPricing.prototype.listeners = function () {
		var that = this;
		this.$cartForm.on(
			'change keyup',
			'input, select, textarea, .qty',
			function () {
				if ( 'variation_id' === $( this ).attr( 'name' ) ) {
					return;
				}

				that.changePrice();
				that.delayLivePricing( 500 );
			}
		);

		if (
			wccs_product_pricing_params.set_min_quantity &&
			1 == wccs_product_pricing_params.set_min_quantity
		) {
			this.$cartForm.on( 'change', 'input.qty', function () {
				var min = $( this ).attr( 'min' );
				if ( null != min && '' !== min && false !== min ) {
					if ( parseFloat( $( this ).val() ) < parseFloat( min ) ) {
						$( this ).val( min );
					}
				}

				var max = $( this ).attr( 'max' );
				if ( null != max && '' !== max && false !== max ) {
					if ( parseFloat( $( this ).val() ) > parseFloat( max ) ) {
						$( this ).val( max );
					}
				}
			} );
		}

		// Triggers when product add-ons update.
		this.$cartForm.on(
			'woocommerce-product-addons-update.wccs_product_pricing',
			function () {
				that.changePrice();
				that.delayLivePricing( 500 );
			}
		);

		// Triggers when extra product options update.
		$( window ).on( 'tc-epo-after-update', function ( e ) {
			that.changePrice();
			that.delayLivePricing( 500 );
		} );
	};

	/**
	 * Handler function execute when WooCommerce found_variation triggered.
	 *
	 * @since  1.0.0
	 *
	 * @param  event
	 * @param  variation
	 *
	 * @return void
	 */
	ProductPricing.prototype.onFoundVariation = function ( event, variation ) {
		// Bulk pricing table.
		if ( this.$bulkTables.length ) {
			this.$bulkTables.hide();
			this.$bulkTitles.hide();
			if (
				this.$bulkTables.filter(
					'[data-variation="' + variation.variation_id + '"]'
				).length
			) {
				this.$bulkTables
					.filter(
						'[data-variation="' + variation.variation_id + '"]'
					)
					.show();
				this.$bulkTitles
					.filter(
						'[data-variation="' + variation.variation_id + '"]'
					)
					.show();
			} else if ( this.$parentTable.length ) {
				this.$parentTable.show();
				this.$parentTableTitle.show();
			}
			this.changePrice();
		}

		// Purchase pricing messages.
		if ( this.$messages.length ) {
			this.$messages.hide();
			if (
				this.$messages.filter(
					'[data-variation="' + variation.variation_id + '"]'
				).length
			) {
				this.$messages
					.filter(
						'[data-variation="' + variation.variation_id + '"]'
					)
					.show();
			} else if ( this.$parentMessage.length ) {
				this.$parentMessage.show();
			}
		}

		this.delayLivePricing( 500 );
	};

	/**
	 * Handler function execute when WooCommerce hide_variation triggered.
	 *
	 * @since  1.0.0
	 *
	 * @param  event
	 *
	 * @return void
	 */
	ProductPricing.prototype.onHideVariation = function ( event ) {
		// Bulk pricing table.
		if ( this.$bulkTables.length ) {
			this.$bulkTables.hide();
			this.$bulkTitles.hide();
			if ( this.$parentTable.length ) {
				this.$parentTable.show();
				this.$parentTableTitle.show();
			}
			this.changePrice();
		}

		// Purchase pricing messages.
		if ( this.$messages.length ) {
			this.$messages.hide();
			if ( this.$parentMessage.length ) {
				this.$parentMessage.show();
			}
		}
	};

	/**
	 * Call live pricing with a delay.
	 *
	 * @since   3.8.2
	 *
	 * @returns void
	 */
	ProductPricing.prototype.delayLivePricing = function ( delay ) {
		if ( ! delay ) {
			return this.livePricing();
		}

		if ( this.delayedLivePricing ) {
			clearTimeout( this.delayedLivePricing );
		}

		this.delayedLivePricing = setTimeout( this.livePricing, delay );
	};

	/**
	 * Display live price of the product.
	 *
	 * @since   2.2.0
	 *
	 * @returns void
	 */
	ProductPricing.prototype.livePricing = function () {
		var productId = $( 'input[name="variation_id"' ).val();
		if ( ! productId ) {
			productId = $( 'button[name="add-to-cart"]' ).val();
			productId = productId
				? productId
				: $( 'input[name="add-to-cart"' ).val();
		}

		if ( this.changedPrice ) {
			if (
				! wccs_product_pricing_params.display_countdown_timer ||
				0 == wccs_product_pricing_params.display_countdown_timer
			) {
				return;
			}

			if ( ! productId || isNaN( productId * 1 ) || 0 >= productId * 1 ) {
				return;
			}

			if (
				this.productTimer.length &&
				-1 < this.productTimer.indexOf( productId * 1 )
			) {
				return;
			}
		}

		if (
			! wccs_product_pricing_params.display_live_price ||
			1 != wccs_product_pricing_params.display_live_price
		) {
			if (
				! wccs_product_pricing_params.display_live_total_price ||
				1 != wccs_product_pricing_params.display_live_total_price
			) {
				if (
					! wccs_product_pricing_params.display_countdown_timer ||
					0 == wccs_product_pricing_params.display_countdown_timer
				) {
					return;
				}
			}
		}

		if ( ! this.$cartForm.length ) {
			return;
		}

		this.$livePriceContainer = this.$livePriceContainer.length
			? this.$livePriceContainer
			: $( '.wccs-live-price-container' );
		this.$countdownTimer = this.$countdownTimer.length
			? this.$countdownTimer
			: $( '.wccs-countdown-timer-container' );

		if (
			! this.$livePriceContainer.length &&
			! this.$countdownTimer.length
		) {
			return;
		}

		// Set delay if livePricing is already running.
		if ( this.livePriceRunning ) {
			this.delayLivePricing( 500 );
			return;
		}

		var content = '';
		if (
			! this.changedPrice &&
			wccs_product_pricing_params.display_live_price &&
			1 == wccs_product_pricing_params.display_live_price
		) {
			if ( ! $( '.wccs-live-price', this.$livePriceContainer ).length ) {
				content +=
					'<div class="wccs-live-price-section wccs-live-price-price-section">';
				content += wccs_product_pricing_params.live_pricing_label
					? '<span>' +
					  wccs_product_pricing_params.live_pricing_label +
					  ':</span> '
					: '';
				content += '<span class="wccs-live-price price"></span>';
				content += '</div>';
			}
		}
		if (
			! this.changedPrice &&
			wccs_product_pricing_params.display_live_total_price &&
			1 == wccs_product_pricing_params.display_live_total_price
		) {
			if (
				! $( '.wccs-live-total-price', this.$livePriceContainer ).length
			) {
				content +=
					'<div class="wccs-live-price-section wccs-live-price-total-price-section">';
				content += wccs_product_pricing_params.live_pricing_total_label
					? '<span>' +
					  wccs_product_pricing_params.live_pricing_total_label +
					  ':</span> '
					: '';
				content += '<span class="wccs-live-total-price price"></span>';
				content += '</div>';
			}
		}
		if ( ! this.changedPrice && content.length ) {
			this.$livePriceContainer.html( content );
		}

		if ( ! this.changedPrice ) {
			$(
				'.wccs-live-price, .wccs-live-total-price',
				this.$livePriceContainer
			).html( '' );
			var dots = 0;
			var interval = setInterval( function () {
				if ( 0 === dots++ % 5 ) {
					$(
						'.wccs-live-price, .wccs-live-total-price',
						this.$livePriceContainer
					).html( '.' );
				} else {
					$(
						'.wccs-live-price, .wccs-live-total-price',
						this.$livePriceContainer
					).append( '.' );
				}
			}, 250 );
		}

		this.livePriceRunning = true;

		var data = this.$cartForm.serialize(),
			that = this;
		if ( ! /add-to-cart=/gi.test( data ) ) {
			data += '&add-to-cart=' + wccs_product_pricing_params.product_id;
		}

		$.ajax( {
			url: wccs_product_pricing_params.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wccs_live_price',
				nonce: wccs_product_pricing_params.nonce,
				data: data,
			},
		} )
			.done( function ( response ) {
				clearInterval( interval );
				if ( response && 1 == response.success && response.data ) {
					if (
						! that.changedPrice &&
						wccs_product_pricing_params.display_live_price &&
						1 == wccs_product_pricing_params.display_live_price
					) {
						if ( false !== response.data.price ) {
							var price = '';
							if ( response.data.prices_quantities ) {
								var countPrices = 0;
								for ( var prop in response.data
									.prices_quantities ) {
									price +=
										'<div class="wccs-live-price-qty"><span class="wccs-live-price-qty-price">' +
										prop +
										'</span> <span class="wccs-live-price-qty-quantity"> &times; ' +
										response.data.prices_quantities[
											prop
										] +
										'</span></div>';
									++countPrices;
								}
								if ( 1 == countPrices && prop ) {
									price = prop;
								} else {
									price = price.length
										? '<div class="wccs-live-price-qty-container">' +
										  price +
										  '</div>'
										: '';
									if (
										price.length &&
										response.data.prices_quantities_sum
									) {
										price +=
											'<div class="wccs-live-price-qty-total"><span>' +
											response.data
												.prices_quantities_sum +
											'</span></div>';
									}
								}
							}
							$(
								'.wccs-live-price',
								that.$livePriceContainer
							).html(
								price.length ? price : response.data.price
							);
						} else if (
							null !== response.data.main_price &&
							'always' ===
								wccs_product_pricing_params.live_price_display_type
						) {
							$(
								'.wccs-live-price',
								that.$livePriceContainer
							).html( response.data.main_price );
						}
					}

					if (
						! that.changedPrice &&
						wccs_product_pricing_params.display_live_total_price &&
						1 ==
							wccs_product_pricing_params.display_live_total_price
					) {
						if ( false !== response.data.price ) {
							$(
								'.wccs-live-total-price',
								that.$livePriceContainer
							).html( response.data.total_price );
						} else if (
							null != response.data.main_total_price &&
							'always' ===
								wccs_product_pricing_params.live_price_display_type
						) {
							$(
								'.wccs-live-total-price',
								that.$livePriceContainer
							).html( response.data.main_total_price );
						}
					}

					if (
						wccs_product_pricing_params.display_countdown_timer &&
						0 != wccs_product_pricing_params.display_countdown_timer
					) {
						if ( response.data.remaining_time ) {
							that.countDownTimer( response.data.remaining_time );
						} else {
							that.stopCountdownTimer();
						}
					}

					if (
						productId &&
						! isNaN( productId * 1 ) &&
						0 < productId * 1 &&
						-1 == that.productTimer.indexOf( productId * 1 )
					) {
						that.productTimer.push( productId * 1 );
					}

					if ( ! that.changedPrice ) {
						if (
							false !== response.data.price ||
							( null != response.data.main_price &&
								'always' ===
									wccs_product_pricing_params.live_price_display_type )
						) {
							that.$livePriceContainer.slideDown();
						} else {
							that.$livePriceContainer.slideUp().html( '' );
						}
					}
				} else {
					that.stopCountdownTimer();
					if ( ! that.changedPrice ) {
						that.$livePriceContainer.slideUp().html( '' );
					}
				}
			} )
			.fail( function () {
				that.stopCountdownTimer();
				if ( ! that.changedPrice ) {
					that.$livePriceContainer.slideUp().html( '' );
				}
			} )
			.always( function () {
				that.livePriceRunning = false;
				clearInterval( interval );
			} );
	};

	ProductPricing.prototype.countDownTimer = function ( remainingTime ) {
		if ( this.flipDown ) {
			if (
				false === this.flipDown.remainingTime ||
				remainingTime < this.flipDown.remainingTime
			) {
				this.flipDown.updateTime( {
					type: 'remainingTime',
					value: remainingTime - 1,
				} );
			}

			return;
		}

		this.$countdownTimer.slideDown();
		this.flipDown = new FlipDown(
			{ type: 'remainingTime', value: remainingTime - 1 },
			'wccs-countdown-timer-content',
			{
				headingPosition: 'bottom',
				daysLabel: wccs_product_pricing_params.countdown_timer_days,
				hoursLabel: wccs_product_pricing_params.countdown_timer_hours,
				minutesLabel:
					wccs_product_pricing_params.countdown_timer_minutes,
				secondsLabel:
					wccs_product_pricing_params.countdown_timer_seconds,
			}
		).start();
	};

	ProductPricing.prototype.stopCountdownTimer = function () {
		if ( ! this.flipDown ) {
			return;
		}

		this.flipDown.stop();
		this.flipDown = false;
		this.$countdownTimer.slideUp().html( '' );
	};

	ProductPricing.prototype.changePrice = function () {
		this.changedPrice = false;
		if ( ! this.$bulkTables.length ) {
			return;
		}

		if (
			'undefined' !==
				wccs_product_pricing_params.bulk_pricing_change_price &&
			'server' === wccs_product_pricing_params.bulk_pricing_change_price
		) {
			return;
		}

		var productId = $( 'button[name="add-to-cart"]' ).val();
		productId = productId
			? productId
			: $( 'input[name="add-to-cart"' ).val();
		var variationId = $( 'input[name="variation_id"' ).val();

		if ( 0 < productId ) {
			this.changedPrice = this.changePriceByBulk( productId, false );
		}

		if ( 0 < variationId ) {
			var changed = this.changePriceByBulk( variationId, true );
			this.changedPrice = changed ? changed : this.changedPrice;
		}
	};

	ProductPricing.prototype.changePriceByBulk = function (
		productId,
		isVariation
	) {
		if ( ! this.$bulkTables.length ) {
			return;
		}

		var defaultPrice = isVariation
			? this.getVariationPrice( productId )
			: this.getProductPrice( productId );

		const variationPriceUpdate =
			'undefined' !==
				typeof wccs_product_pricing_params.variation_pirce_update &&
			1 == wccs_product_pricing_params.variation_pirce_update;

		var $table = $(
			'.wccs-bulk-pricing-table-container[data-product="' +
				productId +
				'"]'
		);
		if ( ! $table.length ) {
			if ( isVariation ) {
				if ( variationPriceUpdate ) {
					this.setProductPrice( defaultPrice, productId );
				}
			} else {
				this.setProductPrice( defaultPrice, productId );
			}
			return false;
		}

		var quantity = $( '.qty', this.$cartForm ).val();
		if ( ! quantity || isNaN( quantity * 1 ) || 0 >= quantity * 1 ) {
			if ( isVariation ) {
				this.setVariationPrice( defaultPrice, productId );
				if ( variationPriceUpdate ) {
					this.setProductPrice( defaultPrice, productId );
				}
			} else {
				this.setProductPrice( defaultPrice, productId );
			}
			return false;
		}

		var $td;

		$( 'td[data-type="price"]', $table ).each( function () {
			var $this = $( this );
			var min = $this.data( 'quantity-min' );
			var max = $this.data( 'quantity-max' );
			min = min && ! isNaN( min * 1 ) ? min * 1 : '';
			max = max && ! isNaN( max * 1 ) && 0 < max * 1 ? max * 1 : '';

			if ( '' != min && quantity >= min ) {
				if ( '' === max || quantity <= max ) {
					$td = $this;
					return false;
				}
			}
		} );

		if ( isVariation ) {
			var price = $td ? $td.html() : defaultPrice;
			this.setVariationPrice( price, productId );
			if ( variationPriceUpdate ) {
				this.setProductPrice( price, productId );
			}
		} else {
			var price = $td ? $td.html() : defaultPrice;
			this.setProductPrice( price, productId );
		}

		return true;
	};

	ProductPricing.prototype.setProductPrice = function ( price, productId ) {
		if ( ! /<del/.test( price ) ) {
			var regularPrice = this.getProductRegularPrice( productId );
			if ( regularPrice && regularPrice != price ) {
				price =
					'<del aria-hidden="true">' +
					regularPrice +
					'</del> <ins>' +
					price +
					'</ins>';
			}
		}

		$( '.product .price, .product .wc-block-components-product-price' )
			.first()
			.html( price );
	};

	ProductPricing.prototype.setVariationPrice = function ( price, productId ) {
		if ( ! /<del/.test( price ) ) {
			var regularPrice = this.getVariationRegularPrice( productId );
			if ( regularPrice && regularPrice != price ) {
				price =
					'<del aria-hidden="true">' +
					regularPrice +
					'</del> <ins>' +
					price +
					'</ins>';
			}
		}

		$( '.woocommerce-variation .price' ).first().html( price );
	};

	ProductPricing.prototype.getProductPrice = function ( productId ) {
		if ( ! productId ) {
			return false;
		}

		if ( this.prices[ productId ] ) {
			return this.prices[ productId ];
		}

		var $price = $(
			'.product .price, .product .wc-block-components-product-price'
		).first();
		if ( ! $price || ! $price.length ) {
			return false;
		}

		this.prices[ productId ] = $price.html();

		return this.prices[ productId ];
	};

	ProductPricing.prototype.getProductRegularPrice = function ( productId ) {
		var price = this.getProductPrice( productId );
		if ( ! price ) {
			return false;
		}

		var match = price.match( /<del\b[^>]*>(.*?)<\/del>/i );
		return match ? match[ 1 ] : price;
	};

	ProductPricing.prototype.getVariationPrice = function ( variationId ) {
		if ( ! variationId ) {
			return false;
		}

		if ( this.prices[ variationId ] ) {
			return this.prices[ variationId ];
		}

		var $price = $( '.woocommerce-variation .price' ).first();
		if ( ! $price || ! $price.length ) {
			return false;
		}

		this.prices[ variationId ] = $price.html();

		return this.prices[ variationId ];
	};

	ProductPricing.prototype.getVariationRegularPrice = function (
		variationId
	) {
		var price = this.getVariationPrice( variationId );
		if ( ! price ) {
			return false;
		}

		var match = price.match( /<del\b[^>]*>(.*?)<\/del>/i );
		return match ? match[ 1 ] : price;
	};

	ProductPricing.prototype.tracks = function () {
		if (
			'undefined' === typeof wccs_product_pricing_params.analytics ||
			0 >= wccs_product_pricing_params.analytics
		) {
			return;
		}

		var productId = $( 'button[name="add-to-cart"]' ).val();
		productId = productId
			? productId
			: $( 'input[name="add-to-cart"' ).val();

		if (
			! productId &&
			'undefined' !== typeof wccs_product_pricing_params.product_id
		) {
			productId = wccs_product_pricing_params.product_id;
		}

		if ( ! productId || 0 >= productId * 1 ) {
			return;
		}

		$.ajax( {
			url: wccs_product_pricing_params.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'asnp_wccs_product_tracks',
				nonce: wccs_product_pricing_params.nonce,
				product_id: productId,
			},
		} );
	};

	/**
	 * Creating a singleton instance of ProductPricing.
	 */
	var Singleton = ( function () {
		var instance;

		return {
			getInstance: function () {
				if ( ! instance ) {
					instance = new ProductPricing();
				}
				return instance;
			},
		};
	} )();

	$.fn.wccs_get_product_pricing = function () {
		return Singleton.getInstance();
	};

	$( function () {
		$().wccs_get_product_pricing();
	} );
} )( jQuery );

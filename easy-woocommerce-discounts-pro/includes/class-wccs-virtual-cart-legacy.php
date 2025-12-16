<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Virtual_Cart extends WCCS_Abstract_Virtual_Cart {

    public function __construct( $cart = null ) {
        parent::__construct( $cart );
    }

    /**
     * Function to apply discounts to a product and get the discounted price (before tax is applied).
     *
     * @param mixed $values
     * @param mixed $price
     * @param bool $add_totals (default: false)
     * @return float price
     */
    public function get_discounted_price( $values, $price, $add_totals = false ) {
        if ( ! $price ) {
            return $price;
        }

        $undiscounted_price = $price;

        if ( ! empty( $this->coupons ) ) {
            $product = $values['data'];

            foreach ( $this->coupons as $code => $coupon ) {
                if ( $coupon->is_valid() && ( $coupon->is_valid_for_product( $product, $values ) || $coupon->is_valid_for_cart() ) ) {
                    $discount_amount = $coupon->get_discount_amount( 'yes' === get_option( 'woocommerce_calc_discounts_sequentially', 'no' ) ? $price : $undiscounted_price, $values, true );
                    $discount_amount = min( $price, $discount_amount );
                    $price           = max( $price - $discount_amount, 0 );

                    // Store the totals for DISPLAY in the cart.
                    if ( $add_totals ) {
                        $total_discount     = $discount_amount * $values['quantity'];
                        $total_discount_tax = 0;

                        if ( wc_tax_enabled() && $product->is_taxable() ) {
                            $tax_rates          = WC_Tax::get_rates( $product->get_tax_class() );
                            $taxes              = WC_Tax::calc_tax( $discount_amount, $tax_rates, $this->prices_include_tax );
                            $total_discount_tax = WC_Tax::get_tax_total( $taxes ) * $values['quantity'];
                            $total_discount     = $this->prices_include_tax ? $total_discount - $total_discount_tax : $total_discount;
                            $this->discount_cart_tax += $total_discount_tax;
                        }

                        $this->discount_cart     += $total_discount;
                    }
                }

                // If the price is 0, we can stop going through coupons because there is nothing more to discount for this product.
                if ( 0 >= $price ) {
                    break;
                }
            }
        }

        return apply_filters( 'woocommerce_get_discounted_price', $price, $values, $this );
    }

    /**
     * Calculate totals for the items in the cart.
     *
     * @since 2.2.2
     */
    public function calculate_totals() {
        $this->coupons = $this->get_coupons();

        do_action( 'wccs_virtual_cart_before_calculate_totals', $this->cart );

        if ( $this->is_empty() ) {
            return;
        }

        $tax_rates      = array();
        $shop_tax_rates = array();
        $cart           = $this->get_cart();

        /**
         * Calculate subtotals for items. This is done first so that discount logic can use the values.
         */
        foreach ( $cart as $cart_item_key => $values ) {
            $product           = $values['data'];
            $line_price        = WCCS()->product_helpers->wc_get_price( $product ) * $values['quantity'];
            $line_subtotal     = 0;
            $line_subtotal_tax = 0;

            $tax_class = WCCS_Helpers::wc_version_check() ? $product->get_tax_class( true ) : $product->tax_class;

            /**
             * No tax to calculate.
             */
            if ( ! $product->is_taxable() ) {

                // Subtotal is the undiscounted price
                $this->subtotal += $line_price;
                $this->subtotal_ex_tax += $line_price;

            /**
             * Prices include tax.
             *
             * To prevent rounding issues we need to work with the inclusive price where possible.
             * otherwise we'll see errors such as when working with a 9.99 inc price, 20% VAT which would.
             * be 8.325 leading to totals being 1p off.
             *
             * Pre tax coupons come off the price the customer thinks they are paying - tax is calculated.
             * afterwards.
             *
             * e.g. $100 bike with $10 coupon = customer pays $90 and tax worked backwards from that.
             */
            } elseif ( $this->prices_include_tax ) {

                // Get base tax rates
                if ( empty( $shop_tax_rates[ $tax_class ] ) ) {
                    $shop_tax_rates[ $tax_class ] = WC_Tax::get_base_tax_rates( $tax_class );
                }

                // Get item tax rates
                if ( empty( $tax_rates[ $product->get_tax_class() ] ) ) {
                    $tax_rates[ $product->get_tax_class() ] = WC_Tax::get_rates( $product->get_tax_class() );
                }

                $base_tax_rates = $shop_tax_rates[ $tax_class ];
                $item_tax_rates = $tax_rates[ $product->get_tax_class() ];

                /**
                 * ADJUST TAX - Calculations when base tax is not equal to the item tax.
                 *
                 * The woocommerce_adjust_non_base_location_prices filter can stop base taxes being taken off when dealing with out of base locations.
                 * e.g. If a product costs 10 including tax, all users will pay 10 regardless of location and taxes.
                 * This feature is experimental @since 2.4.7 and may change in the future. Use at your risk.
                 */
                if ( $item_tax_rates !== $base_tax_rates && apply_filters( 'woocommerce_adjust_non_base_location_prices', true ) ) {

                    // Work out a new base price without the shop's base tax
                    $taxes                 = WC_Tax::calc_tax( $line_price, $base_tax_rates, true, true );

                    // Now we have a new item price (excluding TAX)
                    $line_subtotal         = $line_price - array_sum( $taxes );

                    // Now add modified taxes
                    $tax_result            = WC_Tax::calc_tax( $line_subtotal, $item_tax_rates );
                    $line_subtotal_tax     = array_sum( $tax_result );

                /**
                 * Regular tax calculation (customer inside base and the tax class is unmodified.
                 */
                } else {

                    // Calc tax normally
                    $taxes                 = WC_Tax::calc_tax( $line_price, $item_tax_rates, true );
                    $line_subtotal_tax     = array_sum( $taxes );
                    $line_subtotal         = $line_price - array_sum( $taxes );
                }

            /**
             * Prices exclude tax.
             *
             * This calculation is simpler - work with the base, untaxed price.
             */
            } else {

                // Get item tax rates
                if ( empty( $tax_rates[ $product->get_tax_class() ] ) ) {
                    $tax_rates[ $product->get_tax_class() ] = WC_Tax::get_rates( $product->get_tax_class() );
                }

                $item_tax_rates        = $tax_rates[ $product->get_tax_class() ];

                // Base tax for line before discount - we will store this in the order data
                $taxes                 = WC_Tax::calc_tax( $line_price, $item_tax_rates );
                $line_subtotal_tax     = array_sum( $taxes );

                $line_subtotal         = $line_price;
            }

            // Add to main subtotal
            $this->subtotal        += $line_subtotal + $line_subtotal_tax;
            $this->subtotal_ex_tax += $line_subtotal;
        }

        // Order cart items by price so coupon logic is 'fair' for customers and not based on order added to cart.
        uasort( $cart, apply_filters( 'woocommerce_sort_by_subtotal_callback', array( $this, 'sort_by_subtotal' ) ) );

        /**
         * Calculate totals for items.
         */
        foreach ( $cart as $cart_item_key => $values ) {

            $product = $values['data'];

            // Prices
            $base_price = WCCS()->product_helpers->wc_get_price( $product );
            $line_price = WCCS()->product_helpers->wc_get_price( $product ) * $values['quantity'];

            // Tax data
            $taxes = array();
            $discounted_taxes = array();

            /**
             * No tax to calculate.
             */
            if ( ! $product->is_taxable() ) {

                // Discounted Price (price with any pre-tax discounts applied)
                $discounted_price      = $this->get_discounted_price( $values, $base_price, true );
                $line_subtotal_tax     = 0;
                $line_subtotal         = $line_price;
                $line_tax              = 0;
                $line_total            = round( $discounted_price * $values['quantity'], WCCS_Helpers::wc_get_rounding_precision() );

            /**
             * Prices include tax.
             */
            } elseif ( $this->prices_include_tax ) {

                $base_tax_rates = $shop_tax_rates[ $tax_class ];
                $item_tax_rates = $tax_rates[ $product->get_tax_class() ];

                /**
                 * ADJUST TAX - Calculations when base tax is not equal to the item tax.
                 *
                 * The woocommerce_adjust_non_base_location_prices filter can stop base taxes being taken off when dealing with out of base locations.
                 * e.g. If a product costs 10 including tax, all users will pay 10 regardless of location and taxes.
                 * This feature is experimental @since 2.4.7 and may change in the future. Use at your risk.
                 */
                if ( $item_tax_rates !== $base_tax_rates && apply_filters( 'woocommerce_adjust_non_base_location_prices', true ) ) {

                    // Work out a new base price without the shop's base tax
                    $taxes             = WC_Tax::calc_tax( $line_price, $base_tax_rates, true, true );

                    // Now we have a new item price (excluding TAX)
                    $line_subtotal     = round( $line_price - array_sum( $taxes ), WCCS_Helpers::wc_get_rounding_precision() );
                    $taxes             = WC_Tax::calc_tax( $line_subtotal, $item_tax_rates );
                    $line_subtotal_tax = array_sum( $taxes );

                    // Adjusted price (this is the price including the new tax rate)
                    $adjusted_price    = ( $line_subtotal + $line_subtotal_tax ) / $values['quantity'];

                    // Apply discounts and get the discounted price FOR A SINGLE ITEM
                    $discounted_price  = $this->get_discounted_price( $values, $adjusted_price, true );

                    // Convert back to line price
                    $discounted_line_price = $discounted_price * $values['quantity'];

                    // Now use rounded line price to get taxes.
                    $discounted_taxes  = WC_Tax::calc_tax( $discounted_line_price, $item_tax_rates, true );
                    $line_tax          = array_sum( $discounted_taxes );
                    $line_total        = $discounted_line_price - $line_tax;

                /**
                 * Regular tax calculation (customer inside base and the tax class is unmodified.
                 */
                } else {

                    // Work out a new base price without the item tax
                    $taxes             = WC_Tax::calc_tax( $line_price, $item_tax_rates, true );

                    // Now we have a new item price (excluding TAX)
                    $line_subtotal     = $line_price - array_sum( $taxes );
                    $line_subtotal_tax = array_sum( $taxes );

                    // Calc prices and tax (discounted)
                    $discounted_price = $this->get_discounted_price( $values, $base_price, true );

                    // Convert back to line price
                    $discounted_line_price = $discounted_price * $values['quantity'];

                    // Now use rounded line price to get taxes.
                    $discounted_taxes  = WC_Tax::calc_tax( $discounted_line_price, $item_tax_rates, true );
                    $line_tax          = array_sum( $discounted_taxes );
                    $line_total        = $discounted_line_price - $line_tax;
                }

                // Tax rows - merge the totals we just got
                foreach ( array_keys( $this->taxes + $discounted_taxes ) as $key ) {
                    $this->taxes[ $key ] = ( isset( $discounted_taxes[ $key ] ) ? $discounted_taxes[ $key ] : 0 ) + ( isset( $this->taxes[ $key ] ) ? $this->taxes[ $key ] : 0 );
                }

            /**
             * Prices exclude tax.
             */
            } else {

                $item_tax_rates        = $tax_rates[ $product->get_tax_class() ];

                // Work out a new base price without the shop's base tax
                $taxes                 = WC_Tax::calc_tax( $line_price, $item_tax_rates );

                // Now we have the item price (excluding TAX)
                $line_subtotal         = $line_price;
                $line_subtotal_tax     = array_sum( $taxes );

                // Now calc product rates
                $discounted_price      = $this->get_discounted_price( $values, $base_price, true );
                $discounted_taxes      = WC_Tax::calc_tax( $discounted_price * $values['quantity'], $item_tax_rates );
                $discounted_tax_amount = array_sum( $discounted_taxes );
                $line_tax              = $discounted_tax_amount;
                $line_total            = $discounted_price * $values['quantity'];

                // Tax rows - merge the totals we just got
                foreach ( array_keys( $this->taxes + $discounted_taxes ) as $key ) {
                    $this->taxes[ $key ] = ( isset( $discounted_taxes[ $key ] ) ? $discounted_taxes[ $key ] : 0 ) + ( isset( $this->taxes[ $key ] ) ? $this->taxes[ $key ] : 0 );
                }
            }

            // Cart contents total is based on discounted prices and is used for the final total calculation
            $this->cart_contents_total += $line_total;

            /**
             * Store costs + taxes for lines. For tax inclusive prices, we do some extra rounding logic so the stored
             * values "add up" when viewing the order in admin. This does have the disadvatage of not being able to
             * recalculate the tax total/subtotal accurately in the future, but it does ensure the data looks correct.
             *
             * Tax exclusive prices are not affected.
             */
            if ( ! $product->is_taxable() || $this->prices_include_tax ) {
                $this->cart_contents[ $cart_item_key ]['line_total']        = round( $line_total + $line_tax - wc_round_tax_total( $line_tax ), $this->dp );
                $this->cart_contents[ $cart_item_key ]['line_subtotal']     = round( $line_subtotal + $line_subtotal_tax - wc_round_tax_total( $line_subtotal_tax ), $this->dp );
                $this->cart_contents[ $cart_item_key ]['line_tax']          = wc_round_tax_total( $line_tax );
                $this->cart_contents[ $cart_item_key ]['line_subtotal_tax'] = wc_round_tax_total( $line_subtotal_tax );
                $this->cart_contents[ $cart_item_key ]['line_tax_data']     = array( 'total' => array_map( 'wc_round_tax_total', $discounted_taxes ), 'subtotal' => array_map( 'wc_round_tax_total', $taxes ) );
            } else {
                $this->cart_contents[ $cart_item_key ]['line_total']        = $line_total;
                $this->cart_contents[ $cart_item_key ]['line_subtotal']     = $line_subtotal;
                $this->cart_contents[ $cart_item_key ]['line_tax']          = $line_tax;
                $this->cart_contents[ $cart_item_key ]['line_subtotal_tax'] = $line_subtotal_tax;
                $this->cart_contents[ $cart_item_key ]['line_tax_data']     = array( 'total' => $discounted_taxes, 'subtotal' => $taxes );
            }
        }

        $is_vat_exempt = is_callable( array( WC()->customer, 'get_is_vat_exempt' ) ) ? WC()->customer->get_is_vat_exempt() : WC()->customer->is_vat_exempt();

        // Only calculate the grand total + shipping if on the cart/checkout
        if ( is_checkout() || is_cart() || defined( 'WOOCOMMERCE_CHECKOUT' ) || defined( 'WOOCOMMERCE_CART' ) ) {

            // Calculate the Shipping
            $this->calculate_shipping();

            // Trigger the fees API where developers can add fees to the cart
            $this->calculate_fees();

            // Total up/round taxes and shipping taxes
            if ( $this->round_at_subtotal ) {
                $this->tax_total          = WC_Tax::get_tax_total( $this->taxes );
                $this->shipping_tax_total = WC_Tax::get_tax_total( $this->shipping_taxes );
                $this->taxes              = array_map( array( 'WC_Tax', 'round' ), $this->taxes );
                $this->shipping_taxes     = array_map( array( 'WC_Tax', 'round' ), $this->shipping_taxes );
            } else {
                $this->tax_total          = array_sum( $this->taxes );
                $this->shipping_tax_total = array_sum( $this->shipping_taxes );
            }

            // VAT exemption done at this point - so all totals are correct before exemption
            if ( $is_vat_exempt ) {
                $this->remove_taxes();
            }

            // Grand Total - Discounted product prices, discounted tax, shipping cost + tax
            $this->total = max( 0, apply_filters( 'woocommerce_calculated_total', round( $this->cart_contents_total + $this->tax_total + $this->shipping_tax_total + $this->shipping_total + $this->fee_total, $this->dp ), $this ) );

        } else {

            // Set tax total to sum of all tax rows
            $this->tax_total = WC_Tax::get_tax_total( $this->taxes );

            // VAT exemption done at this point - so all totals are correct before exemption
            if ( $is_vat_exempt ) {
                $this->remove_taxes();
            }
        }

        do_action( 'wccs_virtual_cart_after_calculate_totals', $this->cart );
    }

}

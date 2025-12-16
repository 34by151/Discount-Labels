<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with Product Add-Ons.
 * 
 * @link  https://woocommerce.com/products/product-add-ons/
 *
 * @since 3.9.0
 */
class WCCS_Compatibility_Product_Addons {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        if ( 'product_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
            $this->loader->add_filter( 'wccs_cart_item_discounted_price', $this, 'cart_item_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_price', $this, 'cart_item_main_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_display_price', $this, 'cart_item_main_display_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_before_discounted_price', $this, 'cart_item_before_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_prices', $this, 'cart_item_prices', 10, 2 );
            $this->loader->add_filter( 'wccs_live_price_get_prices_quantities', $this, 'live_price_get_prices_quantities', 10, 2 );
            $this->loader->add_filter( 'wccs_live_price_total_price', $this, 'get_total_price', 10, 3 );
            $this->loader->add_filter( 'wccs_live_price_main_total_price', $this, 'get_total_price', 10, 3 );
            $this->loader->add_filter( 'wccs_live_price_cart_item_discounted_price', $this, 'live_cart_item_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_live_price_cart_item_main_price', $this, 'live_cart_item_discounted_price', 10, 2 );
        }   
    }

    public function cart_item_discounted_price( $discounted_price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $discounted_price;
        }

        return (float) $discounted_price + $this->get_addons_price( $cart_item, $discounted_price );
    }

    public function cart_item_main_price( $price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $price;
        }

        return (float) $price + $this->get_addons_price( $cart_item, (float) $price );
    }

    public function live_cart_item_discounted_price( $discounted_price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $discounted_price;
        }

        if ( $this->has_flat_fee( $cart_item ) ) {
            return $discounted_price;
        }

        return $this->cart_item_discounted_price( $discounted_price, $cart_item );
    }

    public function cart_item_main_display_price( $price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $price;
        }

        return (float) $price + $this->get_addons_price( $cart_item, (float) $price );
    }

    public function cart_item_before_discounted_price( $price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $price;
        }

        $product_price = (float) WCCS()->product_helpers->wc_get_price( $cart_item['data']->get_id() );
        return WCCS()->cart->get_product_price(
            $cart_item['data'],
            array(
                'price' => $product_price + $this->get_addons_price( $cart_item, $product_price ),
            )
        );
    }

    public function cart_item_prices( $prices, $cart_item ) {
        if ( empty( $cart_item['addons'] ) || empty( $prices ) ) {
            return $prices;
        }

        $product_price  = (float) WCCS()->product_helpers->wc_get_price( $cart_item['data']->get_id() );
        $options_prices = $this->get_addons_price( $cart_item, $product_price );
        if ( empty( $options_prices ) ) {
            return $prices;
        }

        $value = array();

        foreach ( $prices as $price => $qty ) {
            $price                    = (float) $price + $options_prices;
            $value[ (string) $price ] = $qty;
        }

        return $value;
    }

    public function live_price_get_prices_quantities( $prices, $cart_item ) {
        if ( empty( $cart_item['addons'] ) || empty( $prices ) ) {
            return $prices;
        }

        // Do not change prices when there is flat_fee option.
        if ( $this->has_flat_fee( $cart_item ) ) {
            return $prices;
        }

        return $this->cart_item_prices( $prices, $cart_item );
    }

    public function get_total_price( $total_price, $price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $total_price;
        }

        if ( ! $this->has_flat_fee( $cart_item ) ) {
            return $total_price;
        }

        return ( (float) $price + $this->get_addons_price( $cart_item, $price ) ) * $cart_item['quantity'];
    }

    public function live_price_cart_item( $cart_item_data, $data ) {
        if ( empty( $cart_item_data ) || empty( $data ) ) {
            return $cart_item_data;
        }

        $product_addon_cart = $GLOBALS['Product_Addon_Cart'];
        if ( ! isset( $product_addon_cart ) ) {
            return $cart_item_data;
        }

        $_POST = $data;
        return $product_addon_cart->add_cart_item_data( $cart_item_data, $data['add-to-cart'] );
    }

    public function get_addons_price( $cart_item_data, $product_price ) {
        if ( empty( $cart_item_data ) ) {
            return 0;
        }

        // Adapted from WC_Product_Addons_Cart->add_cart_item method.
        $price    = 0;
        $quantity = $cart_item_data['quantity'];
        foreach ( $cart_item_data['addons'] as $addon ) {
            $price_type  = $addon['price_type'];
            $addon_price = $addon['price'];

            switch ( $price_type ) {
                case 'percentage_based':
                    $price += (float) ( $product_price * ( $addon_price / 100 ) );
                    break;
                case 'flat_fee':
                    $price += (float) ( $addon_price / $quantity );
                    break;
                default:
                    $price += (float) $addon_price;
                    break;
            }
        }

        return (float) $price;
    }

    public function has_flat_fee( $cart_item_data ) {
        if ( empty( $cart_item_data ) || empty( $cart_item_data['addons'] ) ) {
            return false;
        }

        foreach ( $cart_item_data['addons'] as $addon ) {
            if ( 'flat_fee' === $addon['price_type'] ) {
                return true;
            }
        }

        return false;
    }

}

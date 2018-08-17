<?php

namespace WC_Toolkit;

use \WC_Data_Store;
use \Exception;

class Ajax
{
    public function __construct()
    {
        $ajax_events = [
            'add_to_cart',
            'remove_from_cart',
            'cart_set_quantity',
            'add_coupon',
            'remove_coupon',
            'cart_fragments'
        ];

        foreach ($ajax_events as $ajax_event) {
            add_action('wc_ajax_site_' . $ajax_event, [ $this, $ajax_event ]);
        }
    }


    /**
     * Returns fragments on success
     */
    public function add_to_cart()
    {
        $product_id = absint($this->_get('product_id'));
        $quantity = $this->_get('quantity') ? absint($this->_get('quantity')) : 1;
        $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
        $product = wc_get_product( $product_id );

        if ( !$passed_validation ) {
            $this->_return_first_notice();
        }

        if ( $product->get_type() == 'variable' ) {
            $cart_item = $this->add_to_cart_handler_variable($product_id);
        } else {
            $cart_item = WC()->cart->add_to_cart($product_id, $quantity);
        }

        if ($cart_item) {
            wc_clear_notices();
            $this->_send_cart_success();
        } else {
            $this->_return_first_notice();
        }
    }


    /**
     * Returns fragments on success
     */
    public function remove_from_cart()
    {
        $item_key = wc_clean($this->_get('item_key'));

        if ($item_key) {
            WC()->cart->remove_cart_item($item_key);
            $this->_send_cart_success();
        }

        exit();
    }


    /**
     * Note: Has allowance for quantity to be zero
     */
    public function cart_set_quantity()
    {
        $item_key = sanitize_text_field($this->_get('item_key'));
        $quantity = absint($this->_get('quantity'));

        if (! $item_key) {
            wp_send_json_error();
        }


        if (! $quantity) {
            // remove
            WC()->cart->remove_cart_item($item_key);
        } else {

            $cart_item = WC()->cart->get_cart_item($item_key);
            $product_data = wc_get_product($cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id']);

            $passed_validation  = apply_filters( 'woocommerce_update_cart_validation', true, $item_key, $cart_item, $quantity );

            if ( !$passed_validation ) {
                $this->_return_first_notice();
            }


            // Stock check - this time accounting for whats already in-cart
            if ($managing_stock = $product_data->managing_stock()) {
                /**
                 * Check stock based on all items in the cart
                 */
                if (! $product_data->has_enough_stock($quantity)) {
                    wp_send_json_error(array(
                        'message' => sprintf(
                            __('You cannot add that amount to the cart &mdash; we have %s in stock and you already have %s in your cart.', 'woocommerce'),
                            $product_data->get_stock_quantity(),
                            $cart_item['quantity']
                        )
                    ));
                }
            }


            // ok to change the qty
            WC()->cart->set_quantity($item_key, $quantity);
        }

        $this->_send_cart_success();
    }


    /**
     * Sends fragments on success or first error notice on error
     */
    public function add_coupon()
    {
        $coupon_code = sanitize_text_field($this->_get('coupon_code'));

        if (! $coupon_code) {
            wp_send_json_error(array(
                'message' => __('Coupon could not be applied.', 'wc-toolkit')
            ));
        }

        if (WC()->cart->add_discount($coupon_code)) {
            wc_clear_notices(); // remove success message
            $this->_send_cart_success();
        } else {
            $this->_return_first_notice();
        }
    }


    /**
     * Sends fragments on success or first error notice on error
     */
    public function remove_coupon()
    {
        $coupon_code = sanitize_text_field($this->_get('coupon_code'));

        if (! $coupon_code) {
            wp_send_json_error(array(
                'message' => __('Coupon could not removed.', 'wc-toolkit')
            ));
        }


        if (WC()->cart->remove_coupon($coupon_code)) {
            wc_clear_notices(); // remove success message
            $this->_send_cart_success();
        } else {
            $this->_return_first_notice();
        }
    }


    /**
     * Get the first notice and clear
     */
    private function _return_first_notice()
    {
        // catch notices and return first notice
        $notices = wc_get_notices();

        if (! empty($notices)) {
            wc_clear_notices();
            $notice = current($notices);
            if (is_array($notice)) {
                $notice = current($notice);
            }

            $notice = strip_tags($notice);
            $notice = str_replace(__('View Cart', 'woocommerce'), '', $notice);

            wp_send_json_error(array(
                'message' => $notice
            ));
        } else {
            wp_send_json_error(array(
                'message' => $notices
            ));
        }
    }

    /**
     * Handle adding variable products to the cart
     * @since 2.4.6 Split from add_to_cart_action
     * @param int $product_id
     * @return bool success or not
     */
    private function add_to_cart_handler_variable( $product_id ) {
        try {
            $variation_id       = empty( $_REQUEST['variation_id'] ) ? '' : absint( wp_unslash( $_REQUEST['variation_id'] ) );
            $quantity           = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_REQUEST['quantity'] ) ); // WPCS: sanitization ok.
            $missing_attributes = array();
            $variations         = array();
            $adding_to_cart     = wc_get_product( $product_id );

            if ( ! $adding_to_cart ) {
                return false;
            }

            // If the $product_id was in fact a variation ID, update the variables.
            if ( $adding_to_cart->is_type( 'variation' ) ) {
                $variation_id   = $product_id;
                $product_id     = $adding_to_cart->get_parent_id();
                $adding_to_cart = wc_get_product( $product_id );

                if ( ! $adding_to_cart ) {
                    return false;
                }
            }

            // Gather posted attributes.
            $posted_attributes = array();

            foreach ( $adding_to_cart->get_attributes() as $attribute ) {
                if ( ! $attribute['is_variation'] ) {
                    continue;
                }
                $attribute_key = 'attribute_' . sanitize_title( $attribute['name'] );

                if ( isset( $_REQUEST[ $attribute_key ] ) ) {
                    if ( $attribute['is_taxonomy'] ) {
                        // Don't use wc_clean as it destroys sanitized characters.
                        $value = sanitize_title( wp_unslash( $_REQUEST[ $attribute_key ] ) );
                    } else {
                        $value = html_entity_decode( wc_clean( wp_unslash( $_REQUEST[ $attribute_key ] ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ); // WPCS: sanitization ok.
                    }

                    $posted_attributes[ $attribute_key ] = $value;
                }
            }

            // If no variation ID is set, attempt to get a variation ID from posted attributes.
            if ( empty( $variation_id ) ) {
                $data_store   = WC_Data_Store::load( 'product' );
                $variation_id = $data_store->find_matching_product_variation( $adding_to_cart, $posted_attributes );
            }

            // Do we have a variation ID?
            if ( empty( $variation_id ) ) {
                throw new Exception( __( 'Please choose product options&hellip;', 'woocommerce' ) );
            }

            // Check the data we have is valid.
            $variation_data = wc_get_product_variation_attributes( $variation_id );

            foreach ( $adding_to_cart->get_attributes() as $attribute ) {
                if ( ! $attribute['is_variation'] ) {
                    continue;
                }

                // Get valid value from variation data.
                $attribute_key = 'attribute_' . sanitize_title( $attribute['name'] );
                $valid_value   = isset( $variation_data[ $attribute_key ] ) ? $variation_data[ $attribute_key ]: '';

                /**
                 * If the attribute value was posted, check if it's valid.
                 *
                 * If no attribute was posted, only error if the variation has an 'any' attribute which requires a value.
                 */
                if ( isset( $posted_attributes[ $attribute_key ] ) ) {
                    $value = $posted_attributes[ $attribute_key ];

                    // Allow if valid or show error.
                    if ( $valid_value === $value ) {
                        $variations[ $attribute_key ] = $value;
                    } elseif ( '' === $valid_value && in_array( $value, $attribute->get_slugs() ) ) {
                        // If valid values are empty, this is an 'any' variation so get all possible values.
                        $variations[ $attribute_key ] = $value;
                    } else {
                        throw new Exception( sprintf( __( 'Invalid value posted for %s', 'woocommerce' ), wc_attribute_label( $attribute['name'] ) ) );
                    }
                } elseif ( '' === $valid_value ) {
                    $missing_attributes[] = wc_attribute_label( $attribute['name'] );
                }
            }
            if ( ! empty( $missing_attributes ) ) {
                throw new Exception( sprintf( _n( '%s is a required field', '%s are required fields', count( $missing_attributes ), 'woocommerce' ), wc_format_list_of_items( $missing_attributes ) ) );
            }
        } catch ( Exception $e ) {
            wc_add_notice( $e->getMessage(), 'error' );
            return false;
        }

        $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

        if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations ) ) {
            wc_add_to_cart_message( array( $product_id => $quantity ), true );
            return true;
        }

        return false;
    }

    public function cart_fragments()
    {
        $this->_send_cart_success();
    }

    private function _send_cart_success()
    {
        wp_send_json_success([
            'fragments' => Cart_Fragments::get_fragments(),
            'hash' => Cart_Fragments::get_fragments_hash()
        ]);

    }

    /**
     * @param $key
     * @return mixed
     */
    private function _get($key)
    {
        if (isset($_REQUEST[$key])) {
            return $_REQUEST[$key];
        }
        return false;
    }
}

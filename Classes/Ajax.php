<?php

namespace WC_Toolkit;

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
        $variation_id = absint($this->_get('variation_id'));
        $quantity = $this->_get('quantity') ? absint($this->_get('quantity')) : 1;


        if (! empty($variation_id)) {
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
    private function add_to_cart_handler_variable($product_id)
    {
        $adding_to_cart     = wc_get_product($product_id);
        $variation_id       = empty($_REQUEST['variation_id']) ? '' : absint($_REQUEST['variation_id']);
        $quantity           = empty($_REQUEST['quantity']) ? 1 : wc_stock_amount($_REQUEST['quantity']);
        $missing_attributes = array();
        $variations         = array();
        $attributes         = $adding_to_cart->get_attributes();
        $variation          = wc_get_product($variation_id);

        // Verify all attributes
        foreach ($attributes as $attribute) {
            if (! $attribute['is_variation']) {
                continue;
            }

            $taxonomy = 'attribute_' . sanitize_title($attribute['name']);

            if (isset($_REQUEST[ $taxonomy ])) {

                // Get value from post data
                if ($attribute['is_taxonomy']) {
                    // Don't use wc_clean as it destroys sanitized characters
                    $value = sanitize_title(stripslashes($_REQUEST[ $taxonomy ]));
                } else {
                    $value = wc_clean(stripslashes($_REQUEST[ $taxonomy ]));
                }

                // Get valid value from variation
                $valid_value = $variation->variation_data[ $taxonomy ];

                // Allow if valid
                if ('' === $valid_value || $valid_value === $value) {
                    $variations[ $taxonomy ] = $value;
                    continue;
                }
            } else {
                $missing_attributes[] = wc_attribute_label($attribute['name']);
            }
        }

        if ($missing_attributes) {
            wc_add_notice(sprintf(_n('%s is a required field', '%s are required fields', sizeof($missing_attributes), 'woocommerce'), wc_format_list_of_items($missing_attributes)), 'error');
        } elseif (empty($variation_id)) {
            wc_add_notice(__('Please choose product options&hellip;', 'woocommerce'), 'error');
        } else {
            // Add to cart validation
            $passed_validation    = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations);

            if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations) !== false) {
                wc_add_to_cart_message($product_id);
                return true;
            }
        }
        return false;
    }

    public function cart_fragments()
    {
        $this->_send_cart_success();
    }

    private function _send_cart_success()
    {
        wp_send_json([
            'fragments' => Cart_Fragments::get_fragments(),
            'hash' => Cart_Fragments::get_fragments_hash()
        ]);

        wp_send_json_success();
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

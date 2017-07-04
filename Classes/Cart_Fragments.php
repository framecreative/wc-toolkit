<?php

namespace WC_Toolkit;

class Cart_Fragments
{

    /**
     * @param array $params
     */
    public function __construct()
    {
        if (is_admin()) {
            return;
        }

        add_action('wp', [ $this, 'maybe_set_hash_cookie' ], 1000);
        add_action('shutdown', [ $this, 'maybe_set_hash_cookie' ], 1000);
    }

    /**
     * Set a cookie with the fragments hash so we can auto refresh on dynamic pages
     * Also sets the cookie on shutdown after ajax requests like add to cart
     */
    public function maybe_set_hash_cookie()
    {
        if (! did_action('wp_loaded') || headers_sent()) {
            return;
        }

        wc_setcookie('site_cart_fragments_hash', $this->get_fragments_hash());
    }

    /**
     * Add an underscore at the start of a fragment name to exclude from the hash
     *
     * @return array
     */
    public static function get_fragments()
    {
        return [
            'html' => apply_filters('woocommerce_fragments_html', []),
            'data' => apply_filters('woocommerce_fragments_data', [
                'currency' => get_woocommerce_currency()
            ])
        ];
    }

    /**
     * Hash determines when cart session cache should be refreshed
     *
     * @return string
     */
    public static function get_fragments_hash()
    {
        $hash = [
            'cart_data'    => WC()->cart->get_cart_for_session(),
            'cart_coupons' => WC()->cart->get_applied_coupons(),
            'user'         => get_current_user_id(),
            'currency'     => get_woocommerce_currency()
        ];

        $hash = apply_filters('woocommerce_fragments_hash', $hash);

        return md5(json_encode($hash));
    }
}

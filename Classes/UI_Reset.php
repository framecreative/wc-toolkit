<?php

namespace WC_Toolkit;

class UI_Reset
{

    /**
     * @param array $params
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [ $this, 'scripts' ]);
        add_filter('woocommerce_enqueue_styles', '__return_empty_array');
    }

    public function scripts()
    {
        wp_dequeue_script('prettyPhoto');
        wp_dequeue_script('prettyPhoto-init');
        wp_dequeue_style('woocommerce_prettyPhoto_css');
        wp_dequeue_script('wc-cart-fragments');

        // remove and minify select2 with theme
        wp_dequeue_script('select2');
        wp_dequeue_style('select2');
    }
}

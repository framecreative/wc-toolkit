# WC Toolkit
Better ajax endpoints for woocommerce

## Install

`composer require framedigital/wc-toolkit`

## Setup

Add `new \WC_Toolkit\WC_Toolkit();` to your function.php or anywhere in your code you initialise your theme.

Then use the filter `woocommerce_fragments_data` to add your custom cart fragments

```php

add_filter('woocommerce_fragments_data', function($fragments)
  {
    $fragments['span.js-cart-total'] = '<span class="js-cart-total">' . WC()->cart->get_cart_total() . '</span>';
    return $fragments;
  }
);

```


## Reset UI

Using `new \WC_Toolkit\UI_Reset();` will dequeue all Woocommerce styles, pretty photo js/css, select2 js/css, and the default cart fragments js.

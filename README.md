# WC Toolkit
Better ajax endpoints for woocommerce

Creates the following ajax endpoints:
- add_to_cart
- remove_from_cart
- cart_set_quantity
- add_coupon
- remove_coupon
- cart_fragments

## Install

`composer require framecreative/wc-toolkit`

## Setup

Add `new \WC_Toolkit\WC_Toolkit();` to your function.php or anywhere in your code you initialise your theme.

Then use the filters `woocommerce_fragments_data` or `woocommerce_fragments_html` to add your custom cart fragments

## Upgrading to version 2

Ajax success responses are now sent with `wp_send_json_success` instead of `wp_send_json`. So JS fragment code will need to refer to `response.data.fragments` instead of `response.fragments`.

## Reset UI

Using `new \WC_Toolkit\UI_Reset();` will dequeue all Woocommerce styles, pretty photo js/css, select2 js/css, and the default cart fragments js.

## Example

Add data to fragments
```php
add_filter('woocommerce_fragments_data', function($fragments)
  {
    $fragments['span.js-cart-total'] = '<span class="js-cart-total">' . WC()->cart->get_cart_total() . '</span>';
    return $fragments;
  }
);
```
Create jQuery ajax call
```js
var endpoint = 'add_to_cart';
var addToCartUrl = wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', `site_${endpoint}`));
var data = { product_id: 1, quantity: 1};

$.ajax({
  url: addToCartUrl,
  data: data,
  method: 'POST'
})
  
  
```

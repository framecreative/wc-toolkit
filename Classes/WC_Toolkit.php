<?php

namespace WC_Toolkit;

class WC_Toolkit
{
    public function __construct($UIReset = null)
    {
        new Ajax();
        new Cart_Fragments();
        if ($UIReset) {
            new UI_Reset();
        }
    }
}

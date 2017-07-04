<?php

namespace WC_Toolkit;

class WC_Toolkit
{
    public function __construct()
    {
        new Ajax();
        new Cart_Fragments();
    }
}

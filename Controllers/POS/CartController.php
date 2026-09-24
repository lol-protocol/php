<?php

namespace App\Controllers\POS;

class CartController
{
    public function show($params = [])
    {
        return view('pos/cart/show');
    }
}

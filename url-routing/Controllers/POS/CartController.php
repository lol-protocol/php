<?php

declare(strict_types=1);

namespace App\Controllers\POS;

class CartController
{
    public function show($params = [])
    {
        return view('pos/cart/show');
    }
}

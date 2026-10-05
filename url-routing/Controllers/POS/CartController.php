<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;

class CartController extends BaseController
{
    public function show(array $params = []): string
    {
        return view('pos/cart/show');
    }
}

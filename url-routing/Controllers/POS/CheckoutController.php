<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;

class CheckoutController extends BaseController
{
    public function index(array $params = []): string
    {
        return view('pos/checkout/index');
    }

    public function shipping(array $params = []): string
    {
        return view('pos/checkout/shipping');
    }

    public function payment(array $params = []): string
    {
        return view('pos/checkout/payment');
    }

    public function confirm(array $params = []): string
    {
        return view('pos/checkout/confirm');
    }
}

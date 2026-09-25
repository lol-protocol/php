<?php

declare(strict_types=1);

namespace App\Controllers\POS;

class CheckoutController
{
    public function index($params = [])
    {
        return view('pos/checkout/index');
    }

    public function shipping($params = [])
    {
        return view('pos/checkout/shipping');
    }

    public function payment($params = [])
    {
        return view('pos/checkout/payment');
    }

    public function confirm($params = [])
    {
        return view('pos/checkout/confirm');
    }
}

<?php

namespace App\Controllers\POS;

class OrderController
{
    public function show($params = [])
    {
        $order = []; // TODO: fetch from DB by $params['id']

        return view('pos/order/show', ['order' => $order]);
    }

    public function invoice($params = [])
    {
        return view('pos/order/invoice', ['id' => $params['id']]);
    }

    public function track($params = [])
    {
        return view('pos/order/track', ['id' => $params['id']]);
    }

    public function devolucion($params = [])
    {
        return view('pos/order/devolucion', ['id' => $params['id']]);
    }
}

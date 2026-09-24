<?php

namespace App\Controllers\POS;

class CuentaController
{
    public function index($params = [])
    {
        // TODO: requiere sesion iniciada
        return view('pos/cuenta/index');
    }
}

<?php

namespace App\Controllers\Genealogy;

class CuentaController
{
    public function index($params = [])
    {
        // TODO: requiere sesion iniciada
        return view('genealogy/cuenta/index');
    }
}

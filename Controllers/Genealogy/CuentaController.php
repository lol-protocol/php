<?php

namespace App\Controllers\Genealogy;

class CuentaController
{
    public function index($params = [])
    {
        // TODO: requiere sesion iniciada
        return view('genealogy/cuenta/index');
    }

    public function colecciones($params = [])
    {
        $colecciones = [];

        return view('genealogy/cuenta/colecciones', ['colecciones' => $colecciones]);
    }

    public function aportes($params = [])
    {
        $aportes = [];

        return view('genealogy/cuenta/aportes', ['aportes' => $aportes]);
    }
}

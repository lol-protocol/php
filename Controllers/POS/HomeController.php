<?php

namespace App\Controllers\POS;

class HomeController
{
    public function index($params = [])
    {
        $tipo = $_GET['t'] ?? null;
        $query = $_GET['q'] ?? '';

        if ($tipo !== null) {
            // TODO: listado/busqueda filtrada por tipo (largo de digitos) y texto
            return view('pos/home/listado', ['tipo' => $tipo, 'query' => $query]);
        }

        return view('pos/home/index');
    }
}

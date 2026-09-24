<?php

namespace App\Controllers\Genealogy;

class HomeController
{
    public function index($params = [])
    {
        $tipo = $_GET['t'] ?? null;
        $query = $_GET['q'] ?? '';

        if ($tipo !== null) {
            // TODO: listado/busqueda filtrada por tipo (largo de digitos) y texto
            return view('genealogy/home/listado', ['tipo' => $tipo, 'query' => $query]);
        }

        return view('genealogy/home/index');
    }
}

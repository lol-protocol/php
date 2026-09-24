<?php

namespace App\Controllers\POS;

class HomeController
{
    private function validateType($tipo)
    {
        if ($tipo === null) {
            return null;
        }
        if (!ctype_digit($tipo) || strlen($tipo) > 2) {
            return null;
        }
        return $tipo;
    }

    private function validateQuery($query)
    {
        if (!is_string($query)) {
            return '';
        }
        $query = trim($query);
        if (strlen($query) > 255) {
            $query = substr($query, 0, 255);
        }
        return $query;
    }

    public function index($params = [])
    {
        $tipo = $this->validateType($_GET['t'] ?? null);
        $query = $this->validateQuery($_GET['q'] ?? '');

        if ($tipo !== null) {
            // TODO: listado/busqueda filtrada por tipo (largo de digitos) y texto
            return view('pos/home/listado', ['tipo' => $tipo, 'query' => $query]);
        }

        return view('pos/home/index');
    }
}

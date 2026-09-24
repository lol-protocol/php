<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

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
            return view('genealogy/home/listado', ['tipo' => $tipo, 'query' => $query]);
        }

        return view('genealogy/home/index');
    }
}

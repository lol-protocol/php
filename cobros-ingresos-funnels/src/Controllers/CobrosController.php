<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\FacturaRepository;
use App\View;

final class CobrosController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);
        $estado = $_GET['estado'] ?? '';

        $facturaRepo = new FacturaRepository();
        $aging = $facturaRepo->carteraAging();

        View::render('cobros/index', [
            'meses' => $meses,
            'estado' => $estado,
            'kpis' => $facturaRepo->kpis($desde, $hasta),
            'ingresosPorMes' => $facturaRepo->ingresosPorMes($desde, $hasta),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'facturas' => $facturaRepo->listado($desde, $hasta, $estado ?: null),
            'activePage' => 'cobros',
            'titulo' => 'Cobros e ingresos',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\BoletaRepository;
use App\View;

final class CobrosController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);
        $estado = $_GET['estado'] ?? '';

        $boletaRepo = new BoletaRepository();
        $aging = $boletaRepo->carteraAging();

        View::render('cobros/index', [
            'meses' => $meses,
            'estado' => $estado,
            'kpis' => $boletaRepo->kpis($desde, $hasta),
            'ingresosPorMes' => $boletaRepo->ingresosPorMes($desde, $hasta),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'boletas' => $boletaRepo->listado($desde, $hasta, $estado ?: null),
            'activePage' => 'cobros',
            'titulo' => 'Cobros e ingresos',
        ]);
    }
}

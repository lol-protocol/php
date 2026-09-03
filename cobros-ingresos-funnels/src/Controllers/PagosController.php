<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\PagoRepository;
use App\View;

final class PagosController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);

        $pagoRepo = new PagoRepository();

        View::render('pagos/index', [
            'meses' => $meses,
            'cobrosPorMes' => $pagoRepo->cobrosPorMes($desde, $hasta),
            'porMetodo' => $pagoRepo->porMetodo($desde, $hasta),
            'pagos' => $pagoRepo->listado($desde, $hasta),
            'activePage' => 'pagos',
            'titulo' => 'Pagos',
        ]);
    }
}

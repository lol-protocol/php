<?php

declare(strict_types=1);

namespace App\Controllers;

use App\FiltroDePeriodo;
use App\Repositories\FunnelRepository;
use App\Repositories\MonedaRepository;
use App\Repositories\SegmentacionRepository;
use App\View;

final class CohortesController
{
    public function index(): void
    {
        $filtros = FiltroDePeriodo::rangoActivo();
        ['desde' => $desde, 'hasta' => $hasta] = $filtros;

        View::render('cohortes/conversion_y_ltv', $filtros + [
            'tasas' => MonedaRepository::estadoDeLasTasas(),
            'cohortes' => (new FunnelRepository())->cohortes($desde, $hasta),
            'ltvPorCohorte' => (new SegmentacionRepository())->ltvPorCohorte(),
            'activePage' => 'cohortes',
            'titulo' => 'Cohortes de conversión',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\FunnelRepository;
use App\View;

final class CohortesController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);

        View::render('cohortes/index', [
            'meses' => $meses,
            'cohortes' => (new FunnelRepository())->cohortes($desde, $hasta),
            'activePage' => 'cohortes',
            'titulo' => 'Cohortes de conversion',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\FunnelRepository;
use App\Repositories\SegmentacionRepository;
use App\View;

final class CohortesController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        $personalizado = Filtros::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? Filtros::rango($meses);

        View::render('cohortes/index', [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
            'cohortes' => (new FunnelRepository())->cohortes($desde, $hasta),
            'ltvPorCohorte' => (new SegmentacionRepository())->ltvPorCohorte(),
            'activePage' => 'cohortes',
            'titulo' => 'Cohortes de conversion',
        ]);
    }
}

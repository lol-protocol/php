<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\FunnelRepository;
use App\View;

final class FunnelController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);

        $funnelRepo = new FunnelRepository();

        View::render('funnel/index', [
            'meses' => $meses,
            'resumen' => $funnelRepo->resumenEtapas($desde, $hasta),
            'porCanal' => $funnelRepo->porCanal($desde, $hasta),
            'serieMensual' => $funnelRepo->serieMensual($desde, $hasta),
            'tiempoPromedioConversion' => $funnelRepo->tiempoPromedioConversionDias(),
            'activePage' => 'funnel',
            'titulo' => 'Funnel de conversion',
        ]);
    }
}

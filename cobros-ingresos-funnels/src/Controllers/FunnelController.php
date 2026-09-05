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
        $personalizado = Filtros::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? Filtros::rango($meses);

        $funnelRepo = new FunnelRepository();

        View::render('funnel/index', [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
            'resumen' => $funnelRepo->resumenEtapas($desde, $hasta),
            'porCanal' => $funnelRepo->porCanal($desde, $hasta),
            'porPais' => $funnelRepo->porPais($desde, $hasta),
            'porGenero' => $funnelRepo->porGenero($desde, $hasta),
            'porRangoEdad' => $funnelRepo->porRangoEdad($desde, $hasta),
            'serieMensual' => $funnelRepo->serieMensual($desde, $hasta),
            'tiempoPromedioConversion' => $funnelRepo->tiempoPromedioConversionDias(),
            'activePage' => 'funnel',
            'titulo' => 'Funnel de conversion',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\FiltroDePeriodo;
use App\Repositories\FunnelRepository;
use App\Repositories\MonedaRepository;
use App\View;

final class FunnelController
{
    public function index(): void
    {
        $filtros = FiltroDePeriodo::rangoActivo();
        ['desde' => $desde, 'hasta' => $hasta] = $filtros;

        $funnelRepo = new FunnelRepository();

        View::render('funnel/conversion', $filtros + [
            'resumen' => $funnelRepo->resumenEtapas($desde, $hasta),
            'porCanal' => $funnelRepo->porCanal($desde, $hasta),
            'ingresosPorCanal' => $funnelRepo->ingresosPorCanal($desde, $hasta),
            'clientesSinRecorrido' => $funnelRepo->clientesSinRecorrido(),
            'tasas' => MonedaRepository::estadoDeLasTasas(),
            'porPais' => $funnelRepo->porPais($desde, $hasta),
            'porGenero' => $funnelRepo->porGenero($desde, $hasta),
            'porRangoEdad' => $funnelRepo->porRangoEdad($desde, $hasta),
            'serieMensual' => $funnelRepo->serieMensual($desde, $hasta),
            'tiempoPromedioConversion' => $funnelRepo->tiempoPromedioConversionDias($desde, $hasta),
            'activePage' => 'funnel',
            'titulo' => 'Funnel de conversión',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\FiltroDePeriodo;
use App\Repositories\FunnelRepository;
use App\Repositories\IngresosYCobrosRepository;
use App\Repositories\MonedaRepository;
use App\Repositories\SegmentacionRepository;
use App\View;

final class DashboardController
{
    public function index(): void
    {
        $filtros = FiltroDePeriodo::rangoActivo();
        ['desde' => $desde, 'hasta' => $hasta] = $filtros;

        $ingresosRepo = new IngresosYCobrosRepository();
        $funnelRepo = new FunnelRepository();
        $segmentacionRepo = new SegmentacionRepository();

        $ingresos = $ingresosRepo->ingresosPorMes($desde, $hasta);
        $cobros = $ingresosRepo->cobrosPorMes($desde, $hasta);
        $serieMensual = self::combinarPorMes($ingresos, $cobros);
        $aging = $ingresosRepo->carteraAging();

        [$desdeAnt, $hastaAnt] = FiltroDePeriodo::rangoAnterior($desde, $hasta);
        [$desdeAnio, $hastaAnio] = FiltroDePeriodo::rangoAnioAnterior($desde, $hasta);
        $segmentacion = $segmentacionRepo->topPorDimensiones();

        View::render('dashboard/panel', $filtros + [
            'tasas' => MonedaRepository::estadoDeLasTasas(),
            'kpis' => $ingresosRepo->kpis($desde, $hasta),
            'kpisAnterior' => $ingresosRepo->kpis($desdeAnt, $hastaAnt),
            'kpisAnioAnterior' => $ingresosRepo->kpis($desdeAnio, $hastaAnio),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'serieMensual' => $serieMensual,
            'funnelResumen' => $funnelRepo->resumenEtapas($desde, $hasta),
            'segmentacion' => [
                'País' => $segmentacion['pais'],
                'Ciudad' => $segmentacion['ciudad'],
                'Idioma' => $segmentacion['idioma'],
                'Género' => $segmentacion['genero'],
                'Rango de edad' => $segmentacion['rango_edad'],
            ],
            'activePage' => 'dashboard',
            'titulo' => 'Dashboard',
        ]);
    }

    private static function combinarPorMes(array $ingresos, array $cobros): array
    {
        $porMes = [];
        foreach ($ingresos as $fila) {
            $porMes[$fila['mes']]['ingresos'] = (float) $fila['total'];
        }
        foreach ($cobros as $fila) {
            $porMes[$fila['mes']]['cobros'] = (float) $fila['total'];
        }
        ksort($porMes);

        $resultado = [];
        foreach ($porMes as $mes => $valores) {
            $resultado[] = [
                'mes' => $mes,
                'ingresos' => $valores['ingresos'] ?? 0.0,
                'cobros' => $valores['cobros'] ?? 0.0,
            ];
        }
        return $resultado;
    }
}

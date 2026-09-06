<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\FunnelRepository;
use App\Repositories\IngresosRepository;
use App\Repositories\SegmentacionRepository;
use App\View;

final class DashboardController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        $personalizado = Filtros::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? Filtros::rango($meses);

        $ingresosRepo = new IngresosRepository();
        $funnelRepo = new FunnelRepository();
        $segmentacionRepo = new SegmentacionRepository();

        $ingresos = $ingresosRepo->ingresosPorMes($desde, $hasta);
        $cobros = $ingresosRepo->cobrosPorMes($desde, $hasta);
        $serieMensual = self::combinarPorMes($ingresos, $cobros);
        $aging = $ingresosRepo->carteraAging();

        [$desdeAnt, $hastaAnt] = Filtros::rangoAnterior($desde, $hasta);
        [$desdeAnio, $hastaAnio] = Filtros::rangoAnioAnterior($desde, $hasta);

        View::render('dashboard', [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
            'kpis' => $ingresosRepo->kpis($desde, $hasta),
            'kpisAnterior' => $ingresosRepo->kpis($desdeAnt, $hastaAnt),
            'kpisAnioAnterior' => $ingresosRepo->kpis($desdeAnio, $hastaAnio),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'serieMensual' => $serieMensual,
            'funnelResumen' => $funnelRepo->resumenEtapas($desde, $hasta),
            'segmentacion' => [
                'Pais' => $segmentacionRepo->topPorPais(),
                'Ciudad' => $segmentacionRepo->topPorCiudad(),
                'Idioma' => $segmentacionRepo->topPorIdioma(),
                'Genero' => $segmentacionRepo->topPorGenero(),
                'Rango de edad' => $segmentacionRepo->topPorRangoEdad(),
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

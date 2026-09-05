<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\ClienteRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\FunnelRepository;
use App\Repositories\PagoRepository;
use App\View;

final class DashboardController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        $personalizado = Filtros::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? Filtros::rango($meses);

        $boletaRepo = new BoletaRepository();
        $pagoRepo = new PagoRepository();
        $funnelRepo = new FunnelRepository();
        $clienteRepo = new ClienteRepository();

        $ingresos = $boletaRepo->ingresosPorMes($desde, $hasta);
        $cobros = $pagoRepo->cobrosPorMes($desde, $hasta);
        $serieMensual = self::combinarPorMes($ingresos, $cobros);
        $aging = $boletaRepo->carteraAging();

        [$desdeAnt, $hastaAnt] = Filtros::rangoAnterior($desde, $hasta);
        [$desdeAnio, $hastaAnio] = Filtros::rangoAnioAnterior($desde, $hasta);

        View::render('dashboard', [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
            'kpis' => $boletaRepo->kpis($desde, $hasta),
            'kpisAnterior' => $boletaRepo->kpis($desdeAnt, $hastaAnt),
            'kpisAnioAnterior' => $boletaRepo->kpis($desdeAnio, $hastaAnio),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'serieMensual' => $serieMensual,
            'funnelResumen' => $funnelRepo->resumenEtapas($desde, $hasta),
            'segmentacion' => [
                'Pais' => $clienteRepo->topPorPais(),
                'Ciudad' => $clienteRepo->topPorCiudad(),
                'Idioma' => $clienteRepo->topPorIdioma(),
                'Genero' => $clienteRepo->topPorGenero(),
                'Rango de edad' => $clienteRepo->topPorRangoEdad(),
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

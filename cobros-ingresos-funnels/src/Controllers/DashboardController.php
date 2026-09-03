<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\ClienteRepository;
use App\Repositories\FacturaRepository;
use App\Repositories\FunnelRepository;
use App\Repositories\PagoRepository;
use App\View;

final class DashboardController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);

        $facturaRepo = new FacturaRepository();
        $pagoRepo = new PagoRepository();
        $funnelRepo = new FunnelRepository();
        $clienteRepo = new ClienteRepository();

        $ingresos = $facturaRepo->ingresosPorMes($desde, $hasta);
        $cobros = $pagoRepo->cobrosPorMes($desde, $hasta);
        $serieMensual = self::combinarPorMes($ingresos, $cobros);
        $aging = $facturaRepo->carteraAging();

        View::render('dashboard', [
            'meses' => $meses,
            'kpis' => $facturaRepo->kpis($desde, $hasta),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'serieMensual' => $serieMensual,
            'funnelResumen' => $funnelRepo->resumenEtapas($desde, $hasta),
            'topClientes' => $clienteRepo->topPorFacturacion(5),
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

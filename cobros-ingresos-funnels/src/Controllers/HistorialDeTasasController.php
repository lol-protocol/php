<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Filtros;
use App\Paginacion;
use App\Repositories\HistorialDeTasasRepository;
use App\Repositories\MonedaRepository;
use App\View;

/**
 * Las tasas de cambio: sin ?moneda=, una fila por moneda con su tasa de hoy y cuanto cambio en 30 dias; con ella,
 * el historial de esa moneda, paginado. Solo lee: las tasas las carga bin/actualizar_tasas.php.
 */
final class HistorialDeTasasController
{
    public function index(): void
    {
        $repo = new HistorialDeTasasRepository();
        $monedas = $repo->monedas();
        $avisos = [];

        $moneda = Filtros::opcion('moneda', $monedas, '');
        if (Filtros::opcionInexistente('moneda', $monedas)) {
            $avisos[] = Avisos::atencion('La moneda pedida no existe; se muestran todas.');
        }

        $datos = [
            'avisos' => $avisos,
            'monedas' => $monedas,
            'moneda' => $moneda,
            'tasas' => MonedaRepository::estadoDeLasTasas(),
            'activePage' => 'tasas',
            'titulo' => 'Tasas de cambio',
        ];

        if ($moneda === '') {
            View::render('tasas/resumen', $datos + [
                'filas' => $repo->resumen(),
                'dias' => HistorialDeTasasRepository::DIAS_DEL_RESUMEN,
            ]);

            return;
        }

        $pagina = $repo->deLaMoneda($moneda, Paginacion::pagina());
        View::render('tasas/historial', $datos + [
            'filas' => $pagina['filas'],
            'total' => $pagina['total'],
            'pagina' => $pagina['pagina'],
            'totalPaginas' => $pagina['totalPaginas'],
        ]);
    }
}

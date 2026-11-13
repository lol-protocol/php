<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Filtros;
use App\Repositories\FlujoDeCajaRepository;
use App\Repositories\MonedaRepository;
use App\View;

/** El flujo de caja esperado: cuanto deberia entrar cada mes segun el vencimiento de lo que falta cobrar. Solo lee. */
final class FlujoDeCajaController
{
    public function index(): void
    {
        $avisos = [];
        $meses = (int) Filtros::opcion('meses', FlujoDeCajaRepository::HORIZONTES, (string) FlujoDeCajaRepository::MESES_POR_DEFECTO);
        if (Filtros::opcionInexistente('meses', FlujoDeCajaRepository::HORIZONTES)) {
            $avisos[] = Avisos::atencion('El horizonte pedido no existe; se muestran los próximos ' . FlujoDeCajaRepository::MESES_POR_DEFECTO . ' meses.');
        }

        $cubetas = (new FlujoDeCajaRepository())->cubetas($meses);

        View::render('flujo/esperado', [
            'avisos' => $avisos,
            'meses' => $meses,
            'cubetas' => $cubetas,
            'totales' => FlujoDeCajaRepository::totales($cubetas),
            'tasas' => MonedaRepository::estadoDeLasTasas(),
            'activePage' => 'flujo',
            'titulo' => 'Flujo de caja esperado',
        ]);
    }
}

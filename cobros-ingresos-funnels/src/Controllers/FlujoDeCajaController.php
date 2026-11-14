<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Descarga;
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

    /**
     * Descarga en CSV las filas que la pantalla muestra con el horizonte de la URL (un valor que no existe cae en el
     * de por defecto, como en la pantalla pero sin aviso). El vencimiento sale como «Ya vencido», «Más adelante» o el
     * mes en ISO (2026-11), que una planilla ordena; los saldos van como numeros, con los dos decimales del dolar.
     * No lleva una fila de totales: los clientes de una fila se repiten en otra y no se pueden sumar.
     */
    public function exportar(): void
    {
        $meses = (int) Filtros::opcion('meses', FlujoDeCajaRepository::HORIZONTES, (string) FlujoDeCajaRepository::MESES_POR_DEFECTO);

        Descarga::csv(
            'flujo-de-caja',
            ['Vencimiento', 'Saldo USD', 'Boletas', 'Clientes'],
            self::filasParaExportar((new FlujoDeCajaRepository())->cubetas($meses))
        );
    }

    /**
     * @param list<array{clave: string, boletas: int, clientes: int, saldo_usd: float}> $cubetas
     * @return list<list<string|int|float>>
     */
    private static function filasParaExportar(array $cubetas): array
    {
        return array_map(
            static fn (array $c): array => [
                FlujoDeCajaRepository::ETIQUETAS_FIJAS[$c['clave']] ?? $c['clave'],
                $c['saldo_usd'],
                $c['boletas'],
                $c['clientes'],
            ],
            $cubetas
        );
    }
}

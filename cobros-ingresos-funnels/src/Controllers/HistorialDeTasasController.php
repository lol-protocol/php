<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\CeldaNumerica;
use App\Descarga;
use App\Filtros;
use App\Paginacion;
use App\Repositories\HistorialDeTasasRepository;
use App\Repositories\MonedaRepository;
use App\View;
use Generator;

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

    /**
     * Descarga en CSV lo que la pantalla muestra: sin ?moneda=, el resumen de todas; con ella, TODO el historial de
     * esa moneda, no solo la pagina que se ve. Una moneda que no existe cae en el resumen, como en la pantalla pero
     * sin aviso. Las tasas van como numeros con sus decimales (ocho el valor en dolares, que es como se guarda, seis
     * las unidades por dolar), y la fecha de una tasa de ejemplo como «de ejemplo», igual que en la pantalla.
     */
    public function exportar(): void
    {
        $repo = new HistorialDeTasasRepository();
        $moneda = Filtros::opcion('moneda', $repo->monedas(), '');

        if ($moneda === '') {
            Descarga::csv(
                'tasas-de-cambio',
                ['Moneda', 'Nombre', 'Valor en USD', '1 USD =', 'Cotización', 'Cambio en ' . HistorialDeTasasRepository::DIAS_DEL_RESUMEN . ' días (%)'],
                self::filasDelResumen($repo->resumen())
            );

            return;
        }

        // $moneda es una clave del catalogo (Filtros::opcion la valida), nunca texto de la URL: va en el nombre del archivo.
        Descarga::csv(
            'tasas-de-cambio-' . $moneda,
            ['Cotización', 'Valor en USD', '1 USD =', 'Cambio (%)', 'Fuente', 'Registrada'],
            self::filasDelHistorial($repo->exportarHistorial($moneda))
        );
    }

    /**
     * @param list<array{codigo: string, nombre: string, tasa_a_usd: string, cotizada_en: ?string, tasa_antes: ?string}> $filas
     * @return Generator<int, list<string|CeldaNumerica|null>>
     */
    private static function filasDelResumen(array $filas): Generator
    {
        foreach ($filas as $f) {
            yield [$f['codigo'], $f['nombre'], ...self::tasaEnCeldas($f['tasa_a_usd'], $f['tasa_antes']), self::cotizacion($f['cotizada_en'])];
        }
    }

    /**
     * @param iterable<array<string, mixed>> $filas
     * @return Generator<int, list<string|CeldaNumerica|null>>
     */
    private static function filasDelHistorial(iterable $filas): Generator
    {
        foreach ($filas as $f) {
            [$valor, $unidades, $cambio] = self::tasaEnCeldas((string) $f['tasa_a_usd'], $f['tasa_anterior'] === null ? null : (string) $f['tasa_anterior']);
            yield [
                self::cotizacion($f['cotizada_en'] === null ? null : (string) $f['cotizada_en']),
                $valor,
                $unidades,
                $cambio,
                $f['fuente'] === null ? null : (string) $f['fuente'],
                substr((string) $f['registrada_en'], 0, 16),
            ];
        }
    }

    /**
     * El valor en dolares, las unidades por dolar y el cambio frente a $anterior (null si no hay con que comparar).
     *
     * @return array{0: CeldaNumerica, 1: ?CeldaNumerica, 2: ?CeldaNumerica}
     */
    private static function tasaEnCeldas(string $tasa, ?string $anterior): array
    {
        $valor = (float) $tasa;
        $cambio = $anterior === null ? null : variacion_pct($valor, (float) $anterior);

        return [
            new CeldaNumerica($valor, 8),
            $valor > 0.0 ? new CeldaNumerica(1 / $valor, 6) : null,
            $cambio === null ? null : new CeldaNumerica($cambio, 2),
        ];
    }

    /** La fecha de la cotizacion hasta el minuto, o «de ejemplo» si nadie actualizo todavia esa tasa. */
    private static function cotizacion(?string $cotizadaEn): string
    {
        return $cotizadaEn === null ? 'de ejemplo' : substr($cotizadaEn, 0, 16);
    }
}

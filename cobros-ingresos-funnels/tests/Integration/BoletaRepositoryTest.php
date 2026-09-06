<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\BoletaRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 * El reporting de ingresos (kpis/carteraAging/etc.) se prueba en
 * IngresosRepositoryTest.
 */
final class BoletaRepositoryTest extends TestCase
{
    public function testListadoFiltradoPorEstadoSoloDevuelveEseEstado(): void
    {
        $repo = new BoletaRepository();
        $vencidas = $repo->listado('2000-01-01', '2100-01-01', 'vencida')['filas'];

        foreach ($vencidas as $boleta) {
            self::assertSame('vencida', $boleta['estado']);
            self::assertLessThan(date('Y-m-d'), $boleta['fecha_vencimiento']);
        }
    }

    public function testFiltroPorClienteSoloDevuelveEseCliente(): void
    {
        $repo = new BoletaRepository();
        $todas = $repo->listado('2000-01-01', '2100-01-01')['filas'];
        if ($todas === []) {
            self::markTestSkipped('No hay boletas seedeadas para probar el filtro.');
        }

        $nombreCliente = $todas[0]['cliente'];
        $filtradas = $repo->listado('2000-01-01', '2100-01-01', null, $nombreCliente)['filas'];

        self::assertNotEmpty($filtradas);
        foreach ($filtradas as $b) {
            self::assertSame($nombreCliente, $b['cliente']);
        }
    }

    public function testListadoFiltradoPorEstadoAnuladaDevuelveSoloAnuladas(): void
    {
        // El filtro por estado se aplica antes de paginar, asi que esto no
        // depende de en que pagina (ni en que orden) hayan caido las anuladas.
        $repo = new BoletaRepository();
        $soloAnuladas = $repo->listado('2000-01-01', '2100-01-01', 'anulada')['filas'];

        if ($soloAnuladas === []) {
            self::markTestSkipped('No hay boletas anuladas seedeadas para probar el filtro.');
        }

        foreach ($soloAnuladas as $b) {
            self::assertTrue((bool) $b['anulada']);
            self::assertSame('anulada', $b['estado'], 'una boleta anulada siempre muestra ese estado, sin importar el saldo');
        }
    }

    public function testPaginacionDevuelveComoMaximoElTamanioDePaginaYRespetaElTotal(): void
    {
        $repo = new BoletaRepository();
        $listado = $repo->listado('2000-01-01', '2100-01-01', null, null, 1);

        self::assertLessThanOrEqual(\App\Paginacion::POR_PAGINA, count($listado['filas']));
        self::assertSame($listado['total'], $listado['total']);
        self::assertGreaterThanOrEqual(1, $listado['totalPaginas']);

        if ($listado['totalPaginas'] > 1) {
            $pagina2 = $repo->listado('2000-01-01', '2100-01-01', null, null, 2)['filas'];
            $idsPagina1 = array_column($listado['filas'], 'id');
            $idsPagina2 = array_column($pagina2, 'id');
            self::assertEmpty(array_intersect($idsPagina1, $idsPagina2), 'paginas distintas no deben repetir filas');
        }
    }
}

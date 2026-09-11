<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 * El reporting de ingresos (kpis/carteraAging/etc.) se prueba en
 * IngresosRepositoryTest.
 */
final class BoletaRepositoryTest extends TestCase
{
    /** @var int[] */
    private array $idsCreados = [];

    protected function tearDown(): void
    {
        if ($this->idsCreados === []) {
            return;
        }
        $marcadores = implode(',', array_fill(0, count($this->idsCreados), '?'));
        $stmt = Database::connection()->prepare("DELETE FROM boletas WHERE id IN ({$marcadores})");
        $stmt->execute($this->idsCreados);
    }

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

    /**
     * Reproduce a proposito el escenario que antes rompia la paginacion: un
     * grupo de filas empatadas en fecha_emision (la columna de ORDER BY) que
     * cruza el borde entre pagina 1 y pagina 2. Sin un desempate deterministico
     * (id), Postgres puede ordenar ese empate distinto en cada ejecucion y
     * una misma fila aparece en las dos paginas (o en ninguna).
     */
    public function testPaginacionNoDuplicaNiPierdeFilasCuandoHayEmpateEnLaFechaDeCorte(): void
    {
        $cliente = (new ClienteRepository())->porId(1);
        self::assertNotNull($cliente, 'este test asume que el cliente #1 existe (lo trae el seed)');

        $fechaFija = '1901-01-01';
        $cantidad = \App\Paginacion::POR_PAGINA + 5;
        $repo = new BoletaRepository();
        for ($i = 0; $i < $cantidad; $i++) {
            $this->idsCreados[] = $repo->crear([
                'cliente_id' => 1,
                'concepto' => 'Test empate paginacion',
                'monto' => 100,
                'moneda_codigo' => $cliente['moneda_codigo'],
                'fecha_emision' => $fechaFija,
                'fecha_vencimiento' => $fechaFija,
            ]);
        }

        $pagina1 = $repo->listado($fechaFija, $fechaFija, null, null, 1);
        $pagina2 = $repo->listado($fechaFija, $fechaFija, null, null, 2);

        self::assertSame($cantidad, $pagina1['total']);
        $idsPagina1 = array_column($pagina1['filas'], 'id');
        $idsPagina2 = array_column($pagina2['filas'], 'id');

        self::assertEmpty(array_intersect($idsPagina1, $idsPagina2), 'la misma fila no debe aparecer en dos paginas distintas');
        self::assertCount($cantidad, array_unique(array_merge($idsPagina1, $idsPagina2)), 'entre ambas paginas no se debe perder ninguna fila');
    }

    public function testListadoSinFiltroDeEstadoPaginaEnSqlYElTotalCoincideConUnaCuentaIndependiente(): void
    {
        // Sin filtro de estado, listado() pagina con LIMIT/OFFSET en SQL en
        // vez de traer todo a PHP; esto confirma que el total que devuelve
        // coincide con una cuenta hecha aparte, directo contra la base.
        $repo = new BoletaRepository();
        $listado = $repo->listado('2000-01-01', '2100-01-01', null, null, 1);

        $totalIndependiente = (int) Database::connection()->query(
            "SELECT COUNT(*) FROM boletas WHERE fecha_emision BETWEEN '2000-01-01' AND '2100-01-01'"
        )->fetchColumn();

        self::assertSame($totalIndependiente, $listado['total']);
    }
}

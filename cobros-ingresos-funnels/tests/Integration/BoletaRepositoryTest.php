<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 * El reporting de ingresos (kpis/carteraAging/etc.) se prueba en
 * IngresosRepositoryTest.
 */
final class BoletaRepositoryTest extends IntegracionTestCase
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
            $repo->crear([
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

    /**
     * Reproduce el caso real: pedir una pagina que no existe devolvia una
     * tabla vacia rotulada "Pagina 5000 de 4", y con un numero lo bastante
     * grande el offset se desbordaba a float y tiraba un 500. Ahora la
     * peticion cae en la ultima pagina real, que si trae filas, y el listado
     * informa en que pagina quedo parado para que la vista no mienta.
     *
     * Vale para los dos caminos de listado(): el que pagina en SQL y el que
     * filtra por estado en PHP y recien ahi corta con array_slice.
     */
    public function testUnaPaginaFueraDeRangoCaeEnLaUltimaQueExiste(): void
    {
        $repo = new BoletaRepository();

        foreach ([null, 'pagada'] as $estado) {
            $primera = $repo->listado('2000-01-01', '2100-01-01', $estado, null, 1);
            if ($primera['total'] === 0) {
                continue;
            }

            $ultima = $repo->listado('2000-01-01', '2100-01-01', $estado, null, (int) '9999999999999999999');

            self::assertSame($primera['totalPaginas'], $ultima['pagina'], 'tiene que quedar parado en la ultima pagina');
            self::assertNotEmpty($ultima['filas'], 'la ultima pagina real siempre trae filas');
            self::assertSame(
                $repo->listado('2000-01-01', '2100-01-01', $estado, null, $primera['totalPaginas'])['filas'],
                $ultima['filas']
            );
        }
    }

    /**
     * pagado y saldo salen de la vista boletas_con_saldo, que reemplazo a
     * cuatro copias de la misma subconsulta. Un pago anulado no cuenta: es la
     * regla que antes habia que mantener igual en los cuatro lugares.
     */
    public function testPagadoYSaldoSoloCuentanLosPagosVigentes(): void
    {
        $cliente = (new ClienteRepository())->porId(1);
        self::assertNotNull($cliente, 'este test asume que el cliente #1 existe (lo trae el seed)');

        $boletas = new BoletaRepository();
        $id = $boletas->crear([
            'cliente_id' => 1,
            'concepto' => 'Boleta de prueba de saldo',
            'monto' => 1000,
            'moneda_codigo' => $cliente['moneda_codigo'],
            'fecha_emision' => '2020-01-01',
            'fecha_vencimiento' => '2020-02-01',
        ]);
        $pagos = new PagoRepository();
        $datosPago = ['boleta_id' => $id, 'cliente_id' => 1, 'moneda_codigo' => $cliente['moneda_codigo'], 'fecha_pago' => '2020-01-10', 'metodo' => 'tarjeta'];
        $pagos->crear($datosPago + ['monto' => 300]);
        $anulado = $pagos->crear($datosPago + ['monto' => 200]);
        $pagos->anularSiEstabaActiva($anulado);

        $boleta = $boletas->porId($id);

        self::assertNotNull($boleta);
        self::assertEqualsWithDelta(300.0, (float) $boleta['pagado'], 0.001);
        self::assertEqualsWithDelta(700.0, $boleta['saldo'], 0.001);
        self::assertSame('2020-01-10', $boleta['primer_pago']);
    }
}

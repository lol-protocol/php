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

        self::assertGreaterThan(1, $listado['totalPaginas'], 'el seed tiene que dar mas de una pagina de boletas para que esta prueba pruebe algo');

        // Se recorren todas las paginas: cada una trae como maximo POR_PAGINA
        // filas y, juntas, tienen exactamente el total, sin repetir ninguna.
        $ids = [];
        for ($pagina = 1; $pagina <= $listado['totalPaginas']; $pagina++) {
            $filas = $repo->listado('2000-01-01', '2100-01-01', null, null, $pagina)['filas'];
            self::assertLessThanOrEqual(\App\Paginacion::POR_PAGINA, count($filas));
            array_push($ids, ...array_column($filas, 'id'));
        }

        self::assertCount($listado['total'], $ids, 'las paginas, juntas, tienen exactamente el total de filas');
        self::assertSame($ids, array_values(array_unique($ids)), 'ninguna fila aparece en dos paginas');
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
     * Vale con y sin filtro de estado: en los dos casos listado() pagina en SQL.
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
     * El filtro de estado se resuelve en SQL con la misma regla que el estado
     * que muestra cada fila (EstadoBoleta; EstadoBoletaSqlTest compara las dos en
     * todos los bordes). Con los cinco estados presentes a la vez, cada filtro
     * devuelve exactamente las boletas de su estado, pagina bien, se combina con
     * el filtro por cliente y entre los cinco se reparten todas. Incluye el caso
     * de precedencia: una boleta con un pago parcial pero ya vencida es "vencida".
     */
    public function testCadaFiltroDeEstadoDevuelveExactamenteLasBoletasDeEseEstado(): void
    {
        $cliente = (new ClienteRepository())->porId(1);
        self::assertNotNull($cliente, 'este test asume que el cliente #1 existe (lo trae el seed)');

        $fecha = '1903-03-03';
        $vencida = '1903-04-02';
        $futuro = date('Y-m-d', strtotime('+10 days'));
        $repo = new BoletaRepository();
        $pagos = new PagoRepository();
        $crear = static function (string $vencimiento, float $pagado = 0.0, bool $anular = false) use ($repo, $pagos, $cliente, $fecha): void {
            $id = $repo->crear([
                'cliente_id' => 1,
                'concepto' => 'Test de filtro de estado',
                'monto' => 100,
                'moneda_codigo' => $cliente['moneda_codigo'],
                'fecha_emision' => $fecha,
                'fecha_vencimiento' => $vencimiento,
            ]);
            if ($pagado > 0) {
                $pagos->crear([
                    'boleta_id' => $id,
                    'cliente_id' => 1,
                    'monto' => $pagado,
                    'moneda_codigo' => $cliente['moneda_codigo'],
                    'fecha_pago' => $fecha,
                    'metodo' => 'transferencia',
                ]);
            }
            if ($anular) {
                $repo->anularSiEstabaActiva($id);
            }
        };

        for ($i = 0; $i < 24; $i++) {
            $crear($vencida);                 // vencida: sin pagos
        }
        $crear($vencida, 40.0);               // vencida aunque tenga un pago parcial
        $crear($vencida, 40.0);
        for ($i = 0; $i < 3; $i++) {
            $crear($vencida, 100.0);          // pagada completa, aunque ya haya vencido
        }
        $crear($futuro, 40.0);                // parcial: tiene un pago y todavia no vence
        $crear($futuro, 40.0);
        for ($i = 0; $i < 4; $i++) {
            $crear($futuro);                  // pendiente
        }
        $crear($futuro, 0.0, true);           // anulada

        $esperado = ['vencida' => 26, 'pagada' => 3, 'parcial' => 2, 'pendiente' => 4, 'anulada' => 1];
        foreach ($esperado as $estado => $cantidad) {
            $listado = $repo->listado($fecha, $fecha, $estado);
            self::assertSame($cantidad, $listado['total'], "total de boletas {$estado}");
            foreach ($listado['filas'] as $fila) {
                self::assertSame($estado, $fila['estado'], "una fila de {$estado} muestra otro estado");
            }
        }
        self::assertSame(array_sum($esperado), $repo->listado($fecha, $fecha)['total'], 'los cinco estados se reparten todas las boletas');

        $pagina1 = $repo->listado($fecha, $fecha, 'vencida', null, 1);
        $pagina2 = $repo->listado($fecha, $fecha, 'vencida', null, 2);
        self::assertSame(2, $pagina1['totalPaginas']);
        self::assertCount(\App\Paginacion::POR_PAGINA, $pagina1['filas']);
        self::assertCount(1, $pagina2['filas']);
        self::assertEmpty(array_intersect(array_column($pagina1['filas'], 'id'), array_column($pagina2['filas'], 'id')));

        self::assertSame(26, $repo->listado($fecha, $fecha, 'vencida', $cliente['nombre'])['total'], 'estado y cliente a la vez');
        self::assertSame(0, $repo->listado($fecha, $fecha, 'vencida', 'Nadie se llama asi ' . uniqid())['total']);
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

    /**
     * La ficha del cliente lista todo su historial, lo mas reciente primero.
     * Con varias boletas el mismo dia el SQL no promete ningun orden entre
     * ellas (y puede cambiarlo entre dos cargas): a igual fecha va primero la
     * de mayor id, la cargada despues, como ya hacen las notas de credito.
     */
    public function testLaFichaDesempataPorIdLasBoletasDeLaMismaFecha(): void
    {
        $cliente = (new ClienteRepository())->porId(1);
        self::assertNotNull($cliente, 'este test asume que el cliente #1 existe (lo trae el seed)');

        $fecha = '1901-01-01';
        $repo = new BoletaRepository();
        for ($i = 0; $i < 4; $i++) {
            $repo->crear([
                'cliente_id' => 1,
                'concepto' => 'Test desempate de la ficha',
                'monto' => 100,
                'moneda_codigo' => $cliente['moneda_codigo'],
                'fecha_emision' => $fecha,
                'fecha_vencimiento' => $fecha,
            ]);
        }

        $ids = array_values(array_map(
            static fn (array $b): int => (int) $b['id'],
            array_filter($repo->porCliente(1), static fn (array $b): bool => $b['fecha_emision'] === $fecha)
        ));
        $esperado = $ids;
        rsort($esperado);

        self::assertCount(4, $ids);
        self::assertSame($esperado, $ids, 'a igual fecha, la de mayor id primero');
    }

    /** Lo mismo para los pagos de la ficha: a igual fecha, el de mayor id primero. */
    public function testLaFichaDesempataPorIdLosPagosDeLaMismaFecha(): void
    {
        $cliente = (new ClienteRepository())->porId(1);
        self::assertNotNull($cliente, 'este test asume que el cliente #1 existe (lo trae el seed)');

        $fecha = '1901-01-01';
        $repo = new PagoRepository();
        for ($i = 0; $i < 4; $i++) {
            $repo->crear([
                'boleta_id' => null,
                'cliente_id' => 1,
                'monto' => 10,
                'moneda_codigo' => $cliente['moneda_codigo'],
                'fecha_pago' => $fecha,
                'metodo' => 'efectivo',
            ]);
        }

        $ids = array_values(array_map(
            static fn (array $p): int => (int) $p['id'],
            array_filter($repo->porCliente(1), static fn (array $p): bool => $p['fecha_pago'] === $fecha)
        ));
        $esperado = $ids;
        rsort($esperado);

        self::assertCount(4, $ids);
        self::assertSame($esperado, $ids, 'a igual fecha, el de mayor id primero');
    }
}

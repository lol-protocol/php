<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\NotaCreditoRepository;
use DateTimeImmutable;
use PDOException;

/**
 * Corre contra la base configurada por las env vars DB_*. Las notas creadas
 * aca se deshacen con la transaccion de cada test.
 */
final class NotaCreditoRepositoryTest extends IntegracionTestCase
{
    private function crearNota(float $monto, string $fecha): int
    {
        $boleta = Database::connection()->query(
            'SELECT id, cliente_id, moneda_codigo FROM boletas ORDER BY id LIMIT 1'
        )->fetch();
        self::assertNotFalse($boleta, 'este test asume que el seed dejo al menos una boleta');

        return (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta['id'],
            'cliente_id' => $boleta['cliente_id'],
            'monto' => $monto,
            'moneda_codigo' => $boleta['moneda_codigo'],
            'fecha' => $fecha,
            'motivo' => 'Nota de prueba ' . uniqid(),
        ]);
    }

    public function testCrearDevuelveElIdYLaNotaQuedaEnElClienteCorrespondiente(): void
    {
        $id = $this->crearNota(120.50, '2026-03-15');

        $boleta = Database::connection()->query('SELECT cliente_id FROM boletas ORDER BY id LIMIT 1')->fetch();
        $notas = (new NotaCreditoRepository())->porCliente((int) $boleta['cliente_id']);

        self::assertGreaterThan(0, $id);
        self::assertContains($id, array_map(static fn (array $n): int => (int) $n['id'], $notas));
    }

    /**
     * Antes comparaba el total de marzo contra el de enero, y solo pasaba
     * porque el seed no dejaba notas en ninguno de los dos meses. Ahora cada
     * mes se compara contra si mismo: las dos notas son iguales, asi que si
     * la de julio se colara en marzo, marzo sumaria el doble que julio.
     */
    public function testTotalEnRangoSoloCuentaLasNotasDeEseRango(): void
    {
        $repo = new NotaCreditoRepository();
        $total = static fn (string $mes): float => $repo->totalEnRangoUsd("{$mes}-01", (new DateTimeImmutable("{$mes}-01"))->format('Y-m-t'));
        [$eneroAntes, $marzoAntes, $julioAntes] = [$total('2026-01'), $total('2026-03'), $total('2026-07')];

        $this->crearNota(100.0, '2026-03-15');
        $this->crearNota(100.0, '2026-07-15');

        $sumoMarzo = $total('2026-03') - $marzoAntes;
        $sumoJulio = $total('2026-07') - $julioAntes;

        self::assertGreaterThan(0.0, $sumoMarzo, 'la nota de marzo tiene que sumar al total de marzo');
        self::assertEqualsWithDelta($sumoJulio, $sumoMarzo, 0.0001, 'cada nota suma solo en su propio mes');
        self::assertEqualsWithDelta($eneroAntes, $total('2026-01'), 0.0001, 'ninguna de las dos es de enero');
    }

    public function testPorMesAgrupaCadaNotaEnSuMes(): void
    {
        $this->crearNota(100.0, '2026-03-15');

        $porMes = (new NotaCreditoRepository())->porMesUsd('2026-03-01', '2026-03-31');

        self::assertArrayHasKey('2026-03', $porMes);
        self::assertGreaterThan(0.0, $porMes['2026-03']);
    }

    // ---- La devolucion de cada nota (migracion 012) ----

    /** @return list<int> los ids de la pagina de notas que da listado() */
    private static function idsDelListado(string $estado): array
    {
        return array_values(array_map(
            static fn (array $nota): int => (int) $nota['id'],
            (new NotaCreditoRepository())->listado($estado, 1)['filas']
        ));
    }

    /** La nota con ese id, que tiene que existir. */
    private static function nota(int $id): array
    {
        $nota = (new NotaCreditoRepository())->porId($id);
        self::assertNotNull($nota, "la nota #{$id} existe");

        return $nota;
    }

    public function testUnaNotaNuevaEstaPendienteHastaQueSeRegistraSuDevolucion(): void
    {
        $repo = new NotaCreditoRepository();
        $id = $this->crearNota(80.0, '2026-03-15');

        $nota = $repo->porId($id);
        self::assertNotNull($nota);
        self::assertNull($nota['fecha_devolucion'], 'una nota recien emitida no tiene devolucion');
        self::assertContains($id, self::idsDelListado('pendientes'));
        self::assertNotContains($id, self::idsDelListado('devueltas'));

        self::assertTrue($repo->registrarDevolucionSiEstabaPendiente($id, '2026-03-20'));

        self::assertSame('2026-03-20', self::nota($id)['fecha_devolucion']);
        self::assertNotContains($id, self::idsDelListado('pendientes'));
        self::assertContains($id, self::idsDelListado('devueltas'));
        self::assertContains($id, self::idsDelListado('todas'));
    }

    public function testRegistrarLaDevolucionDosVecesSoloCuentaLaPrimera(): void
    {
        $repo = new NotaCreditoRepository();
        $id = $this->crearNota(80.0, '2026-03-15');

        self::assertTrue($repo->registrarDevolucionSiEstabaPendiente($id, '2026-03-20'));
        self::assertFalse($repo->registrarDevolucionSiEstabaPendiente($id, '2026-03-25'), 'ya estaba devuelta: el llamador no debe auditar otra vez');

        self::assertSame('2026-03-20', self::nota($id)['fecha_devolucion'], 'se conserva la primera fecha');
    }

    public function testRegistrarUnaNotaQueNoExisteNoCambiaNada(): void
    {
        self::assertFalse((new NotaCreditoRepository())->registrarDevolucionSiEstabaPendiente(2_000_000_000, '2026-03-20'));
        self::assertNull((new NotaCreditoRepository())->porId(2_000_000_000));
    }

    public function testLaBaseRechazaUnaDevolucionAnteriorALaNota(): void
    {
        $repo = new NotaCreditoRepository();
        $id = $this->crearNota(80.0, '2026-03-15');

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('notas_credito_devolucion_no_antes_de_la_nota');

        $repo->registrarDevolucionSiEstabaPendiente($id, '2026-03-14');
    }

    /** Las pendientes salen primero las mas viejas (llevan mas esperando); el resto, las mas recientes. */
    public function testElOrdenDelListadoDependeDelEstado(): void
    {
        $vieja = $this->crearNota(10.0, '2026-01-10');
        $nueva = $this->crearNota(10.0, '2026-02-10');

        $pendientes = self::idsDelListado('pendientes');
        self::assertLessThan(array_search($nueva, $pendientes, true), array_search($vieja, $pendientes, true), 'pendientes: la mas vieja primero');

        $todas = self::idsDelListado('todas');
        self::assertLessThan(array_search($vieja, $todas, true), array_search($nueva, $todas, true), 'todas: la mas reciente primero');
    }

    public function testUnEstadoDesconocidoCuentaComoPendientes(): void
    {
        $id = $this->crearNota(10.0, '2026-03-15');

        self::assertContains($id, self::idsDelListado('cualquier cosa'));
    }

    public function testElListadoTraeAlClienteYLaCantidadTotal(): void
    {
        $this->crearNota(10.0, '2026-03-15');

        $pagina = (new NotaCreditoRepository())->listado('todas', 1);

        self::assertSame(1, $pagina['pagina']);
        self::assertGreaterThanOrEqual(1, $pagina['total']);
        self::assertNotSame('', $pagina['filas'][0]['cliente'], 'cada fila trae el nombre del cliente');
        self::assertArrayHasKey('fecha_devolucion', $pagina['filas'][0]);
    }

    public function testLaFichaDelClienteTraeLaFechaDeDevolucion(): void
    {
        $repo = new NotaCreditoRepository();
        $id = $this->crearNota(10.0, '2026-03-15');
        $repo->registrarDevolucionSiEstabaPendiente($id, '2026-03-18');
        $clienteId = (int) self::nota($id)['cliente_id'];

        $propia = array_values(array_filter($repo->porCliente($clienteId), static fn (array $n): bool => (int) $n['id'] === $id));

        self::assertSame('2026-03-18', $propia[0]['fecha_devolucion']);
    }

    public function testEstadoPedidoSoloAceptaLosFiltrosQueExisten(): void
    {
        self::assertSame('pendientes', NotaCreditoRepository::estadoPedido('pendientes'));
        self::assertSame('devueltas', NotaCreditoRepository::estadoPedido('devueltas'));
        self::assertSame('todas', NotaCreditoRepository::estadoPedido('todas'));
        self::assertSame('', NotaCreditoRepository::estadoPedido(''));
        self::assertSame('', NotaCreditoRepository::estadoPedido('inventado'));
    }

    public function testPendientesSumaLoQueFaltaDevolverYSeVaciaAlDevolver(): void
    {
        $repo = new NotaCreditoRepository();
        $antes = $repo->pendientes();

        $id = $this->crearNota(100.0, '2026-03-15');
        $tasaDeHoy = (float) Database::connection()->query(
            "SELECT m.tasa_a_usd FROM notas_credito n JOIN monedas m ON m.codigo = n.moneda_codigo WHERE n.id = {$id}"
        )->fetchColumn();
        $despues = $repo->pendientes();

        self::assertSame($antes['cantidad'] + 1, $despues['cantidad']);
        self::assertEqualsWithDelta($antes['total_usd'] + 100.0 * $tasaDeHoy, $despues['total_usd'], 0.0001);

        $repo->registrarDevolucionSiEstabaPendiente($id, '2026-03-16');
        $final = $repo->pendientes();

        self::assertSame($antes['cantidad'], $final['cantidad']);
        self::assertEqualsWithDelta($antes['total_usd'], $final['total_usd'], 0.0001);
    }

    /**
     * Lo que falta devolver es plata por pagar hoy: se valua a la tasa de hoy de la
     * moneda, no a la que la nota guardo para anular el cobro original. Si cambia
     * la de la nota (la que usan los reportes de cobros), el pendiente no se mueve.
     */
    public function testLoPendienteSeValuaALaTasaDeHoyYNoALaDeLaNota(): void
    {
        $repo = new NotaCreditoRepository();
        $id = $this->crearNota(100.0, '2026-03-15');
        $antes = $repo->pendientes()['total_usd'];

        Database::connection()->exec("UPDATE notas_credito SET tasa_a_usd = tasa_a_usd * 7 WHERE id = {$id}");

        self::assertEqualsWithDelta($antes, $repo->pendientes()['total_usd'], 0.0001);
    }
}

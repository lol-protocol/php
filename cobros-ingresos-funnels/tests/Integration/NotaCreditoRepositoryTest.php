<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\NotaCreditoRepository;
use DateTimeImmutable;

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
}

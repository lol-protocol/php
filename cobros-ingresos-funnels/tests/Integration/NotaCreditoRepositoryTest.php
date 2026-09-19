<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\NotaCreditoRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Las notas creadas
 * aca se borran en el tearDown: son datos de prueba, no el soft-delete de
 * una entidad de negocio.
 */
final class NotaCreditoRepositoryTest extends TestCase
{
    /** @var int[] */
    private array $idsCreados = [];

    protected function tearDown(): void
    {
        if ($this->idsCreados === []) {
            return;
        }
        $marcadores = implode(',', array_fill(0, count($this->idsCreados), '?'));
        $stmt = Database::connection()->prepare("DELETE FROM notas_credito WHERE id IN ({$marcadores})");
        $stmt->execute($this->idsCreados);
    }

    private function crearNota(float $monto, string $fecha): int
    {
        $boleta = Database::connection()->query(
            'SELECT id, cliente_id, moneda_codigo FROM boletas ORDER BY id LIMIT 1'
        )->fetch();
        self::assertNotFalse($boleta, 'este test asume que el seed dejo al menos una boleta');

        $id = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta['id'],
            'cliente_id' => $boleta['cliente_id'],
            'monto' => $monto,
            'moneda_codigo' => $boleta['moneda_codigo'],
            'fecha' => $fecha,
            'motivo' => 'Nota de prueba ' . uniqid(),
        ]);
        $this->idsCreados[] = $id;

        return $id;
    }

    public function testCrearDevuelveElIdYLaNotaQuedaEnElClienteCorrespondiente(): void
    {
        $id = $this->crearNota(120.50, '2026-03-15');

        $boleta = Database::connection()->query('SELECT cliente_id FROM boletas ORDER BY id LIMIT 1')->fetch();
        $notas = (new NotaCreditoRepository())->porCliente((int) $boleta['cliente_id']);

        self::assertGreaterThan(0, $id);
        self::assertContains($id, array_map(static fn (array $n): int => (int) $n['id'], $notas));
    }

    public function testTotalEnRangoSoloCuentaLasNotasDeEseRango(): void
    {
        $repo = new NotaCreditoRepository();
        $antes = $repo->totalEnRangoUsd('2026-03-01', '2026-03-31');

        $this->crearNota(100.0, '2026-03-15');
        $this->crearNota(100.0, '2026-07-15');

        $despues = $repo->totalEnRangoUsd('2026-03-01', '2026-03-31');

        self::assertGreaterThan($antes, $despues, 'la nota de marzo tiene que sumar al total de marzo');
        self::assertEqualsWithDelta(
            $antes,
            $repo->totalEnRangoUsd('2026-01-01', '2026-01-31'),
            0.0001,
            'la nota de julio no debe aparecer en el total de enero'
        );
    }

    public function testPorMesAgrupaCadaNotaEnSuMes(): void
    {
        $this->crearNota(100.0, '2026-03-15');

        $porMes = (new NotaCreditoRepository())->porMesUsd('2026-03-01', '2026-03-31');

        self::assertArrayHasKey('2026-03', $porMes);
        self::assertGreaterThan(0.0, $porMes['2026-03']);
    }
}

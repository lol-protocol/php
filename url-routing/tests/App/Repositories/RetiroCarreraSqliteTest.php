<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\RetiroRepository;
use App\Support\Database;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * What RetiroConcurrenciaTest proves on PostgreSQL (the real race between two people), the part of it that does
 * not need two writers, on SQLite: crear() answers a unique violation by trying the whole transaction again, and
 * answers anything else (a CHECK, a database that refuses) by failing at once.
 *
 * SQLite cannot play the other person: it undoes the whole statement on an error, the winner's row included, so
 * a trigger that «takes number 1 first» would be undone with it. The collision is raised by a trigger instead, on
 * the attempts a PHP function says (it counts them: PHP's counter survives the rollback), with the message
 * SQLite itself gives for a primary key that already exists.
 */
class RetiroCarreraSqliteTest extends TestCase
{
    private int $intentos = 0;

    /** @param string $mensaje what the trigger aborts the INSERT with, on every attempt up to $hasta */
    private function conFallo(string $mensaje, int $hasta): Database
    {
        $db = TestDatabases::fresh('sqlite', 'genealogy');
        $db->pdo()->sqliteCreateFunction('intento', function (): int {
            return ++$this->intentos;
        }, 0);
        $db->pdo()->exec(
            "CREATE TRIGGER falla BEFORE INSERT ON pedidos_retiro WHEN intento() <= {$hasta}
             BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END"
        );

        return $db;
    }

    public function testUnChoqueDeUnicidadSeReintentaYElPedidoSeGuardaCompleto(): void
    {
        $db = $this->conFallo('UNIQUE constraint failed: pedidos_retiro.id', 1);

        $numero = (new RetiroRepository($db))->crear('persona', 6128473105, 'me_nombra', 'Es falso.', 'quien@example.com');

        $this->assertSame(1, $numero);
        $this->assertSame(2, $this->intentos, 'the first attempt failed and the second was the one that stayed');
        $this->assertSame(1, (int)$db->fetchValue('SELECT COUNT(*) FROM pedidos_retiro'), 'one pedido, not one per attempt');
        $this->assertSame(1, (int)$db->fetchValue("SELECT COUNT(*) FROM pedido_retiro_eventos WHERE pedido_id = 1 AND estado = 'recibido'"), 'its event with it');
    }

    public function testSiElChoqueNoCesaSeRindeAlAgotarLosIntentos(): void
    {
        $db = $this->conFallo('UNIQUE constraint failed: pedidos_retiro.id', 99);

        try {
            (new RetiroRepository($db))->crear('persona', 6128473105, 'me_nombra', 'Es falso.', 'quien@example.com');
            $this->fail('a collision that never ends must reach the caller');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('UNIQUE', $e->getMessage());
        }

        $this->assertGreaterThan(1, $this->intentos, 'it did try again');
        $this->assertLessThan(10, $this->intentos, 'and it did not try forever');
        $this->assertSame(0, (int)$db->fetchValue('SELECT COUNT(*) FROM pedidos_retiro'), 'nothing half-written');
    }

    public function testOtroErrorNoSeReintenta(): void
    {
        $db = $this->conFallo('CHECK constraint failed: motivo', 99);

        try {
            (new RetiroRepository($db))->crear('persona', 6128473105, 'me_nombra', 'Es falso.', 'quien@example.com');
            $this->fail('an error that is not a collision must reach the caller');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('CHECK', $e->getMessage());
        }

        $this->assertSame(1, $this->intentos, 'it would fail the same way every time: one attempt');
    }
}

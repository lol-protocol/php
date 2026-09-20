<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Corre contra la base configurada por las env vars DB_*. Usa la tabla
 * auditoria (de solo insercion) como banco de pruebas: se cuentan sus filas
 * antes y despues, sin tocar datos de negocio.
 */
final class DatabaseTransaccionTest extends TestCase
{
    private function totalAuditoria(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
    }

    private function insertarFilaDeAuditoria(string $detalle): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle)
             VALUES (NULL, :accion, :entidad, :entidad_id, :detalle)'
        );
        $stmt->execute([
            ':accion' => 'crear',
            ':entidad' => 'prueba_transaccion',
            ':entidad_id' => 999999,
            ':detalle' => $detalle,
        ]);
    }

    public function testCommiteaTodasLasEscriturasSiLaOperacionTermina(): void
    {
        $antes = $this->totalAuditoria();

        Database::transaccion(function (): void {
            $this->insertarFilaDeAuditoria('Transaccion OK a ' . uniqid());
            $this->insertarFilaDeAuditoria('Transaccion OK b ' . uniqid());
        });

        self::assertSame($antes + 2, $this->totalAuditoria());

        Database::connection()
            ->prepare("DELETE FROM auditoria WHERE entidad = 'prueba_transaccion'")
            ->execute();
    }

    /**
     * El caso que motiva el helper: si la segunda escritura falla, la
     * primera no puede quedar aplicada (una boleta anulada sin su nota de
     * credito seria irrecuperable, porque la guarda de idempotencia
     * impediria reintentar).
     */
    public function testRevierteLaPrimeraEscrituraSiLaSegundaFalla(): void
    {
        $antes = $this->totalAuditoria();

        try {
            Database::transaccion(function (): void {
                $this->insertarFilaDeAuditoria('Esta no deberia sobrevivir ' . uniqid());
                throw new RuntimeException('falla simulada en la segunda escritura');
            });
            self::fail('la excepcion tenia que propagarse despues del rollback');
        } catch (RuntimeException $e) {
            self::assertSame('falla simulada en la segunda escritura', $e->getMessage());
        }

        self::assertSame($antes, $this->totalAuditoria(), 'el rollback tiene que dejar la tabla como estaba');
    }
}

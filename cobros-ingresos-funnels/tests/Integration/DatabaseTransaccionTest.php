<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\AuditoriaRepository;
use LogicException;
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

        $propagada = null;
        try {
            Database::transaccion(function (): void {
                $this->insertarFilaDeAuditoria('Esta no deberia sobrevivir ' . uniqid());
                throw new RuntimeException('falla simulada en la segunda escritura');
            });
        } catch (RuntimeException $e) {
            $propagada = $e;
        }

        self::assertNotNull($propagada, 'la excepcion tenia que propagarse despues del rollback');
        self::assertSame('falla simulada en la segunda escritura', $propagada->getMessage());

        self::assertSame($antes, $this->totalAuditoria(), 'el rollback tiene que dejar la tabla como estaba');
    }

    public function testDevuelveLoQueDevuelveLaOperacion(): void
    {
        self::assertSame(42, Database::transaccion(static fn (): int => 42));
    }

    /**
     * Una operacion que llama a otra (o un test que envuelve todo en una
     * transaccion) no puede hacer dos beginTransaction(): la de adentro se
     * anida con un SAVEPOINT. Si falla, se deshace solo lo suyo y la de
     * afuera sigue y commitea lo propio.
     */
    public function testUnaAnidadaQueFallaDeshaceSoloLoSuyo(): void
    {
        $antes = $this->totalAuditoria();

        Database::transaccion(function (): void {
            $this->insertarFilaDeAuditoria('De la de afuera ' . uniqid());
            try {
                Database::transaccion(function (): void {
                    $this->insertarFilaDeAuditoria('De la anidada ' . uniqid());
                    throw new RuntimeException('falla la anidada');
                });
            } catch (RuntimeException) {
                // la de afuera decide seguir
            }
        });

        self::assertSame($antes + 1, $this->totalAuditoria(), 'queda la de afuera, no la anidada');
        self::assertFalse(Database::connection()->inTransaction());

        Database::connection()
            ->prepare("DELETE FROM auditoria WHERE entidad = 'prueba_transaccion'")
            ->execute();
    }

    public function testLoQueHaceUnaAnidadaSeDeshaceSiFallaLaDeAfuera(): void
    {
        $antes = $this->totalAuditoria();

        try {
            Database::transaccion(function (): void {
                Database::transaccion(function (): void {
                    $this->insertarFilaDeAuditoria('Anidada que termino bien ' . uniqid());
                });
                throw new RuntimeException('falla la de afuera despues');
            });
        } catch (RuntimeException) {
        }

        self::assertSame($antes, $this->totalAuditoria(), 'la anidada no commitea por su cuenta');
    }

    /**
     * La auditoria tiene que quedar en la misma transaccion que el cambio
     * que describe. Un flujo que la registre suelto falla enseguida, en vez
     * de arriesgar un cambio sin auditar.
     */
    public function testAuditarFueraDeUnaTransaccionEsUnError(): void
    {
        $this->expectException(LogicException::class);

        AuditoriaRepository::auditarComoUsuarioActual('crear', 'prueba_transaccion', 1, 'Fuera de transaccion');
    }
}

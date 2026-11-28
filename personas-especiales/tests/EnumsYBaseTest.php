<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use PDO;
use PersonasEspeciales\AuditoriaPdo;
use PersonasEspeciales\Estado;
use PersonasEspeciales\Evento;
use PersonasEspeciales\Exclusion;
use PersonasEspeciales\Motivo;
use PersonasEspeciales\RegistroPdo;
use PersonasEspeciales\Solicitante;
use PersonasEspeciales\TipoEvento;
use PHPUnit\Framework\TestCase;

/**
 * Cada valor de un enum también está escrito a mano en un CHECK de la migración. Si se agrega un caso al
 * enum y se olvida la migración, esto falla aquí y no en producción con una excepción de PostgreSQL.
 */
final class EnumsYBaseTest extends TestCase
{
    use PostgresDePrueba;

    private PDO $pdo;
    private DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->pdo = $this->conectar();
        $this->t0 = new DateTimeImmutable('2026-01-10 10:00:00+00');
    }

    public function testLaBaseAceptaCadaMotivoYCadaSolicitante(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $n = 0;
        foreach (Motivo::cases() as $motivo) {
            foreach (Solicitante::cases() as $solicitante) {
                $id = 's-' . ++$n;
                $registro->insertar(Exclusion::solicitar($id, 'p-' . $n, $solicitante, $motivo, $this->t0));

                $leida = $registro->porId($id);
                self::assertSame($motivo, $leida?->motivo());
                self::assertSame($solicitante, $leida->solicitante());
            }
        }
        self::assertSame(count(Motivo::cases()) * count(Solicitante::cases()), $n);
    }

    public function testLaBaseAceptaCadaEstado(): void
    {
        $registro = new RegistroPdo($this->pdo);
        foreach (Estado::cases() as $i => $estado) {
            $id = 's-' . $i;
            // activa y revocada llevan la prueba con la que se verificó; rechazada, la razón del rechazo.
            $prueba = $estado === Estado::Pendiente ? null : 'una prueba';
            $registro->insertar(Exclusion::desde($id, 'p-' . $i, 'persona', 'otro', $prueba, $estado->value, $this->t0, $this->t0));

            self::assertSame($estado, $registro->porId($id)?->estado());
        }
    }

    public function testLaBaseAceptaCadaTipoDeEvento(): void
    {
        (new RegistroPdo($this->pdo))->insertar(Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Otro, $this->t0));
        $auditoria = new AuditoriaPdo($this->pdo);

        foreach (TipoEvento::cases() as $tipo) {
            $auditoria->registrar(new Evento('s-1', $tipo, 'actor-1', $this->t0));
        }

        $guardados = array_map(static fn (Evento $e): TipoEvento => $e->tipo, $auditoria->eventosDe('s-1'));
        self::assertSame(TipoEvento::cases(), $guardados);
    }
}

<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use PersonasEspeciales\Auditoria;
use PersonasEspeciales\AuditoriaEnMemoria;
use PersonasEspeciales\AuditoriaPdo;
use PersonasEspeciales\Estado;
use PersonasEspeciales\Evento;
use PersonasEspeciales\Gestion;
use PersonasEspeciales\RegistroEnMemoria;
use PersonasEspeciales\RegistroPdo;
use PersonasEspeciales\Solicitante;
use PersonasEspeciales\TipoEvento;
use PHPUnit\Framework\TestCase;

final class GestionTest extends TestCase
{
    use PostgresDePrueba;

    private DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->t0 = new DateTimeImmutable('2026-01-10 10:00:00+00');
    }

    private function enMemoria(): array
    {
        $registro = new RegistroEnMemoria();
        $auditoria = new AuditoriaEnMemoria();

        return [new Gestion($registro, $auditoria), $registro, $auditoria];
    }

    public function testElCaminoCompletoDejaUnEventoPorPaso(): void
    {
        [$gestion, $registro, $auditoria] = $this->enMemoria();

        $gestion->solicitar('s-1', 'p-1', Solicitante::Persona, 'Pide.', 'solicitante-1', $this->t0);
        $gestion->verificar('s-1', 'Cédula.', 'revisor-9', $this->t0->modify('+1 day'));
        $gestion->revocar('s-1', 'revisor-9', $this->t0->modify('+2 days'));

        $tipos = array_map(static fn (Evento $e): TipoEvento => $e->tipo, $auditoria->eventosDe('s-1'));
        self::assertSame([TipoEvento::Solicitada, TipoEvento::Verificada, TipoEvento::Revocada], $tipos);
        self::assertSame(Estado::Revocada, $registro->porId('s-1')?->estado());
        self::assertSame('revisor-9', $auditoria->eventosDe('s-1')[1]->actor);
    }

    public function testRechazarDejaSuEvento(): void
    {
        [$gestion, , $auditoria] = $this->enMemoria();
        $gestion->solicitar('s-2', 'p-2', Solicitante::Apoderado, 'Pide.', 'a', $this->t0);

        $gestion->rechazar('s-2', 'No corresponde.', 'revisor-1', $this->t0);

        self::assertSame(TipoEvento::Rechazada, $auditoria->eventosDe('s-2')[1]->tipo);
    }

    public function testUnaTransicionInvalidaNoDejaEvento(): void
    {
        [$gestion, , $auditoria] = $this->enMemoria();
        $gestion->solicitar('s-3', 'p-3', Solicitante::Persona, 'Pide.', 'a', $this->t0);

        try {
            $gestion->revocar('s-3', 'revisor-1', $this->t0); // pendiente: no se revoca
            self::fail('Debía fallar.');
        } catch (DomainException) {
            // esperado
        }

        self::assertCount(1, $auditoria->eventosDe('s-3'));
    }

    public function testSolicitudInexistenteEsUnError(): void
    {
        [$gestion] = $this->enMemoria();

        $this->expectException(DomainException::class);
        $gestion->verificar('no-existe', 'Cédula.', 'revisor-1', $this->t0);
    }

    public function testNoSePisaUnaSolicitudConElMismoId(): void
    {
        [$gestion, $registro] = $this->enMemoria();
        $gestion->solicitar('s-4', 'p-4', Solicitante::Persona, 'Pide.', 'a', $this->t0);

        try {
            $gestion->solicitar('s-4', 'p-OTRA', Solicitante::Persona, 'Otra.', 'b', $this->t0);
            self::fail('Debía fallar.');
        } catch (DomainException) {
            // esperado
        }

        self::assertSame('p-4', $registro->porId('s-4')?->personaId());
    }

    public function testElActorNoPuedeEstarVacio(): void
    {
        [$gestion] = $this->enMemoria();

        $this->expectException(InvalidArgumentException::class);
        $gestion->solicitar('s-5', 'p-5', Solicitante::Persona, 'Pide.', '  ', $this->t0);
    }

    public function testConPostgresLaAuditoriaQuedaEnLaBase(): void
    {
        $pdo = $this->conectar();
        $auditoria = new AuditoriaPdo($pdo);
        $gestion = new Gestion(new RegistroPdo($pdo), $auditoria, $pdo);

        $gestion->solicitar('s-1', 'p-1', Solicitante::FamiliarFallecido, 'Pide.', 'solicitante-1', $this->t0);
        $gestion->verificar('s-1', 'Acta.', 'revisor-9', $this->t0->modify('+1 day'));

        $eventos = $auditoria->eventosDe('s-1');
        self::assertCount(2, $eventos);
        self::assertSame(TipoEvento::Verificada, $eventos[1]->tipo);
        self::assertEquals($this->t0->modify('+1 day'), $eventos[1]->ocurridoEn);
        self::assertSame(Estado::Activa, (new RegistroPdo($pdo))->porId('s-1')?->estado());
    }

    public function testSiFallaLaAuditoriaNoQuedaElCambio(): void
    {
        $pdo = $this->conectar();
        $gestion = new Gestion(new RegistroPdo($pdo), new AuditoriaPdo($pdo), $pdo);
        $gestion->solicitar('s-1', 'p-1', Solicitante::Persona, 'Pide.', 'a', $this->t0);

        $rota = new class implements Auditoria {
            public function registrar(Evento $evento): void
            {
                throw new \RuntimeException('auditoría caída');
            }

            public function eventosDe(string $exclusionId): array
            {
                return [];
            }
        };
        try {
            (new Gestion(new RegistroPdo($pdo), $rota, $pdo))->verificar('s-1', 'Cédula.', 'revisor-1', $this->t0);
            self::fail('Debía fallar.');
        } catch (\RuntimeException) {
            // esperado
        }

        // Sin evento no hay cambio: sigue pendiente, y la persona sigue visible.
        self::assertSame(Estado::Pendiente, (new RegistroPdo($pdo))->porId('s-1')?->estado());
        self::assertFalse($pdo->inTransaction());
    }

    /** @dataProvider modificaciones */
    public function testLaBaseNoDejaEditarNiBorrarLaAuditoria(string $sql): void
    {
        $pdo = $this->conectar();
        (new Gestion(new RegistroPdo($pdo), new AuditoriaPdo($pdo), $pdo))
            ->solicitar('s-1', 'p-1', Solicitante::Persona, 'Pide.', 'a', $this->t0);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('solo admite INSERT');
        $pdo->exec($sql);
    }

    /** @return iterable<string, array{string}> */
    public static function modificaciones(): iterable
    {
        yield 'UPDATE' => ["UPDATE exclusion_evento SET actor = 'otro'"];
        yield 'DELETE' => ['DELETE FROM exclusion_evento'];
        yield 'TRUNCATE' => ['TRUNCATE exclusion_evento'];
    }

    public function testNoHayEventoDeUnaSolicitudQueNoExiste(): void
    {
        $pdo = $this->conectar();

        $this->expectException(PDOException::class);
        (new AuditoriaPdo($pdo))->registrar(new Evento('fantasma', TipoEvento::Solicitada, 'a', $this->t0));
    }
}

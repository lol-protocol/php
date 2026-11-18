<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use PDO;
use PersonasEspeciales\Exclusion;
use PersonasEspeciales\RegistroPdo;
use PersonasEspeciales\Solicitante;
use PersonasEspeciales\Visibilidad;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra PostgreSQL de verdad. Sin TEST_PG_DSN se omite (como en url-routing);
 * la CI lo corre con un servicio de PostgreSQL.
 */
final class RegistroPdoTest extends TestCase
{
    private PDO $pdo;
    private DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $dsn = getenv('TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Sin TEST_PG_DSN: se omite la prueba contra PostgreSQL.');
        }

        $this->pdo = new PDO($dsn, getenv('TEST_PG_USER') ?: null, getenv('TEST_PG_PASSWORD') ?: null);
        $this->pdo->exec('DROP TABLE IF EXISTS exclusion');
        $this->pdo->exec((string) file_get_contents(__DIR__ . '/../database/esquema.sql'));
        $this->t0 = new DateTimeImmutable('2026-01-10 10:00:00+00');
    }

    public function testGuardarYLeerUnaActiva(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $activa = Exclusion::solicitar('s-1', 'p-1', Solicitante::FamiliarFallecido, 'Pide no aparecer.', $this->t0)
            ->verificar('Acta de defunción.', $this->t0->modify('+1 day'));
        $registro->guardar($activa);

        $leidas = $registro->activasDe('p-1');

        self::assertCount(1, $leidas);
        self::assertSame('s-1', $leidas[0]->id());
        self::assertSame(Solicitante::FamiliarFallecido, $leidas[0]->solicitante());
        self::assertSame('Acta de defunción.', $leidas[0]->prueba());
        self::assertEquals($this->t0->modify('+1 day'), $leidas[0]->cambiadaEn());
    }

    public function testPendienteNoSeLeeComoActiva(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $registro->guardar(Exclusion::solicitar('s-2', 'p-2', Solicitante::Persona, 'Pide.', $this->t0));

        self::assertSame([], $registro->activasDe('p-2'));
    }

    public function testGuardarOtraVezReemplazaLaMismaSolicitud(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $pendiente = Exclusion::solicitar('s-3', 'p-3', Solicitante::Apoderado, 'Pide.', $this->t0);
        $registro->guardar($pendiente);
        $registro->guardar($pendiente->verificar('Poder notarial.', $this->t0->modify('+1 day')));

        self::assertCount(1, $registro->activasDe('p-3'));
        $total = (int) $this->pdo->query('SELECT count(*) FROM exclusion')->fetchColumn();
        self::assertSame(1, $total);
    }

    public function testRevocarLaSacaDeLaPolitica(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $activa = Exclusion::solicitar('s-4', 'p-4', Solicitante::Persona, 'Pide.', $this->t0)
            ->verificar('Cédula.', $this->t0);
        $registro->guardar($activa);

        $visibilidad = new Visibilidad($registro);
        self::assertFalse($visibilidad->esVisible('p-4'));

        $registro->guardar($activa->revocar($this->t0->modify('+2 days')));
        self::assertTrue($visibilidad->esVisible('p-4'));
    }
}

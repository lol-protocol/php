<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DomainException;
use PDO;
use PersonasEspeciales\Estado;
use PersonasEspeciales\Exclusion;
use PersonasEspeciales\RegistroPdo;
use PersonasEspeciales\Motivo;
use PersonasEspeciales\Solicitante;
use PersonasEspeciales\Visibilidad;
use PHPUnit\Framework\TestCase;

/** Corre contra PostgreSQL de verdad (ver PostgresDePrueba). */
final class RegistroPdoTest extends TestCase
{
    use PostgresDePrueba;

    private PDO $pdo;
    private DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->pdo = $this->conectar();
        $this->t0 = new DateTimeImmutable('2026-01-10 10:00:00+00');
    }

    public function testInsertarRechazaUnIdRepetidoYNoTocaLaOriginal(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $registro->insertar(Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Seguridad, $this->t0));

        try {
            $registro->insertar(Exclusion::solicitar('s-1', 'p-OTRA', Solicitante::Apoderado, Motivo::Otro, $this->t0));
            self::fail('Debía fallar.');
        } catch (DomainException) {
            // esperado
        }

        $original = $registro->porId('s-1');
        self::assertSame('p-1', $original?->personaId());
        self::assertSame(Motivo::Seguridad, $original->motivo());
    }

    public function testActualizarFallaSiOtroCambioElEstado(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $pendiente = Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Otro, $this->t0);
        $registro->insertar($pendiente);
        $registro->actualizar($pendiente->verificar('Cédula.', $this->t0), Estado::Pendiente);

        // Alguien más rechazaría la misma solicitud leída como pendiente: ya no lo está.
        $this->expectException(DomainException::class);
        $registro->actualizar($pendiente->rechazar('No corresponde.', $this->t0), Estado::Pendiente);
    }

    public function testActualizarFallaSiLaSolicitudNoExiste(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $fantasma = Exclusion::solicitar('fantasma', 'p-1', Solicitante::Persona, Motivo::Otro, $this->t0);

        $this->expectException(DomainException::class);
        $registro->actualizar($fantasma->verificar('Cédula.', $this->t0), Estado::Pendiente);
    }

    public function testActualizarSoloCambiaLoQueUnaTransicionCambia(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $registro->insertar(Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Seguridad, $this->t0));

        // Un objeto con el mismo id pero otra persona, otro motivo y otra fecha de alta: solo debe
        // entrar lo que una transición cambia (estado, prueba, fecha del cambio).
        $distinto = Exclusion::desde(
            's-1', 'p-OTRA', 'apoderado', 'otro', 'Cédula.', 'activa',
            $this->t0->modify('-5 days'), $this->t0->modify('+1 day'),
        );
        $registro->actualizar($distinto, Estado::Pendiente);

        $guardada = $registro->porId('s-1');
        self::assertSame('p-1', $guardada?->personaId());
        self::assertSame(Solicitante::Persona, $guardada->solicitante());
        self::assertSame(Motivo::Seguridad, $guardada->motivo());
        self::assertEquals($this->t0, $guardada->creadaEn());
        self::assertSame(Estado::Activa, $guardada->estado());
        self::assertSame('Cédula.', $guardada->prueba());
    }

    public function testDosRevisoresALaVezSoloUnoGana(): void
    {
        $revisorA = new RegistroPdo($this->pdo);
        $revisorB = new RegistroPdo($this->abrir()); // otra conexión: otro revisor
        $revisorA->insertar(Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Otro, $this->t0));

        // Los dos leen la solicitud como pendiente...
        $vistaA = $revisorA->porId('s-1');
        $vistaB = $revisorB->porId('s-1');
        self::assertNotNull($vistaA);
        self::assertNotNull($vistaB);

        // ...A la verifica, y B, que decidió con la vista vieja, ya no puede rechazarla.
        $revisorA->actualizar($vistaA->verificar('Cédula.', $this->t0), Estado::Pendiente);
        try {
            $revisorB->actualizar($vistaB->rechazar('No corresponde.', $this->t0), Estado::Pendiente);
            self::fail('B pisó el cambio de A.');
        } catch (DomainException) {
            // esperado
        }

        self::assertSame(Estado::Activa, $revisorA->porId('s-1')?->estado());
    }

    public function testLaBaseRechazaUnMotivoDeTextoLibre(): void
    {
        // La lista cerrada vale también en la base: aunque otro código escriba directo, no entra texto libre.
        $insertar = $this->pdo->prepare(
            "INSERT INTO exclusion (id, persona_id, solicitante, motivo, prueba, estado, creada_en, cambiada_en)
             VALUES ('x', 'p', 'persona', :motivo, NULL, 'pendiente', now(), now())"
        );

        try {
            $insertar->execute(['motivo' => 'es judío']);
            self::fail('La base aceptó un motivo de texto libre.');
        } catch (\PDOException $e) {
            self::assertStringContainsString('motivo', $e->getMessage());
        }
        $insertar->execute(['motivo' => 'seguridad']); // uno de la lista sí entra
        $this->addToAssertionCount(1);
    }

    public function testPorIdDevuelveLaSolicitudEnCualquierEstado(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $registro->insertar(Exclusion::solicitar('s-7', 'p-7', Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0));

        self::assertSame('p-7', $registro->porId('s-7')?->personaId());
        self::assertNull($registro->porId('no-existe'));
    }

    public function testConExclusionActivaConsultaPorLotes(): void
    {
        $registro = new RegistroPdo($this->pdo);
        // 1.200 personas (más que un lote de 500); excluidas: una de cada lote y una repetida.
        $ids = array_map(static fn (int $i): string => "p-{$i}", range(1, 1200));
        foreach (['p-10', 'p-600', 'p-1199'] as $n => $persona) {
            $registro->insertar(
                Exclusion::solicitar("s-{$n}", $persona, Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0)
                    ->verificar('Cédula.', $this->t0)
            );
        }
        $registro->insertar(Exclusion::solicitar('s-pend', 'p-20', Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0));

        $excluidas = $registro->conExclusionActiva([...$ids, 'p-10']);
        sort($excluidas);

        self::assertSame(['p-10', 'p-1199', 'p-600'], $excluidas);
        self::assertSame([], $registro->conExclusionActiva([]));
    }

    public function testFiltrarConservaElOrdenEnVariosLotes(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $registro->insertar(
            Exclusion::solicitar('s-1', 'p-3', Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0)
                ->verificar('Cédula.', $this->t0)
        );
        $ids = array_map(static fn (int $i): string => "p-{$i}", range(1, 700));

        $visibles = (new Visibilidad($registro))->filtrar($ids);

        self::assertCount(699, $visibles);
        self::assertSame(['p-1', 'p-2', 'p-4'], array_slice($visibles, 0, 3));
    }

    public function testInsertarYLeerUnaActiva(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $activa = Exclusion::solicitar('s-1', 'p-1', Solicitante::FamiliarFallecido, Motivo::PrivacidadPersonal, $this->t0)
            ->verificar('Acta de defunción.', $this->t0->modify('+1 day'));
        $registro->insertar($activa);

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
        $registro->insertar(Exclusion::solicitar('s-2', 'p-2', Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0));

        self::assertSame([], $registro->activasDe('p-2'));
    }

    public function testActualizarCambiaLaMismaSolicitudSinDuplicarla(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $pendiente = Exclusion::solicitar('s-3', 'p-3', Solicitante::Apoderado, Motivo::PrivacidadPersonal, $this->t0);
        $registro->insertar($pendiente);
        $registro->actualizar($pendiente->verificar('Poder notarial.', $this->t0->modify('+1 day')), Estado::Pendiente);

        self::assertCount(1, $registro->activasDe('p-3'));
        $total = (int) $this->pdo->query('SELECT count(*) FROM exclusion')->fetchColumn();
        self::assertSame(1, $total);
    }

    public function testRevocarLaSacaDeLaPolitica(): void
    {
        $registro = new RegistroPdo($this->pdo);
        $activa = Exclusion::solicitar('s-4', 'p-4', Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0)
            ->verificar('Cédula.', $this->t0);
        $registro->insertar($activa);

        $visibilidad = new Visibilidad($registro);
        self::assertFalse($visibilidad->esVisible('p-4'));

        $registro->actualizar($activa->revocar($this->t0->modify('+2 days')), Estado::Activa);
        self::assertTrue($visibilidad->esVisible('p-4'));
    }
}

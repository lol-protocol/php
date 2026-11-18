<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PersonasEspeciales\Estado;
use PersonasEspeciales\Exclusion;
use PersonasEspeciales\Solicitante;
use PHPUnit\Framework\TestCase;

final class ExclusionTest extends TestCase
{
    private DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->t0 = new DateTimeImmutable('2026-01-10 10:00:00+00');
    }

    private function pendiente(): Exclusion
    {
        return Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, 'Pide no aparecer.', $this->t0);
    }

    public function testSolicitarQuedaPendienteSinPrueba(): void
    {
        $e = $this->pendiente();

        self::assertSame(Estado::Pendiente, $e->estado());
        self::assertNull($e->prueba());
        self::assertFalse($e->estaActiva());
        self::assertSame(Solicitante::Persona, $e->solicitante());
    }

    public function testVerificarActivaConPrueba(): void
    {
        $t1 = $this->t0->modify('+1 day');
        $e = $this->pendiente()->verificar('Cédula presentada en la oficina.', $t1);

        self::assertSame(Estado::Activa, $e->estado());
        self::assertTrue($e->estaActiva());
        self::assertSame('Cédula presentada en la oficina.', $e->prueba());
        self::assertEquals($t1, $e->cambiadaEn());
        self::assertEquals($this->t0, $e->creadaEn());
    }

    public function testVerificarSinPruebaVacia(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pendiente()->verificar('   ', $this->t0);
    }

    public function testRechazarGuardaLaRazonYNoActiva(): void
    {
        $e = $this->pendiente()->rechazar('La prueba no corresponde al solicitante.', $this->t0);

        self::assertSame(Estado::Rechazada, $e->estado());
        self::assertFalse($e->estaActiva());
        self::assertSame('La prueba no corresponde al solicitante.', $e->prueba());
    }

    public function testRevocarSoloDesdeActiva(): void
    {
        $activa = $this->pendiente()->verificar('Acta de defunción.', $this->t0);
        $revocada = $activa->revocar($this->t0->modify('+5 days'));

        self::assertSame(Estado::Revocada, $revocada->estado());
        self::assertFalse($revocada->estaActiva());
        self::assertSame('Acta de defunción.', $revocada->prueba());

        $this->expectException(DomainException::class);
        $this->pendiente()->revocar($this->t0);
    }

    public function testEstadosFinalesNoCambian(): void
    {
        $rechazada = $this->pendiente()->rechazar('No corresponde.', $this->t0);

        $this->expectException(DomainException::class);
        $rechazada->verificar('Otra prueba.', $this->t0);
    }

    public function testNoSePuedeRechazarUnaActiva(): void
    {
        $activa = $this->pendiente()->verificar('Prueba.', $this->t0);

        $this->expectException(DomainException::class);
        $activa->rechazar('Razón.', $this->t0);
    }

    public function testInmutable(): void
    {
        $original = $this->pendiente();
        $original->verificar('Prueba.', $this->t0->modify('+1 day'));

        self::assertSame(Estado::Pendiente, $original->estado());
    }

    public function testNoVuelveAtrasEnElTiempo(): void
    {
        $e = $this->pendiente()->verificar('Prueba.', $this->t0->modify('+1 day'));

        $this->expectException(InvalidArgumentException::class);
        $e->revocar($this->t0);
    }

    /** @dataProvider camposVacios */
    public function testCamposObligatoriosNoPuedenEstarVacios(string $id, string $persona, string $motivo): void
    {
        $this->expectException(InvalidArgumentException::class);
        Exclusion::solicitar($id, $persona, Solicitante::Apoderado, $motivo, $this->t0);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function camposVacios(): iterable
    {
        yield 'id vacío' => ['', 'p-1', 'Motivo.'];
        yield 'persona vacía' => ['s-1', ' ', 'Motivo.'];
        yield 'motivo vacío' => ['s-1', 'p-1', ''];
    }

    public function testMotivoNoPasaDeQuinientosCaracteres(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, str_repeat('a', Exclusion::MOTIVO_MAX + 1), $this->t0);
    }

    public function testMotivoDeQuinientosCaracteresSiPasa(): void
    {
        $motivo = str_repeat('á', Exclusion::MOTIVO_MAX);
        $e = Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, $motivo, $this->t0);

        self::assertSame($motivo, $e->motivo());
    }

    public function testDesdeReconstruyeUnaActiva(): void
    {
        $e = Exclusion::desde(
            's-9', 'p-9', 'familiar_fallecido', 'Pide no aparecer.', 'Acta.', 'activa',
            $this->t0, $this->t0->modify('+1 day'),
        );

        self::assertSame(Estado::Activa, $e->estado());
        self::assertSame(Solicitante::FamiliarFallecido, $e->solicitante());
    }

    public function testDesdeRechazaActivaSinPrueba(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Exclusion::desde('s-9', 'p-9', 'persona', 'Motivo.', null, 'activa', $this->t0, $this->t0);
    }

    public function testDesdeRechazaSolicitanteDesconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Exclusion::desde('s-9', 'p-9', 'religion', 'Motivo.', null, 'pendiente', $this->t0, $this->t0);
    }

    public function testDesdeRechazaEstadoDesconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Exclusion::desde('s-9', 'p-9', 'persona', 'Motivo.', null, 'borrada', $this->t0, $this->t0);
    }

    public function testDesdeRechazaCambioAnteriorAlAlta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Exclusion::desde('s-9', 'p-9', 'persona', 'Motivo.', null, 'pendiente', $this->t0, $this->t0->modify('-1 day'));
    }

    public function testNoHayCategoriasDePersonaEnElModelo(): void
    {
        // La decisión de la Fase 0: solo quien pide, nunca una etiqueta de la persona.
        $valores = array_map(static fn (Solicitante $s): string => $s->value, Solicitante::cases());

        self::assertSame(['persona', 'familiar_fallecido', 'apoderado'], $valores);
    }
}

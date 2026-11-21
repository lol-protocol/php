<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DomainException;
use PersonasEspeciales\Estado;
use PersonasEspeciales\Exclusion;
use PersonasEspeciales\Motivo;
use PersonasEspeciales\RegistroEnMemoria;
use PersonasEspeciales\Solicitante;
use PHPUnit\Framework\TestCase;

/** El registro en memoria tiene que comportarse como el de PDO (insertar y actualizar con las mismas reglas). */
final class RegistroEnMemoriaTest extends TestCase
{
    private DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->t0 = new DateTimeImmutable('2026-01-10 10:00:00+00');
    }

    public function testInsertarRechazaUnIdRepetido(): void
    {
        $registro = new RegistroEnMemoria();
        $registro->insertar(Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Seguridad, $this->t0));

        try {
            $registro->insertar(Exclusion::solicitar('s-1', 'p-OTRA', Solicitante::Persona, Motivo::Otro, $this->t0));
            self::fail('Debía fallar.');
        } catch (DomainException) {
            // esperado
        }

        self::assertSame('p-1', $registro->porId('s-1')?->personaId());
    }

    public function testActualizarFallaSiCambioElEstadoOSiNoExiste(): void
    {
        $registro = new RegistroEnMemoria();
        $pendiente = Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Otro, $this->t0);
        $registro->insertar($pendiente);
        $registro->actualizar($pendiente->verificar('Cédula.', $this->t0), Estado::Pendiente);

        try {
            $registro->actualizar($pendiente->rechazar('No corresponde.', $this->t0), Estado::Pendiente);
            self::fail('Debía fallar por el estado.');
        } catch (DomainException) {
            // esperado
        }

        $this->expectException(DomainException::class);
        $registro->actualizar(
            Exclusion::solicitar('fantasma', 'p-9', Solicitante::Persona, Motivo::Otro, $this->t0)->verificar('x', $this->t0),
            Estado::Pendiente,
        );
    }

    public function testActualizarSoloCambiaLoQueUnaTransicionCambia(): void
    {
        $registro = new RegistroEnMemoria();
        $registro->insertar(Exclusion::solicitar('s-1', 'p-1', Solicitante::Persona, Motivo::Seguridad, $this->t0));

        $registro->actualizar(
            Exclusion::desde('s-1', 'p-OTRA', 'apoderado', 'otro', 'Cédula.', 'activa', $this->t0->modify('-5 days'), $this->t0->modify('+1 day')),
            Estado::Pendiente,
        );

        $guardada = $registro->porId('s-1');
        self::assertSame('p-1', $guardada?->personaId());
        self::assertSame(Motivo::Seguridad, $guardada->motivo());
        self::assertEquals($this->t0, $guardada->creadaEn());
        self::assertSame(Estado::Activa, $guardada->estado());
    }
}

<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use PersonasEspeciales\Estado;
use PersonasEspeciales\Exclusion;
use PersonasEspeciales\RegistroEnMemoria;
use PersonasEspeciales\Motivo;
use PersonasEspeciales\Solicitante;
use PersonasEspeciales\Visibilidad;
use PHPUnit\Framework\TestCase;

final class VisibilidadTest extends TestCase
{
    private DateTimeImmutable $t0;
    private RegistroEnMemoria $registro;
    private Visibilidad $visibilidad;

    protected function setUp(): void
    {
        $this->t0 = new DateTimeImmutable('2026-01-10 10:00:00+00');
        $this->registro = new RegistroEnMemoria();
        $this->visibilidad = new Visibilidad($this->registro);
    }

    private function excluir(string $id, string $persona): void
    {
        $e = Exclusion::solicitar($id, $persona, Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0)
            ->verificar('Cédula presentada.', $this->t0);
        $this->registro->insertar($e);
    }

    public function testPersonaSinRegistroEsVisible(): void
    {
        self::assertTrue($this->visibilidad->esVisible('nadie'));
    }

    public function testExcluidaNoEsVisibleNiConectableNiExportable(): void
    {
        $this->excluir('s-1', 'p-1');

        self::assertFalse($this->visibilidad->esVisible('p-1'));
        self::assertFalse($this->visibilidad->puedeExportar('p-1'));
        self::assertFalse($this->visibilidad->puedeConectar('p-1', 'p-2'));
        self::assertFalse($this->visibilidad->puedeConectar('p-2', 'p-1'));
    }

    public function testConectarSoloSiAmbasSonVisibles(): void
    {
        self::assertTrue($this->visibilidad->puedeConectar('p-2', 'p-3'));
    }

    public function testPendienteNoExcluye(): void
    {
        $pendiente = Exclusion::solicitar('s-2', 'p-4', Solicitante::Apoderado, Motivo::PrivacidadPersonal, $this->t0);
        $this->registro->insertar($pendiente);

        self::assertTrue($this->visibilidad->esVisible('p-4'));
    }

    public function testRechazadaNoExcluye(): void
    {
        $rechazada = Exclusion::solicitar('s-3', 'p-5', Solicitante::Persona, Motivo::PrivacidadPersonal, $this->t0)
            ->rechazar('No corresponde.', $this->t0);
        $this->registro->insertar($rechazada);

        self::assertTrue($this->visibilidad->esVisible('p-5'));
    }

    public function testRevocadaVuelveAVerse(): void
    {
        $this->excluir('s-4', 'p-6');
        self::assertFalse($this->visibilidad->esVisible('p-6'));

        $activa = $this->registro->activasDe('p-6')[0];
        $this->registro->actualizar($activa->revocar($this->t0->modify('+1 day')), Estado::Activa);

        self::assertTrue($this->visibilidad->esVisible('p-6'));
    }

    public function testFiltrarQuitaExcluidasYConservaElOrden(): void
    {
        $this->excluir('s-5', 'p-8');

        self::assertSame(
            ['p-7', 'p-9', 'p-10'],
            $this->visibilidad->filtrar(['p-7', 'p-8', 'p-9', 'p-10']),
        );
    }

    public function testConExclusionActivaDevuelveCadaPersonaUnaVez(): void
    {
        $this->excluir('s-8', 'p-12');
        $this->excluir('s-9', 'p-12');
        $this->excluir('s-10', 'p-13');

        $excluidas = $this->registro->conExclusionActiva(['p-12', 'p-13', 'p-14', 'p-12']);
        sort($excluidas);

        self::assertSame(['p-12', 'p-13'], $excluidas);
    }

    public function testRegistroPuedeTenerVariasExclusionesDeUnaPersona(): void
    {
        // Una persona puede tener dos solicitudes (p. ej. de su familia y de ella misma): basta una activa.
        $this->excluir('s-6', 'p-11');
        $this->excluir('s-7', 'p-11');
        $activas = $this->registro->activasDe('p-11');

        self::assertCount(2, $activas);
        self::assertFalse($this->visibilidad->esVisible('p-11'));
    }
}

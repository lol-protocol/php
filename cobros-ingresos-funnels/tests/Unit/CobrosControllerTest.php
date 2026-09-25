<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controllers\CobrosController;
use PHPUnit\Framework\TestCase;

final class CobrosControllerTest extends TestCase
{
    public function testMontoMayorOIgualALoCobradoLoCubre(): void
    {
        $boleta = ['pagado' => 100.0];

        self::assertTrue(CobrosController::montoCubreLoYaCobrado(100.0, $boleta));
        self::assertTrue(CobrosController::montoCubreLoYaCobrado(150.0, $boleta));
    }

    /**
     * Reproduce el bug real: una boleta con $602.85 ya cobrados se podia
     * editar bajando su monto a $10, dejando un saldo negativo sin aviso.
     */
    public function testMontoMenorALoYaCobradoNoLoCubre(): void
    {
        $boleta = ['pagado' => 602.85];

        self::assertFalse(CobrosController::montoCubreLoYaCobrado(10.0, $boleta));
    }

    public function testSinPagosCualquierMontoPositivoLoCubre(): void
    {
        $boleta = ['pagado' => 0.0];

        self::assertTrue(CobrosController::montoCubreLoYaCobrado(1.0, $boleta));
    }

    public function testVencimientoPosteriorOIgualALaEmisionEsValido(): void
    {
        self::assertTrue(CobrosController::vencimientoNoAnteriorALaEmision('2026-01-15', '2026-02-15'));
        self::assertTrue(CobrosController::vencimientoNoAnteriorALaEmision('2026-01-15', '2026-01-15'));
    }

    /** Reproduce el caso real: emision 2026-06-01 con vencimiento 2020-01-01, aceptado antes del fix. */
    public function testVencimientoAnteriorALaEmisionNoEsValido(): void
    {
        self::assertFalse(CobrosController::vencimientoNoAnteriorALaEmision('2026-06-01', '2020-01-01'));
    }

    public function testSinPagosLaEmisionPuedeMoverseALibertad(): void
    {
        $boleta = ['primer_pago' => null];

        self::assertTrue(CobrosController::emisionNoPosteriorAlPrimerPago('2030-01-01', $boleta));
    }

    /** Reproduce el caso real: boleta con un pago del 2025-09-04, emision movida a 2026-01-01. */
    public function testLaEmisionNoPuedeQuedarDespuesDelPrimerPago(): void
    {
        $boleta = ['primer_pago' => '2025-09-04'];

        self::assertFalse(CobrosController::emisionNoPosteriorAlPrimerPago('2026-01-01', $boleta));
        self::assertTrue(CobrosController::emisionNoPosteriorAlPrimerPago('2025-09-04', $boleta));
        self::assertTrue(CobrosController::emisionNoPosteriorAlPrimerPago('2025-09-01', $boleta));
    }
}

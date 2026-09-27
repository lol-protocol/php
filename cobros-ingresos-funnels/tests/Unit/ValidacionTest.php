<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Validacion;
use PHPUnit\Framework\TestCase;

final class ValidacionTest extends TestCase
{
    public function testMontoNormalEstaEnRango(): void
    {
        self::assertTrue(Validacion::montoEnRango(0.01));
        self::assertTrue(Validacion::montoEnRango(1500.50));
        self::assertTrue(Validacion::montoEnRango(Validacion::MONTO_MAXIMO));
    }

    /** NUMERIC(14, 2) lo redondea a 0.00 y viola el CHECK (monto > 0): era un 500. */
    public function testMontoQueRedondeaACeroNoEstaEnRango(): void
    {
        self::assertFalse(Validacion::montoEnRango(0.004));
        self::assertFalse(Validacion::montoEnRango(0.0));
        self::assertFalse(Validacion::montoEnRango(-5.0));
    }

    /** Desborda NUMERIC(14, 2): tambien era un 500. */
    public function testMontoGiganteOInfinitoNoEstaEnRango(): void
    {
        self::assertFalse(Validacion::montoEnRango(1e12));
        self::assertFalse(Validacion::montoEnRango(INF));
        self::assertFalse(Validacion::montoEnRango(NAN));
    }

    public function testFaltanCamposUsaElRangoDeMonto(): void
    {
        self::assertFalse(Validacion::faltanCampos(['x'], 10.0));
        self::assertTrue(Validacion::faltanCampos(['x'], 0.004));
        self::assertTrue(Validacion::faltanCampos(['x'], INF));
        self::assertTrue(Validacion::faltanCampos([' '], 10.0));
        self::assertFalse(Validacion::faltanCampos(['x']));
    }
}

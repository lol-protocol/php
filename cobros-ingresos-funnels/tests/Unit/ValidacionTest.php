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

    /** Las columnas son TEXT: sin tope se guardaba un nombre de 100.000 caracteres. */
    public function testUnTextoQueSePasaDeSuLargoSeRechazaConSuMensaje(): void
    {
        self::assertNull(Validacion::primerTextoLargo([['El nombre', str_repeat('a', Validacion::MAX_NOMBRE), Validacion::MAX_NOMBRE]]), 'justo en el maximo entra');
        self::assertSame(
            'El nombre no puede superar los 120 caracteres.',
            Validacion::primerTextoLargo([['El nombre', str_repeat('a', 121), Validacion::MAX_NOMBRE]])
        );
    }

    public function testElMensajeEsElDelPrimerCampoQueSePasa(): void
    {
        $mensaje = Validacion::primerTextoLargo([
            ['El nombre', 'Ana', Validacion::MAX_NOMBRE],
            ['La ciudad', str_repeat('c', Validacion::MAX_CIUDAD + 1), Validacion::MAX_CIUDAD],
            ['El idioma', str_repeat('i', Validacion::MAX_IDIOMA + 1), Validacion::MAX_IDIOMA],
        ]);

        self::assertSame('La ciudad no puede superar los 100 caracteres.', $mensaje);
    }

    /** Se cuentan caracteres, no bytes: 120 "ñ" son 240 bytes y tienen que entrar. */
    public function testElLargoSeCuentaEnCaracteresNoEnBytes(): void
    {
        self::assertNull(Validacion::primerTextoLargo([['El nombre', str_repeat('ñ', 120), 120]]));
        self::assertNotNull(Validacion::primerTextoLargo([['El nombre', str_repeat('ñ', 121), 120]]));
    }

    public function testElEmailSeValidaEnElServidor(): void
    {
        foreach (['ana@example.com', 'ana.perez+facturas@sub.example.com.ar'] as $valido) {
            self::assertTrue(Validacion::emailEsValido($valido), $valido);
        }
        foreach (['', 'ana', 'ana@', '@example.com', 'ana@@example.com', 'ana perez@example.com', 'ana@example'] as $invalido) {
            self::assertFalse(Validacion::emailEsValido($invalido), "'{$invalido}'");
        }
    }

    public function testFechaEsValidaAceptaFechasReales(): void
    {
        self::assertTrue(Validacion::fechaEsValida('2026-01-15'));
        self::assertTrue(Validacion::fechaEsValida('2024-02-29'), '2024 es bisiesto');
    }

    public function testFechaEsValidaRechazaTextoSuelto(): void
    {
        self::assertFalse(Validacion::fechaEsValida('esto-no-es-una-fecha'));
        self::assertFalse(Validacion::fechaEsValida(''));
    }

    public function testFechaEsValidaRechazaFechasImposiblesAunqueTenganElFormatoCorrecto(): void
    {
        self::assertFalse(Validacion::fechaEsValida('2026-13-40'), 'mes 13 no existe');
        self::assertFalse(Validacion::fechaEsValida('2025-02-29'), '2025 no es bisiesto');
    }
}

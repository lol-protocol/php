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

    /** El yen o el won no tienen fraccion: 100.50 JPY no existe. Las de 2 y 3 decimales no se tocan. */
    public function testUnaMonedaSinDecimalesSoloAdmiteMontosEnteros(): void
    {
        self::assertTrue(Validacion::montoCabeEnLosDecimales(1500.0, 0));
        self::assertTrue(Validacion::montoCabeEnLosDecimales(1.0, 0));
        self::assertFalse(Validacion::montoCabeEnLosDecimales(1500.5, 0));
        self::assertFalse(Validacion::montoCabeEnLosDecimales(0.01, 0));
        self::assertTrue(Validacion::montoCabeEnLosDecimales(1500.5, 2));
        self::assertTrue(Validacion::montoCabeEnLosDecimales(1500.55, 2));
    }

    /** 0.1 + 0.2 + 0.7 da 1.0000000000000002 en flotante: se compara en centavos, no con == sobre el float. */
    public function testElRuidoDeLaComaFlotanteNoHaceFraccionario(): void
    {
        self::assertTrue(Validacion::montoCabeEnLosDecimales(0.1 + 0.2 + 0.7, 0));
        self::assertTrue(Validacion::montoCabeEnLosDecimales(round(602.0 * 1.1 / 1.1, 2), 0));
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

    public function testFechaNoEsFuturaAceptaHoyYElPasadoPeroNoManana(): void
    {
        self::assertTrue(Validacion::fechaNoEsFutura('2026-01-15', '2026-06-15'));
        self::assertTrue(Validacion::fechaNoEsFutura('2026-06-15', '2026-06-15'), 'hoy vale');
        self::assertFalse(Validacion::fechaNoEsFutura('2026-06-16', '2026-06-15'), 'mañana no');
        self::assertFalse(Validacion::fechaNoEsFutura('2999-01-01'), 'sin $hoy se usa el dia real');
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

    public function testElMinimoDeUnMontoEsLaUnidadMenorDeLaMoneda(): void
    {
        self::assertTrue(Validacion::montoEnRango(0.001, 3), 'una milesima de dinar');
        self::assertFalse(Validacion::montoEnRango(0.0004, 3));
        self::assertFalse(Validacion::montoEnRango(0.001, 2), 'en dolares una milesima no existe');
        self::assertFalse(Validacion::montoEnRango(0.001), 'por defecto, dos decimales');
        self::assertTrue(Validacion::montoEnRango(0.01, 0), 'la moneda sin decimales deja el rechazo a montoCabeEnLosDecimales()');
        self::assertTrue(Validacion::montoEnRango(Validacion::MONTO_MAXIMO, 3));
        self::assertFalse(Validacion::montoEnRango(Validacion::MONTO_MAXIMO + 1, 3));
    }

    public function testElTopeEsElDeLaColumnaConTresDecimales(): void
    {
        self::assertSame(999_999_999_999.999, Validacion::MONTO_MAXIMO);
    }
}

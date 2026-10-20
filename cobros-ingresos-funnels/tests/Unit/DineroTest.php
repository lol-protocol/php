<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dinero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class DineroTest extends TestCase
{
    public function testPasaUnMontoACentavosEnteros(): void
    {
        self::assertSame(60285, Dinero::centavos(602.85));
        self::assertSame(1, Dinero::centavos(0.01));
        self::assertSame(0, Dinero::centavos(0.0));
        self::assertSame(-1, Dinero::centavos(-0.01), 'un saldo negativo (sobrecobro) conserva su signo');
        self::assertSame(1200, Dinero::centavos(12));
    }

    public function testElTopeDeLaColumnaCabeSinPerderPrecision(): void
    {
        // NUMERIC(14, 2): Validacion::MONTO_MAXIMO es el mayor monto que acepta la base.
        self::assertSame(99_999_999_999_999, Dinero::centavos(999_999_999_999.99));
    }

    public function testAceptaElTextoQueDevuelveLaBase(): void
    {
        self::assertSame(60285, Dinero::centavos('602.85'));
        self::assertSame(10000, Dinero::centavos('100.00'));
        self::assertSame(5, Dinero::centavos('0.05'));
    }

    /** 0.57 * 100 da 56.99999999999999: truncar a entero perderia un centavo. */
    public function testRedondeaAlCentavoEnVezDeTruncar(): void
    {
        self::assertSame(57, Dinero::centavos(0.57));
        self::assertSame(113, Dinero::centavos(1.13));
    }

    public function testAbsorbeElRuidoDeUnaSumaDeFloats(): void
    {
        self::assertNotSame(0.3, 0.1 + 0.2, 'el float arrastra ruido...');
        self::assertSame(30, Dinero::centavos(0.1 + 0.2), '...y los centavos no');

        // En float, 807751.45 + 562824.39 da 1370575.8399999999 y no 1370575.84.
        self::assertLessThan(1370575.84, 807751.45 + 562824.39);
        self::assertSame(137_057_584, Dinero::centavos(807751.45 + 562824.39));
    }

    /** Sumar en centavos da el mismo resultado que la suma decimal exacta, con montos de la base de sobra. */
    public function testSumarEnCentavosEsExactoParaMuchosPares(): void
    {
        $azar = new Randomizer(new Mt19937(2026)); // semilla propia: no toca el mt_rand global
        for ($i = 0; $i < 20_000; $i++) {
            $a = $azar->getInt(1, 99_999_999) / 100;
            $b = $azar->getInt(1, 99_999_999) / 100;

            self::assertSame(
                Dinero::centavos($a) + Dinero::centavos($b),
                Dinero::centavos(round($a + $b, 2)),
                "{$a} + {$b}"
            );
        }
    }

    public function testUnCentavoOMenosCuentaComoCero(): void
    {
        self::assertSame(1, Dinero::centavos(Dinero::UN_CENTAVO), 'la constante es de verdad un centavo');

        foreach ([0.0, 0.004, Dinero::UN_CENTAVO, -3.5] as $monto) {
            self::assertTrue(Dinero::cuentaComoCero($monto), "{$monto} cuenta como cero");
        }
        foreach ([0.02, 1.0, 602.85] as $monto) {
            self::assertFalse(Dinero::cuentaComoCero($monto), "{$monto} no cuenta como cero");
        }
    }

    /** 0.1 + 0.2 - 0.29 da 0.010000000000000009: en float "pasa" de 0.01, en centavos es exactamente un centavo. */
    public function testElRuidoDeUnaCuentaNoSacaAUnCentavoDeLaRegla(): void
    {
        self::assertGreaterThan(Dinero::UN_CENTAVO, 0.1 + 0.2 - 0.29, 'el float arrastra ruido...');
        self::assertTrue(Dinero::cuentaComoCero(0.1 + 0.2 - 0.29), '...y la regla no');
    }

    public function testUnMontoInfinitoEsUnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dinero::centavos(INF);
    }

    public function testUnMontoNanEsUnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dinero::centavos(NAN);
    }
}

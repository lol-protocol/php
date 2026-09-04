<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EstadoBoleta;
use PHPUnit\Framework\TestCase;

final class EstadoBoletaTest extends TestCase
{
    public function testSinPagosYNoVencidaEsPendiente(): void
    {
        $r = EstadoBoleta::calcular(1000.0, 0.0, '2026-12-31', '2026-06-01');
        self::assertSame('pendiente', $r['estado']);
        self::assertSame(1000.0, $r['saldo']);
    }

    public function testPagoTotalEsPagadaAunqueEsteVencida(): void
    {
        // Pagar todo antes o despues del vencimiento siempre cierra la boleta.
        $r = EstadoBoleta::calcular(1000.0, 1000.0, '2026-01-01', '2026-06-01');
        self::assertSame('pagada', $r['estado']);
        self::assertSame(0.0, $r['saldo']);
    }

    public function testPagoParcialAntesDeVencerEsParcial(): void
    {
        $r = EstadoBoleta::calcular(1000.0, 400.0, '2026-12-31', '2026-06-01');
        self::assertSame('parcial', $r['estado']);
        self::assertEqualsWithDelta(600.0, $r['saldo'], 0.001);
    }

    public function testSinPagosYVencidaEsVencida(): void
    {
        $r = EstadoBoleta::calcular(1000.0, 0.0, '2026-05-01', '2026-06-01');
        self::assertSame('vencida', $r['estado']);
    }

    public function testPagoParcialYVencidaEsVencidaNoParcial(): void
    {
        // Estar vencida pesa mas que un pago parcial: hay saldo abierto y ya paso la fecha.
        $r = EstadoBoleta::calcular(1000.0, 400.0, '2026-05-01', '2026-06-01');
        self::assertSame('vencida', $r['estado']);
    }

    public function testVenceHoyTodaviaNoEstaVencida(): void
    {
        $r = EstadoBoleta::calcular(1000.0, 0.0, '2026-06-01', '2026-06-01');
        self::assertSame('pendiente', $r['estado']);
    }

    public function testSaldoResidualPorRedondeoSeConsideraPagada(): void
    {
        // Diferencias de centavos por redondeo de moneda no deben quedar "pendientes" para siempre.
        $r = EstadoBoleta::calcular(1000.0, 999.995, '2026-12-31', '2026-06-01');
        self::assertSame('pagada', $r['estado']);
    }

    public function testSobrepagoNuncaDaSaldoNegativoVisible(): void
    {
        $r = EstadoBoleta::calcular(1000.0, 1200.0, '2026-12-31', '2026-06-01');
        self::assertSame('pagada', $r['estado']);
        self::assertEqualsWithDelta(-200.0, $r['saldo'], 0.001);
    }
}

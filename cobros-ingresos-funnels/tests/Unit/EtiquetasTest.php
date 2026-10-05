<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Etiquetas;
use PHPUnit\Framework\TestCase;

final class EtiquetasTest extends TestCase
{
    public function testEstadoBoletaTraduceLosCincoEstados(): void
    {
        self::assertSame('Pagada', Etiquetas::estadoBoleta('pagada'));
        self::assertSame('Pendiente', Etiquetas::estadoBoleta('pendiente'));
        self::assertSame('Parcial', Etiquetas::estadoBoleta('parcial'));
        self::assertSame('Vencida', Etiquetas::estadoBoleta('vencida'));
        self::assertSame('Anulada', Etiquetas::estadoBoleta('anulada'));
    }

    public function testMetodoPagoTraduceLosTresMetodos(): void
    {
        self::assertSame('Transferencia', Etiquetas::metodoPago('transferencia'));
        self::assertSame('Tarjeta', Etiquetas::metodoPago('tarjeta'));
        self::assertSame('Efectivo', Etiquetas::metodoPago('efectivo'));
    }

    public function testCanalTraduceLosCincoCanales(): void
    {
        self::assertSame('Organico', Etiquetas::canal('organico'));
        self::assertSame('Ads', Etiquetas::canal('ads'));
        self::assertSame('Referido', Etiquetas::canal('referido'));
        self::assertSame('Redes sociales', Etiquetas::canal('redes_sociales'));
        self::assertSame('Email', Etiquetas::canal('email'));
    }

    /** Un valor fuera del mapa se muestra tal cual en vez de romper o quedar en blanco. */
    public function testUnValorDesconocidoDevuelveElValorCrudo(): void
    {
        self::assertSame('otro', Etiquetas::estadoBoleta('otro'));
        self::assertSame('otro', Etiquetas::metodoPago('otro'));
        self::assertSame('otro', Etiquetas::canal('otro'));
    }

    public function testLosGettersDeListaTraenTodasLasClaves(): void
    {
        self::assertSame(['pagada', 'pendiente', 'parcial', 'vencida', 'anulada'], array_keys(Etiquetas::estadosBoleta()));
        self::assertSame(['transferencia', 'tarjeta', 'efectivo'], array_keys(Etiquetas::metodosPago()));
    }
}

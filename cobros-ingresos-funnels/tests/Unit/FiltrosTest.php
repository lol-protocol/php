<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Filtros;
use PHPUnit\Framework\TestCase;

final class FiltrosTest extends TestCase
{
    private const ESTADOS = ['pendientes' => 'Pendientes', 'devueltas' => 'Devueltas', 'todas' => 'Todas'];

    protected function setUp(): void
    {
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testTextoQuitaLosEspaciosDeLosBordesYDejaLosDeAdentro(): void
    {
        $_GET['cliente'] = "  Ana  María \t";

        self::assertSame('Ana  María', Filtros::texto('cliente'));
    }

    public function testTextoAusenteEsVacio(): void
    {
        self::assertSame('', Filtros::texto('cliente'));
    }

    public function testOpcionDevuelveLaPedidaSiEsDeLaLista(): void
    {
        $_GET['estado'] = 'devueltas';

        self::assertSame('devueltas', Filtros::opcion('estado', self::ESTADOS));
    }

    public function testOpcionSinPedidoOInexistenteCaeEnLaPrimeraDeLaLista(): void
    {
        self::assertSame('pendientes', Filtros::opcion('estado', self::ESTADOS), 'sin pedido');

        $_GET['estado'] = 'inventado';
        self::assertSame('pendientes', Filtros::opcion('estado', self::ESTADOS), 'inexistente');
    }

    public function testOpcionUsaElValorPorDefectoQueSeLeDa(): void
    {
        $_GET['estado'] = 'inventado';

        self::assertSame('todas', Filtros::opcion('estado', self::ESTADOS, 'todas'));
        self::assertSame('', Filtros::opcion('estado', self::ESTADOS, ''), "'' es «todos» en el filtro de estado de Cobros");
    }

    public function testUnaClaveDeOtraListaNoVale(): void
    {
        $_GET['orden'] = 'devueltas';

        self::assertSame('vencido', Filtros::opcion('orden', ['vencido' => 'Vencido', 'saldo' => 'Saldo']));
        self::assertTrue(Filtros::opcionInexistente('orden', ['vencido' => 'Vencido', 'saldo' => 'Saldo']));
    }

    public function testOpcionInexistenteSoloSiSePidioAlgoQueNoEsDeLaLista(): void
    {
        self::assertFalse(Filtros::opcionInexistente('estado', self::ESTADOS), 'no se pidio nada');

        $_GET['estado'] = '';
        self::assertFalse(Filtros::opcionInexistente('estado', self::ESTADOS), 'vacio es no haber pedido nada');

        $_GET['estado'] = 'todas';
        self::assertFalse(Filtros::opcionInexistente('estado', self::ESTADOS), 'una de la lista');

        $_GET['estado'] = 'inventado';
        self::assertTrue(Filtros::opcionInexistente('estado', self::ESTADOS), 'una inventada');
    }

    public function testElPedidoSeComparaPorLaClaveExactaSinMayusculasNiEspacios(): void
    {
        foreach (['Pendientes', ' pendientes', 'pendientes '] as $pedido) {
            $_GET['estado'] = $pedido;
            self::assertTrue(Filtros::opcionInexistente('estado', self::ESTADOS), "«{$pedido}»");
        }
    }
}

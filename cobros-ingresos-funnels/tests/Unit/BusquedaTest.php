<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Busqueda;
use PHPUnit\Framework\TestCase;

final class BusquedaTest extends TestCase
{
    public function testUnTextoSinComodinesQuedaEntreLosDosPorcentajes(): void
    {
        self::assertSame('%garcia%', Busqueda::contiene('garcia'));
        self::assertSame('%María José%', Busqueda::contiene('María José'));
        self::assertSame('%%', Busqueda::contiene(''));
    }

    public function testElPorcentajeElGuionBajoYLaBarraDelTextoSeEscapan(): void
    {
        self::assertSame('%50\%%', Busqueda::contiene('50%'));
        self::assertSame('%a\_b%', Busqueda::contiene('a_b'));
        self::assertSame('%a\\\\b%', Busqueda::contiene('a\\b'));
        self::assertSame('%\%\_\\\\%', Busqueda::contiene('%_\\'));
    }

    public function testSinTextoElFiltroNoAgregaNadaALaConsulta(): void
    {
        self::assertSame(['', []], Busqueda::filtro(null, ['c.nombre'], 'cliente', ' AND '));
        self::assertSame(['', []], Busqueda::filtro('', ['c.nombre'], 'cliente', ' AND '));
    }

    public function testElFiltroDeUnaColumnaUsaElConectorYElParametroDados(): void
    {
        self::assertSame(
            [' AND (c.nombre ILIKE :cliente)', [':cliente' => '%garcia%']],
            Busqueda::filtro('garcia', ['c.nombre'], 'cliente', ' AND '),
        );
    }

    public function testElFiltroDeVariasColumnasLasUneConOrBajoUnSoloParametro(): void
    {
        self::assertSame(
            [' WHERE (c.nombre ILIKE :q OR c.email ILIKE :q)', [':q' => '%50\%%']],
            Busqueda::filtro('50%', ['c.nombre', 'c.email'], 'q', ' WHERE '),
        );
    }
}

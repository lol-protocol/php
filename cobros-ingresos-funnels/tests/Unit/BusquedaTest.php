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
}

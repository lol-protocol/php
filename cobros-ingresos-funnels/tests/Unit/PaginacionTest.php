<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Paginacion;
use PHPUnit\Framework\TestCase;

final class PaginacionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['pagina']);
    }

    public function testPaginaPorDefectoEs1(): void
    {
        unset($_GET['pagina']);
        self::assertSame(1, Paginacion::pagina());
    }

    public function testPaginaLeeElParametroDeLaQuery(): void
    {
        $_GET['pagina'] = '3';
        self::assertSame(3, Paginacion::pagina());
    }

    public function testPaginaNuncaBajaDe1(): void
    {
        $_GET['pagina'] = '0';
        self::assertSame(1, Paginacion::pagina());

        $_GET['pagina'] = '-5';
        self::assertSame(1, Paginacion::pagina());
    }

    public function testOffsetEsCeroEnLaPrimeraPagina(): void
    {
        self::assertSame(0, Paginacion::offset(1));
    }

    public function testOffsetAvanzaDeAPorPagina(): void
    {
        self::assertSame(Paginacion::POR_PAGINA, Paginacion::offset(2));
        self::assertSame(Paginacion::POR_PAGINA * 2, Paginacion::offset(3));
    }

    public function testTotalPaginasRedondeaHaciaArriba(): void
    {
        self::assertSame(1, Paginacion::totalPaginas(1));
        self::assertSame(1, Paginacion::totalPaginas(Paginacion::POR_PAGINA));
        self::assertSame(2, Paginacion::totalPaginas(Paginacion::POR_PAGINA + 1));
    }

    public function testTotalPaginasNuncaBajaDe1AunqueNoHayaFilas(): void
    {
        self::assertSame(1, Paginacion::totalPaginas(0));
    }
}

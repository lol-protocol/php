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

    /**
     * Reproduce el caso real: "?pagina=5000" sobre cuatro paginas de datos
     * devolvia una tabla vacia rotulada "Pagina 5000 de 4", con el unico link
     * de vuelta apuntando a la 4999.
     */
    public function testAcotarRecortaLaPaginaALaUltimaQueExiste(): void
    {
        $cuatroPaginas = Paginacion::POR_PAGINA * 4;

        self::assertSame(4, Paginacion::acotar(5000, $cuatroPaginas));
        self::assertSame(3, Paginacion::acotar(3, $cuatroPaginas));
        self::assertSame(1, Paginacion::acotar(-7, $cuatroPaginas));
    }

    public function testAcotarDaLaPagina1CuandoNoHayFilas(): void
    {
        self::assertSame(1, Paginacion::acotar(99, 0));
    }

    /**
     * Sin acotar, ($pagina - 1) * POR_PAGINA sobre un int ya saturado en
     * PHP_INT_MAX se desbordaba a float y tiraba un 500 en las cuatro
     * pantallas paginadas (TypeError en offset(), o bigint out of range en
     * Postgres). Acotado primero, el offset sigue siendo un int usable.
     */
    public function testAcotarEvitaElDesbordeDelOffsetConUnaPaginaAbsurda(): void
    {
        $pagina = Paginacion::acotar((int) '9999999999999999999', Paginacion::POR_PAGINA * 4);

        self::assertSame(4, $pagina);
        self::assertIsInt(Paginacion::offset($pagina));
        self::assertSame(Paginacion::POR_PAGINA * 3, Paginacion::offset($pagina));
    }
}

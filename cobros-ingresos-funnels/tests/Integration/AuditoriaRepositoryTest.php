<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\AuditoriaRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. La auditoria es de
 * solo insercion (no hay metodo de borrado a proposito), asi que estos tests
 * agregan filas de prueba en vez de limpiar antes/despues.
 */
final class AuditoriaRepositoryTest extends TestCase
{
    public function testRegistrarQuedaPrimeroEnElListadoPorSerElMasReciente(): void
    {
        $repo = new AuditoriaRepository();
        $detalle = 'Registro de prueba ' . uniqid();

        $repo->registrar(null, 'crear', 'boleta', 999999, $detalle);

        $filas = $repo->listado(1)['filas'];

        self::assertNotEmpty($filas);
        self::assertSame($detalle, $filas[0]['detalle']);
        self::assertSame('crear', $filas[0]['accion']);
        self::assertSame('boleta', $filas[0]['entidad']);
        self::assertSame('Sistema', $filas[0]['usuario'], 'sin usuario_id asociado debe mostrar Sistema');
    }

    public function testListadoDevuelveTotalYTotalPaginasConsistentesConPaginacion(): void
    {
        $repo = new AuditoriaRepository();
        $repo->registrar(null, 'crear', 'cliente', 999998, 'Fila de prueba ' . uniqid());

        $listado = $repo->listado(1);

        self::assertGreaterThanOrEqual(1, $listado['total']);
        self::assertLessThanOrEqual(\App\Paginacion::POR_PAGINA, count($listado['filas']));
        self::assertSame(\App\Paginacion::totalPaginas($listado['total']), $listado['totalPaginas']);
    }

    public function testPaginaDosNoRepiteFilasDeLaPaginaUno(): void
    {
        $repo = new AuditoriaRepository();
        $pagina1 = $repo->listado(1);

        if ($pagina1['totalPaginas'] < 2) {
            self::markTestSkipped('No hay suficientes filas de auditoria para probar una segunda pagina.');
        }

        $pagina2 = $repo->listado(2);

        self::assertEmpty(
            array_intersect(array_column($pagina1['filas'], 'detalle'), array_column($pagina2['filas'], 'detalle')),
            'paginas distintas no deben repetir filas'
        );
    }
}

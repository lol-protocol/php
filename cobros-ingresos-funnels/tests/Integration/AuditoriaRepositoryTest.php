<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Paginacion;
use App\Repositories\AuditoriaRepository;

/**
 * Corre contra la base configurada por las env vars DB_*. La auditoria es de
 * solo insercion (no hay metodo de borrado a proposito): las filas que agregan
 * estos tests se deshacen con la transaccion de cada uno.
 */
final class AuditoriaRepositoryTest extends IntegracionTestCase
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
        self::assertLessThanOrEqual(Paginacion::POR_PAGINA, count($listado['filas']));
        self::assertSame(Paginacion::totalPaginas($listado['total']), $listado['totalPaginas']);
    }

    /**
     * Este test se salteaba en todas las corridas: dependia de que el seed
     * dejara mas de una pagina de auditoria, y el seed no audita nada, asi
     * que la paginacion de Auditoria nunca se habia probado. Ahora se arma
     * sus propias filas: dos paginas completas, todas con el mismo creado_en
     * (el now() de la transaccion) para que el unico desempate sea el id.
     */
    public function testPaginaDosNoRepiteFilasDeLaPaginaUno(): void
    {
        $repo = new AuditoriaRepository();
        for ($i = 0; $i < 2 * Paginacion::POR_PAGINA; $i++) {
            $repo->registrar(null, 'crear', 'prueba_paginacion', $i, "Fila de paginacion {$i}");
        }

        // El listado no expone el id; entidad#entidad_id es unico entre estas filas.
        $clavesDe = static fn (array $listado): array => array_map(
            static fn (array $fila): string => $fila['entidad'] . '#' . $fila['entidad_id'],
            $listado['filas']
        );
        $pagina1 = $clavesDe($repo->listado(1));
        $pagina2 = $clavesDe($repo->listado(2));

        self::assertCount(Paginacion::POR_PAGINA, $pagina1);
        self::assertCount(Paginacion::POR_PAGINA, $pagina2);
        self::assertEmpty(array_intersect($pagina1, $pagina2), 'paginas distintas no deben repetir filas');
    }

    public function testAuditarDentroDeUnaTransaccionRegistraLaFila(): void
    {
        $detalle = 'Auditoria en transaccion ' . uniqid();

        AuditoriaRepository::auditarComoUsuarioActual('crear', 'prueba', 1, $detalle);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM auditoria WHERE detalle = :detalle');
        $stmt->execute([':detalle' => $detalle]);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }
}

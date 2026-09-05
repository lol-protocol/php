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

        $listado = $repo->listado(5);

        self::assertNotEmpty($listado);
        self::assertSame($detalle, $listado[0]['detalle']);
        self::assertSame('crear', $listado[0]['accion']);
        self::assertSame('boleta', $listado[0]['entidad']);
        self::assertSame('Sistema', $listado[0]['usuario'], 'sin usuario_id asociado debe mostrar Sistema');
    }

    public function testListadoRespetaElLimite(): void
    {
        $repo = new AuditoriaRepository();
        for ($i = 0; $i < 3; $i++) {
            $repo->registrar(null, 'crear', 'cliente', $i, 'Fila de prueba ' . uniqid());
        }

        self::assertCount(2, $repo->listado(2));
    }
}

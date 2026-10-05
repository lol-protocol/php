<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\PaisRepository;

/** Corre contra la base configurada por las env vars DB_* (el seed carga el catalogo de paises). */
final class PaisRepositoryTest extends IntegracionTestCase
{
    public function testExisteSoloParaLosCodigosDelCatalogo(): void
    {
        $repo = new PaisRepository();

        self::assertTrue($repo->existe('AR'));
        self::assertTrue($repo->existe('US'));
        foreach (['', 'ZZ', 'ar', 'ARG', 'ZZZZ', '%', "AR'--"] as $invalido) {
            self::assertFalse($repo->existe($invalido), "'{$invalido}'");
        }
    }

    public function testTodosLosPaisesDelListadoExisten(): void
    {
        $repo = new PaisRepository();

        foreach ($repo->listado() as $pais) {
            self::assertTrue($repo->existe($pais['codigo']), $pais['codigo']);
        }
    }
}

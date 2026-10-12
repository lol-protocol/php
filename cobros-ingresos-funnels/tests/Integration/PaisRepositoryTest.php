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

    public function testLaEdadDeMayoriaSaleDelCatalogo(): void
    {
        $repo = new PaisRepository();

        self::assertSame(['nombre' => 'Argentina', 'mayoria_de_edad' => 18], $repo->mayoriaDeEdad('AR'));
        self::assertSame(['nombre' => 'Tailandia', 'mayoria_de_edad' => 20], $repo->mayoriaDeEdad('TH'));
        self::assertSame(['nombre' => 'Singapur', 'mayoria_de_edad' => 21], $repo->mayoriaDeEdad('SG'));
        foreach (['', 'ZZ', 'ar', "AR'--"] as $invalido) {
            self::assertNull($repo->mayoriaDeEdad($invalido), "'{$invalido}' no es un pais");
        }
    }

    public function testTodosLosPaisesTienenEdadDeMayoria(): void
    {
        $repo = new PaisRepository();

        foreach ($repo->listado() as $pais) {
            $edad = $repo->mayoriaDeEdad($pais['codigo']);
            self::assertNotNull($edad, $pais['codigo']);
            self::assertGreaterThanOrEqual(16, $edad['mayoria_de_edad'], $pais['codigo']);
            self::assertLessThanOrEqual(25, $edad['mayoria_de_edad'], $pais['codigo']);
        }
    }

    public function testElTopeDelSelectorEsLaEdadMasBaja(): void
    {
        self::assertSame(18, (new PaisRepository())->menorMayoriaDeEdad());
    }

    public function testLosPaisesConOtraEdadVanAgrupadosPorEdad(): void
    {
        $porEdad = (new PaisRepository())->conMayoriaDeEdadDistinta();

        self::assertSame([19, 20, 21], array_keys($porEdad), 'de menor a mayor, sin la general');
        self::assertSame(['Argelia', 'Canada', 'Corea del Sur'], $porEdad[19], 'alfabetico');
        self::assertSame(['Tailandia'], $porEdad[20]);
        self::assertSame(['Bahrein', 'Egipto', 'Emiratos Arabes Unidos', 'Honduras', 'Kuwait', 'Singapur'], $porEdad[21]);
    }
}

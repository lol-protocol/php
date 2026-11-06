<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\FusionGrupoRepository;
use App\Repositories\Genealogy\FusionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\SitemapRepository;
use App\Repositories\Genealogy\SupresionRepository;
use App\Support\Database;
use App\Support\Privacidad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * `actualizado_en` is the <lastmod> of a page: it moves when the page's content changes (the merges and the
 * erasure of a persona, which are the writes this codebase makes) and it does not move when only who may see
 * the page changes (marking a persona as alive). A row nobody wrote has none, and the sitemap then says nothing.
 */
class ActualizadoEnTest extends TestCase
{
    private const JOSE = 6128473101;
    private const JUAN = 6128473105;
    private const DUPLICADO = 6128473190;
    private const HIJO_DEL_DUPLICADO = 6128473191;
    private const GARCIA = 582317;
    private const GARCIA_SIN_TILDE = 582399;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private static function fecha(Database $db, string $tabla, int $id): ?string
    {
        $fila = $db->fetchOne("SELECT actualizado_en FROM {$tabla} WHERE id = ?", [$id]);

        return $fila === null || $fila['actualizado_en'] === null ? null : (string)$fila['actualizado_en'];
    }

    #[DataProvider('drivers')]
    public function testNothingHasADateUntilSomeoneWritesIt(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);

        foreach (['personas', 'sucesos', 'registros', 'colecciones', 'grupos', 'organizaciones'] as $tabla) {
            $this->assertSame(0, (int)$db->fetchValue("SELECT COUNT(*) FROM {$tabla} WHERE actualizado_en IS NOT NULL"), $tabla);
        }
    }

    #[DataProvider('drivers')]
    public function testSitemapGivesTheDayOfEachPageAndNothingForARowWithoutOne(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("UPDATE personas SET actualizado_en = '2026-03-04 10:11:12' WHERE id = ?", [self::JOSE]);
        $db->execute("UPDATE colecciones SET actualizado_en = '2026-05-06 23:59:59' WHERE id = 1048293");
        $repo = new SitemapRepository($db, Privacidad::publica());

        $personas = array_column($repo->entradas('10', 1), 'fecha', 'clave');
        $this->assertSame('2026-03-04', $personas[(string)self::JOSE], 'the day, not the time');
        $this->assertNull($personas['6128473104'], 'a row nobody wrote has no date');
        $this->assertSame(['1048293' => '2026-05-06'], array_column($repo->entradas('7', 1), 'fecha', 'clave'));
        $this->assertSame([null], array_unique(array_column($repo->entradas('l', 1), 'fecha')), 'places keep no dates');
    }

    /** A date never brings a hidden page back into the sitemap, nor hides a visible one. */
    #[DataProvider('drivers')]
    public function testTheDateDoesNotChangeWhoIsListed(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $antes = (new SitemapRepository($db, Privacidad::publica()))->claves('10', 1);
        $db->execute("UPDATE personas SET actualizado_en = '2026-01-01 00:00:00'");

        $this->assertSame($antes, (new SitemapRepository($db, Privacidad::publica()))->claves('10', 1));
    }

    #[DataProvider('drivers')]
    public function testMarkingAPersonaAliveDoesNotMoveTheDate(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);

        (new PersonaRepository($db, Privacidad::propietario()))->fijarViva(self::JOSE, true);

        $this->assertNull(self::fecha($db, 'personas', self::JOSE), 'who may see the page is not what the page says');
    }

    #[DataProvider('drivers')]
    public function testMergingPersonasMovesTheDateOfTheOneThatStaysAndOfTheChildren(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, sexo) VALUES (?, 'Juan', 'García', 'M')", [self::DUPLICADO]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, padre_id) VALUES (?, 'Pedro', 'García', ?)", [self::HIJO_DEL_DUPLICADO, self::DUPLICADO]);

        (new FusionRepository($db))->fusionar(self::DUPLICADO, self::JUAN);

        $this->assertNotNull(self::fecha($db, 'personas', self::JUAN));
        $this->assertNotNull(self::fecha($db, 'personas', self::HIJO_DEL_DUPLICADO));
        $this->assertNull(self::fecha($db, 'personas', self::JOSE), 'an unrelated persona does not move');
    }

    #[DataProvider('drivers')]
    public function testMergingGroupsMovesTheDateOfTheOneThatStaysAndOfItsNewMembers(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("INSERT INTO grupos (id, apellido) VALUES (?, 'Garcia')", [self::GARCIA_SIN_TILDE]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, grupo_id) VALUES (6128473192, 'Ana', 'Garcia', ?)", [self::GARCIA_SIN_TILDE]);

        (new FusionGrupoRepository($db))->fusionar(self::GARCIA_SIN_TILDE, self::GARCIA);

        $this->assertNotNull(self::fecha($db, 'grupos', self::GARCIA));
        $this->assertNotNull(self::fecha($db, 'personas', 6128473192));
        $this->assertNull(self::fecha($db, 'grupos', 582318), 'another group does not move');
        $this->assertNull(self::fecha($db, 'personas', self::JOSE), 'a member of the group that stayed does not move');
    }

    /** What loses something when a persona is erased is read again by a search engine: it is told the page changed. */
    #[DataProvider('drivers')]
    public function testErasingAPersonaMovesTheDateOfThePagesThatLoseSomething(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (6128479801, 'Ella', 'Prueba'), (6128479802, 'Cónyuge', 'Prueba'), (6128479803, 'Ajena', 'Prueba')");
        $db->execute("INSERT INTO personas (id, nombres, apellidos, madre_id) VALUES (6128479804, 'Hijo', 'Prueba', 6128479801)");
        $db->execute("INSERT INTO vinculos (persona_a, persona_b, tipo) VALUES (6128479801, 6128479802, 'conyuge')");
        $db->execute("INSERT INTO organizaciones (id, nombre, tipo) VALUES (10998, 'Parroquia', 'parroquia'), (10997, 'Otra', 'parroquia')");
        $db->execute("INSERT INTO organizacion_miembros (organizacion_id, persona_id, rol) VALUES (10998, 6128479801, 'feligresa')");

        (new SupresionRepository($db))->suprimir(6128479801);

        $this->assertNotNull(self::fecha($db, 'personas', 6128479804), 'her child');
        $this->assertNotNull(self::fecha($db, 'personas', 6128479802), 'her spouse');
        $this->assertNotNull(self::fecha($db, 'organizaciones', 10998), 'the organization she was in');
        $this->assertNull(self::fecha($db, 'personas', 6128479803), 'a persona who had nothing to do with her');
        $this->assertNull(self::fecha($db, 'organizaciones', 10997), 'an organization she was not in');
    }
}

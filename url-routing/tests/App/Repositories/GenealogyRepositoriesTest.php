<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\ColeccionRepository;
use App\Repositories\Genealogy\GrupoRepository;
use App\Repositories\Genealogy\LugarRepository;
use App\Repositories\Genealogy\OrganizacionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\RegistroRepository;
use App\Repositories\Genealogy\SucesoRepository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * Runs against the demo seed (database/genealogy/seeds) on every available
 * engine. Family used throughout:
 *
 *   José (…101) + María (…102)
 *     └ Antonio (…103) + Carmen (…104)
 *         ├ Juan (…105) + Elena (…107)
 *         │   ├ Carlos (…108)
 *         │   └ Lucía (…109)
 *         └ Rosa (…106)
 */
class GenealogyRepositoriesTest extends TestCase
{
    private const JOSE = 6128473101;
    private const ANTONIO = 6128473103;
    private const JUAN = 6128473105;
    private const ROSA = 6128473106;
    private const CARLOS = 6128473108;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private function db(string $driver): Database
    {
        return TestDatabases::seeded($driver, 'genealogy');
    }

    /** @return list<int> */
    private static function ids(array $rows): array
    {
        return array_map(static fn(array $r) => (int)$r['id'], $rows);
    }

    #[DataProvider('drivers')]
    public function testFindPersonaTakesDatesFromEvents(string $driver): void
    {
        $juan = (new PersonaRepository($this->db($driver)))->find(self::JUAN);

        $this->assertSame('Juan', $juan['nombres']);
        $this->assertSame('1925-02-14', $juan['nacimiento']);
        $this->assertNull($juan['defuncion']);
        $this->assertSame('mx/jal/gdl', $juan['lugar_nacimiento']);
        $this->assertSame('García', $juan['grupo_apellido']);
    }

    #[DataProvider('drivers')]
    public function testFindMissingPersonaIsNull(string $driver): void
    {
        $this->assertNull((new PersonaRepository($this->db($driver)))->find(9999999999));
    }

    #[DataProvider('drivers')]
    public function testAscendenciaWalksAllGenerations(string $driver): void
    {
        $rows = (new PersonaRepository($this->db($driver)))->ascendencia(self::CARLOS);

        $porGeneracion = [];
        foreach ($rows as $r) {
            $porGeneracion[(int)$r['generacion']][] = (int)$r['id'];
        }
        ksort($porGeneracion);

        $this->assertSame([
            1 => [6128473105, 6128473107],
            2 => [6128473103, 6128473104],
            3 => [6128473101, 6128473102],
        ], array_map(static function (array $ids) { sort($ids); return $ids; }, $porGeneracion));
    }

    #[DataProvider('drivers')]
    public function testAscendenciaRespectsGenerationCap(string $driver): void
    {
        $rows = (new PersonaRepository($this->db($driver)))->ascendencia(self::CARLOS, 1);

        $this->assertSame([1], array_values(array_unique(array_map(static fn($r) => (int)$r['generacion'], $rows))));
    }

    #[DataProvider('drivers')]
    public function testDescendenciaFromTheRoot(string $driver): void
    {
        $rows = (new PersonaRepository($this->db($driver)))->descendencia(self::JOSE);
        $ids = self::ids($rows);
        sort($ids);

        $this->assertSame([self::ANTONIO, self::JUAN, self::ROSA, self::CARLOS, 6128473109], $ids);
    }

    #[DataProvider('drivers')]
    public function testVinculosCoversEveryRelationKind(string $driver): void
    {
        $rows = (new PersonaRepository($this->db($driver)))->vinculos(self::JUAN);

        $relaciones = [];
        foreach ($rows as $r) {
            $relaciones[$r['relacion']][] = (int)$r['id'];
        }

        $this->assertSame([self::ANTONIO], $relaciones['padre']);
        $this->assertSame([6128473104], $relaciones['madre']);
        $this->assertSame([6128473107], $relaciones['conyuge']);
        $this->assertSame([self::ROSA], $relaciones['hermano']);
        $this->assertEqualsCanonicalizing([self::CARLOS, 6128473109], $relaciones['hijo']);
        $this->assertSame(['padre', 'madre', 'conyuge', 'hermano', 'hijo'], array_keys($relaciones));
    }

    #[DataProvider('drivers')]
    public function testVinculosFindsLinksInBothDirections(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver));

        $deCarlos = array_column($repo->vinculos(self::CARLOS), 'relacion', 'id');
        $deRosa = array_column($repo->vinculos(self::ROSA), 'relacion', 'id');

        $this->assertSame('padrino', $deCarlos[self::ROSA]);
        $this->assertSame('padrino', $deRosa[self::CARLOS]);
    }

    #[DataProvider('drivers')]
    public function testCronologiaIsInDateOrder(string $driver): void
    {
        $rows = (new PersonaRepository($this->db($driver)))->cronologia(self::ANTONIO);

        $this->assertSame(
            ['nacimiento', 'migracion', 'matrimonio', 'defuncion'],
            array_column($rows, 'tipo')
        );
        $this->assertSame('Oviedo', $rows[0]['lugar_nombre']);
    }

    #[DataProvider('drivers')]
    public function testBuscarPersonaIsCaseInsensitive(string $driver): void
    {
        $rows = (new PersonaRepository($this->db($driver)))->buscar('GARCÍA martínez');

        $this->assertSame([self::CARLOS, 6128473109], self::ids($rows));
    }

    #[DataProvider('drivers')]
    public function testBuscarTreatsLikeWildcardsLiterally(string $driver): void
    {
        $this->assertSame([], (new PersonaRepository($this->db($driver)))->buscar('%%%__'));
    }

    #[DataProvider('drivers')]
    public function testSucesoWithParticipantsAndRecords(string $driver): void
    {
        $repo = new SucesoRepository($this->db($driver));

        $this->assertSame('matrimonio', $repo->find(412000006)['tipo']);
        $this->assertEqualsCanonicalizing([self::ANTONIO, 6128473104], self::ids($repo->participantes(412000006)));
        $this->assertSame([81372002], self::ids($repo->registros(412000006)));
    }

    #[DataProvider('drivers')]
    public function testRegistroWithSourceAndEvents(string $driver): void
    {
        $repo = new RegistroRepository($this->db($driver));
        $registro = $repo->find(81372001);

        $this->assertSame('Parroquia de San José de Analco', $registro['organizacion_nombre']);
        $this->assertStringContainsString('foja 112', $registro['fuente']);
        $this->assertSame([412000007], self::ids($repo->sucesos(81372001)));
    }

    #[DataProvider('drivers')]
    public function testColeccionNormalisesBooleanAcrossEngines(string $driver): void
    {
        $repo = new ColeccionRepository($this->db($driver));

        $this->assertTrue($repo->find(1048293)['publica']);
        $this->assertFalse($repo->find(1048294)['publica']);
        $this->assertSame(9, (int)$repo->find(1048293)['total_personas']);
    }

    #[DataProvider('drivers')]
    public function testOnlyPublicColeccionesAreSearchable(string $driver): void
    {
        $repo = new ColeccionRepository($this->db($driver));

        $this->assertSame([1048293], self::ids($repo->buscar('')));
        $this->assertSame([1048294], self::ids($repo->deUsuario(2)));
    }

    #[DataProvider('drivers')]
    public function testGrupoDispersionCountsBirthplaces(string $driver): void
    {
        $rows = (new GrupoRepository($this->db($driver)))->dispersion(582317);

        $this->assertSame(
            ['mx/jal/gdl' => 3, 'es/ast/ovi' => 2, 'mx/jal/tlq' => 1],
            array_map('intval', array_column($rows, 'total', 'lugar_ruta'))
        );
    }

    #[DataProvider('drivers')]
    public function testOrganizacionMembersAndRecords(string $driver): void
    {
        $repo = new OrganizacionRepository($this->db($driver));

        $this->assertEqualsCanonicalizing([self::JUAN, 6128473107], self::ids($repo->miembros(10232)));
        $this->assertSame([81372001], self::ids($repo->registros(10232)));
    }

    #[DataProvider('drivers')]
    public function testLugarIncludesEverythingBelowIt(string $driver): void
    {
        $repo = new LugarRepository($this->db($driver));

        $this->assertSame(['mx', 'mx/jal', 'mx/jal/gdl'], array_column($repo->jerarquia('mx/jal/gdl'), 'ruta'));
        $this->assertSame(['mx/jal/gdl', 'mx/jal/tlq'], array_column($repo->hijos('mx/jal'), 'ruta'));

        $enMexico = self::ids($repo->personas('mx'));
        $enGuadalajara = self::ids($repo->personas('mx/jal/gdl'));

        $this->assertCount(6, $enMexico);
        $this->assertCount(5, $enGuadalajara);
        $this->assertNotContains(self::ROSA, $enGuadalajara, 'Rosa was born in Tlaquepaque');
        $this->assertContains(self::ROSA, $enMexico);
    }

    #[DataProvider('drivers')]
    public function testLugarDoesNotMatchSiblingCodePrefix(string $driver): void
    {
        // "mx/jal" must not pick up a hypothetical "mx/jalx" — the LIKE pattern
        // requires the separator.
        $this->assertSame([], (new LugarRepository($this->db($driver)))->personas('mx/ja'));
    }
}

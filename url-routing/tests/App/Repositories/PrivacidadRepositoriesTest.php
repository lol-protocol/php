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
use App\Support\GedcomExporter;
use App\Support\Privacidad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * What a visitor may see about living people, on every available engine.
 *
 * "Today" is fixed at 2026-10-09, so the cut-off is 1916-10-09 and, in the demo
 * seed, these are presumed alive: Juan (1925), Rosa (1928), Elena (1929),
 * Carlos (1952) and Lucía (1955). José, María, Antonio and Carmen were born
 * earlier. The owner's view is covered by GenealogyRepositoriesTest.
 */
class PrivacidadRepositoriesTest extends TestCase
{
    private const JOSE = 6128473101;
    private const MARIA = 6128473102;
    private const ANTONIO = 6128473103;
    private const CARMEN = 6128473104;
    private const JUAN = 6128473105;
    private const ROSA = 6128473106;
    private const ELENA = 6128473107;
    private const CARLOS = 6128473108;
    private const LUCIA = 6128473109;

    private const VIVAS = [self::JUAN, self::ROSA, self::ELENA, self::CARLOS, self::LUCIA];
    private const FALLECIDAS = [self::JOSE, self::MARIA, self::ANTONIO, self::CARMEN];

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private static function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-09');
    }

    private static function publica(?\DateTimeImmutable $hoy = null): Privacidad
    {
        return Privacidad::publica($hoy ?? self::hoy());
    }

    private function db(string $driver): Database
    {
        return TestDatabases::seeded($driver, 'genealogy');
    }

    /** @return list<int> */
    private static function ids(array $rows): array
    {
        return array_map(static fn(array $r): int => (int)$r['id'], $rows);
    }

    // --- who is presumed alive -------------------------------------------------

    #[DataProvider('drivers')]
    public function testOnlyPeopleBornRecentlyWithNoDeathAreHidden(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver), self::publica());

        foreach (self::VIVAS as $id) {
            $this->assertTrue($repo->find($id)['oculta'], "{$id} should be hidden");
        }
        foreach (self::FALLECIDAS as $id) {
            $this->assertFalse($repo->find($id)['oculta'], "{$id} should be visible");
        }
    }

    /** María and Carmen have no death recorded, but were born over a century ago: nothing says they live. */
    #[DataProvider('drivers')]
    public function testOldPeopleWithoutADeathRecordAreNotPresumedAlive(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver), self::publica());

        $this->assertNull($repo->find(self::MARIA)['defuncion']);
        $this->assertFalse($repo->find(self::MARIA)['oculta']);
        $this->assertFalse($repo->find(self::CARMEN)['oculta']);
    }

    #[DataProvider('drivers')]
    public function testTheCutOffMovesWithTheDate(string $driver): void
    {
        // In 2040 the cut-off is 1930-01-01: Juan, Rosa and Elena are old enough, Carlos and Lucía are not.
        $repo = new PersonaRepository($this->db($driver), self::publica(new \DateTimeImmutable('2040-01-01')));

        foreach ([self::JUAN, self::ROSA, self::ELENA] as $id) {
            $this->assertFalse($repo->find($id)['oculta'], (string)$id);
        }
        foreach ([self::CARLOS, self::LUCIA] as $id) {
            $this->assertTrue($repo->find($id)['oculta'], (string)$id);
        }
    }

    #[DataProvider('drivers')]
    public function testTheBoundaryIsExactlyOneHundredAndTenYearsOfBirthdays(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $limite = self::publica()->umbral();
        $dia = (new \DateTimeImmutable($limite))->modify('+1 day')->format('Y-m-d');

        $this->nacida($db, 6128479001, $limite);
        $this->nacida($db, 6128479002, $dia);
        $repo = new PersonaRepository($db, self::publica());

        $this->assertFalse($repo->find(6128479001)['oculta'], 'born exactly on the cut-off: old enough');
        $this->assertTrue($repo->find(6128479002)['oculta'], 'born one day later: still presumed alive');
    }

    #[DataProvider('drivers')]
    public function testADeathRecordMakesAnyoneVisible(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $this->nacida($db, 6128479003, '2000-01-01');
        $this->suceso($db, 412999003, 'defuncion', '2020-05-05', [6128479003]);

        $this->assertFalse((new PersonaRepository($db, self::publica()))->find(6128479003)['oculta']);
    }

    /** Nothing says an undated persona lives, and hiding every undated ancestor would hollow out the catalog. */
    #[DataProvider('drivers')]
    public function testAPersonaWithNoDatesIsNotPresumedAlive(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (6128479004, 'Sin', 'Fechas')");

        $this->assertFalse((new PersonaRepository($db, self::publica()))->find(6128479004)['oculta']);
    }

    // --- the explicit flag ------------------------------------------------------

    #[DataProvider('drivers')]
    public function testTheVivaFlagOverridesWhatDatesSay(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute('UPDATE personas SET viva = TRUE WHERE id = ?', [self::JOSE]);
        $db->execute('UPDATE personas SET viva = FALSE WHERE id = ?', [self::JUAN]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, viva) VALUES (6128479005, 'Sin', 'Fechas pero viva', TRUE)");
        $repo = new PersonaRepository($db, self::publica());

        $this->assertTrue($repo->find(self::JOSE)['oculta'], 'viva = TRUE hides someone the dates say died in 1941');
        $this->assertFalse($repo->find(self::JUAN)['oculta'], 'viva = FALSE shows someone the dates would hide');
        $this->assertTrue($repo->find(6128479005)['oculta'], 'viva = TRUE hides an undated persona');
        $this->assertTrue($repo->find(self::CARLOS)['oculta'], 'NULL still deduces');
        $this->assertSame('Juan', $repo->find(self::JUAN)['nombres']);
    }

    // --- the persona itself ----------------------------------------------------------

    #[DataProvider('drivers')]
    public function testAHiddenPersonaHasNoIdentifyingData(string $driver): void
    {
        $juan = (new PersonaRepository($this->db($driver), self::publica()))->find(self::JUAN);

        $this->assertSame(Privacidad::NOMBRE_OCULTO, $juan['nombres']);
        $this->assertSame('', $juan['apellidos']);
        foreach (['sexo', 'nacimiento', 'defuncion', 'lugar_nacimiento', 'grupo_id', 'grupo_apellido'] as $campo) {
            $this->assertNull($juan[$campo], $campo);
        }
        // The shape of the tree is kept: it names nobody.
        $this->assertSame(self::ANTONIO, (int)$juan['padre_id']);
        $this->assertSame(self::CARMEN, (int)$juan['madre_id']);
    }

    #[DataProvider('drivers')]
    public function testAVisiblePersonaKeepsEverything(string $driver): void
    {
        $antonio = (new PersonaRepository($this->db($driver), self::publica()))->find(self::ANTONIO);

        $this->assertSame('Antonio', $antonio['nombres']);
        $this->assertSame('García Fernández', $antonio['apellidos']);
        $this->assertSame('M', $antonio['sexo']);
        $this->assertSame('1898-01-20', $antonio['nacimiento']);
        $this->assertSame('1977-01-18', $antonio['defuncion']);
        $this->assertSame('es/ast/ovi', $antonio['lugar_nacimiento']);
        $this->assertSame('García', $antonio['grupo_apellido']);
    }

    #[DataProvider('drivers')]
    public function testTheOwnerSeesEveryoneUnmasked(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver), Privacidad::propietario());

        foreach ([...self::VIVAS, ...self::FALLECIDAS] as $id) {
            $this->assertFalse($repo->find($id)['oculta'], (string)$id);
        }
        $this->assertSame('Carlos', $repo->find(self::CARLOS)['nombres']);
        $this->assertSame('1952-03-22', $repo->find(self::CARLOS)['nacimiento']);
    }

    /** A caller that forgets to say who is asking must get the visitor's view, not the owner's. */
    #[DataProvider('drivers')]
    public function testTheDefaultViewIsTheVisitors(string $driver): void
    {
        $this->assertTrue((new PersonaRepository($this->db($driver)))->find(self::CARLOS)['oculta']);
    }

    // --- searching and listing -------------------------------------------------------------

    /** Matching a living person by name, even to show a hidden row, would let anyone test whether they exist. */
    #[DataProvider('drivers')]
    public function testSearchNeverFindsLivingPeople(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver), self::publica());

        $this->assertSame([], $repo->buscar('carlos'));
        $this->assertSame([], $repo->buscar('García Martínez'));
        $this->assertSame([], $repo->buscar('lucía'));
        $this->assertSame([], $repo->buscar('persona viva'), 'the mask must not be searchable either');
        $this->assertEqualsCanonicalizing([self::JOSE, self::ANTONIO], self::ids($repo->buscar('garcía')));
        $this->assertEqualsCanonicalizing(self::FALLECIDAS, self::ids($repo->buscar('')));
    }

    #[DataProvider('drivers')]
    public function testDescendenciaKeepsTheShapeOfTheTreeButNamesNobodyAlive(string $driver): void
    {
        $filas = (new PersonaRepository($this->db($driver), self::publica()))->descendencia(self::JOSE);
        $porId = array_column($filas, null, 'id');

        $this->assertEqualsCanonicalizing(
            [self::ANTONIO, self::JUAN, self::ROSA, self::CARLOS, self::LUCIA],
            self::ids($filas)
        );
        $this->assertSame('Antonio', $porId[self::ANTONIO]['nombres']);
        foreach ([self::JUAN, self::ROSA, self::CARLOS, self::LUCIA] as $id) {
            $this->assertSame(Privacidad::NOMBRE_OCULTO, $porId[$id]['nombres']);
            $this->assertNull($porId[$id]['nacimiento']);
            $this->assertTrue($porId[$id]['oculta']);
        }
        // Hidden rows come last in their generation and in id order, so the order says nothing about them.
        $segunda = array_values(array_filter($filas, static fn(array $f): bool => (int)$f['generacion'] === 2));
        $this->assertSame([self::JUAN, self::ROSA], self::ids($segunda));
    }

    #[DataProvider('drivers')]
    public function testVinculosMaskLivingRelativesOfAVisiblePersona(string $driver): void
    {
        $filas = (new PersonaRepository($this->db($driver), self::publica()))->vinculos(self::ANTONIO);
        $hijos = array_values(array_filter($filas, static fn(array $f): bool => $f['relacion'] === 'hijo'));
        $padres = array_values(array_filter($filas, static fn(array $f): bool => $f['relacion'] === 'padre'));

        $this->assertEqualsCanonicalizing([self::JUAN, self::ROSA], self::ids($hijos));
        foreach ($hijos as $hijo) {
            $this->assertSame(Privacidad::NOMBRE_OCULTO, $hijo['nombres']);
            $this->assertNull($hijo['nacimiento']);
        }
        $this->assertSame('José', $padres[0]['nombres']);
    }

    // --- events and records ------------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testAnEventWithALivingParticipantDoesNotExist(string $driver): void
    {
        $repo = new SucesoRepository($this->db($driver), self::publica());

        $this->assertNull($repo->find(412000007), 'the birth of Juan');
        $this->assertNull($repo->find(412000010), 'the birth of Carlos');
        $this->assertSame('matrimonio', $repo->find(412000006)['tipo'], 'Antonio and Carmen married long ago');
        $this->assertEqualsCanonicalizing(
            [412000001, 412000002, 412000003, 412000004, 412000005, 412000006, 412000012, 412000013],
            self::ids($repo->buscar(''))
        );
        $this->assertSame([], $repo->buscar('1952'), 'nor can it be found by what it says');
    }

    #[DataProvider('drivers')]
    public function testAnEventSharedWithAPersonWhoLivesIsHiddenFromTheDeceasedToo(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $this->suceso($db, 412999001, 'bautizo', '1990-01-01', [self::ANTONIO, self::CARLOS]);
        $repo = new PersonaRepository($db, self::publica());

        $eventos = array_column($repo->cronologia(self::ANTONIO), 'id');

        $this->assertNotContains(412999001, array_map('intval', $eventos));
        $this->assertContains(412000013, array_map('intval', $eventos), 'his own death stays');
        $this->assertContains(412999001, array_map('intval', array_column((new PersonaRepository($db, Privacidad::propietario()))->cronologia(self::ANTONIO), 'id')));
    }

    #[DataProvider('drivers')]
    public function testACronologiaOfALivingPersonaIsEmpty(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver), self::publica());

        foreach (self::VIVAS as $id) {
            $this->assertSame([], $repo->cronologia($id), (string)$id);
        }
        $this->assertCount(4, $repo->cronologia(self::ANTONIO));
    }

    /** A record's title and source usually name the person it documents. */
    #[DataProvider('drivers')]
    public function testARecordThatDocumentsALivingPersonDoesNotExist(string $driver): void
    {
        $db = $this->db($driver);
        $repo = new RegistroRepository($db, self::publica());

        $this->assertNull($repo->find(81372001), 'the baptism of Juan');
        $this->assertSame('acta', $repo->find(81372002)['tipo']);
        $this->assertEqualsCanonicalizing([81372002, 81372003], self::ids($repo->buscar('')));
        $this->assertSame([], $repo->buscar('Juan'));
        $this->assertSame([], (new SucesoRepository($db, self::publica()))->registros(412000007));
        $this->assertSame([81372002], self::ids((new SucesoRepository($db, self::publica()))->registros(412000006)));
    }

    #[DataProvider('drivers')]
    public function testOrganizationsListNeitherLivingMembersNorHiddenRecords(string $driver): void
    {
        $repo = new OrganizacionRepository($this->db($driver), self::publica());

        $this->assertSame([], $repo->miembros(10232), 'Juan and Elena are its only members');
        $this->assertSame([], $repo->registros(10232), 'its only record is the baptism of Juan');
        $this->assertSame([81372002], self::ids($repo->registros(10231)));
    }

    // --- places, surnames, trees -----------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testPlacesLeaveOutLivingPeopleAndTheirEvents(string $driver): void
    {
        $repo = new LugarRepository($this->db($driver), self::publica());

        $this->assertSame([self::CARMEN], self::ids($repo->personas('mx/jal/gdl')), 'Juan and Elena were born here too');
        $this->assertSame([], $repo->personas('mx/jal/tlq'), 'Rosa was born in Tlaquepaque');
        $this->assertEqualsCanonicalizing(
            [412000004, 412000005, 412000006, 412000013],
            self::ids($repo->sucesos('mx/jal'))
        );
    }

    #[DataProvider('drivers')]
    public function testSurnamePagesCountOnlyPeopleWhoAreVisible(string $driver): void
    {
        $repo = new GrupoRepository($this->db($driver), self::publica());

        $this->assertSame(2, (int)$repo->find(582317)['total_personas'], 'José and Antonio');
        $this->assertEqualsCanonicalizing([self::JOSE, self::ANTONIO], self::ids($repo->red(582317)));
        $this->assertSame(['es/ast/ovi' => 2], array_map('intval', array_column($repo->dispersion(582317), 'total', 'lugar_ruta')));
    }

    #[DataProvider('drivers')]
    public function testATreeKeepsItsHiddenPeopleSoItKeepsItsShape(string $driver): void
    {
        $filas = (new ColeccionRepository($this->db($driver), self::publica()))->personas(1048293);

        $this->assertCount(9, $filas);
        $this->assertSame(self::VIVAS, array_slice(self::ids($filas), -5), 'hidden people last, in id order');
        $this->assertSame(array_fill(0, 5, Privacidad::NOMBRE_OCULTO), array_slice(array_column($filas, 'nombres'), -5));
        $this->assertSame(array_fill(0, 4, false), array_slice(array_column($filas, 'oculta'), 0, 4));
        $carlos = array_column($filas, null, 'id')[self::CARLOS];
        $this->assertSame(self::JUAN, (int)$carlos['padre_id']);
    }

    /** The GEDCOM leaves the site entirely: it must carry nothing about the living but the shape of the tree. */
    #[DataProvider('drivers')]
    public function testTheGedcomOfAVisitorCarriesNothingAboutLivingPeople(string $driver): void
    {
        $personas = (new ColeccionRepository($this->db($driver), self::publica()))->personasParaExportar(1048293);
        $gedcom = (new GedcomExporter())->export($personas, 'Familia García');

        foreach (['Carlos', 'Lucía', 'Juan', 'Rosa', 'Elena', 'García Martínez', 'Martínez Soto', '1952', '1955', '1925', '1928', '1929', 'Tlaquepaque'] as $dato) {
            $this->assertStringNotContainsString($dato, $gedcom, $dato);
        }
        $this->assertSame(9, substr_count($gedcom, ' INDI'));
        $this->assertSame(5, substr_count($gedcom, '1 NAME ' . Privacidad::NOMBRE_OCULTO));
        $this->assertStringContainsString('1 NAME José /García Álvarez/', $gedcom);
        $this->assertStringContainsString('2 DATE 20 JAN 1898', $gedcom);

        preg_match_all('/^\d (?:FAMS|FAMC|HUSB|WIFE|CHIL) (@\w+@)/m', $gedcom, $usados);
        preg_match_all('/^0 (@\w+@) /m', $gedcom, $definidos);
        $this->assertSame([], array_values(array_diff(array_unique($usados[1]), $definidos[1])), 'every pointer resolves');
    }

    // --- helpers ------------------------------------------------------------------------------------

    private function nacida(Database $db, int $id, string $fecha): void
    {
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (?, 'Prueba', 'Límite')", [$id]);
        $this->suceso($db, $id - 6128000000 + 412000000 + 5000, 'nacimiento', $fecha, [$id]);
    }

    /** @param list<int> $participantes */
    private function suceso(Database $db, int $id, string $tipo, string $fecha, array $participantes): void
    {
        $db->execute('INSERT INTO sucesos (id, tipo, fecha) VALUES (?, ?, ?)', [$id, $tipo, $fecha]);
        foreach ($participantes as $persona) {
            $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [$id, $persona]);
        }
    }
}

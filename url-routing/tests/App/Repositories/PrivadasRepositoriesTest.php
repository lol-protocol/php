<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\ColeccionRepository;
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
 * What a visitor may see about a persona who is only in private collections.
 *
 * In the demo seed that is Manuel Ruiz Cano (1870-1944), who is in "Borrador de
 * Luis" (1048294, private) and nowhere else. He died long ago, so the only reason
 * to hide him is the collection. Juan and Elena are also in that collection but
 * in the public one too, so they are not private (they are hidden for being
 * presumed alive; see PrivacidadRepositoriesTest).
 */
class PrivadasRepositoriesTest extends TestCase
{
    private const MANUEL = 6128473110;
    private const ANTONIO = 6128473103;
    private const JOSE = 6128473101;

    private const PUBLICA = 1048293;
    private const PRIVADA = 1048294;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private static function visita(): Privacidad
    {
        return Privacidad::publica(new \DateTimeImmutable('2026-10-09'));
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

    // --- who counts as private --------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testAPersonaOnlyInPrivateCollectionsIsHiddenAndNamedAsPrivate(string $driver): void
    {
        $manuel = (new PersonaRepository($this->db($driver), self::visita()))->find(self::MANUEL);

        $this->assertTrue($manuel['oculta']);
        $this->assertSame(Privacidad::NOMBRE_PRIVADO, $manuel['nombres']);
        $this->assertSame('', $manuel['apellidos']);
        $this->assertNull($manuel['sexo']);
        $this->assertNull($manuel['nacimiento']);
        $this->assertNull($manuel['defuncion']);
        $this->assertNull($manuel['lugar_nacimiento']);
    }

    /** She is not alive, so the mask must not say she is. */
    #[DataProvider('drivers')]
    public function testThePrivateMaskIsNotTheLivingMask(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver), self::visita());

        $this->assertNotSame(Privacidad::NOMBRE_OCULTO, $repo->find(self::MANUEL)['nombres']);
        $this->assertSame(Privacidad::NOMBRE_OCULTO, $repo->find(6128473108)['nombres'], 'Carlos is still masked as living');
    }

    #[DataProvider('drivers')]
    public function testTheOwnerSeesHimWhole(string $driver): void
    {
        $manuel = (new PersonaRepository($this->db($driver), Privacidad::propietario()))->find(self::MANUEL);

        $this->assertFalse($manuel['oculta']);
        $this->assertSame('Manuel', $manuel['nombres']);
        $this->assertSame('Ruiz Cano', $manuel['apellidos']);
        $this->assertSame('1870-03-04', $manuel['nacimiento']);
        $this->assertSame('1944-08-19', $manuel['defuncion']);
    }

    /** Elena and Juan are in the private collection and in the public one: one public collection is enough. */
    #[DataProvider('drivers')]
    public function testOneOfSeveralCollectionsBeingPublicIsEnough(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute('INSERT INTO coleccion_personas (coleccion_id, persona_id) VALUES (?, ?)', [self::PRIVADA, self::ANTONIO]);
        $repo = new PersonaRepository($db, self::visita());

        $this->assertFalse($repo->find(self::ANTONIO)['oculta'], 'Antonio is in both');
        $this->assertSame('Antonio', $repo->find(self::ANTONIO)['nombres']);
    }

    /** Nothing says she belongs to anyone, so there is nothing to keep private. */
    #[DataProvider('drivers')]
    public function testAPersonaInNoCollectionIsNotPrivate(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (6128473199, 'Sin', 'Colección')");

        $this->assertFalse((new PersonaRepository($db, self::visita()))->find(6128473199)['oculta']);
    }

    #[DataProvider('drivers')]
    public function testPublishingTheCollectionShowsHim(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute('UPDATE colecciones SET publica = ? WHERE id = ?', [true, self::PRIVADA]);

        $manuel = (new PersonaRepository($db, self::visita()))->find(self::MANUEL);

        $this->assertFalse($manuel['oculta']);
        $this->assertSame('Manuel', $manuel['nombres']);
    }

    /** The other way round: when the only public collection goes private, so does José, who is only in it. */
    #[DataProvider('drivers')]
    public function testMakingTheOnlyPublicCollectionPrivateHidesItsPeople(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $db->execute('UPDATE colecciones SET publica = ? WHERE id = ?', [false, self::PUBLICA]);
        $repo = new PersonaRepository($db, self::visita());

        $this->assertTrue($repo->find(self::JOSE)['oculta']);
        $this->assertSame(Privacidad::NOMBRE_PRIVADO, $repo->find(self::JOSE)['nombres']);
        $this->assertSame([], $repo->buscar(''), 'nobody is left to find');
    }

    // --- search and lists ---------------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testSearchNeverFindsHim(string $driver): void
    {
        $repo = new PersonaRepository($this->db($driver), self::visita());

        foreach (['manuel', 'Ruiz Cano', 'cano', 'persona privada'] as $q) {
            $this->assertSame([], $repo->buscar($q), $q);
        }
        $this->assertNotContains(self::MANUEL, self::ids($repo->buscar('')));
        $this->assertContains(self::MANUEL, self::ids((new PersonaRepository($this->db($driver), Privacidad::propietario()))->buscar('manuel')));
    }

    #[DataProvider('drivers')]
    public function testPlacesAndOrganizationsListNothingAboutHim(string $driver): void
    {
        $db = $this->db($driver);

        $this->assertSame([], self::ids((new OrganizacionRepository($db, self::visita()))->miembros(10232)), 'Juan and Elena are living; Manuel is private');
        $this->assertNotContains(self::MANUEL, self::ids((new LugarRepository($db, self::visita()))->personas('es/ast/ovi')));
        $this->assertContains(self::MANUEL, self::ids((new LugarRepository($db, Privacidad::propietario()))->personas('es/ast/ovi')));
    }

    // --- events and records -------------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testHisEventsDoNotExistForAVisitor(string $driver): void
    {
        $repo = new SucesoRepository($this->db($driver), self::visita());

        $this->assertNull($repo->find(412000014), 'his birth');
        $this->assertNull($repo->find(412000015), 'his death');
        $this->assertNull($repo->find(412000016), 'the trip Antonio made with him');
        $this->assertSame([], $repo->buscar('padrino'), 'nor can they be found by what they say');
    }

    /** Antonio keeps his own events; the one he shared with a private persona is hidden from his timeline. */
    #[DataProvider('drivers')]
    public function testAnEventSharedWithAPrivatePersonaLeavesTheCronologiaOfTheOther(string $driver): void
    {
        $db = $this->db($driver);
        $visitante = array_map('intval', array_column((new PersonaRepository($db, self::visita()))->cronologia(self::ANTONIO), 'id'));
        $dueno = array_map('intval', array_column((new PersonaRepository($db, Privacidad::propietario()))->cronologia(self::ANTONIO), 'id'));

        $this->assertNotContains(412000016, $visitante);
        $this->assertContains(412000013, $visitante, 'his own death stays');
        $this->assertContains(412000016, $dueno);
    }

    #[DataProvider('drivers')]
    public function testHisCronologiaIsEmptyForAVisitor(string $driver): void
    {
        $this->assertSame([], (new PersonaRepository($this->db($driver), self::visita()))->cronologia(self::MANUEL));
    }

    #[DataProvider('drivers')]
    public function testARecordThatDocumentsHimDoesNotExist(string $driver): void
    {
        $db = $this->db($driver);
        $visita = new RegistroRepository($db, self::visita());

        $this->assertNull($visita->find(81372004));
        $this->assertSame([], $visita->buscar('Manuel'));
        $this->assertNotContains(81372004, self::ids($visita->buscar('')));
        $this->assertSame(81372004, (int)(new RegistroRepository($db, Privacidad::propietario()))->find(81372004)['id']);
        $this->assertSame([], (new SucesoRepository($db, self::visita()))->registros(412000015));
    }

    // --- trees ----------------------------------------------------------------------------------

    /** A link from a public tree to him stays, unnamed, so the tree keeps its shape. */
    #[DataProvider('drivers')]
    public function testHeIsAnUnnamedGodfatherInAntoniosVinculos(string $driver): void
    {
        $filas = (new PersonaRepository($this->db($driver), self::visita()))->vinculos(self::ANTONIO);
        $padrino = array_values(array_filter($filas, static fn(array $f): bool => (int)$f['id'] === self::MANUEL));

        $this->assertCount(1, $padrino);
        $this->assertSame(Privacidad::NOMBRE_PRIVADO, $padrino[0]['nombres']);
        $this->assertNull($padrino[0]['nacimiento']);
        $this->assertTrue($padrino[0]['oculta']);
    }

    #[DataProvider('drivers')]
    public function testThePublicTreeHasNothingOfHimAndThePrivateOneIsForTheOwner(string $driver): void
    {
        $repo = new ColeccionRepository($this->db($driver), self::visita());

        $this->assertNotContains(self::MANUEL, self::ids($repo->personas(self::PUBLICA)));
        $this->assertNotContains(1048294, array_map('intval', array_column($repo->buscar(''), 'id')), 'private trees are not listed');
    }

    /** He is in no public tree, so no public GEDCOM can list him; if he were linked from one, the file must carry no data of his. */
    #[DataProvider('drivers')]
    public function testNoGedcomAVisitorCanGetCarriesHisData(string $driver): void
    {
        $repo = new ColeccionRepository($this->db($driver), self::visita());
        $gedcom = (new GedcomExporter())->export($repo->personasParaExportar(self::PUBLICA), 'Familia García');

        foreach (['Manuel', 'Ruiz Cano', '1870', '1944'] as $dato) {
            $this->assertStringNotContainsString($dato, $gedcom, $dato);
        }
    }
}

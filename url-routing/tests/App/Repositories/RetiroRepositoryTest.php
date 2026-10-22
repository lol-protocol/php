<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\RetiroRepository;
use App\Repositories\Genealogy\SitemapRepository;
use App\Support\Privacidad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** Pedidos de retiro write, so each test gets its own seeded database, on SQLite and PostgreSQL. */
class RetiroRepositoryTest extends TestCase
{
    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    #[DataProvider('drivers')]
    public function testAPedidoIsNumberedAndEveryDecisionIsAppended(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $repo = new RetiroRepository($db);

        $primero = $repo->crear('persona', 6128473105, 'me_nombra', 'Es falso.', 'a@example.com');
        $segundo = $repo->crear('grupo', 582317, 'otro', 'Otro.', 'b@example.com');
        $this->assertSame([1, 2], [$primero, $segundo]);
        $this->assertFalse($repo->retirado('persona', 6128473105), 'a new pedido hides nothing');

        $this->assertTrue($repo->decidir($primero, 'ocultar', 'Lo reviso.'));
        $this->assertTrue($repo->retirado('persona', 6128473105));
        $this->assertFalse($repo->retirado('persona', 6128473106), 'only that page');
        $this->assertFalse($repo->decidir(99, 'retirar', null), 'no such pedido');

        $todos = $repo->todos();
        $this->assertSame([$segundo, $primero], array_map('intval', array_column($todos, 'id')), 'pending ones first');
        $this->assertSame(['recibido', 'oculto'], array_column($todos[1]['eventos'], 'estado'));
        $this->assertSame([null, 'Lo reviso.'], array_column($todos[1]['eventos'], 'nota'));

        $this->assertTrue($repo->decidir($primero, 'rechazar', null));
        $this->assertFalse($repo->retirado('persona', 6128473105), 'rejected: shown again');
    }

    #[DataProvider('drivers')]
    public function testOnlyWhatAVisitorSeesCanBeReported(string $driver): void
    {
        $repo = new RetiroRepository(TestDatabases::seeded($driver, 'genealogy'));

        $this->assertTrue($repo->existe('persona', 6128473105));
        $this->assertTrue($repo->existe('registro', 81372002));
        $this->assertFalse($repo->existe('registro', 81372001), 'it would give a living person away');
        $this->assertFalse($repo->existe('persona', 6128473199), 'no such persona');
        $this->assertFalse($repo->existe('lugar', 1), 'not a type that can be reported');
    }

    #[DataProvider('drivers')]
    public function testAWithdrawnPageLeavesAVisitorsSearchButNotTheOwners(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $repo = new RetiroRepository($db);
        $ids = static fn(array $filas): array => array_map('intval', array_column($filas, 'id'));
        $visitante = static fn(string $clase) => new $clase($db, Privacidad::publica());
        $propietario = static fn(string $clase) => new $clase($db, Privacidad::propietario());

        $casos = [
            ['persona', 6128473101, \App\Repositories\Genealogy\PersonaRepository::class, 'josé'],
            ['grupo', 582317, \App\Repositories\Genealogy\GrupoRepository::class, 'garcía'],
            ['registro', 81372002, \App\Repositories\Genealogy\RegistroRepository::class, 'matrimonio'],
            ['organizacion', 10232, \App\Repositories\Genealogy\OrganizacionRepository::class, ''],
            ['coleccion', 1048293, \App\Repositories\Genealogy\ColeccionRepository::class, ''],
            ['suceso', 412000006, \App\Repositories\Genealogy\SucesoRepository::class, 'matrimonio'],
        ];
        foreach ($casos as [$tipo, $id, $clase, $texto]) {
            $this->assertContains($id, $ids($visitante($clase)->buscar($texto)), "{$tipo}: precondition");
            $repo->decidir($repo->crear($tipo, $id, 'otro', 'x', 'c@example.com'), 'ocultar', null);
            $this->assertNotContains($id, $ids($visitante($clase)->buscar($texto)), "{$tipo}: out of a visitor's search");
            $this->assertContains($id, $ids($propietario($clase)->buscar($texto)), "{$tipo}: the owner still finds it");
        }
    }

    #[DataProvider('drivers')]
    public function testAWithdrawnPersonaIsMaskedAndHerEventsAndRecordsLeaveTheVisitorsView(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $repo = new RetiroRepository($db);
        $visitante = Privacidad::publica();
        $personas = static fn() => $db->fetchAll('SELECT id, nombres, oculta FROM ' . $visitante->personas() . ' p WHERE id = 6128473101');

        $this->assertSame('José', $personas()[0]['nombres'], 'precondition');
        $suceso = (int)$db->fetchValue('SELECT suceso_id FROM suceso_participantes WHERE persona_id = 6128473101 LIMIT 1');
        $this->assertNotNull($db->fetchValue('SELECT 1 FROM ' . $visitante->sucesos() . ' e WHERE e.id = ?', [$suceso]), 'precondition: the event is visible');

        $repo->decidir($repo->crear('persona', 6128473101, 'familiar', 'x', 'c@example.com'), 'retirar', null);
        $this->assertSame('Persona privada', $personas()[0]['nombres']);
        $this->assertNull($db->fetchValue('SELECT 1 FROM ' . $visitante->sucesos() . ' e WHERE e.id = ?', [$suceso]), 'her events go too');
        $this->assertNotNull($db->fetchValue('SELECT 1 FROM ' . Privacidad::propietario()->sucesos() . ' e WHERE e.id = ?', [$suceso]));
    }

    #[DataProvider('drivers')]
    public function testAWithdrawnPageLeavesTheSitemap(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $repo = new RetiroRepository($db);
        $sitemap = new SitemapRepository($db, Privacidad::publica());
        $this->assertContains('81372002', $sitemap->claves('8', 1));

        $repo->decidir($repo->crear('registro', 81372002, 'terminos', 'x', 'c@example.com'), 'retirar', null);
        $this->assertNotContains('81372002', $sitemap->claves('8', 1));
        $this->assertContains('582317', $sitemap->claves('6', 1), 'the other types still list');
    }
}

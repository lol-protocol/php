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

    /** Moves a pedido's arrival back in time, so a test does not have to wait for a deadline. */
    private static function llegoHace(\App\Support\Database $db, int $numero, int $horas, int $ahora): void
    {
        $db->execute('UPDATE pedidos_retiro SET creado_en = ? WHERE id = ?', [date('Y-m-d H:i:s', $ahora - $horas * 3600), $numero]);
    }

    #[DataProvider('drivers')]
    public function testPendientesAreTheOnesWaitingOldestFirstWithoutPersonalData(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $repo = new RetiroRepository($db);
        $ahora = 1_800_000_000;
        $reciente = $repo->crear('persona', 6128473105, 'me_nombra', 'Es falso: no vivo allí.', 'secreto@example.com');
        $viejo = $repo->crear('suceso', 412000001, 'terminos', 'Insulta a mi madre.', 'otro@example.com');
        $decidido = $repo->crear('grupo', 582317, 'otro', 'Otro.', 'c@example.com');
        self::llegoHace($db, $reciente, 2, $ahora);
        self::llegoHace($db, $viejo, 50, $ahora);
        self::llegoHace($db, $decidido, 100, $ahora);
        $repo->decidir($decidido, 'rechazar', null);

        $pendientes = $repo->pendientes($ahora);

        $this->assertSame([$viejo, $reciente], array_column($pendientes, 'id'), 'oldest first, and not the one already decided');
        $this->assertSame([50, 2], array_column($pendientes, 'horas'));
        $this->assertSame(['id', 'tipo', 'recurso_id', 'motivo', 'creado_en', 'horas'], array_keys($pendientes[0]));
        $this->assertStringNotContainsString('secreto@example.com', json_encode($pendientes));
        $this->assertStringNotContainsString('Insulta', json_encode($pendientes), 'a cron mail must not carry what they wrote');
    }

    #[DataProvider('drivers')]
    public function testOcultarVencidosHidesOnlyThosePastTheDeadlineAndSaysWhy(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $repo = new RetiroRepository($db);
        $ahora = 1_800_000_000;
        $tarde = $repo->crear('persona', 6128473105, 'me_nombra', 'a', 'a@example.com');
        $justo = $repo->crear('persona', 6128473106, 'familiar', 'b', 'b@example.com');
        $a_tiempo = $repo->crear('persona', 6128473107, 'otro', 'c', 'c@example.com');
        $rechazado = $repo->crear('persona', 6128473108, 'otro', 'd', 'd@example.com');
        self::llegoHace($db, $tarde, 30, $ahora);
        self::llegoHace($db, $justo, 24, $ahora);
        self::llegoHace($db, $a_tiempo, 23, $ahora);
        self::llegoHace($db, $rechazado, 90, $ahora);
        $repo->decidir($rechazado, 'rechazar', 'No es cierto.');

        $ocultados = $repo->ocultarVencidos(24, $ahora);

        $this->assertSame([$tarde, $justo], $ocultados, 'the deadline itself counts as late; 23 hours does not');
        $this->assertTrue($repo->retirado('persona', 6128473105));
        $this->assertTrue($repo->retirado('persona', 6128473106));
        $this->assertFalse($repo->retirado('persona', 6128473107), 'still on time: shown');
        $this->assertFalse($repo->retirado('persona', 6128473108), 'the owner already rejected it: untouched');

        $eventos = array_column(array_filter($repo->todos(), static fn(array $p): bool => (int)$p['id'] === $tarde), 'eventos')[0];
        $this->assertSame(['recibido', 'oculto'], array_column($eventos, 'estado'));
        $this->assertStringContainsString('Oculto automáticamente', (string)$eventos[1]['nota']);
        $this->assertStringContainsString('30 horas', (string)$eventos[1]['nota']);
        $this->assertStringContainsString('plazo de 24 h', (string)$eventos[1]['nota']);
        $this->assertSame(date('Y-m-d H:i:s', $ahora), $eventos[1]['ocurrido_en'], 'the record says when');

        $this->assertSame([], $repo->ocultarVencidos(24, $ahora), 'a second run has nothing left to hide');
        $this->assertSame([$a_tiempo], array_column($repo->pendientes($ahora), 'id'));
    }

    #[DataProvider('drivers')]
    public function testTheOwnerCanStillRejectWhatWasHiddenAutomatically(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy', seed: true);
        $repo = new RetiroRepository($db);
        $ahora = 1_800_000_000;
        $numero = $repo->crear('persona', 6128473105, 'me_nombra', 'a', 'a@example.com');
        self::llegoHace($db, $numero, 40, $ahora);
        $repo->ocultarVencidos(24, $ahora);
        $this->assertTrue($repo->retirado('persona', 6128473105));

        $this->assertTrue($repo->decidir($numero, 'rechazar', 'Revisado: no hay nada que retirar.'));

        $this->assertFalse($repo->retirado('persona', 6128473105), 'rejecting brings the page back, as ever');
        $eventos = $repo->todos()[0]['eventos'];
        $this->assertSame(['recibido', 'oculto', 'rechazado'], array_column($eventos, 'estado'));
    }
}

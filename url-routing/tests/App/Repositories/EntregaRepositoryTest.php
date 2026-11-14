<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\EntregaRepository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** Delivering with a key what the site holds about a persona: the link, the key and what stops a guess, on every engine. */
class EntregaRepositoryTest extends TestCase
{
    private const ELLA = 6128479601;
    private const HIJO = 6128479602;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private function mundo(string $driver): Database
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $db->execute("INSERT INTO usuarios (id, email, nombre) VALUES (1, 'u@example.com', 'U')");
        $db->execute("INSERT INTO personas (id, nombres, apellidos, sexo, aportado_por) VALUES (?, 'Rosa Elena', 'Quintana Soto', 'F', 1)", [self::ELLA]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, madre_id) VALUES (?, 'Hijo', 'Secreto', ?)", [self::HIJO, self::ELLA]);
        $db->execute("INSERT INTO sucesos (id, tipo, fecha, descripcion) VALUES (412979601, 'nacimiento', '1850-03-04', 'Nació en casa')");
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (412979601, ?, 'principal')", [self::ELLA]);

        return $db;
    }

    #[DataProvider('drivers')]
    public function testItGivesALinkAndAKeyAndStoresNeitherOfThem(string $driver): void
    {
        $db = $this->mundo($driver);

        $e = (new EntregaRepository($db))->crear(self::ELLA, 7, 1_700_000_000);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $e['token']);
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{4}(-[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{4}){2}$/', $e['clave']);
        $this->assertSame(date('Y-m-d H:i:s', 1_700_000_000 + 7 * 86400), $e['vence_en']);
        $guardado = json_encode($db->fetchAll('SELECT * FROM entregas_persona'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($e['token'], $guardado, 'a leaked table cannot rebuild the link');
        $this->assertStringNotContainsString($e['clave'], $guardado);
        $this->assertStringNotContainsString(str_replace('-', '', $e['clave']), $guardado);
        $this->assertSame(hash('sha256', $e['token']), $db->fetchValue('SELECT token_hash FROM entregas_persona'));
    }

    #[DataProvider('drivers')]
    public function testEveryDeliveryHasItsOwnTokenAndKey(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));
        $tokens = $claves = [];

        for ($i = 0; $i < 25; $i++) {
            $e = $repo->crear(self::ELLA);
            $tokens[$e['token']] = $claves[$e['clave']] = true;
        }

        $this->assertCount(25, $tokens);
        $this->assertCount(25, $claves);
    }

    #[DataProvider('drivers')]
    public function testItRefusesAPersonaThatDoesNotExistAndAnAbsurdValidity(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new EntregaRepository($db);

        foreach ([[6128479777, 7], [self::ELLA, 0], [self::ELLA, 91]] as [$id, $dias]) {
            try {
                $repo->crear($id, $dias);
                $this->fail("{$driver}: {$id} {$dias}");
            } catch (\DomainException) {
            }
        }

        $this->assertSame(0, (int)$db->fetchValue('SELECT COUNT(*) FROM entregas_persona'), 'nothing was stored');
    }

    #[DataProvider('drivers')]
    public function testTheRightKeyOpensItWhateverTheCaseOrTheDashes(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));
        $e = $repo->crear(self::ELLA);

        $this->assertSame(self::ELLA, $repo->abrir($e['token'], $e['clave']));
        $this->assertSame(self::ELLA, $repo->abrir($e['token'], strtolower($e['clave'])));
        $this->assertSame(self::ELLA, $repo->abrir($e['token'], str_replace('-', ' ', ' ' . $e['clave'] . ' ')));
    }

    #[DataProvider('drivers')]
    public function testAWrongKeyIsCountedAndTheFifthLocksTheLinkEvenForTheRightKey(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));
        $e = $repo->crear(self::ELLA);

        for ($i = 1; $i <= EntregaRepository::MAX_INTENTOS; $i++) {
            $this->assertTrue($repo->vigente($e['token']), "attempt {$i}");
            $this->assertSame('clave', $repo->abrir($e['token'], 'AAAA-AAAA-AAA' . $i));
        }

        $this->assertFalse($repo->vigente($e['token']), 'locked');
        $this->assertNull($repo->abrir($e['token'], $e['clave']), 'the right key no longer helps: whoever guessed cannot keep trying');
    }

    #[DataProvider('drivers')]
    public function testTheRightKeyGivesTheAttemptsBack(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));
        $e = $repo->crear(self::ELLA);
        for ($i = 0; $i < EntregaRepository::MAX_INTENTOS - 1; $i++) {
            $repo->abrir($e['token'], 'ZZZZ-ZZZZ-ZZZZ');
        }

        $this->assertSame(self::ELLA, $repo->abrir($e['token'], $e['clave']));
        $this->assertSame('clave', $repo->abrir($e['token'], 'ZZZZ-ZZZZ-ZZZZ'));
        $this->assertTrue($repo->vigente($e['token']), 'the person who made typos is not locked out by them');
    }

    #[DataProvider('drivers')]
    public function testItOpensAFewTimesAndThenStopsExisting(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));
        $e = $repo->crear(self::ELLA);

        for ($i = 0; $i < EntregaRepository::MAX_VISTAS; $i++) {
            $this->assertSame(self::ELLA, $repo->abrir($e['token'], $e['clave']), "opening {$i}");
        }

        $this->assertFalse($repo->vigente($e['token']));
        $this->assertNull($repo->abrir($e['token'], $e['clave']));
    }

    #[DataProvider('drivers')]
    public function testItStopsExistingWhenItExpiresAndALinkMadeUpNeverExisted(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));
        $e = $repo->crear(self::ELLA, 2, 1_700_000_000);

        $this->assertTrue($repo->vigente($e['token'], 1_700_000_000 + 86400));
        $this->assertSame(self::ELLA, $repo->abrir($e['token'], $e['clave'], 1_700_000_000 + 86400));
        $this->assertFalse($repo->vigente($e['token'], 1_700_000_000 + 2 * 86400), 'at the exact moment it expires');
        $this->assertNull($repo->abrir($e['token'], $e['clave'], 1_700_000_000 + 3 * 86400));
        $this->assertNull($repo->abrir(str_repeat('a', 64), 'AAAA-AAAA-AAAA'));
        $this->assertFalse($repo->vigente(str_repeat('a', 64)));
    }

    #[DataProvider('drivers')]
    public function testRevokingEndsWhatStillWorksAndOnlyThat(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));
        $uno = $repo->crear(self::ELLA);
        $dos = $repo->crear(self::ELLA);
        $otra = $repo->crear(self::HIJO);
        $vieja = $repo->crear(self::ELLA, 1, time() - 5 * 86400);

        $this->assertSame(2, $repo->revocar(self::ELLA), 'the expired one was not counted');
        $this->assertSame(0, $repo->revocar(self::ELLA));
        $this->assertNull($repo->abrir($uno['token'], $uno['clave']));
        $this->assertNull($repo->abrir($dos['token'], $dos['clave']));
        $this->assertSame(self::HIJO, $repo->abrir($otra['token'], $otra['clave']), 'another persona is not affected');
        $this->assertFalse($repo->vigente($vieja['token']));
    }

    #[DataProvider('drivers')]
    public function testWhatItShowsIsHerFichaAndCountsOfHerRelativesNeverTheirNames(string $driver): void
    {
        $repo = new EntregaRepository($this->mundo($driver));

        $c = $repo->contenido(self::ELLA);

        $this->assertSame('Rosa Elena', $c['ficha']['Nombres']);
        $this->assertSame('Quintana Soto', $c['ficha']['Apellidos']);
        $this->assertSame(1, $c['familiares']['hijos']);
        $this->assertSame('Nació en casa', $c['sucesos'][0]['descripcion']);
        $json = json_encode($c, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Secreto', $json, 'her child\'s surname is the child\'s data');
        $this->assertStringNotContainsString('aportado_por', $json);
        $this->assertStringNotContainsString('grupo_id', $json);
        $this->assertNull($repo->contenido(6128479777), 'a persona that no longer exists has nothing to show');
    }

    #[DataProvider('drivers')]
    public function testErasingTheDeliveriesThatExpiredLongAgoKeepsTheRest(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new EntregaRepository($db);
        $ahora = 1_700_000_000;
        $vieja = $repo->crear(self::ELLA, 2, $ahora - 500 * 86400);       // expired about 498 days ago
        $reciente = $repo->crear(self::ELLA, 2, $ahora - 40 * 86400);     // expired 38 days ago
        $viva = $repo->crear(self::ELLA, 90, $ahora - 10 * 86400);        // still works
        $otra = $repo->crear(self::HIJO, 2, $ahora - 800 * 86400);        // another persona's, also old

        $this->assertSame(2, $repo->depurar(12, $ahora), 'the two that expired more than 12 months ago');
        $this->assertSame(0, $repo->depurar(12, $ahora), 'once');

        $restan = array_map('intval', array_column($db->fetchAll('SELECT id FROM entregas_persona ORDER BY id'), 'id'));
        $this->assertSame([$reciente['id'], $viva['id']], $restan);
        $this->assertSame(1, $repo->depurar(1, $ahora), 'a shorter retention reaches the recent one');
        $this->assertSame([$viva['id']], array_map('intval', array_column($db->fetchAll('SELECT id FROM entregas_persona'), 'id')), 'one that still works is never touched, however old');
        $this->assertNotSame($vieja['id'], $otra['id']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Repositories\Genealogy\EntregaRepository;
use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * /e/{token}/ end to end: a persona opens, with a key, what the site holds about her, including what it hides from a
 * visitor. A link that is not alive is a 404, the same as one made up. Its own database, like RetiroTest.
 */
class EntregaTest extends TestCase
{
    private const HOST = 'tudominio.local';
    private const JUAN = 6128473105;

    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-entrega-' . getmypid();
        @mkdir(self::$dir, 0777, true);
        $migrator = Migrator::forSite(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'), 'genealogy');
        $migrator->migrate();
        $migrator->seed();
    }

    public static function tearDownAfterClass(): void
    {
        exec('rm -rf ' . escapeshellarg(self::$dir));
    }

    /** @return array{int, string, string} [status, body, sessionId] */
    private function pedir(string $uri, string $metodo = 'GET', array $post = [], ?string $sid = null, array $entorno = []): array
    {
        $cmd = [PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__, 2) . '/Support/request.php',
            self::HOST, $uri, $metodo, http_build_query($post), $sid ?? '', ''];
        $env = $entorno + [
            'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
            'LOG_FILE' => self::$dir . '/app.log',
            'PATH' => (string)getenv('PATH'),
        ];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $body = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        proc_close($proc);

        $this->assertMatchesRegularExpression('/STATUS:\d{3}$/', $stderr, "No status for {$uri}; stderr: {$stderr}");
        $this->assertStringNotContainsString('Warning', $stderr, $stderr);
        preg_match('/^SESSION:(.*)$/m', $stderr, $ms);

        return [(int)substr($stderr, -3), $body, $ms[1] ?? ''];
    }

    private function repo(): EntregaRepository
    {
        return new EntregaRepository(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'));
    }

    /** @return array{string, string, string, string} [uri, sessionId, csrfToken, key] of a fresh delivery */
    private function nueva(int $persona = self::JUAN, int $dias = 7): array
    {
        $e = $this->repo()->crear($persona, $dias);
        $uri = '/e/' . $e['token'] . '/';
        [$codigo, $body, $sid] = $this->pedir($uri);
        $this->assertSame(200, $codigo);
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $m);
        $this->assertNotEmpty($m);

        return [$uri, $sid, $m[1], $e['clave']];
    }

    public function testTheLinkAsksForTheKeyAndShowsNothingBeforeIt(): void
    {
        [$codigo, $body] = $this->pedir($this->nueva()[0]);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('name="clave"', $body);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $body);
        $this->assertStringNotContainsString('García', $body, 'not a word of her data before the key');
        $this->assertStringNotContainsString('href="/r/', $body, 'a page with a token in its URL has no outgoing links');
    }

    public function testTheRightKeyShowsHerWholeFichaAndNotHerRelativesNames(): void
    {
        [$uri, $sid, $token, $clave] = $this->nueva();

        [$codigo, $body] = $this->pedir($uri, 'POST', ['csrf_token' => $token, 'clave' => strtolower($clave)], $sid);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Juan', $body);
        $this->assertStringContainsString('García Hernández', $body, 'what a visitor sees as «Persona viva»');
        $this->assertStringContainsString('Familiares registrados', $body);
        $this->assertStringContainsString('Sucesos en los que apareces', $body);
        $this->assertStringNotContainsString('Antonio', $body, 'her father\'s name is his data');
        $this->assertStringNotContainsString('Fernández', $body);
        $this->assertStringNotContainsString('aportado_por', $body);
        $this->assertStringNotContainsString($clave, $body);
        $this->assertStringNotContainsString('García', (string)@file_get_contents(self::$dir . '/app.log'), 'the log says it was opened, never what it showed');
        $this->assertStringNotContainsString($clave, (string)@file_get_contents(self::$dir . '/app.log'));
    }

    public function testAWrongKeyIsRefusedAndShowsNothing(): void
    {
        [$uri, $sid, $token] = $this->nueva();

        [$codigo, $body] = $this->pedir($uri, 'POST', ['csrf_token' => $token, 'clave' => 'AAAA-AAAA-AAAA'], $sid);

        $this->assertSame(422, $codigo);
        $this->assertStringContainsString('La clave no es correcta', $body);
        $this->assertStringContainsString('role="alert"', $body);
        $this->assertStringNotContainsString('García', $body);
        $this->assertSame(403, $this->pedir($uri, 'POST', ['csrf_token' => 'x', 'clave' => 'AAAA'], $sid)[0], 'wrong CSRF token');
        $this->assertSame(405, $this->pedir($uri, 'PUT')[0]);
    }

    public function testALinkThatIsNotAliveIsA404LikeOneMadeUp(): void
    {
        [$bloqueada, $sid, $token] = $this->nueva();
        for ($i = 0; $i < EntregaRepository::MAX_INTENTOS; $i++) {
            $this->pedir($bloqueada, 'POST', ['csrf_token' => $token, 'clave' => 'AAAA-AAAA-AAA' . $i], $sid, ['TEST_REMOTE_ADDR' => '198.51.100.' . $i . '-' . bin2hex(random_bytes(3))]);
        }
        $revocada = $this->repo()->crear(self::JUAN);
        $this->repo()->revocar(self::JUAN);
        $vencida = $this->repo()->crear(self::JUAN, 1, time() - 3 * 86400);

        $cuerpos = [];
        foreach ([
            $bloqueada,
            '/e/' . $revocada['token'] . '/',
            '/e/' . $vencida['token'] . '/',
            '/e/' . str_repeat('a', 64) . '/',
            '/e/abc/',
            '/e/' . str_repeat('z', 64) . '/',
            '/e/',
        ] as $uri) {
            [$codigo, $cuerpos[$uri]] = $this->pedir($uri);
            $this->assertSame(404, $codigo, $uri);
        }
        $this->assertCount(1, array_unique(array_map(static fn(string $c): string => preg_replace('/csrf[^"]*"[^"]*"/', '', $c) ?? '', $cuerpos)), 'the same body: nothing says which one it was');
    }

    public function testItOpensAFewTimesAndThenIsGone(): void
    {
        [$uri, $sid, $token, $clave] = $this->nueva();

        for ($i = 0; $i < EntregaRepository::MAX_VISTAS; $i++) {
            $this->assertSame(200, $this->pedir($uri, 'POST', ['csrf_token' => $token, 'clave' => $clave], $sid)[0], "opening {$i}");
        }

        $this->assertSame(404, $this->pedir($uri)[0]);
        $this->assertSame(404, $this->pedir($uri, 'POST', ['csrf_token' => $token, 'clave' => $clave], $sid)[0]);
    }

    public function testAVisitorCanOnlyTryAFewKeysPerHourOverEveryLink(): void
    {
        $entorno = ['TEST_REMOTE_ADDR' => '198.51.100.7-' . bin2hex(random_bytes(4)), 'ENTREGA_MAX_POR_HORA' => '2'];
        [$uno, $sidUno, $tokenUno] = $this->nueva();
        [$dos, $sidDos, $tokenDos] = $this->nueva();

        $this->assertSame(422, $this->pedir($uno, 'POST', ['csrf_token' => $tokenUno, 'clave' => 'AAAA-AAAA-AAAA'], $sidUno, $entorno)[0]);
        $this->assertSame(422, $this->pedir($dos, 'POST', ['csrf_token' => $tokenDos, 'clave' => 'AAAA-AAAA-AAAA'], $sidDos, $entorno)[0]);
        $this->assertSame(429, $this->pedir($uno, 'POST', ['csrf_token' => $tokenUno, 'clave' => 'AAAA-AAAA-AAAA'], $sidUno, $entorno)[0], 'the third in the hour, on another link too');
    }

    public function testAnErasedPersonaLeavesALinkThatShowsNothing(): void
    {
        $db = new Database('sqlite:' . self::$dir . '/genealogy.sqlite');
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (6128479999, 'Efímera', 'Persona')");
        [$uri, $sid, $token, $clave] = $this->nueva(6128479999);
        $db->execute('DELETE FROM personas WHERE id = 6128479999');

        $this->assertSame(404, $this->pedir($uri, 'POST', ['csrf_token' => $token, 'clave' => $clave], $sid)[0]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * The form of /s/{id}/ end to end: anyone, without an account, asks about a persona's data, and the solicitud waits for
 * the owner without giving, changing or erasing anything. Its own database, like RetiroTest.
 */
class SolicitudTest extends TestCase
{
    private const HOST = 'tudominio.local';
    private const PEDIR = '/s/6128473105/';

    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-solicitud-' . getmypid();
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

    /** @return array{string, string} [sessionId, csrfToken] from the page at $uri */
    private function formulario(string $uri = self::PEDIR): array
    {
        [$codigo, $body, $sid] = $this->pedir($uri);
        $this->assertSame(200, $codigo, $uri);
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $m);
        $this->assertNotEmpty($m, "no CSRF token on {$uri}");

        return [$sid, $m[1]];
    }

    private function valida(string $token): array
    {
        return ['csrf_token' => $token, 'derecho' => 'acceso', 'detalle' => 'Soy Juan García: quiero ver qué datos míos tienen.', 'contacto' => 'juan@example.com'];
    }

    private function db(): Database
    {
        return new Database('sqlite:' . self::$dir . '/genealogy.sqlite');
    }

    private function cuantas(): int
    {
        return (int)$this->db()->fetchValue('SELECT COUNT(*) FROM solicitudes_persona');
    }

    public function testOnlyAPersonasPageLinksToTheForm(): void
    {
        $this->assertStringContainsString('href="' . self::PEDIR . '"', $this->pedir('/6128473105/')[1]);
        $this->assertStringContainsString('href="/r/6128473105/"', $this->pedir('/6128473105/')[1], 'next to the one to withdraw it');
        $this->assertStringContainsString('href="' . self::PEDIR . '"', $this->pedir('/6128473105/4/')[1], 'an action of the persona too');
        foreach (['/582317/', '/', '/9999999999/'] as $uri) {
            $this->assertStringNotContainsString('href="/s/', $this->pedir($uri)[1], "not {$uri}: a surname, the home, a 404");
        }
    }

    public function testTheFormIsOnlyForAPersonaThatExists(): void
    {
        [$codigo, $body] = $this->pedir(self::PEDIR);
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Pedir ver, corregir o borrar los datos de una persona', $body);
        $this->assertStringContainsString('name="derecho"', $body);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $body);
        $this->assertStringContainsString('no entrega, cambia ni borra nada por sí solo', $body, 'says what receiving it does');

        foreach (['/s/9999999999/', '/s/abc/', '/s/', '/s/6128473105/1/', '/s/582317/', '/s/123/'] as $uri) {
            $this->assertSame(404, $this->pedir($uri)[0], $uri);
        }
        $this->assertSame(405, $this->pedir(self::PEDIR, 'PUT')[0]);
    }

    public function testASentSolicitudWaitsForTheOwnerAndDoesNothingElse(): void
    {
        [$sid, $token] = $this->formulario();
        $personas = (int)$this->db()->fetchValue('SELECT COUNT(*) FROM personas');

        $this->assertSame(403, $this->pedir(self::PEDIR, 'POST', ['csrf_token' => 'x'] + $this->valida($token), $sid)[0], 'wrong token');
        foreach (['derecho' => 'borrar_todo', 'detalle' => '   ', 'contacto' => 'no-es-un-correo'] as $campo => $malo) {
            [$codigo, $body] = $this->pedir(self::PEDIR, 'POST', [$campo => $malo] + $this->valida($token), $sid);
            $this->assertSame(422, $codigo, $campo);
            $this->assertStringContainsString('role="alert"', $body, $campo);
        }
        $this->assertSame(422, $this->pedir(self::PEDIR, 'POST', ['detalle' => str_repeat('a', 2001)] + $this->valida($token), $sid)[0]);
        $this->assertSame(0, $this->cuantas(), 'none of them was stored');

        $this->assertSame(303, $this->pedir(self::PEDIR, 'POST', $this->valida($token), $sid)[0]);
        $fila = $this->db()->fetchOne('SELECT * FROM solicitudes_persona');
        $this->assertSame([6128473105, 'acceso', 'pendiente', 'juan@example.com'], [(int)$fila['persona_id'], $fila['derecho'], $fila['estado'], $fila['contacto']]);
        $this->assertSame($personas, (int)$this->db()->fetchValue('SELECT COUNT(*) FROM personas'), 'it erased nobody');
        $this->assertSame(200, $this->pedir('/6128473105/')[0], 'and hid nothing');
        $this->assertStringNotContainsString('juan@example.com', (string)@file_get_contents(self::$dir . '/app.log'), 'the log never names who asked');
        $this->assertStringNotContainsString('quiero ver qué datos', (string)@file_get_contents(self::$dir . '/app.log'));
    }

    public function testEveryRightCanBeAsked(): void
    {
        [$sid, $token] = $this->formulario();
        $antes = $this->cuantas();

        foreach (['rectificacion', 'supresion'] as $i => $derecho) {
            $this->assertSame(303, $this->pedir(self::PEDIR, 'POST', ['derecho' => $derecho] + $this->valida($token), $sid, ['TEST_REMOTE_ADDR' => "198.51.100.{$i}-" . bin2hex(random_bytes(4))])[0], $derecho);
        }

        $this->assertSame($antes + 2, $this->cuantas());
    }

    public function testAVisitorCanOnlySendSomeSolicitudesPerHour(): void
    {
        // its own persona: the ones of the other tests would hit the limit of what waits on one persona first
        $uri = '/s/6128473102/';
        $entorno = ['TEST_REMOTE_ADDR' => '198.51.100.7-' . bin2hex(random_bytes(4)), 'SOLICITUD_MAX_POR_HORA' => '2'];
        [$sid, $token] = $this->formulario($uri);
        $enviar = fn() => $this->pedir($uri, 'POST', $this->valida($token), $sid, $entorno)[0];

        $this->assertSame(303, $enviar());
        $this->assertSame(303, $enviar());
        $antes = $this->cuantas();
        $this->assertSame(429, $enviar(), 'the third in the hour');
        $this->assertSame($antes, $this->cuantas(), 'the refused one was not stored');
        $this->assertSame(303, $this->pedir($uri, 'POST', $this->valida($token), $sid,
            ['TEST_REMOTE_ADDR' => '198.51.100.8-' . bin2hex(random_bytes(4)), 'SOLICITUD_MAX_POR_HORA' => '2'])[0], 'another visitor is not affected');
    }

    public function testAPersonaStopsTakingSolicitudesWhenManyAreWaiting(): void
    {
        $repo = new \App\Repositories\Genealogy\SolicitudRepository($this->db());
        for ($i = 0; $i < 3; $i++) {
            $repo->crear(6128473106, 'acceso', 'x', "p{$i}@example.com");
        }
        [$sid, $token] = $this->formulario('/s/6128473106/');
        $uri = '/s/6128473106/';

        $this->assertSame(429, $this->pedir($uri, 'POST', $this->valida($token), $sid, ['SOLICITUD_MAX_PENDIENTES' => '3'])[0]);
        $this->assertSame(303, $this->pedir($uri, 'POST', $this->valida($token), $sid, ['SOLICITUD_MAX_PENDIENTES' => '4'])[0], 'under the limit');
    }

    public function testTheDecoyFieldDiscardsTheBotWithoutSayingSo(): void
    {
        [, $form] = $this->pedir(self::PEDIR);
        $this->assertStringContainsString('name="sitio_web"', $form);
        $this->assertStringContainsString('aria-hidden="true"', $form);

        [$sid, $token] = $this->formulario();
        $antes = $this->cuantas();
        $this->assertSame(303, $this->pedir(self::PEDIR, 'POST', ['sitio_web' => 'http://spam.example'] + $this->valida($token), $sid)[0], 'the same answer a person gets');
        $this->assertSame($antes, $this->cuantas(), 'nothing stored');
    }
}

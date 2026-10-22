<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * Pedidos de retiro end to end: a visitor asks to withdraw a page at /r/{id}/ without an account,
 * the owner decides in /0/3/, and a hidden or withdrawn page answers 410 to visitors (with all its
 * actions, and out of the sitemap) while the owner still sees it. Its own database, so the pedidos
 * it leaves behind don't reach PagesTest.
 */
class RetiroTest extends TestCase
{
    private const HOST = 'tudominio.local';
    private const TOKEN = 'token-de-prueba-0123456789';
    private const JUAN = '/6128473105/';
    private const PEDIR = '/r/6128473105/';

    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-retiro-' . getmypid();
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
    private function pedir(string $uri, string $metodo = 'GET', array $post = [], ?string $sid = null, bool $propietario = false): array
    {
        $autorizacion = $propietario ? 'Basic ' . base64_encode('propietario:' . self::TOKEN) : '';
        $cmd = [PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__, 2) . '/Support/request.php',
            self::HOST, $uri, $metodo, http_build_query($post), $sid ?? '', $autorizacion];
        $env = [
            'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
            'LOG_FILE' => self::$dir . '/app.log',
            'OWNER_TOKEN' => self::TOKEN,
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
    private function formulario(string $uri, bool $propietario = false): array
    {
        [$codigo, $body, $sid] = $this->pedir($uri, propietario: $propietario);
        $this->assertSame(200, $codigo, $uri);
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $m);
        $this->assertNotEmpty($m, "no CSRF token on {$uri}");

        return [$sid, $m[1]];
    }

    private function pedidoValido(string $token): array
    {
        return ['csrf_token' => $token, 'motivo' => 'me_nombra', 'detalle' => 'Dice que estafé a mi familia: es falso.', 'contacto' => 'ana@example.com'];
    }

    private function db(): Database
    {
        return new Database('sqlite:' . self::$dir . '/genealogy.sqlite');
    }

    public function testEveryPageOfAResourceLinksToTheForm(): void
    {
        $this->assertStringContainsString('href="' . self::PEDIR . '"', $this->pedir(self::JUAN)[1]);
        $this->assertStringContainsString('href="/r/6128473105/"', $this->pedir('/6128473105/4/')[1], 'an action of the persona too');
        $this->assertStringContainsString('href="/r/582317/"', $this->pedir('/582317/')[1], 'a surname');
        $this->assertStringNotContainsString('href="/r/', $this->pedir('/')[1], 'not the home page');
        $this->assertStringNotContainsString('href="/r/', $this->pedir('/9999999999/')[1], 'not a 404');
    }

    public function testTheFormIsOnlyForPagesAVisitorCanSee(): void
    {
        [$codigo, $body] = $this->pedir(self::PEDIR);
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Pedir el retiro de una página', $body);
        $this->assertStringContainsString('name="contacto"', $body);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $body);

        foreach (['/r/9999999999/', '/r/abc/', '/r/', '/r/6128473105/1/', '/r/123/'] as $uri) {
            $this->assertSame(404, $this->pedir($uri)[0], $uri);
        }
        $this->assertSame(405, $this->pedir(self::PEDIR, 'PUT')[0]);
    }

    public function testASentPedidoWaitsForTheOwnerAndHidesNothing(): void
    {
        [$sid, $token] = $this->formulario(self::PEDIR);

        $this->assertSame(403, $this->pedir(self::PEDIR, 'POST', ['csrf_token' => 'x'] + $this->pedidoValido($token), $sid)[0], 'wrong token');
        foreach (['motivo' => 'cualquiera', 'detalle' => '   ', 'contacto' => 'no-es-un-correo'] as $campo => $malo) {
            [$codigo, $body] = $this->pedir(self::PEDIR, 'POST', [$campo => $malo] + $this->pedidoValido($token), $sid);
            $this->assertSame(422, $codigo, $campo);
            $this->assertStringContainsString('role="alert"', $body, $campo);
        }
        $this->assertSame(422, $this->pedir(self::PEDIR, 'POST', ['detalle' => str_repeat('a', 2001)] + $this->pedidoValido($token), $sid)[0]);
        $this->assertSame(0, (int)$this->db()->fetchValue('SELECT COUNT(*) FROM pedidos_retiro'), 'none of them was stored');

        $this->assertSame(303, $this->pedir(self::PEDIR, 'POST', $this->pedidoValido($token), $sid)[0]);
        $pedido = $this->db()->fetchOne('SELECT * FROM pedidos_retiro ORDER BY id DESC LIMIT 1');
        $this->assertSame(['persona', 6128473105, 'me_nombra', 'pendiente', 'ana@example.com'],
            [$pedido['tipo'], (int)$pedido['recurso_id'], $pedido['motivo'], $pedido['estado'], $pedido['contacto']]);
        $this->assertSame('recibido', $this->db()->fetchValue('SELECT estado FROM pedido_retiro_eventos WHERE pedido_id = ?', [$pedido['id']]));
        $this->assertSame(200, $this->pedir(self::JUAN)[0], 'receiving a pedido hides nothing by itself');
        $this->assertStringNotContainsString('ana@example.com', (string)@file_get_contents(self::$dir . '/app.log'), 'the log never names who asked');
    }

    public function testAPageHiddenForPrivacyCannotBeReportedNorTurnInto410(): void
    {
        // The baptism of Juan (born 1925, no death): a record that would give a living person away.
        $this->assertSame(404, $this->pedir('/81372001/')[0], 'precondition: hidden from visitors');
        $this->assertSame(404, $this->pedir('/r/81372001/')[0], 'no form for it');

        $repo = new \App\Repositories\Genealogy\RetiroRepository($this->db());
        $repo->decidir($repo->crear('registro', 81372001, 'otro', 'x', 'c@example.com'), 'retirar', null);
        $this->assertSame(404, $this->pedir('/81372001/')[0], 'still a 404, not a 410 that confirms it exists');
    }

    public function testAWithdrawnPersonaIsNamedNowhereElseForAVisitor(): void
    {
        $db = $this->db();
        $repo = new \App\Repositories\Genealogy\RetiroRepository($db);
        // José (6128473101) is Antonio's father, and Antonio's ascendencia page lists him by name.
        $antes = $this->pedir('/6128473103/1/')[1];
        $this->assertStringContainsString('José García Álvarez', $antes, 'precondition');

        $numero = $repo->crear('persona', 6128473101, 'familiar', 'x', 'd@example.com');
        $repo->decidir($numero, 'retirar', null);
        $despues = $this->pedir('/6128473103/1/')[1];
        $this->assertStringNotContainsString('José', $despues, 'masked in the tree of his son');
        $this->assertStringContainsString('Persona privada', $despues);
        $this->assertStringNotContainsString('José García Álvarez', $this->pedir('/6128473101/2/')[1] . $this->pedir('/582317/')[1] . $this->pedir('/b/10/')[1]);
        $this->assertSame(410, $this->pedir('/6128473101/')[0]);
        $this->assertStringContainsString('José García Álvarez', $this->pedir('/6128473103/1/', propietario: true)[1], 'the owner still sees him');

        $repo->decidir($numero, 'rechazar', null);
        $this->assertStringContainsString('José García Álvarez', $this->pedir('/6128473103/1/')[1], 'rejected: named again');
    }

    public function testTheOwnerHidesWithdrawsOrRejectsAndEveryStepIsRecorded(): void
    {
        $numero = (new \App\Repositories\Genealogy\RetiroRepository($this->db()))->crear('registro', 81372002, 'terminos', 'Copia un acta ajena.', 'b@example.com');
        $this->assertSame(401, $this->pedir('/0/3/')[0], 'a visitor is asked for the token');

        [$sid, $token] = $this->formulario('/0/3/', propietario: true);
        $decidir = fn(string $decision, string $nota = '') => $this->pedir('/0/3/', 'POST',
            ['csrf_token' => $token, 'pedido' => (string)$numero, 'decision' => $decision, 'nota' => $nota], $sid, true)[0];

        $cola = $this->pedir('/0/3/', propietario: true)[1];
        $this->assertStringContainsString('Copia un acta ajena.', $cola);
        $this->assertStringContainsString('llegó hoy', $cola, 'how long a pending pedido has waited');
        $this->assertSame(400, $decidir('borrar'), 'unknown decision');
        $this->assertSame(404, $this->pedir('/0/3/', 'POST', ['csrf_token' => $token, 'pedido' => '999999', 'decision' => 'ocultar'], $sid, true)[0]);

        $this->assertSame(303, $decidir('ocultar', 'Lo reviso.'));
        [$codigo, $body] = $this->pedir('/81372002/');
        $this->assertSame(410, $codigo);
        $this->assertStringContainsString('Página retirada', $body);
        $this->assertStringNotContainsString('Acta', $body);
        $this->assertSame(410, $this->pedir('/81372002/1/')[0], 'its actions too');
        $this->assertSame(200, $this->pedir('/81372002/', propietario: true)[0], 'the owner still sees it');
        $this->assertStringNotContainsString('/81372002/', $this->pedir('/sitemap/8/1.xml')[1], 'out of the sitemap');

        $this->assertSame(303, $decidir('rechazar', 'El acta es pública.'));
        $this->assertSame(200, $this->pedir('/81372002/')[0], 'rejected: shown again');
        $this->assertStringContainsString('/81372002/', $this->pedir('/sitemap/8/1.xml')[1]);

        $this->assertSame(303, $decidir('retirar'));
        $this->assertSame(410, $this->pedir('/81372002/')[0]);

        $eventos = $this->db()->fetchAll('SELECT estado, nota FROM pedido_retiro_eventos WHERE pedido_id = ? ORDER BY orden', [$numero]);
        $this->assertSame(['recibido', 'oculto', 'rechazado', 'retirado'], array_column($eventos, 'estado'));
        $this->assertSame([null, 'Lo reviso.', 'El acta es pública.', null], array_column($eventos, 'nota'));
        $this->assertStringContainsString('El acta es pública.', $this->pedir('/0/3/', propietario: true)[1], 'the history is on the owner\'s page');
    }
}

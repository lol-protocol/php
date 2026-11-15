<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Repositories\Genealogy\SolicitudRepository;
use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * /0/4/ end to end: the owner reads the solicitudes of data that anyone made at /s/{id}/ and closes each one. Nobody else
 * sees it, and closing gives, changes and erases nothing. Its own database, like RetiroTest.
 */
class SolicitudesPanelTest extends TestCase
{
    private const HOST = 'tudominio.local';
    private const TOKEN = 'token-de-prueba-0123456789';
    private const PANEL = '/0/4/';

    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-panel-solicitudes-' . getmypid();
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

    private function repo(): SolicitudRepository
    {
        return new SolicitudRepository(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'));
    }

    /** @return array{string, string} [sessionId, csrfToken] from the panel, as the owner */
    private function formulario(): array
    {
        [$codigo, $body, $sid] = $this->pedir(self::PANEL, propietario: true);
        $this->assertSame(200, $codigo);
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $m);
        $this->assertNotEmpty($m, 'no CSRF token: is there anything pending?');

        return [$sid, $m[1]];
    }

    public function testNobodyButTheOwnerSeesItNorClosesAnything(): void
    {
        $n = $this->repo()->crear(6128473105, 'acceso', 'Texto privado', 'secreto@example.com');

        [$codigo, $cuerpo] = $this->pedir(self::PANEL);
        $this->assertSame(401, $codigo, 'a visitor is asked for the token');
        $this->assertStringNotContainsString('secreto@example.com', $cuerpo);
        $this->assertStringNotContainsString('Texto privado', $cuerpo);
        $this->assertSame(401, $this->pedir(self::PANEL, 'POST', ['solicitud' => (string)$n, 'decision' => 'atender'])[0]);
        $this->assertSame('pendiente', $this->repo()->ver($n)['estado']);
        $this->assertSame(405, $this->pedir(self::PANEL, 'PUT', propietario: true)[0]);
    }

    public function testTheOwnerReadsEverySolicitudWithTheEmailMaskedAndOneClickAway(): void
    {
        $abierta = $this->repo()->crear(6128473105, 'supresion', 'Que se borre todo lo mío', 'ana.secreta@example.com');
        $cerrada = $this->repo()->crear(6128473106, 'acceso', 'Quiero verlo', 'beto@example.com');
        $this->repo()->cerrar($cerrada, 'atender', 'Resumen mandado');
        $depurada = $this->repo()->crear(6128473105, 'rectificacion', 'Corregir', 'carla@example.com', time() - 800 * 86400);
        $this->repo()->cerrar($depurada, 'rechazar', 'No respondió', time() - 500 * 86400);
        $this->repo()->depurar(12);

        [$codigo, $body] = $this->pedir(self::PANEL, propietario: true);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString("Solicitud n.º {$abierta} · Pendiente", $body);
        $this->assertStringContainsString('Borrar mis datos', $body, 'the right asked for, in the words of the form');
        $this->assertStringContainsString('Que se borre todo lo mío', $body);
        $this->assertStringContainsString('a***@example.com', $body, 'masked on the line that is always on screen');
        $this->assertMatchesRegularExpression('#<details><summary>Mostrar el correo completo</summary>ana\.secreta@example\.com</details>#', $body);
        $this->assertStringContainsString("php bin/persona.php resumen 6128473105", $body, 'the command to answer it');
        $this->assertStringContainsString("Solicitud n.º {$cerrada} · Atendida", $body);
        $this->assertStringContainsString('Resumen mandado', $body);
        $this->assertStringContainsString("Solicitud n.º {$depurada} · Rechazada", $body);
        $this->assertMatchesRegularExpression('/Datos personales<\/dt><dd>Borrados el \d{4}-\d{2}-\d{2}/', $body);
        $this->assertStringNotContainsString('carla@example.com', $body, 'once erased, it is not there');
        $this->assertStringContainsString('href="/0/4/" aria-current="page"', $body, 'the nav marks this section');
        $this->assertSame(3 * count($this->repo()->pendientes()), substr_count($body, 'name="solicitud"'), 'three forms per pending solicitud (close, delivery, simulation), and none for the closed ones');
    }

    public function testClosingRecordsTheNoteLeavesNothingElseChangedAndNamesNobodyInTheLog(): void
    {
        $n = $this->repo()->crear(6128473105, 'acceso', 'Mi dirección es Calle Falsa 123', 'privado@example.com');
        $personas = (int)(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'))->fetchValue('SELECT COUNT(*) FROM personas');
        [$sid, $token] = $this->formulario();

        $this->assertSame(403, $this->pedir(self::PANEL, 'POST', ['csrf_token' => 'x', 'solicitud' => (string)$n, 'decision' => 'atender'], $sid, true)[0], 'wrong token');
        foreach ([['solicitud' => 'abc', 'decision' => 'atender'], ['solicitud' => (string)$n, 'decision' => 'borrar_todo'], ['solicitud' => (string)$n, 'decision' => ''], ['solicitud' => (string)$n, 'decision' => 'atender', 'nota' => str_repeat('a', 2001)]] as $mal) {
            $this->assertSame(400, $this->pedir(self::PANEL, 'POST', ['csrf_token' => $token] + $mal, $sid, true)[0], json_encode($mal, JSON_THROW_ON_ERROR));
        }
        $this->assertSame(404, $this->pedir(self::PANEL, 'POST', ['csrf_token' => $token, 'solicitud' => '999999', 'decision' => 'atender'], $sid, true)[0], 'there is none');
        $this->assertSame('pendiente', $this->repo()->ver($n)['estado'], 'none of the above closed it');

        $this->assertSame(303, $this->pedir(self::PANEL, 'POST', ['csrf_token' => $token, 'solicitud' => (string)$n, 'decision' => 'rechazar', 'nota' => 'No se pudo comprobar quién pide'], $sid, true)[0]);

        $fila = $this->repo()->ver($n);
        $this->assertSame(['rechazada', 'No se pudo comprobar quién pide'], [$fila['estado'], $fila['nota']]);
        $this->assertNotNull($fila['cerrada_en']);
        $this->assertSame($personas, (int)(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'))->fetchValue('SELECT COUNT(*) FROM personas'), 'closing erased nobody');
        $log = (string)@file_get_contents(self::$dir . '/app.log');
        $this->assertStringContainsString('solicitud de datos cerrada', $log);
        $this->assertStringNotContainsString('privado@example.com', $log);
        $this->assertStringNotContainsString('Calle Falsa', $log);
        $this->assertSame(404, $this->pedir(self::PANEL, 'POST', ['csrf_token' => $token, 'solicitud' => (string)$n, 'decision' => 'atender'], $sid, true)[0], 'a closed one is never re-closed: the first answer stays');
        $this->assertSame('rechazada', $this->repo()->ver($n)['estado']);
    }

    /** @return array{int, string} an existing persona and her surnames */
    private function persona(): array
    {
        $fila = (new Database('sqlite:' . self::$dir . '/genealogy.sqlite'))->fetchOne('SELECT id, apellidos FROM personas ORDER BY id LIMIT 1');
        $this->assertNotNull($fila);

        return [(int)$fila['id'], (string)$fila['apellidos']];
    }

    private function entregas(): int
    {
        return (int)(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'))->fetchValue('SELECT COUNT(*) FROM entregas_persona');
    }

    public function testADeliveryIsMadeForThePersonaOfThePendingSolicitudAndShownOnlyOnce(): void
    {
        [$persona] = $this->persona();
        $n = $this->repo()->crear($persona, 'acceso', 'x', 'pide@example.com');
        [$sid, $token] = $this->formulario();
        $antes = $this->entregas();
        $post = ['csrf_token' => $token, 'accion' => 'entrega', 'solicitud' => (string)$n];

        $this->assertSame(401, $this->pedir(self::PANEL, 'POST', $post)[0], 'a visitor cannot');
        $this->assertSame(403, $this->pedir(self::PANEL, 'POST', ['csrf_token' => 'x'] + $post, $sid, true)[0], 'wrong CSRF token');
        $this->assertSame(404, $this->pedir(self::PANEL, 'POST', ['solicitud' => '999999'] + $post, $sid, true)[0], 'no such solicitud');
        foreach (['0', '91', '-1', 'x', '7.5'] as $dias) {
            $this->assertSame(422, $this->pedir(self::PANEL, 'POST', ['dias' => $dias] + $post, $sid, true)[0], "dias={$dias}");
        }
        $this->assertSame($antes, $this->entregas(), 'none of the above made a delivery');

        [$codigo, $cuerpo] = $this->pedir(self::PANEL, 'POST', ['dias' => '5', 'persona' => '1'] + $post, $sid, true);

        $this->assertSame(200, $codigo);
        $this->assertSame($antes + 1, $this->entregas());
        $this->assertMatchesRegularExpression('#<code>http://' . preg_quote(self::HOST, '#') . '/e/([0-9a-f]{64})/</code>#', $cuerpo, 'the link, complete');
        preg_match('#/e/([0-9a-f]{64})/#', $cuerpo, $enlace);
        preg_match('#Clave \(mándala por <strong>otro</strong> medio\)</dt><dd><code>([^<]+)</code>#', $cuerpo, $clave);
        $this->assertNotEmpty($clave, 'the key is on the page');
        $this->assertStringContainsString('única vez que se muestran', $cuerpo);
        $this->assertStringContainsString("php bin/persona.php entrega {$persona} --revocar", $cuerpo, 'what to do when it goes to the wrong place');

        $repo = new \App\Repositories\Genealogy\EntregaRepository(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'));
        $this->assertSame($persona, $repo->abrir($enlace[1], $clave[1]), 'it opens, and it is for the persona of the solicitud, not the one typed in the form');
        $log = (string)@file_get_contents(self::$dir . '/app.log');
        $this->assertStringContainsString('entrega con clave creada desde el panel', $log);
        $this->assertStringNotContainsString($enlace[1], $log, 'the token is never logged');
        $this->assertStringNotContainsString($clave[1], $log, 'nor the key');
        $this->assertStringNotContainsString($enlace[1], $this->pedir(self::PANEL, propietario: true)[1], 'the panel does not show it again');
    }

    public function testADeliveryCannotBeMadeForAClosedSolicitudOrForAPersonaWhoIsGone(): void
    {
        [$persona] = $this->persona();
        $cerrada = $this->repo()->crear($persona, 'acceso', 'x', 'a@example.com');
        $this->repo()->cerrar($cerrada, 'atender', null);
        $fantasma = $this->repo()->crear(6128479999, 'acceso', 'x', 'b@example.com');
        [$sid, $token] = $this->formulario();
        $antes = $this->entregas();

        $this->assertSame(404, $this->pedir(self::PANEL, 'POST', ['csrf_token' => $token, 'accion' => 'entrega', 'solicitud' => (string)$cerrada], $sid, true)[0]);
        [$codigo, $cuerpo] = $this->pedir(self::PANEL, 'POST', ['csrf_token' => $token, 'accion' => 'entrega', 'solicitud' => (string)$fantasma], $sid, true);

        $this->assertSame(422, $codigo);
        $this->assertStringContainsString('No existe la persona', $cuerpo);
        $this->assertStringContainsString('No se hizo nada', $cuerpo);
        $this->assertSame($antes, $this->entregas());
    }

    public function testSimulatingACorrectionSaysWhatItWouldDoAndChangesNothing(): void
    {
        [$persona, $apellidos] = $this->persona();
        $n = $this->repo()->crear($persona, 'rectificacion', 'x', 'pide@example.com');
        [$sid, $token] = $this->formulario();
        $db = new Database('sqlite:' . self::$dir . '/genealogy.sqlite');
        $correcciones = (int)$db->fetchValue('SELECT COUNT(*) FROM rectificaciones_persona');
        $post = ['csrf_token' => $token, 'accion' => 'simular', 'solicitud' => (string)$n, 'campo' => 'personas.apellidos', 'valor' => 'Apellido Nuevo Simulado'];

        $this->assertSame(401, $this->pedir(self::PANEL, 'POST', $post)[0]);
        $this->assertSame(403, $this->pedir(self::PANEL, 'POST', ['csrf_token' => 'x'] + $post, $sid, true)[0]);
        [$codigo, $cuerpo] = $this->pedir(self::PANEL, 'POST', $post, $sid, true);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString("Se corregiría <code>personas.apellidos</code> de la persona {$persona}", $cuerpo);
        $this->assertMatchesRegularExpression('/(No cambia|CAMBIA) lo que ve un visitante/', $cuerpo, 'what a visitor would see');
        $this->assertStringContainsString('Nada cambió', $cuerpo);
        $this->assertStringContainsString('--confirmar', $cuerpo, 'doing it is the command, not this page');
        $this->assertSame($apellidos, (string)$db->fetchValue('SELECT apellidos FROM personas WHERE id = ?', [$persona]), 'the persona was not touched');
        $this->assertSame($correcciones, (int)$db->fetchValue('SELECT COUNT(*) FROM rectificaciones_persona'), 'and no correction was recorded');
        $this->assertSame('pendiente', $this->repo()->ver($n)['estado'], 'the solicitud is still open');
    }

    public function testASimulationThatCannotBeDoneSaysWhyAndEscapesWhatWasTyped(): void
    {
        [$persona, $apellidos] = $this->persona();
        $n = $this->repo()->crear($persona, 'rectificacion', 'x', 'pide@example.com');
        [$sid, $token] = $this->formulario();
        $post = ['csrf_token' => $token, 'accion' => 'simular', 'solicitud' => (string)$n, 'campo' => 'personas.apellidos', 'valor' => 'x'];

        $casos = [
            [['campo' => 'personas.padre_id', 'valor' => '1'], 'no se corrige con este comando'],
            [['campo' => '<script>alert(1)</script>', 'valor' => 'x'], 'no se corrige con este comando'],
            [['campo' => 'sucesos.fecha', 'valor' => '1990-01-01'], 'falta --suceso'],
            [['campo' => 'personas.sexo', 'valor' => 'Q'], 'personas.sexo es F, M o X'],
            [['suceso' => 'abc'], 'El suceso es un número'],
        ];
        foreach ($casos as [$cambio, $dice]) {
            [$codigo, $cuerpo] = $this->pedir(self::PANEL, 'POST', $cambio + $post, $sid, true);
            $this->assertSame(422, $codigo, json_encode($cambio, JSON_THROW_ON_ERROR));
            $this->assertStringContainsString($dice, $cuerpo);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $cuerpo, 'what was typed is escaped');
        }
        $this->assertSame($apellidos, (string)(new Database('sqlite:' . self::$dir . '/genealogy.sqlite'))->fetchValue('SELECT apellidos FROM personas WHERE id = ?', [$persona]));
    }
}

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
        $this->assertSame(count($this->repo()->pendientes()), substr_count($body, 'name="solicitud"'), 'one form per pending solicitud, and none for the closed ones');
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
}

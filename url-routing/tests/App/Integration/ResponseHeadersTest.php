<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The headers of a response, which PagesTest cannot see: the CLI has no real
 * HTTP response, so header() does nothing there (see tests/Support/request.php).
 * These requests go to PHP's built-in web server instead.
 *
 * What it guards: a page that is hidden from a visitor must be
 * indistinguishable from one that does not exist, headers included. The ids are
 * guessable URLs, so any header that only the hidden page sent would tell
 * anyone which private collections and orders exist.
 */
class ResponseHeadersTest extends TestCase
{
    private const GENEALOGIA = 'tudominio.local';
    private const POS = 'contrastocolor.local';
    private const TOKEN = 'token-de-prueba-0123456789';

    /** Headers that legitimately differ between two requests to the same page. */
    private const VARIABLES = ['date', 'set-cookie'];

    private static string $dir;
    private static int $puerto;
    /** @var resource|null */
    private static $servidor = null;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-headers-' . getmypid();
        @mkdir(self::$dir, 0777, true);

        foreach (['genealogy', 'pos'] as $site) {
            $migrator = Migrator::forSite(new Database('sqlite:' . self::$dir . "/{$site}.sqlite"), $site);
            $migrator->migrate();
            $migrator->seed();
        }

        // A free port: ask the OS for one, release it and hand it to the server.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket, 'No free port for the test server');
        self::$puerto = (int)substr((string)stream_socket_get_name($socket, false), strlen('127.0.0.1:'));
        fclose($socket);

        // Sessions go to a folder of their own, to count what a request leaves behind.
        @mkdir(self::$dir . '/sesiones', 0777, true);

        $publico = dirname(__DIR__, 3) . '/public';
        self::$servidor = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . self::$dir . '/sesiones', '-S', '127.0.0.1:' . self::$puerto, '-t', $publico, $publico . '/index.php'],
            [1 => ['file', self::$dir . '/server.out', 'w'], 2 => ['file', self::$dir . '/server.err', 'w']],
            $pipes,
            null,
            [
                'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
                'DB_DSN_POS' => 'sqlite:' . self::$dir . '/pos.sqlite',
                'LOG_FILE' => self::$dir . '/app.log',
                'OWNER_TOKEN' => self::TOKEN,
                'PATH' => (string)getenv('PATH'),
            ]
        );

        for ($intento = 0; $intento < 50; $intento++) {
            $conexion = @fsockopen('127.0.0.1', self::$puerto, $errno, $error, 0.1);
            if ($conexion !== false) {
                fclose($conexion);
                return;
            }
            usleep(100_000);
        }

        self::fail('The built-in server did not start: ' . (string)@file_get_contents(self::$dir . '/server.err'));
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$servidor)) {
            proc_terminate(self::$servidor);
            proc_close(self::$servidor);
        }
        exec('rm -rf ' . escapeshellarg(self::$dir));
    }

    /**
     * @param array<string, string> $cabeceras request headers
     * @return array{status: int, headers: array<string, list<string>>, body: string}
     */
    private function pedir(string $host, string $uri, array $cabeceras = []): array
    {
        $lineas = ["Host: {$host}"];
        foreach ($cabeceras as $nombre => $valor) {
            $lineas[] = "{$nombre}: {$valor}";
        }

        $contexto = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $lineas),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
        ]]);
        $cuerpo = file_get_contents('http://127.0.0.1:' . self::$puerto . $uri, false, $contexto);
        $this->assertNotFalse($cuerpo, "No response for {$uri}");

        $respuesta = ['status' => 0, 'headers' => [], 'body' => $cuerpo];
        foreach ($http_response_header as $linea) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $linea, $m) === 1) {
                $respuesta['status'] = (int)$m[1];
            } elseif (str_contains($linea, ':')) {
                [$nombre, $valor] = explode(':', $linea, 2);
                $respuesta['headers'][strtolower(trim($nombre))][] = trim($valor);
            }
        }

        return $respuesta;
    }

    /**
     * Everything about a response that one request may share with another: status,
     * body and headers, without the ones that change on every request.
     *
     * @param array{status: int, headers: array<string, list<string>>, body: string} $r
     * @return array{int, string, array<string, list<string>>}
     */
    private static function huella(array $r): array
    {
        $cabeceras = array_diff_key($r['headers'], array_flip(self::VARIABLES));
        ksort($cabeceras);

        return [$r['status'], $r['body'], $cabeceras];
    }

    /** @return array<string, array{string, string, string}> [host, hidden uri, missing uri of the same kind] */
    public static function paginasOcultas(): array
    {
        return [
            'coleccion privada' => [self::GENEALOGIA, '/1048294/', '/1048200/'],
            'coleccion privada, arbol' => [self::GENEALOGIA, '/1048294/1/', '/1048200/1/'],
            'coleccion privada, editar' => [self::GENEALOGIA, '/1048294/2/', '/1048200/2/'],
            'coleccion privada, GEDCOM' => [self::GENEALOGIA, '/1048294/3/', '/1048200/3/'],
            'pedido de otra persona' => [self::POS, '/7305918264731590/', '/4821937065189999/'],
            'pedido, factura' => [self::POS, '/4821937065182734/1/', '/4821937065189999/1/'],
        ];
    }

    #[DataProvider('paginasOcultas')]
    public function testAHiddenPageAnswersExactlyLikeAMissingOne(string $host, string $oculta, string $inexistente): void
    {
        $ocultaR = $this->pedir($host, $oculta);
        $faltaR = $this->pedir($host, $inexistente);

        // The comparison below is only worth something if headers are being observed at all.
        $this->assertSame(404, $faltaR['status']);
        $this->assertArrayHasKey('cache-control', $faltaR['headers'], 'The test server sent no Cache-Control: headers are not being observed');
        $this->assertArrayHasKey('x-content-type-options', $faltaR['headers']);

        $this->assertSame(self::huella($faltaR), self::huella($ocultaR), "{$oculta} must look exactly like {$inexistente}");
    }

    /** The owner's pages do depend on who asks, so a shared cache must not keep them. */
    public function testTheOwnersPagesAreMarkedPrivate(): void
    {
        $respuesta = $this->pedir(self::GENEALOGIA, '/1048294/', ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame(['private, no-store'], $respuesta['headers']['cache-control']);
        $this->assertContains('Authorization', $respuesta['headers']['vary']);
    }

    /** What is known to exist does ask for the credentials; only its absence is a 404. */
    public function testTheAccountAreaAsksForCredentialsInsteadOfPretendingNotToExist(): void
    {
        $respuesta = $this->pedir(self::GENEALOGIA, '/0/');

        $this->assertSame(401, $respuesta['status']);
        $this->assertArrayHasKey('www-authenticate', $respuesta['headers']);
    }

    /**
     * ob_gzhandler already sends `Vary: Accept-Encoding` on every response, compressed or not, so
     * HttpSecurityHeaders must not send its own: it was harmless but every response carried the same
     * header twice. It has to be there exactly once, also next to the owner's `Vary: Authorization`.
     */
    public function testVaryAcceptEncodingIsSentOnceWhetherOrNotTheResponseIsCompressed(): void
    {
        if (!extension_loaded('zlib')) {
            $this->markTestSkipped('Without zlib the app neither compresses nor sends Vary: Accept-Encoding.');
        }

        $propietario = ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)];
        $casos = [
            'home, sin comprimir' => ['/', []],
            'home, comprimida' => ['/', ['Accept-Encoding' => 'gzip']],
            'un 404, comprimido' => ['/99999999/', ['Accept-Encoding' => 'gzip']],
            'pagina del propietario, sin comprimir' => ['/1048294/', $propietario],
            'pagina del propietario, comprimida' => ['/1048294/', $propietario + ['Accept-Encoding' => 'gzip']],
        ];
        foreach ($casos as $nombre => [$uri, $cabeceras]) {
            $respuesta = $this->pedir(self::GENEALOGIA, $uri, $cabeceras);

            $valores = [];
            foreach ($respuesta['headers']['vary'] ?? [] as $linea) {
                foreach (explode(',', $linea) as $valor) {
                    $valores[] = strtolower(trim($valor));
                }
            }
            $this->assertSame(1, count(array_keys($valores, 'accept-encoding', true)), "{$nombre}: Vary was " . implode(' | ', $respuesta['headers']['vary'] ?? ['(none)']));
        }
    }

    // --- sessions: only for whoever needs one -------------------------------------------------------

    private static function archivosDeSesion(): int
    {
        return count(glob(self::$dir . '/sesiones/sess_*') ?: []);
    }

    private static function cookieDeSesion(array $respuesta): ?string
    {
        foreach ($respuesta['headers']['set-cookie'] ?? [] as $cookie) {
            if (preg_match('/^PHPSESSID=([^;]+)/', $cookie, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /** @return array<string, array{string, string}> [host, uri] */
    public static function paginasQueSoloSeLeen(): array
    {
        return [
            'inicio' => [self::GENEALOGIA, '/'],
            'una persona' => [self::GENEALOGIA, '/6128473101/'],
            'una persona que podria vivir' => [self::GENEALOGIA, '/6128473105/'],
            'la busqueda' => [self::GENEALOGIA, '/b/10/garcia/'],
            'una pagina que no existe' => [self::GENEALOGIA, '/99999999/'],
            'una coleccion privada' => [self::GENEALOGIA, '/1048294/'],
            'el POS' => [self::POS, '/'],
        ];
    }

    /**
     * Reading a page is not a reason to open a session: a visitor who only looks gets no cookie, and the server
     * keeps nothing about them. A crawler never sends the cookie back, so with a session per request each of its
     * hits used to leave a file behind.
     */
    #[DataProvider('paginasQueSoloSeLeen')]
    public function testAVisitorWhoOnlyReadsGetsNoCookieAndLeavesNoSession(string $host, string $uri): void
    {
        $antes = self::archivosDeSesion();

        $respuesta = $this->pedir($host, $uri);

        $this->assertNotSame(0, $respuesta['status']);
        $this->assertArrayNotHasKey('set-cookie', $respuesta['headers'], "{$uri} must not set a cookie");
        $this->assertSame($antes, self::archivosDeSesion(), "{$uri} must not leave a session file");
    }

    /**
     * Not opening a session must not make a page cacheable: PHP's session was what sent these headers, and a
     * withdrawal has to take effect on the next request, not when a cache expires.
     */
    #[DataProvider('paginasQueSoloSeLeen')]
    public function testWithoutASessionEveryResponseIsStillNotCacheable(string $host, string $uri): void
    {
        $respuesta = $this->pedir($host, $uri);

        $this->assertSame(['no-store, no-cache, must-revalidate'], $respuesta['headers']['cache-control'], $uri);
        $this->assertSame(['no-cache'], $respuesta['headers']['pragma'], $uri);
        $this->assertSame(['Thu, 19 Nov 1981 08:52:00 GMT'], $respuesta['headers']['expires'], $uri);
    }

    /** A page with a form needs a CSRF token that survives until the POST, so it is what starts the session. */
    public function testAPageWithAFormStartsASession(): void
    {
        $antes = self::archivosDeSesion();

        $respuesta = $this->pedir(self::GENEALOGIA, '/r/6128473105/');

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('name="csrf_token"', $respuesta['body']);
        $cookie = self::cookieDeSesion($respuesta);
        $this->assertNotNull($cookie, 'the form has a token to keep, so it sets the cookie');
        $this->assertSame($antes + 1, self::archivosDeSesion());
        $this->assertStringContainsString('HttpOnly', implode(';', $respuesta['headers']['set-cookie']));
        $this->assertStringContainsString('SameSite=Lax', implode(';', $respuesta['headers']['set-cookie']));
    }

    /**
     * The owner who logs in with the credentials gets a session, and later requests with only the cookie are
     * recognised as theirs: browsing public pages after /0/ still shows what only the owner sees.
     */
    public function testTheOwnerGetsASessionWhenLoggingInAndItIsResumedWithJustTheCookie(): void
    {
        $respuesta = $this->pedir(self::GENEALOGIA, '/0/', ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)]);
        $this->assertSame(200, $respuesta['status']);
        $cookie = self::cookieDeSesion($respuesta);
        $this->assertNotNull($cookie, 'logging in opens the session');

        // Only the cookie now: no credentials. The collection is private, so this is the owner or a 404.
        $conCookie = $this->pedir(self::GENEALOGIA, '/1048294/', ['Cookie' => "PHPSESSID={$cookie}"]);
        $this->assertSame(200, $conCookie['status'], 'the session is the owner\'s');
        $this->assertSame(['private, no-store'], $conCookie['headers']['cache-control']);

        $sinCookie = $this->pedir(self::GENEALOGIA, '/1048294/');
        $this->assertSame(404, $sinCookie['status'], 'without the cookie it is the visitor\'s 404');
    }
}

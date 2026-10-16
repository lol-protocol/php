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

        $publico = dirname(__DIR__, 3) . '/public';
        self::$servidor = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$puerto, '-t', $publico, $publico . '/index.php'],
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
            'pedido de otra persona' => [self::POS, '/order/8137204719001/', '/order/8137204719999/'],
            'pedido, factura' => [self::POS, '/order/8137204719000/1/', '/order/8137204719999/1/'],
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
}

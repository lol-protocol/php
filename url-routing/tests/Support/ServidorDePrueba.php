<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Database;
use App\Support\Migrator;

/**
 * A real HTTP server for a test class: both sites on seeded SQLite files, served by PHP's built-in server, so
 * that what the CLI cannot show (response headers, status lines) can be seen. Use it from a class that extends
 * TestCase: call servidorIniciar() in setUpBeforeClass() and servidorDetener() in tearDownAfterClass().
 *
 * The state is static, and a trait's static properties belong to each class that uses it, so two test classes
 * never share a server or a folder.
 */
trait ServidorDePrueba
{
    private static string $dir;
    private static int $puerto;
    /** @var resource|null */
    private static $servidor = null;

    /**
     * Migrates and seeds both sites in a folder of their own, lets $preparar change them (it gets the folder),
     * and starts the server on a free port.
     *
     * @param array<string, string> $entorno environment of the server, over the defaults (URL_LOG_DIR: off); `{dir}` in a
     *                                       value is the test's folder
     * @param (callable(string): void)|null $preparar
     */
    private static function servidorIniciar(string $nombre, array $entorno = [], ?callable $preparar = null): void
    {
        self::$dir = sys_get_temp_dir() . "/url-routing-{$nombre}-" . getmypid();
        // Sessions go to a folder of their own, to count what a request leaves behind.
        @mkdir(self::$dir . '/sesiones', 0777, true);

        foreach (['genealogy', 'pos'] as $site) {
            $migrator = Migrator::forSite(new Database('sqlite:' . self::$dir . "/{$site}.sqlite"), $site);
            $migrator->migrate();
            $migrator->seed();
        }
        if ($preparar !== null) {
            $preparar(self::$dir);
        }

        // A free port: ask the OS for one, release it and hand it to the server.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket, 'No free port for the test server');
        self::$puerto = (int)substr((string)stream_socket_get_name($socket, false), strlen('127.0.0.1:'));
        fclose($socket);

        $publico = dirname(__DIR__, 2) . '/public';
        self::$servidor = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . self::$dir . '/sesiones', '-S', '127.0.0.1:' . self::$puerto, '-t', $publico, $publico . '/index.php'],
            [1 => ['file', self::$dir . '/server.out', 'w'], 2 => ['file', self::$dir . '/server.err', 'w']],
            $pipes,
            null,
            array_map(static fn(string $v): string => str_replace('{dir}', self::$dir, $v), $entorno) + [
                'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
                'DB_DSN_POS' => 'sqlite:' . self::$dir . '/pos.sqlite',
                'LOG_FILE' => self::$dir . '/app.log',
                'URL_LOG_DIR' => 'off',
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

    private static function servidorDetener(): void
    {
        if (is_resource(self::$servidor)) {
            proc_terminate(self::$servidor);
            proc_close(self::$servidor);
        }
        exec('rm -rf ' . escapeshellarg(self::$dir));
    }

    /**
     * The limiter allows 100 requests a minute per IP and every request here comes from 127.0.0.1: a class that
     * makes more starts again from zero (only the limiter's own files go).
     */
    private static function reiniciarLimitador(): void
    {
        foreach (glob(sys_get_temp_dir() . '/rate_limit_*') ?: [] as $archivo) {
            if (preg_match('/^rate_limit_[0-9a-f]{64}$/', basename($archivo)) === 1) {
                @unlink($archivo);
            }
        }
    }

    /**
     * One GET, without following redirects.
     *
     * @param array<string, string> $cabeceras request headers
     * @return array{status: int, headers: array<string, list<string>>, body: string}
     */
    private function pedirAlServidor(string $host, string $uri, array $cabeceras = []): array
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
}

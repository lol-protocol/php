<?php

declare(strict_types=1);

namespace App\Tests\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Levanta la app de verdad (php -S sobre public/, con las mismas variables de
 * entorno que los tests) y le habla por HTTP como un navegador: con la cookie
 * de sesion, los tokens sacados del HTML y sin seguir las redirecciones.
 *
 * Es para lo que los tests de adentro no pueden ver: el cableado de
 * public/index.php (router, login obligatorio, normalizacion de parametros) y
 * los controllers, que terminan en header() + exit. Es la verificacion con
 * curl que antes se hacia a mano en cada ronda, ahora automatica.
 *
 * El servidor escribe con su propia conexion, asi que lo que un test crea
 * queda commiteado: cada test registra como borrarlo con alTerminar().
 */
abstract class HttpTestCase extends TestCase
{
    /** @var resource|null */
    private static $servidor = null;
    private static string $url = '';
    private static string $log = '';

    private ?string $cookieDeSesion = null;

    /** @var list<callable(): void> */
    private array $limpiezas = [];

    public static function setUpBeforeClass(): void
    {
        // Un puerto libre: se le pide uno al sistema y se lo suelta enseguida.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('No se pudo reservar un puerto para el servidor de prueba.');
        }
        $puerto = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$log = (string) tempnam(sys_get_temp_dir(), 'servidor-http-');
        $servidor = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$puerto}", '-t', dirname(__DIR__, 2) . '/public'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']],
            $tuberias
        );
        if ($servidor === false) {
            throw new RuntimeException('No se pudo lanzar php -S.');
        }
        self::$servidor = $servidor;
        self::$url = "http://127.0.0.1:{$puerto}/index.php";

        for ($intento = 0; $intento < 100; $intento++) {
            $conexion = @fsockopen('127.0.0.1', $puerto);
            if ($conexion !== false) {
                fclose($conexion);
                return;
            }
            usleep(50_000);
        }
        throw new RuntimeException('El servidor de prueba no arranco: ' . file_get_contents(self::$log));
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$servidor)) {
            proc_terminate(self::$servidor);
            proc_close(self::$servidor);
        }
        self::$servidor = null;
        @unlink(self::$log);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->limpiezas) as $limpieza) {
            $limpieza();
        }
        $this->limpiezas = [];
        parent::tearDown();
    }

    /** Registra algo para deshacer al final del test (corre aunque el test falle, en orden inverso). */
    protected function alTerminar(callable $limpieza): void
    {
        $this->limpiezas[] = $limpieza;
    }

    /** @return array{status: int, location: ?string, cuerpo: string} */
    protected function get(string $query): array
    {
        return $this->pedir($query, null);
    }

    /**
     * @param array<string, scalar> $datos
     * @return array{status: int, location: ?string, cuerpo: string}
     */
    protected function post(string $query, array $datos): array
    {
        return $this->pedir($query, $datos);
    }

    protected function iniciarSesion(): void
    {
        $login = $this->get('page=login');
        $respuesta = $this->post('page=login', [
            'email' => 'admin@ejemplo.com',
            'password' => 'admin1234',
            'csrf_token' => self::campoOculto($login['cuerpo'], 'csrf_token'),
        ]);
        $this->assertStatus(302, $respuesta, 'el login con el usuario del seed tiene que funcionar');
    }

    /** El value de un <input type="hidden"> del HTML (el token CSRF o el de envio). */
    protected static function campoOculto(string $html, string $nombre): string
    {
        if (preg_match('/name="' . preg_quote($nombre, '/') . '" value="([^"]*)"/', $html, $coincidencia) !== 1) {
            self::fail("El HTML no tiene el campo {$nombre}.");
        }

        return html_entity_decode($coincidencia[1], ENT_QUOTES);
    }

    /** El numero de un parametro de la redireccion (ej. 'creado' en ?page=pagos&creado=12). */
    protected static function idDeLaRedireccion(array $respuesta, string $parametro): int
    {
        parse_str((string) parse_url((string) $respuesta['location'], PHP_URL_QUERY), $query);
        if (!isset($query[$parametro]) || !is_numeric($query[$parametro])) {
            self::fail("La redireccion '{$respuesta['location']}' no trae {$parametro}.");
        }

        return (int) $query[$parametro];
    }

    /** @param array{status: int, location: ?string, cuerpo: string} $respuesta */
    protected function assertStatus(int $esperado, array $respuesta, string $contexto = ''): void
    {
        self::assertSame(
            $esperado,
            $respuesta['status'],
            trim($contexto . "\nFinal del log del servidor:\n" . implode("\n", array_slice(file(self::$log) ?: [], -15)))
        );
    }

    /**
     * @param array<string, scalar>|null $datos null = GET
     * @return array{status: int, location: ?string, cuerpo: string}
     */
    private function pedir(string $query, ?array $datos): array
    {
        $curl = curl_init(self::$url . '?' . $query);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($this->cookieDeSesion !== null && $this->cookieDeSesion !== '') {
            curl_setopt($curl, CURLOPT_COOKIE, $this->cookieDeSesion);
        }
        if ($datos !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($datos));
        }

        $respuesta = curl_exec($curl);
        if (!is_string($respuesta)) {
            throw new RuntimeException('La peticion a ?' . $query . ' fallo: ' . curl_error($curl));
        }
        $largoCabeceras = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $cabeceras = substr($respuesta, 0, $largoCabeceras);

        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;\r\n]+)/mi', $cabeceras, $cookie) === 1) {
            $this->cookieDeSesion = $cookie[1];
        }
        $location = preg_match('/^Location: ([^\r\n]+)/mi', $cabeceras, $destino) === 1 ? $destino[1] : null;

        return ['status' => $status, 'location' => $location, 'cuerpo' => substr($respuesta, $largoCabeceras)];
    }
}

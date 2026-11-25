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
 * public/index.php (router, normalizacion de parametros) y los controllers,
 * que terminan en header() + exit. Es la verificacion con curl que antes se
 * hacia a mano en cada ronda, ahora automatica.
 *
 * El servidor escribe con su propia conexion, asi que lo que un test crea
 * queda commiteado: cada test registra como borrarlo con alTerminar().
 */
abstract class HttpTestCase extends TestCase
{
    /** @var list<resource> */
    private static array $servidores = [];

    /** @var list<string> la direccion de cada servidor, en el mismo orden */
    private static array $urls = [];
    private static string $log = '';

    private ?string $cookieDeSesion = null;

    /** @var list<callable(): void> */
    private array $limpiezas = [];

    /**
     * Cuantos servidores (procesos de php -S) levanta la clase. Uno, que es lo normal: las peticiones se hacen de a
     * una. Una prueba de concurrencia pide mas para que dos peticiones corran de verdad a la vez, cada una en su
     * proceso y con su propia conexion a la base. No sirve PHP_CLI_SERVER_WORKERS: los trabajadores comparten el
     * socket y, si dos conexiones llegan juntas, uno puede aceptar las dos y atenderlas una tras otra.
     */
    protected static function servidores(): int
    {
        return 1;
    }

    public static function setUpBeforeClass(): void
    {
        self::$log = (string) tempnam(sys_get_temp_dir(), 'servidor-http-');
        for ($i = 0; $i < static::servidores(); $i++) {
            self::levantarUnServidor();
        }
    }

    private static function levantarUnServidor(): void
    {
        // Un puerto libre: se le pide uno al sistema y se lo suelta enseguida.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('No se pudo reservar un puerto para el servidor de prueba.');
        }
        $puerto = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $servidor = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$puerto}", '-t', dirname(__DIR__, 2) . '/public'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']],
            $tuberias
        );
        if ($servidor === false) {
            throw new RuntimeException('No se pudo lanzar php -S.');
        }
        self::$servidores[] = $servidor;
        self::$urls[] = "http://127.0.0.1:{$puerto}/index.php";

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
        foreach (self::$servidores as $servidor) {
            proc_terminate($servidor);
            proc_close($servidor);
        }
        self::$servidores = [];
        self::$urls = [];
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

    /**
     * Las filas de un CSV, ya leidas (la primera es el encabezado). Comprueba de paso la
     * marca de orden UTF-8 del principio y la saca.
     *
     * @return list<list<string|null>>
     */
    protected static function leer(string $cuerpo): array
    {
        self::assertStringStartsWith("\xEF\xBB\xBF", $cuerpo, 'el archivo empieza con la marca UTF-8');
        $flujo = fopen('php://memory', 'w+');
        self::assertNotFalse($flujo);
        fwrite($flujo, substr($cuerpo, 3));
        rewind($flujo);
        $filas = [];
        while (($fila = fgetcsv($flujo, null, ',', '"', '')) !== false) {
            $filas[] = $fila;
        }

        return $filas;
    }

    /** Registra algo para deshacer al final del test (corre aunque el test falle, en orden inverso). */
    protected function alTerminar(callable $limpieza): void
    {
        $this->limpiezas[] = $limpieza;
    }

    /** @return array{status: int, location: ?string, cuerpo: string, cabeceras: string} */
    protected function get(string $query): array
    {
        return $this->pedir($query, null);
    }

    /**
     * @param array<string, scalar> $datos
     * @return array{status: int, location: ?string, cuerpo: string, cabeceras: string}
     */
    protected function post(string $query, array $datos): array
    {
        return $this->pedir($query, $datos);
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

    /** @param array{status: int, location: ?string, cuerpo: string, cabeceras: string} $respuesta */
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
     * @return array{status: int, location: ?string, cuerpo: string, cabeceras: string}
     */
    private function pedir(string $query, ?array $datos): array
    {
        $curl = self::manija($query, $datos, $this->cookieDeSesion);
        $respuesta = curl_exec($curl);
        if (!is_string($respuesta)) {
            throw new RuntimeException('La peticion a ?' . $query . ' fallo: ' . curl_error($curl));
        }
        $resultado = self::interpretar($curl, $respuesta);

        if (($cookie = self::cookieDe($resultado['cabeceras'])) !== null) {
            $this->cookieDeSesion = $cookie;
        }

        return $resultado;
    }

    /**
     * Una sesion de navegador aparte, con su propia cookie, que no toca la del test: dos personas distintas. Pide
     * $query (la pagina de un formulario) y devuelve su cookie y su HTML, de donde salen los tokens. Hace falta una
     * cookie por cada peticion de enParalelo(): PHP serializa las peticiones de una misma sesion (el archivo de
     * sesion queda bloqueado), asi que con una sola no habria concurrencia.
     *
     * @return array{cookie: string, cuerpo: string}
     */
    protected function sesionAparte(string $query): array
    {
        $curl = self::manija($query, null, null);
        $respuesta = curl_exec($curl);
        if (!is_string($respuesta)) {
            throw new RuntimeException('La peticion a ?' . $query . ' fallo: ' . curl_error($curl));
        }
        $resultado = self::interpretar($curl, $respuesta);
        $cookie = self::cookieDe($resultado['cabeceras']);
        if ($cookie === null) {
            self::fail("?{$query} no abrio una sesion (no mando PHPSESSID).");
        }

        return ['cookie' => $cookie, 'cuerpo' => $resultado['cuerpo']];
    }

    /**
     * Varias peticiones a la vez, cada una a un servidor distinto (la clase tiene que pedir al menos tantos con
     * servidores()): procesos de verdad distintos. Cada una con su cookie (ver sesionAparte()); `despues` las
     * escalona: segundos desde el inicio en que sale esa peticion, para que una caiga en medio de lo que hace la otra.
     * Devuelve las respuestas en el orden pedido.
     *
     * @param list<array{query: string, cookie: string, datos?: array<string, scalar>, despues?: float}> $peticiones
     * @return list<array{status: int, location: ?string, cuerpo: string, cabeceras: string}>
     */
    protected function enParalelo(array $peticiones): array
    {
        if (count($peticiones) > count(self::$urls)) {
            self::fail(sprintf('Se pidieron %d peticiones en paralelo y la clase levanta %d servidores (servidores()).', count($peticiones), count(self::$urls)));
        }
        $multi = curl_multi_init();
        $manijas = [];
        $sinSalir = array_keys($peticiones);
        $inicio = microtime(true);
        $activas = 0;

        do {
            foreach ($sinSalir as $posicion => $indice) {
                if (microtime(true) - $inicio >= ($peticiones[$indice]['despues'] ?? 0.0)) {
                    $peticion = $peticiones[$indice];
                    $manijas[$indice] = self::manija($peticion['query'], $peticion['datos'] ?? null, $peticion['cookie'], $indice);
                    curl_multi_add_handle($multi, $manijas[$indice]);
                    unset($sinSalir[$posicion]);
                }
            }
            curl_multi_exec($multi, $activas);
            if ($activas > 0) {
                curl_multi_select($multi, 0.05);
            } elseif ($sinSalir !== []) {
                usleep(10_000);
            }
        } while ($activas > 0 || $sinSalir !== []);

        $respuestas = [];
        foreach ($manijas as $indice => $manija) {
            $cuerpo = curl_multi_getcontent($manija);
            if (!is_string($cuerpo) || curl_errno($manija) !== 0) {
                throw new RuntimeException('La peticion paralela a ?' . $peticiones[$indice]['query'] . ' fallo: ' . curl_error($manija));
            }
            $respuestas[$indice] = self::interpretar($manija, $cuerpo);
            curl_multi_remove_handle($multi, $manija);
        }
        curl_multi_close($multi);
        ksort($respuestas);

        return array_values($respuestas);
    }

    /** @param array<string, scalar>|null $datos null = GET */
    private static function manija(string $query, ?array $datos, ?string $cookie, int $servidor = 0): \CurlHandle
    {
        $curl = curl_init(self::$urls[$servidor] . '?' . $query);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($cookie !== null && $cookie !== '') {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        if ($datos !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($datos));
        }

        return $curl;
    }

    /** @return array{status: int, location: ?string, cuerpo: string, cabeceras: string} */
    private static function interpretar(\CurlHandle $curl, string $respuesta): array
    {
        $largoCabeceras = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $cabeceras = substr($respuesta, 0, $largoCabeceras);
        $location = preg_match('/^Location: ([^\r\n]+)/mi', $cabeceras, $destino) === 1 ? $destino[1] : null;

        return [
            'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'location' => $location,
            'cuerpo' => substr($respuesta, $largoCabeceras),
            'cabeceras' => $cabeceras,
        ];
    }

    /** La cookie de sesion (PHPSESSID=...) que manda una respuesta, si manda una. */
    private static function cookieDe(string $cabeceras): ?string
    {
        return preg_match('/^Set-Cookie: (PHPSESSID=[^;\r\n]+)/mi', $cabeceras, $cookie) === 1 ? $cookie[1] : null;
    }
}

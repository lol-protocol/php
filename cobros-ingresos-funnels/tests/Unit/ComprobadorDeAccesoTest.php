<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Acceso\ComprobadorDeAcceso;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * El comprobador de acceso, de dos maneras: con una funcion que contesta lo que cada test necesita
 * (sin red), y por HTTP de verdad contra un php -S local que hace de panel protegido, abierto, a
 * medias o con redireccion, y el comando bin/comprobar_acceso.php corrido en otro proceso.
 */
final class ComprobadorDeAccesoTest extends TestCase
{
    /** @var resource|null */
    private static $servidor = null;
    private static string $base = '';
    private static string $carpeta = '';

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('No se pudo reservar un puerto para el servidor de prueba.');
        }
        $puerto = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$carpeta = sys_get_temp_dir() . '/acceso-' . bin2hex(random_bytes(4));
        mkdir(self::$carpeta);
        // El primer tramo de la ruta elige como se porta el "panel": /cerrado, /abierto, /mixto, /redirige, /roto.
        file_put_contents(self::$carpeta . '/router.php', <<<'PHP'
            <?php
            $partes = explode('/', trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
            $pantalla = $_GET['page'] ?? '';
            $pedirCredenciales = static function (): void {
                http_response_code(401);
                header('WWW-Authenticate: Basic realm="Panel"');
            };
            switch ($partes[0]) {
                case 'cerrado':
                    $pedirCredenciales();
                    break;
                case 'cerrado-con-403':
                    http_response_code(403);
                    break;
                case 'abierto':
                    echo 'el panel';
                    break;
                case 'mixto':   // protegida la raiz, abierta una pantalla
                    $pantalla === 'clientes' ? print('los clientes') : $pedirCredenciales();
                    break;
                case 'redirige':
                    http_response_code(301);
                    header('Location: https://panel.ejemplo.com/');
                    break;
                case 'roto':
                    http_response_code(502);
                    break;
                default:
                    http_response_code(404);
            }
            PHP);

        $servidor = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$puerto}", self::$carpeta . '/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $tuberias
        );
        if ($servidor === false) {
            throw new RuntimeException('No se pudo lanzar php -S.');
        }
        self::$servidor = $servidor;
        self::$base = "http://127.0.0.1:{$puerto}";

        for ($intento = 0; $intento < 100; $intento++) {
            $conexion = @fsockopen('127.0.0.1', $puerto);
            if ($conexion !== false) {
                fclose($conexion);
                return;
            }
            usleep(50_000);
        }
        throw new RuntimeException('El servidor de prueba no arranco.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$servidor)) {
            proc_terminate(self::$servidor);
            proc_close(self::$servidor);
        }
        self::$servidor = null;
        @unlink(self::$carpeta . '/router.php');
        @rmdir(self::$carpeta);
    }

    /**
     * @param array<string, int|null> $estados estado por URL; el resto contesta $porDefecto
     */
    private static function comprobador(array $estados, ?int $porDefecto, ?string $destino = null): ComprobadorDeAcceso
    {
        return new ComprobadorDeAcceso(static function (string $url) use ($estados, $porDefecto, $destino): array {
            $estado = array_key_exists($url, $estados) ? $estados[$url] : $porDefecto;

            return ['estado' => $estado, 'destino' => $destino, 'error' => $estado === null ? 'sin conexion' : null];
        });
    }

    public function testTodasLasPantallasConCredencialesPedidasEstanProtegidas(): void
    {
        foreach ([401, 403] as $estado) {
            $informe = self::comprobador([], $estado)->comprobar('https://panel.ejemplo.com');

            self::assertSame(ComprobadorDeAcceso::PROTEGIDO, $informe['resultado'], (string) $estado);
            self::assertCount(count(ComprobadorDeAcceso::RUTAS), $informe['pantallas']);
        }
    }

    public function testUnaSolaPantallaQueContestaBastaParaDecirQueEstaAbierto(): void
    {
        $informe = self::comprobador(['https://panel.ejemplo.com/?page=clientes' => 200], 401)->comprobar('https://panel.ejemplo.com');

        self::assertSame(ComprobadorDeAcceso::ABIERTO, $informe['resultado']);
        $abiertas = array_values(array_filter($informe['pantallas'], static fn (array $p): bool => $p['veredicto'] === ComprobadorDeAcceso::ABIERTO));
        self::assertCount(1, $abiertas);
        self::assertSame('/?page=clientes', $abiertas[0]['ruta']);
    }

    public function testAbiertoGanaSobreInconcluso(): void
    {
        $informe = self::comprobador(['https://panel.ejemplo.com/' => 200], 502)->comprobar('https://panel.ejemplo.com');

        self::assertSame(ComprobadorDeAcceso::ABIERTO, $informe['resultado']);
    }

    /** @return iterable<string, array{int|null}> */
    public static function respuestasQueNoPruebanNada(): iterable
    {
        yield 'redireccion' => [301];
        yield 'no encontrada' => [404];
        yield 'error del servidor' => [502];
        yield 'sin conexion' => [null];
    }

    #[DataProvider('respuestasQueNoPruebanNada')]
    public function testUnaRespuestaQueNoEsNiProtegidaNiAbiertaDejaElResultadoInconcluso(?int $estado): void
    {
        $informe = self::comprobador([], $estado, 'https://panel.ejemplo.com/')->comprobar('https://panel.ejemplo.com');

        self::assertSame(ComprobadorDeAcceso::INCONCLUSO, $informe['resultado']);
        self::assertNotNull($informe['pantallas'][0]['detalle']);
    }

    public function testUnaRedireccionDiceAdondeVa(): void
    {
        $informe = self::comprobador([], 301, 'https://panel.ejemplo.com/')->comprobar('http://panel.ejemplo.com');

        self::assertStringContainsString('redirige a https://panel.ejemplo.com/', (string) $informe['pantallas'][0]['detalle']);
    }

    public function testUnaPantallaProtegidaYOtraInconclusaNoSeDanPorProtegidas(): void
    {
        $informe = self::comprobador(['https://panel.ejemplo.com/' => 401], 404)->comprobar('https://panel.ejemplo.com');

        self::assertSame(ComprobadorDeAcceso::INCONCLUSO, $informe['resultado']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function direcciones(): iterable
    {
        yield 'sin barra final' => ['https://panel.ejemplo.com', 'https://panel.ejemplo.com'];
        yield 'con barra final' => ['https://panel.ejemplo.com/', 'https://panel.ejemplo.com'];
        yield 'con espacios' => ["  https://panel.ejemplo.com/ \n", 'https://panel.ejemplo.com'];
        yield 'mayusculas en el esquema' => ['HTTPS://panel.ejemplo.com', 'https://panel.ejemplo.com'];
        yield 'con puerto' => ['http://127.0.0.1:8000/', 'http://127.0.0.1:8000'];
        yield 'colgado de una ruta' => ['https://ejemplo.com/panel/', 'https://ejemplo.com/panel'];
    }

    #[DataProvider('direcciones')]
    public function testNormalizaLaDireccion(string $entrada, string $esperada): void
    {
        self::assertSame($esperada, ComprobadorDeAcceso::normalizar($entrada));
    }

    public function testLasPantallasSeAgreganDespuesDeLaRutaDelPanel(): void
    {
        $pedidas = [];
        $comprobador = new ComprobadorDeAcceso(static function (string $url) use (&$pedidas): array {
            $pedidas[] = $url;

            return ['estado' => 401, 'destino' => null, 'error' => null];
        });

        $comprobador->comprobar('https://ejemplo.com/panel/');

        self::assertSame('https://ejemplo.com/panel/', $pedidas[0]);
        self::assertContains('https://ejemplo.com/panel/?page=clientes', $pedidas);
    }

    /** @return iterable<string, array{string}> */
    public static function direccionesInvalidas(): iterable
    {
        yield 'vacia' => [''];
        yield 'sin esquema' => ['panel.ejemplo.com'];
        yield 'otro esquema' => ['ftp://panel.ejemplo.com'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'sin servidor' => ['https://'];
        yield 'con consulta' => ['https://panel.ejemplo.com/?page=clientes'];
        yield 'con fragmento' => ['https://panel.ejemplo.com/#x'];
        yield 'con usuario y clave' => ['https://usuario:clave@panel.ejemplo.com'];
    }

    #[DataProvider('direccionesInvalidas')]
    public function testRechazaLasDireccionesQueNoSonUnPanel(string $entrada): void
    {
        $this->expectException(InvalidArgumentException::class);

        ComprobadorDeAcceso::normalizar($entrada);
    }

    // ---- por HTTP de verdad ----

    public function testPorHttpUnPanelQuePideCredencialesEstaProtegido(): void
    {
        foreach (['cerrado', 'cerrado-con-403'] as $modo) {
            $informe = (new ComprobadorDeAcceso())->comprobar(self::$base . '/' . $modo);

            self::assertSame(ComprobadorDeAcceso::PROTEGIDO, $informe['resultado'], $modo);
        }
    }

    public function testPorHttpUnPanelQueContestaEstaAbierto(): void
    {
        $informe = (new ComprobadorDeAcceso())->comprobar(self::$base . '/abierto');

        self::assertSame(ComprobadorDeAcceso::ABIERTO, $informe['resultado']);
        self::assertSame(200, $informe['pantallas'][0]['estado']);
    }

    public function testPorHttpUnPanelProtegidoSoloEnLaRaizSeDetectaAbierto(): void
    {
        $informe = (new ComprobadorDeAcceso())->comprobar(self::$base . '/mixto');

        self::assertSame(ComprobadorDeAcceso::ABIERTO, $informe['resultado']);
        self::assertSame(401, $informe['pantallas'][0]['estado']);
    }

    public function testPorHttpUnaRedireccionNoSeSigueYQuedaInconclusa(): void
    {
        $informe = (new ComprobadorDeAcceso())->comprobar(self::$base . '/redirige');

        self::assertSame(ComprobadorDeAcceso::INCONCLUSO, $informe['resultado']);
        self::assertSame(301, $informe['pantallas'][0]['estado']);
        self::assertStringContainsString('https://panel.ejemplo.com/', (string) $informe['pantallas'][0]['detalle']);
    }

    public function testPorHttpUnServidorCaidoQuedaInconcluso(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $puerto = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);   // nadie escucha en ese puerto

        $informe = (new ComprobadorDeAcceso())->comprobar("http://127.0.0.1:{$puerto}");

        self::assertSame(ComprobadorDeAcceso::INCONCLUSO, $informe['resultado']);
        self::assertNull($informe['pantallas'][0]['estado']);
        self::assertStringContainsString('no se pudo conectar', (string) $informe['pantallas'][0]['detalle']);
    }

    // ---- el comando ----

    /**
     * @param list<string> $argumentos
     * @return array{codigo: int, salida: string, errores: string}
     */
    private static function ejecutar(array $argumentos = []): array
    {
        $raiz = dirname(__DIR__, 2);
        $proceso = proc_open(
            [PHP_BINARY, $raiz . '/bin/comprobar_acceso.php', ...$argumentos],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            $raiz
        );
        self::assertIsResource($proceso);
        $salida = (string) stream_get_contents($tuberias[1]);
        $errores = (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);

        return ['codigo' => proc_close($proceso), 'salida' => $salida, 'errores' => $errores];
    }

    public function testElComandoTerminaConCeroSiElPanelEstaProtegido(): void
    {
        $resultado = self::ejecutar([self::$base . '/cerrado']);

        self::assertSame(0, $resultado['codigo']);
        self::assertStringContainsString('PROTEGIDO', $resultado['salida']);
        self::assertSame('', $resultado['errores']);
    }

    public function testElComandoTerminaConUnoSiElPanelEstaAbiertoYDiceCualesPantallas(): void
    {
        $resultado = self::ejecutar([self::$base . '/mixto']);

        self::assertSame(1, $resultado['codigo']);
        self::assertMatchesRegularExpression('/abierto\s+200\s+\/\?page=clientes/', $resultado['salida']);
        self::assertMatchesRegularExpression('/protegido\s+401\s+\/\s/', $resultado['salida']);
        self::assertStringContainsString('ABIERTO', $resultado['errores']);
    }

    public function testElComandoTerminaConDosSiNoPuedeAfirmarNada(): void
    {
        $resultado = self::ejecutar([self::$base . '/redirige']);

        self::assertSame(2, $resultado['codigo']);
        self::assertStringContainsString('INCONCLUSO', $resultado['errores']);
        self::assertStringContainsString('redirige a https://panel.ejemplo.com/', $resultado['salida']);
    }

    public function testElComandoPideLaDireccionYRechazaLoQueNoLoEs(): void
    {
        $sin = self::ejecutar();
        self::assertSame(2, $sin['codigo']);
        self::assertStringContainsString('Uso:', $sin['errores']);

        $mala = self::ejecutar(['ftp://panel.ejemplo.com']);
        self::assertSame(2, $mala['codigo']);
        self::assertStringContainsString('http://', $mala['errores']);

        $desconocida = self::ejecutar(['--rapido']);
        self::assertSame(2, $desconocida['codigo']);

        $ayuda = self::ejecutar(['--ayuda']);
        self::assertSame(0, $ayuda['codigo']);
        self::assertStringContainsString('Uso:', $ayuda['salida']);
    }
}

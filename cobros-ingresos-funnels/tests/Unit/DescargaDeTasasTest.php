<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tasas\DescargaDeTasas;
use App\Tasas\RespuestaDeTasas;
use App\Tasas\TasasInvalidas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * La descarga de las tasas, contra un servidor de mentira en la propia maquina
 * (php -S) que contesta lo que cada test necesita: un JSON, un error, una
 * redireccion, una respuesta gigante. Sin red de verdad.
 */
final class DescargaDeTasasTest extends TestCase
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

        self::$carpeta = sys_get_temp_dir() . '/tasas-' . bin2hex(random_bytes(4));
        mkdir(self::$carpeta);
        file_put_contents(self::$carpeta . '/router.php', <<<'PHP'
            <?php
            switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
                case '/ok':
                    header('Content-Type: application/json');
                    echo '{"base_code": "USD", "rates": {"EUR": 0.86}}';
                    break;
                case '/caido':
                    http_response_code(503);
                    echo 'en mantenimiento';
                    break;
                case '/redirige':
                    http_response_code(302);
                    header('Location: /ok');
                    break;
                case '/gigante':
                    echo str_repeat('x', 3000000);
                    break;
                default:
                    http_response_code(404);
                    echo 'no existe';
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

    public function testBajaElJsonYSePuedeLeerComoRespuestaDeTasas(): void
    {
        $respuesta = RespuestaDeTasas::desdeJson(DescargaDeTasas::leer(self::$base . '/ok'));

        self::assertSame(['EUR' => '0.86'], $respuesta->unidadesPorUsd);
    }

    public function testUnErrorDelServidorSeInformaConSuCodigo(): void
    {
        $this->expectException(TasasInvalidas::class);
        $this->expectExceptionMessage('respondió HTTP 503');

        DescargaDeTasas::leer(self::$base . '/caido');
    }

    public function testUnaDireccionQueNoExisteSeInformaComo404(): void
    {
        $this->expectException(TasasInvalidas::class);
        $this->expectExceptionMessage('respondió HTTP 404');

        DescargaDeTasas::leer(self::$base . '/no-esta');
    }

    /** Una redireccion de https a http dejaria pasar unas tasas adulteradas: no se siguen. */
    public function testNoSigueRedirecciones(): void
    {
        $this->expectException(TasasInvalidas::class);
        $this->expectExceptionMessage('respondió HTTP 302');

        DescargaDeTasas::leer(self::$base . '/redirige');
    }

    public function testUnaRespuestaGiganteNoEsUnaListaDeTasas(): void
    {
        $this->expectException(TasasInvalidas::class);
        $this->expectExceptionMessage('demasiado grande');

        DescargaDeTasas::leer(self::$base . '/gigante');
    }

    public function testSiNadieContestaSeInformaQueNoSePudoDescargar(): void
    {
        $this->expectException(TasasInvalidas::class);
        $this->expectExceptionMessage('No se pudo descargar');

        // Un puerto en el que no escucha nadie: se reserva uno y se lo suelta.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $puerto = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        DescargaDeTasas::leer("http://127.0.0.1:{$puerto}/ok", 2);
    }

    /** @return iterable<string, array{string}> */
    public static function urlsQueNoSeAceptan(): iterable
    {
        yield 'http hacia afuera' => ['http://ejemplo.com/tasas'];
        yield 'un host que solo empieza como localhost' => ['http://localhost.ejemplo.com/tasas'];
        yield 'ftp' => ['ftp://ejemplo.com/tasas'];
        yield 'un archivo' => ['file:///etc/passwd'];
        yield 'sin esquema' => ['//ejemplo.com/tasas'];
        yield 'una ruta' => ['/etc/passwd'];
        yield 'vacia' => [''];
    }

    #[DataProvider('urlsQueNoSeAceptan')]
    public function testSoloSeAceptaHttpsOHttpHaciaLaPropiaMaquina(string $url): void
    {
        $this->expectException(TasasInvalidas::class);
        $this->expectExceptionMessage('tiene que ser https');

        DescargaDeTasas::leer($url);
    }
}

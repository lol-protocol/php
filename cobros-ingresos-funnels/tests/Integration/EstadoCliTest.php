<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Config;
use App\Respaldo\RespaldoDeLaBase;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * bin/estado.php como lo corre cron o quien opera el panel: en otro proceso, con los argumentos, lo que imprime y
 * el codigo con que termina. Lo que da «todo en orden» o no depende de las tasas y los respaldos de la base de
 * este entorno, asi que aqui se prueba lo que no depende de eso: el formato, los argumentos y los casos que
 * siempre piden atencion (sin respaldos, sin base). Las condiciones de cada fila las cubre EstadoDelSistemaTest.
 */
final class EstadoCliTest extends TestCase
{
    private string $carpeta = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = sys_get_temp_dir() . '/estado-cli-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->carpeta . '/*') ?: [] as $archivo) {
            @unlink($archivo);
        }
        @rmdir($this->carpeta);
        parent::tearDown();
    }

    /**
     * @param list<string> $argumentos
     * @param array<string, string> $entorno variables que se suman a las del proceso actual
     * @return array{codigo: int, salida: string, errores: string}
     */
    private function ejecutar(array $argumentos, array $entorno = []): array
    {
        $raiz = dirname(__DIR__, 2);
        $proceso = proc_open(
            [PHP_BINARY, $raiz . '/bin/estado.php', ...$argumentos],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            $raiz,
            array_merge(getenv(), $entorno)
        );
        self::assertIsResource($proceso);
        $salida = (string) stream_get_contents($tuberias[1]);
        $errores = (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);

        return ['codigo' => proc_close($proceso), 'salida' => $salida, 'errores' => $errores];
    }

    public function testMuestraCadaTemaEnSuFilaYTerminaConUnoSiHayAlgoQueAtender(): void
    {
        // Sin carpeta de respaldos: siempre hay algo que atender, sean cuales sean las tasas de la base.
        $resultado = $this->ejecutar(["--carpeta={$this->carpeta}"]);

        self::assertSame(1, $resultado['codigo']);
        self::assertSame('', $resultado['errores']);
        $lineas = explode("\n", trim($resultado['salida']));
        self::assertMatchesRegularExpression('/^OK +Base de datos +PostgreSQL /', $lineas[0]);
        self::assertMatchesRegularExpression('/^OK +Migraciones +al día /', $lineas[1]);
        self::assertMatchesRegularExpression('/^(OK|ATENCIÓN) +Tasas de cambio +/', $lineas[2]);
        self::assertMatchesRegularExpression('/^ATENCIÓN +Respaldos +no hay ninguno /', $lineas[3]);
        self::assertSame('Hay algo que atender.', end($lineas));
        self::assertDirectoryDoesNotExist($this->carpeta, 'mirar el estado no crea la carpeta de respaldos');

        // Los temas salen alineados: el detalle empieza en la misma columna en las cuatro filas.
        $columnas = [];
        foreach (array_slice($lineas, 0, 4) as $linea) {
            if (preg_match('/^(?:OK|ATENCIÓN) +(?:Base de datos|Migraciones|Tasas de cambio|Respaldos) +/u', $linea, $m) !== 1) {
                self::fail("la fila no tiene el formato esperado: {$linea}");
            }
            $columnas[] = mb_strlen($m[0]);
        }
        self::assertCount(1, array_unique($columnas), implode(' ', $columnas));
    }

    public function testConUnRespaldoRecienteLoMuestra(): void
    {
        mkdir($this->carpeta, 0700);
        $nombre = RespaldoDeLaBase::nombreDelArchivo(
            Config::variable('DB_NAME', 'cobros_ingresos_funnels'),
            new DateTimeImmutable('-2 hours', new DateTimeZone('UTC'))
        );
        file_put_contents($this->carpeta . '/' . $nombre, str_repeat('x', 3000));

        $resultado = $this->ejecutar(["--carpeta={$this->carpeta}"]);

        self::assertMatchesRegularExpression('/^OK +Respaldos +el último: ' . preg_quote($nombre, '/') . ', hace 2 h, 2\.9 KB$/m', $resultado['salida']);
    }

    public function testUsaLaCarpetaDeRespaldosDelEntorno(): void
    {
        mkdir($this->carpeta, 0700);
        $nombre = RespaldoDeLaBase::nombreDelArchivo(Config::variable('DB_NAME', 'cobros_ingresos_funnels'), new DateTimeImmutable('-1 hour', new DateTimeZone('UTC')));
        file_put_contents($this->carpeta . '/' . $nombre, 'x');

        $resultado = $this->ejecutar([], ['RESPALDOS_CARPETA' => $this->carpeta]);

        self::assertStringContainsString($nombre, $resultado['salida']);
    }

    public function testSiLaBaseNoRespondeDiceSoloEsoYTerminaConUno(): void
    {
        $resultado = $this->ejecutar(["--carpeta={$this->carpeta}"], ['DB_PASSWORD' => 'clave-incorrecta-98765']);

        self::assertSame(1, $resultado['codigo']);
        self::assertMatchesRegularExpression('/^ATENCIÓN +Base de datos +no responde: /m', $resultado['salida']);
        self::assertStringNotContainsString('Migraciones', $resultado['salida'], 'sin base no se mira nada mas');
        self::assertStringNotContainsString('clave-incorrecta-98765', $resultado['salida'] . $resultado['errores']);
        self::assertStringContainsString('Hay algo que atender.', $resultado['salida']);
    }

    public function testRechazaArgumentosMalosYTieneAyuda(): void
    {
        foreach ([['--rapido'], ['--carpeta='], ['--carpeta=a', '--carpeta=b'], ['extra']] as $argumentos) {
            $resultado = $this->ejecutar($argumentos);

            self::assertSame(2, $resultado['codigo'], implode(' ', $argumentos));
            self::assertStringContainsString('Uso:', $resultado['errores'], implode(' ', $argumentos));
            self::assertSame('', $resultado['salida'], implode(' ', $argumentos));
        }

        $ayuda = $this->ejecutar(['--ayuda']);
        self::assertSame(0, $ayuda['codigo']);
        self::assertStringContainsString('--carpeta', $ayuda['salida']);
    }
}

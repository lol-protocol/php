<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Config;
use App\Respaldo\RespaldoDeLaBase;
use App\Respaldo\RespaldoFallido;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * El respaldo con pg_dump de verdad, contra la base de los tests (variables DB_*), y el comando
 * bin/respaldar.php corrido en otro proceso. Cada test trabaja en su propia carpeta temporal.
 * No necesita datos de ejemplo mas alla de que la base tenga las tablas.
 */
final class RespaldoDeLaBaseTest extends TestCase
{
    private string $carpeta = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = sys_get_temp_dir() . '/respaldos-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->carpeta)) {
            foreach (scandir($this->carpeta) ?: [] as $nombre) {
                if ($nombre !== '.' && $nombre !== '..') {
                    @unlink($this->carpeta . '/' . $nombre);
                }
            }
            @rmdir($this->carpeta);
        }
        parent::tearDown();
    }

    /** @return array{host: string, port: string, nombre: string, usuario: string, clave: string} */
    private static function conexion(): array
    {
        ['DB_NAME' => $nombre, 'DB_USER' => $usuario, 'DB_PASSWORD' => $clave] = Config::variablesObligatorias([
            'DB_NAME' => 'cobros_ingresos_funnels',
            'DB_USER' => 'cobros_app',
            'DB_PASSWORD' => 'cobros_app_dev',
        ]);

        return ['host' => Config::variable('DB_HOST', '127.0.0.1'), 'port' => Config::variable('DB_PORT', '5432'), 'nombre' => $nombre, 'usuario' => $usuario, 'clave' => $clave];
    }

    /** @param array{host: string, port: string, nombre: string, usuario: string, clave: string}|null $conexion */
    private function respaldo(int $dias = 14, string $pgDump = 'pg_dump', ?array $conexion = null): RespaldoDeLaBase
    {
        return new RespaldoDeLaBase($conexion ?? self::conexion(), $this->carpeta, $dias, $pgDump);
    }

    private function nombre(string $base, string $momento): string
    {
        return RespaldoDeLaBase::nombreDelArchivo($base, new DateTimeImmutable($momento, new DateTimeZone('UTC')));
    }

    /** Un archivo que ya estaba en la carpeta (de un respaldo anterior o ajeno). */
    private function dejar(string $nombre, string $contenido = 'x'): string
    {
        if (!is_dir($this->carpeta)) {
            mkdir($this->carpeta, 0700, true);
        }
        $ruta = $this->carpeta . '/' . $nombre;
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    /** @return list<string> */
    private function archivos(): array
    {
        return array_values(array_diff(scandir($this->carpeta) ?: [], ['.', '..']));
    }

    public function testHaceUnRespaldoQuePgRestorePuedeLeerConLosDatos(): void
    {
        $ahora = new DateTimeImmutable('2026-10-14 03:15:00', new DateTimeZone('UTC'));

        $resultado = $this->respaldo()->respaldar($ahora);

        self::assertSame($this->carpeta . '/' . self::conexion()['nombre'] . '-20261014-031500.dump', $resultado['archivo']);
        self::assertFileExists($resultado['archivo']);
        self::assertGreaterThan(1000, $resultado['bytes']);
        self::assertSame($resultado['bytes'], filesize($resultado['archivo']));
        self::assertSame([], $resultado['eliminados']);
        self::assertSame([], $resultado['advertencias']);
        self::assertSame([basename($resultado['archivo'])], $this->archivos(), 'nada mas que el respaldo: ni un .parcial');

        $listado = (string) shell_exec('pg_restore --list ' . escapeshellarg($resultado['archivo']) . ' 2>&1');
        self::assertStringContainsString(' TABLE DATA public boletas ', $listado);
        self::assertStringContainsString(' TABLE DATA public clientes ', $listado);
    }

    public function testLaCarpetaYElArchivoSonSoloDelDuenio(): void
    {
        $resultado = $this->respaldo()->respaldar();

        self::assertSame('0700', substr(sprintf('%o', fileperms($this->carpeta)), -4));
        self::assertSame('0600', substr(sprintf('%o', fileperms($resultado['archivo'])), -4));
    }

    public function testBorraLosRespaldosDeEstaBaseDeMasDeLosDiasQueSeConservan(): void
    {
        $base = self::conexion()['nombre'];
        $ahora = new DateTimeImmutable('2026-10-14 03:15:00', new DateTimeZone('UTC'));
        $viejo = $this->nombre($base, '2026-09-20 03:00:00');         // 24 dias
        $justoFuera = $this->nombre($base, '2026-09-30 03:14:59');    // 14 dias y 1 segundo
        $justoDentro = $this->nombre($base, '2026-09-30 03:15:01');   // 13 dias y 23 horas
        $reciente = $this->nombre($base, '2026-10-13 03:00:00');
        foreach ([$viejo, $justoFuera, $justoDentro, $reciente] as $nombre) {
            $this->dejar($nombre, str_repeat('x', 100));
        }

        $resultado = $this->respaldo(14)->respaldar($ahora);

        self::assertEqualsCanonicalizing([$viejo, $justoFuera], $resultado['eliminados']);
        self::assertEqualsCanonicalizing(
            [$justoDentro, $reciente, basename($resultado['archivo'])],
            $this->archivos()
        );
    }

    public function testNoTocaLoQueNoEsUnRespaldoDeEstaBase(): void
    {
        $base = self::conexion()['nombre'];
        $ahora = new DateTimeImmutable('2026-10-14 03:15:00', new DateTimeZone('UTC'));
        $ajenos = [
            $this->nombre('otra_base', '2020-01-01 00:00:00'),             // respaldo de otra base, viejisimo
            $base . '_copia-20200101-000000.dump',                          // el nombre empieza igual pero es otra cosa
            $base . '-20200101-000000.dump.bak',                            // otra extension
            $base . '-2020-01-01.dump',                                     // otro formato de fecha
            'notas.txt',
        ];
        foreach ($ajenos as $nombre) {
            $this->dejar($nombre);
        }
        // Un enlace con nombre de respaldo viejo: no se sigue ni se borra.
        $destino = $this->dejar('destino-del-enlace.txt', 'no me borres');
        symlink($destino, $this->carpeta . '/' . $this->nombre($base, '2020-02-02 00:00:00'));

        $resultado = $this->respaldo(14)->respaldar($ahora);

        self::assertSame([], $resultado['eliminados']);
        foreach ($ajenos as $nombre) {
            self::assertFileExists($this->carpeta . '/' . $nombre, $nombre);
        }
        self::assertFileExists($destino);
        self::assertTrue(is_link($this->carpeta . '/' . $this->nombre($base, '2020-02-02 00:00:00')));
    }

    public function testBorraUnParcialAbandonadoPeroNoUnoReciente(): void
    {
        $base = self::conexion()['nombre'];
        $abandonado = $this->dejar($this->nombre($base, '2026-10-01 00:00:00') . '.parcial');
        touch($abandonado, time() - 3 * 86_400);
        $enCurso = $this->dejar($this->nombre($base, '2026-10-13 00:00:00') . '.parcial');   // recien tocado: otra corrida podria estar escribiendolo

        $resultado = $this->respaldo()->respaldar(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        self::assertSame([basename($abandonado)], $resultado['eliminados']);
        self::assertFileDoesNotExist($abandonado);
        self::assertFileExists($enCurso);
    }

    public function testUnRespaldoMuchoMasChicoQueElAnteriorPideAtencion(): void
    {
        $base = self::conexion()['nombre'];
        $anterior = $this->dejar($this->nombre($base, '2026-10-13 03:00:00'));
        $f = fopen($anterior, 'r+');
        self::assertNotFalse($f);
        ftruncate($f, 500_000_000);   // un archivo "de 500 MB" (disperso: no ocupa lugar)
        fclose($f);

        $resultado = $this->respaldo()->respaldar(new DateTimeImmutable('2026-10-14 03:00:00', new DateTimeZone('UTC')));

        self::assertCount(1, $resultado['advertencias']);
        self::assertStringContainsString('menos de la mitad', $resultado['advertencias'][0]);
        self::assertStringContainsString(basename($anterior), $resultado['advertencias'][0]);
    }

    public function testUnRespaldoDeTamanioParecidoNoAdvierteNada(): void
    {
        $base = self::conexion()['nombre'];
        $primero = $this->respaldo()->respaldar(new DateTimeImmutable('2026-10-13 03:00:00', new DateTimeZone('UTC')));
        self::assertSame([], $primero['advertencias'], 'el primero no tiene con que compararse');

        $segundo = $this->respaldo()->respaldar(new DateTimeImmutable('2026-10-14 03:00:00', new DateTimeZone('UTC')));

        self::assertSame([], $segundo['advertencias']);
        self::assertCount(2, $this->archivos());
        self::assertNotSame('', $base);
    }

    public function testSiFallaNoDejaUnArchivoNiBorraLosRespaldosAnteriores(): void
    {
        $base = self::conexion()['nombre'];
        $viejo = $this->dejar($this->nombre($base, '2020-01-01 00:00:00'));
        $conexionMala = ['clave' => 'clave-que-no-es-la-de-la-base-12345'] + self::conexion();

        try {
            $this->respaldo(14, 'pg_dump', $conexionMala)->respaldar();
            self::fail('con la clave equivocada el respaldo tenia que fallar');
        } catch (RespaldoFallido $e) {
            self::assertStringContainsString('pg_dump terminó con el código', $e->getMessage());
            self::assertStringNotContainsString('clave-que-no-es-la-de-la-base-12345', $e->getMessage(), 'la clave no sale en los mensajes');
        }

        self::assertSame([basename($viejo)], $this->archivos(), 'ni un .parcial, y el respaldo viejo sigue ahi aunque sea de 2020');
    }

    public function testSiNoEstaPgDumpLoDiceClaro(): void
    {
        try {
            $this->respaldo(14, '/no/existe/pg_dump')->respaldar();
            self::fail('sin pg_dump no hay respaldo');
        } catch (RespaldoFallido $e) {
            self::assertStringContainsString('No se pudo ejecutar /no/existe/pg_dump', $e->getMessage());
        }
        self::assertSame([], $this->archivos());
    }

    public function testNoPisaUnRespaldoDelMismoSegundo(): void
    {
        $ahora = new DateTimeImmutable('2026-10-14 03:15:00', new DateTimeZone('UTC'));
        $existente = $this->dejar($this->nombre(self::conexion()['nombre'], '2026-10-14 03:15:00'), 'original');

        try {
            $this->respaldo()->respaldar($ahora);
            self::fail('no tenia que pisar el respaldo existente');
        } catch (RespaldoFallido $e) {
            self::assertStringContainsString('no se pisa', $e->getMessage());
        }
        self::assertSame('original', file_get_contents($existente));
    }

    public function testNoAceptaConservarMenosDeUnDiaNiUnNombreDeBaseRaro(): void
    {
        foreach ([0, -3] as $dias) {
            try {
                $this->respaldo($dias);
                self::fail("{$dias} dias no tenia que aceptarse");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->respaldo(14, 'pg_dump', ['nombre' => '../../etc/pasaporte'] + self::conexion());
    }

    public function testUnaCarpetaQueEsUnArchivoOUnEnlaceSeRechaza(): void
    {
        file_put_contents($this->carpeta, 'soy un archivo');
        try {
            $this->respaldo()->respaldar();
            self::fail('una carpeta que es un archivo no sirve');
        } catch (RespaldoFallido $e) {
            self::assertStringContainsString('no es una carpeta', $e->getMessage());
        } finally {
            unlink($this->carpeta);
        }
    }

    // ---- el comando ----

    /**
     * @param list<string> $argumentos
     * @param array<string, string> $entorno variables que se suman a las del proceso actual
     * @return array{codigo: int, salida: string, errores: string}
     */
    private function ejecutar(array $argumentos, array $entorno = []): array
    {
        $raiz = dirname(__DIR__, 2);
        $proceso = proc_open(
            [PHP_BINARY, $raiz . '/bin/respaldar.php', ...$argumentos],
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

    public function testElComandoRespaldaYLoCuenta(): void
    {
        $resultado = $this->ejecutar(["--carpeta={$this->carpeta}", '--dias=30']);

        self::assertSame(0, $resultado['codigo'], $resultado['errores']);
        self::assertStringContainsString('Respaldo hecho: ' . $this->carpeta . '/', $resultado['salida']);
        self::assertSame('', $resultado['errores']);
        self::assertCount(1, $this->archivos());
    }

    public function testElComandoUsaLaCarpetaDeLaVariableDeEntorno(): void
    {
        $resultado = $this->ejecutar([], ['RESPALDOS_CARPETA' => $this->carpeta]);

        self::assertSame(0, $resultado['codigo'], $resultado['errores']);
        self::assertCount(1, $this->archivos());
    }

    public function testElComandoAvisaPorLaSalidaDeErroresDeUnRespaldoMuyChico(): void
    {
        $anterior = $this->dejar($this->nombre(self::conexion()['nombre'], '2026-10-13 03:00:00'));
        $f = fopen($anterior, 'r+');
        self::assertNotFalse($f);
        ftruncate($f, 500_000_000);
        fclose($f);

        $resultado = $this->ejecutar(["--carpeta={$this->carpeta}"]);

        self::assertSame(0, $resultado['codigo'], 'el respaldo salio bien: avisa pero no falla');
        self::assertStringContainsString('Pide atención', $resultado['errores']);
    }

    public function testElComandoTerminaConUnoSiFallaYNoMuestraLaClave(): void
    {
        $resultado = $this->ejecutar(["--carpeta={$this->carpeta}"], ['DB_PASSWORD' => 'una-clave-incorrecta-98765']);

        self::assertSame(1, $resultado['codigo']);
        self::assertStringContainsString('Error: pg_dump terminó con el código', $resultado['errores']);
        self::assertStringNotContainsString('una-clave-incorrecta-98765', $resultado['errores'] . $resultado['salida']);
        self::assertSame('', $resultado['salida']);
        self::assertSame([], $this->archivos());
    }

    public function testElComandoRechazaArgumentosMalos(): void
    {
        foreach ([['--dias=0'], ['--dias=abc'], ['--dias=-5'], ['--rapido'], ['--carpeta=']] as $argumentos) {
            $resultado = $this->ejecutar($argumentos);

            self::assertSame(2, $resultado['codigo'], implode(' ', $argumentos));
            self::assertStringContainsString('Uso:', $resultado['errores'], implode(' ', $argumentos));
        }

        $ayuda = $this->ejecutar(['--ayuda']);
        self::assertSame(0, $ayuda['codigo']);
        self::assertStringContainsString('--carpeta', $ayuda['salida']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Config;
use App\Database;
use App\Estado\EstadoDelSistema;
use App\Migrador;
use App\Respaldo\RespaldoDeLaBase;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * El estado del sistema (bin/estado.php): la base, las migraciones, las tasas y los respaldos. Cada test arma el
 * estado de la base dentro de su transaccion (se deshace sola) y una carpeta de respaldos temporal con archivos
 * que solo tienen el nombre: aqui no se hace ningun pg_dump, solo se lee la carpeta.
 */
final class EstadoDelSistemaTest extends IntegracionTestCase
{
    private string $carpeta = '';

    /** Las 12:00 UTC del 12 de noviembre: las tasas «de hoy» y los respaldos se arman contra este reloj. */
    private DateTimeImmutable $ahora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = sys_get_temp_dir() . '/estado-' . bin2hex(random_bytes(4));
        $this->ahora = new DateTimeImmutable('2026-11-12 12:00:00', new DateTimeZone('UTC'));
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

    private static function nombreDeLaBase(): string
    {
        return Config::variable('DB_NAME', 'cobros_ingresos_funnels');
    }

    /** Un respaldo hecho a las $momento UTC (solo el archivo, sin contenido real). */
    private function respaldoDeAntes(string $momento, ?string $base = null): void
    {
        if (!is_dir($this->carpeta)) {
            mkdir($this->carpeta, 0700, true);
        }
        $nombre = RespaldoDeLaBase::nombreDelArchivo($base ?? self::nombreDeLaBase(), new DateTimeImmutable($momento, new DateTimeZone('UTC')));
        file_put_contents($this->carpeta . '/' . $nombre, str_repeat('x', 2048));
    }

    /** Todas las monedas con tasa real, de hace $hace (la base mide «hace N dias» con su propio now()). */
    private function tasasReales(string $hace = '1 hour'): void
    {
        Database::connection()->exec("UPDATE monedas SET tasa_actualizada_en = now() - interval '{$hace}', tasa_fuente = 'fuente-de-prueba' WHERE codigo <> 'USD'");
    }

    private function estado(?Migrador $migrador = null): EstadoDelSistema
    {
        return new EstadoDelSistema($migrador ?? new Migrador(), RespaldoDeLaBase::desdeElEntorno($this->carpeta));
    }

    /**
     * @param list<array{tema: string, ok: bool, detalle: string}> $filas
     * @return array{tema: string, ok: bool, detalle: string}
     */
    private static function fila(array $filas, string $tema): array
    {
        foreach ($filas as $fila) {
            if ($fila['tema'] === $tema) {
                return $fila;
            }
        }
        self::fail("no hay una fila «{$tema}»: " . implode(', ', array_column($filas, 'tema')));
    }

    private function ahoraReal(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function testConTodoEnOrdenNoPideAtencion(): void
    {
        $ahora = $this->ahoraReal();
        $this->tasasReales();
        $this->respaldoDeAntes($ahora->modify('-3 hours')->format('Y-m-d H:i:s'));

        $filas = $this->estado()->comprobar($ahora);

        self::assertSame(['Base de datos', 'Migraciones', 'Tasas de cambio', 'Respaldos'], array_column($filas, 'tema'));
        self::assertTrue(EstadoDelSistema::todoEnOrden($filas), json_encode($filas, JSON_UNESCAPED_UNICODE) ?: '');
        self::assertStringContainsString('PostgreSQL', self::fila($filas, 'Base de datos')['detalle']);
        self::assertStringContainsString('al día', self::fila($filas, 'Migraciones')['detalle']);
        self::assertStringContainsString('fuente-de-prueba', self::fila($filas, 'Tasas de cambio')['detalle']);
        self::assertStringContainsString('hace 3 h', self::fila($filas, 'Respaldos')['detalle']);
    }

    public function testUnaMigracionSinAplicarPideAtencionYDiceCualEs(): void
    {
        $directorio = sys_get_temp_dir() . '/migraciones-' . bin2hex(random_bytes(4));
        mkdir($directorio);
        $sql = $directorio . '/999_una_migracion_de_prueba.sql';
        file_put_contents($sql, 'SELECT 1;');

        try {
            $fila = self::fila($this->estado(new Migrador($directorio))->comprobar($this->ahoraReal()), 'Migraciones');
        } finally {
            @unlink($sql);
            @rmdir($directorio);
        }

        self::assertFalse($fila['ok']);
        self::assertStringContainsString('1 sin aplicar', $fila['detalle']);
        self::assertStringContainsString('999_una_migracion_de_prueba', $fila['detalle']);
        self::assertStringContainsString('php bin/migrar.php', $fila['detalle']);
    }

    public function testConTasasDeEjemploPideAtencion(): void
    {
        Database::connection()->exec('UPDATE monedas SET tasa_actualizada_en = NULL, tasa_fuente = NULL');

        $fila = self::fila($this->estado()->comprobar($this->ahoraReal()), 'Tasas de cambio');

        self::assertFalse($fila['ok']);
        self::assertStringContainsString('de ejemplo', $fila['detalle']);
        self::assertStringContainsString('php bin/actualizar_tasas.php', $fila['detalle']);
    }

    public function testUnaCotizacionViejaDiceQueLaActualizacionDiariaDejoDeCorrer(): void
    {
        $this->tasasReales('10 days');

        $fila = self::fila($this->estado()->comprobar($this->ahoraReal()), 'Tasas de cambio');

        self::assertFalse($fila['ok']);
        self::assertStringContainsString('dejó de correr', $fila['detalle']);
        self::assertStringContainsString('hace 10 días', $fila['detalle']);
    }

    public function testUnaCotizacionDeHaceSeisDiasTodaviaSirve(): void
    {
        $this->tasasReales('6 days');

        self::assertTrue(self::fila($this->estado()->comprobar($this->ahoraReal()), 'Tasas de cambio')['ok']);
    }

    public function testUnaMonedaConMovimientosYTasaDeEjemploPideAtencionYSeNombra(): void
    {
        $db = Database::connection();
        $this->tasasReales();
        $moneda = $db->query("SELECT moneda_codigo FROM boletas WHERE moneda_codigo <> 'USD' GROUP BY moneda_codigo ORDER BY count(*) DESC LIMIT 1")->fetchColumn();
        self::assertIsString($moneda);
        $db->prepare('UPDATE monedas SET tasa_actualizada_en = NULL WHERE codigo = :c')->execute([':c' => $moneda]);

        $fila = self::fila($this->estado()->comprobar($this->ahoraReal()), 'Tasas de cambio');

        self::assertFalse($fila['ok']);
        self::assertStringContainsString($moneda, $fila['detalle']);
        self::assertStringNotContainsString('dejó de correr', $fila['detalle'], 'la cotizacion mas reciente esta al dia: el problema es solo esa moneda');
    }

    public function testSinNingunRespaldoPideAtencion(): void
    {
        $fila = self::fila($this->estado()->comprobar($this->ahoraReal()), 'Respaldos');

        self::assertFalse($fila['ok']);
        self::assertStringContainsString('no hay ninguno', $fila['detalle']);
        self::assertStringContainsString('php bin/respaldar.php', $fila['detalle']);
        self::assertDirectoryDoesNotExist($this->carpeta, 'mirar el estado no crea la carpeta de respaldos');
    }

    public function testElUltimoRespaldoSeMideContraElLimiteDeTreintaYSeisHoras(): void
    {
        $this->respaldoDeAntes('2026-11-11 00:00:00');   // 36 h antes de las 12:00 del 12: justo en el limite
        $enElLimite = self::fila($this->estado()->comprobar($this->ahora), 'Respaldos');
        self::assertTrue($enElLimite['ok'], '36 horas exactas todavia pasan: ' . $enElLimite['detalle']);

        $this->limpiar();
        $this->respaldoDeAntes('2026-11-10 23:59:59');   // un segundo mas de 36 h
        $this->respaldoDeAntes('2026-11-12 11:00:00', 'otra_base');   // uno reciente, pero de otra base: no lo salva
        $pasado = self::fila($this->estado()->comprobar($this->ahora), 'Respaldos');
        self::assertFalse($pasado['ok']);
        self::assertStringContainsString('más de 36 horas', $pasado['detalle']);
        self::assertStringContainsString('hace 36 h', $pasado['detalle']);
    }

    public function testSoloCuentaElRespaldoDeEstaBaseYElMasNuevo(): void
    {
        $this->respaldoDeAntes('2026-11-12 11:00:00', 'otra_base');
        $this->respaldoDeAntes('2026-11-09 03:00:00');
        $this->respaldoDeAntes('2026-11-12 03:00:00');

        $fila = self::fila($this->estado()->comprobar($this->ahora), 'Respaldos');

        self::assertTrue($fila['ok']);
        self::assertStringContainsString('20261112-030000', $fila['detalle'], 'el mas nuevo de esta base');
        self::assertStringContainsString('hace 9 h', $fila['detalle']);
        self::assertStringContainsString('2.0 KB', $fila['detalle']);
        self::assertStringNotContainsString('otra_base', $fila['detalle']);
    }

    public function testElTextoDeCuantoHaceSeLeeEnMinutosHorasODias(): void
    {
        $ahora = new DateTimeImmutable('2026-11-12 12:00:00', new DateTimeZone('UTC'));
        $casos = [
            '2026-11-12 11:59:30' => 'hace 0 min',
            '2026-11-12 11:55:00' => 'hace 5 min',
            '2026-11-12 11:00:00' => 'hace 1 h',
            '2026-11-10 12:00:01' => 'hace 47 h',
            '2026-11-10 12:00:00' => 'hace 2 días',
            '2026-11-02 12:00:00' => 'hace 10 días',
            '2026-11-12 13:00:00' => 'hace 0 min',
        ];
        foreach ($casos as $momento => $esperado) {
            self::assertSame($esperado, EstadoDelSistema::hace(new DateTimeImmutable($momento, new DateTimeZone('UTC')), $ahora), $momento);
        }
    }

    public function testTodoEnOrdenExigeQueTodasLasFilasEstenBien(): void
    {
        $bien = ['tema' => 'A', 'ok' => true, 'detalle' => ''];
        $mal = ['tema' => 'B', 'ok' => false, 'detalle' => ''];

        self::assertTrue(EstadoDelSistema::todoEnOrden([$bien, $bien]));
        self::assertFalse(EstadoDelSistema::todoEnOrden([$bien, $mal]));
        self::assertFalse(EstadoDelSistema::todoEnOrden([$mal, $bien]));
    }

    private function limpiar(): void
    {
        foreach (glob($this->carpeta . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
    }
}

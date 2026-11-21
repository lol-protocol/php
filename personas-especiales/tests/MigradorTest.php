<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PDO;
use PDOException;
use PersonasEspeciales\Migrador;
use PHPUnit\Framework\TestCase;

final class MigradorTest extends TestCase
{
    use PostgresDePrueba;

    private PDO $pdo;
    private string $carpeta;

    protected function setUp(): void
    {
        $this->pdo = $this->conectar();
        $this->carpeta = sys_get_temp_dir() . '/pe-migraciones-' . bin2hex(random_bytes(4));
        mkdir($this->carpeta);
    }

    protected function tearDown(): void
    {
        if (isset($this->carpeta) && is_dir($this->carpeta)) {
            array_map('unlink', glob($this->carpeta . '/*') ?: []);
            rmdir($this->carpeta);
        }
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS pe_prueba_a, pe_prueba_b');
        }
    }

    public function testLasMigracionesDelModuloYaEstanAplicadasYNoSeRepiten(): void
    {
        // conectar() ya las aplicó: correr otra vez no hace nada.
        $otraVez = (new Migrador($this->pdo, __DIR__ . '/../database/migraciones'))->aplicar();

        self::assertSame([], $otraVez);
        $nombres = $this->pdo->query('SELECT nombre FROM schema_migraciones ORDER BY nombre')?->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['001_exclusion.sql'], $nombres);
    }

    public function testAplicaSoloLasNuevasYEnOrden(): void
    {
        file_put_contents($this->carpeta . '/001_a.sql', 'CREATE TABLE pe_prueba_a (x INT)');
        $migrador = new Migrador($this->pdo, $this->carpeta);
        self::assertSame(['001_a.sql'], $migrador->aplicar());

        file_put_contents($this->carpeta . '/002_b.sql', 'CREATE TABLE pe_prueba_b (y INT)');

        self::assertSame(['002_b.sql'], $migrador->aplicar());
    }

    public function testUnaMigracionQueFallaNoDejaNadaAMedias(): void
    {
        // La primera sentencia sí corre; la segunda falla: no debe quedar ni la tabla ni la anotación.
        file_put_contents($this->carpeta . '/001_a.sql', 'CREATE TABLE pe_prueba_a (x INT); SELECT * FROM no_existe;');

        try {
            (new Migrador($this->pdo, $this->carpeta))->aplicar();
            self::fail('Debía fallar.');
        } catch (PDOException) {
            // esperado
        }

        $tabla = $this->pdo->query("SELECT to_regclass('pe_prueba_a')")?->fetchColumn();
        self::assertNull($tabla);
        $anotadas = $this->pdo->query("SELECT count(*) FROM schema_migraciones WHERE nombre = '001_a.sql'")?->fetchColumn();
        self::assertSame(0, (int) $anotadas);
    }

    private function candadoLibre(): bool
    {
        $otra = $this->abrir();
        $consulta = $otra->query('SELECT pg_try_advisory_lock(' . Migrador::CLAVE_CANDADO . ')');
        $libre = $consulta !== false && $consulta->fetchColumn() === true;
        if ($libre) {
            $otra->query('SELECT pg_advisory_unlock(' . Migrador::CLAVE_CANDADO . ')');
        }

        return $libre;
    }

    public function testElCandadoSeLiberaAlTerminar(): void
    {
        file_put_contents($this->carpeta . '/001_a.sql', 'CREATE TABLE pe_prueba_a (x INT)');
        (new Migrador($this->pdo, $this->carpeta))->aplicar();

        self::assertTrue($this->candadoLibre());
    }

    public function testElCandadoSeLiberaSiUnaMigracionFalla(): void
    {
        file_put_contents($this->carpeta . '/001_a.sql', 'SELECT * FROM no_existe');

        try {
            (new Migrador($this->pdo, $this->carpeta))->aplicar();
            self::fail('Debía fallar.');
        } catch (PDOException) {
            // esperado
        }

        self::assertTrue($this->candadoLibre());
    }

    public function testUnaSegundaInstanciaEsperaMientrasOtraTieneElCandado(): void
    {
        $dsn = (string) getenv('TEST_PG_DSN');
        // Esta conexión simula a la instancia que está migrando: tiene el candado.
        $this->pdo->query('SELECT pg_advisory_lock(' . Migrador::CLAVE_CANDADO . ')');

        $proceso = proc_open(
            [PHP_BINARY, __DIR__ . '/../bin/migrar.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            null,
            [
                'PE_DSN' => $dsn,
                'PE_USER' => (string) getenv('TEST_PG_USER'),
                'PE_PASSWORD' => (string) getenv('TEST_PG_PASSWORD'),
                'PATH' => (string) getenv('PATH'),
            ],
        );
        self::assertIsResource($proceso);

        try {
            // Mientras la otra instancia tiene el candado, esta no avanza. Sin candado terminaría de inmediato.
            usleep(1_500_000);
            self::assertTrue(proc_get_status($proceso)['running'], 'La segunda instancia no esperó el candado.');

            $this->pdo->query('SELECT pg_advisory_unlock(' . Migrador::CLAVE_CANDADO . ')');

            $limite = microtime(true) + 15;
            while (proc_get_status($proceso)['running'] && microtime(true) < $limite) {
                usleep(100_000);
            }
            self::assertFalse(proc_get_status($proceso)['running'], 'No terminó al liberarse el candado.');
            $salida = (string) stream_get_contents($tuberias[1]);
            self::assertStringContainsString('sin migraciones pendientes', $salida);
        } finally {
            if (proc_get_status($proceso)['running']) {
                proc_terminate($proceso);
            }
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            proc_close($proceso);
        }
    }

    public function testNombreInvalidoEsUnError(): void
    {
        file_put_contents($this->carpeta . '/1_mal.sql', 'SELECT 1');

        $this->expectException(InvalidArgumentException::class);
        (new Migrador($this->pdo, $this->carpeta))->aplicar();
    }
}

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
        self::assertSame(['001_exclusion.sql', '002_exclusion_evento.sql'], $nombres);
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

    public function testNombreInvalidoEsUnError(): void
    {
        file_put_contents($this->carpeta . '/1_mal.sql', 'SELECT 1');

        $this->expectException(InvalidArgumentException::class);
        (new Migrador($this->pdo, $this->carpeta))->aplicar();
    }
}

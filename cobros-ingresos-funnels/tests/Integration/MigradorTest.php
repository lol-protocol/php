<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Migrador;
use RuntimeException;

/**
 * Corre contra la base configurada por las env vars DB_*. Postgres admite DDL
 * dentro de una transaccion, asi que las tablas que crean estas migraciones
 * de prueba -y su registro en migraciones_aplicadas- se deshacen con la
 * transaccion de cada test.
 */
final class MigradorTest extends IntegracionTestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = sys_get_temp_dir() . '/migraciones-prueba-' . uniqid();
        mkdir($this->directorio);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directorio . '/*.sql') ?: []);
        rmdir($this->directorio);
        parent::tearDown();
    }

    private function tablaExiste(string $tabla): bool
    {
        $stmt = Database::connection()->prepare('SELECT to_regclass(:tabla) IS NOT NULL');
        $stmt->execute([':tabla' => $tabla]);

        return (bool) $stmt->fetchColumn();
    }

    public function testUnaBaseSembradaNoTieneMigracionesPendientes(): void
    {
        $migrador = new Migrador();

        self::assertSame([], $migrador->pendientes(), 'el seed corre todas las migraciones del repo');
        self::assertContains(Migrador::INICIAL, $migrador->aplicadas());
    }

    public function testAplicaUnaMigracionNuevaUnaSolaVez(): void
    {
        $tabla = 'prueba_migrador_' . uniqid();
        file_put_contents("{$this->directorio}/900_prueba.sql", "CREATE TABLE {$tabla} (id INTEGER);");
        $migrador = new Migrador($this->directorio);

        self::assertSame(['900_prueba'], $migrador->aplicar());
        self::assertTrue($this->tablaExiste($tabla));
        self::assertSame([], $migrador->aplicar(), 'la segunda corrida no encuentra nada pendiente (si no, fallaria el CREATE TABLE)');
    }

    public function testLasAplicaEnOrdenDeNumero(): void
    {
        $tabla = 'prueba_orden_' . uniqid();
        file_put_contents("{$this->directorio}/902_agrega_columna.sql", "ALTER TABLE {$tabla} ADD COLUMN nombre TEXT;");
        file_put_contents("{$this->directorio}/901_crea_tabla.sql", "CREATE TABLE {$tabla} (id INTEGER);");

        self::assertSame(['901_crea_tabla', '902_agrega_columna'], (new Migrador($this->directorio))->aplicar());
    }

    /**
     * Una migracion rota no puede quedar a medias ni registrada como hecha:
     * si no, la proxima corrida la saltearia y la base quedaria sin ese
     * cambio para siempre.
     */
    public function testUnaMigracionQueFallaNoQuedaAplicadaNiRegistrada(): void
    {
        $tabla = 'prueba_rota_' . uniqid();
        file_put_contents("{$this->directorio}/903_rota.sql", "CREATE TABLE {$tabla} (id INTEGER); ESTO NO ES SQL;");
        $migrador = new Migrador($this->directorio);

        try {
            $migrador->aplicar();
            self::fail('una migracion con SQL invalido tiene que fallar');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('903_rota', $e->getMessage(), 'el error tiene que nombrar el archivo');
        }

        self::assertFalse($this->tablaExiste($tabla), 'lo que la migracion alcanzo a hacer se deshace');
        self::assertNotContains('903_rota', $migrador->aplicadas());
    }

    /**
     * Una base creada con el viejo schema.sql ya tiene todo lo de la 001; si
     * se la corriera fallaria con "la tabla ya existe". --baseline la
     * registra sin ejecutarla.
     */
    public function testBaselineRegistraLaInicialSinEjecutarla(): void
    {
        Database::connection()->exec("DELETE FROM migraciones_aplicadas WHERE version = '" . Migrador::INICIAL . "'");
        $migrador = new Migrador();
        self::assertContains(Migrador::INICIAL, $migrador->pendientes());

        $migrador->marcarComoAplicada(Migrador::INICIAL);

        self::assertSame([], $migrador->pendientes());
    }

    public function testNoSePuedeMarcarUnaMigracionQueNoExiste(): void
    {
        $this->expectException(RuntimeException::class);

        (new Migrador())->marcarComoAplicada('999_no_existe');
    }
}

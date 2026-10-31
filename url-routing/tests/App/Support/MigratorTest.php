<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

class MigratorTest extends TestCase
{
    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    public function testStatementsSplitOnLineEndingSemicolonsAndDropComments(): void
    {
        $sql = "-- a comment; with a semicolon\nCREATE TABLE a (x TEXT);\n\nINSERT INTO a VALUES ('uno; dos');\nSELECT 1;";

        $this->assertSame(
            ['CREATE TABLE a (x TEXT)', "INSERT INTO a VALUES ('uno; dos')", 'SELECT 1'],
            Migrator::statements($sql)
        );
    }

    #[DataProvider('drivers')]
    public function testMigrateIsIdempotent(string $driver): void
    {
        foreach (['genealogy', 'pos'] as $site) {
            $db = TestDatabases::fresh($driver, $site);
            $migrator = Migrator::forSite($db, $site);

            $this->assertSame([], $migrator->migrate(), "{$site}: nothing pending after the first run");
            $esperadas = $site === 'genealogy'
                ? ['genealogy/001_esquema_inicial', 'genealogy/002_personas_viva', 'genealogy/003_persona_alias', 'genealogy/004_indices_de_listados', 'genealogy/005_pedidos_retiro', 'genealogy/006_pedidos_retiro_marcas', 'genealogy/007_pedidos_retiro_depurados', 'genealogy/008_supresiones_persona']
                : ['pos/001_esquema_inicial', 'pos/002_ordenes_codigo', 'pos/003_orden_eventos_orden'];
            $this->assertSame($esperadas, array_column($db->fetchAll('SELECT version FROM schema_migrations ORDER BY version'), 'version'));
        }
    }

    #[DataProvider('drivers')]
    public function testFailingMigrationLeavesNothingBehind(string $driver): void
    {
        $dir = sys_get_temp_dir() . '/migrator-' . getmypid() . '-' . $driver;
        @mkdir("{$dir}/migrations", 0777, true);
        file_put_contents("{$dir}/migrations/001_ok.sql", "CREATE TABLE ok_uno (id INTEGER PRIMARY KEY);\n");
        file_put_contents("{$dir}/migrations/002_rota.sql", "CREATE TABLE ok_dos (id INTEGER PRIMARY KEY);\nNOT VALID SQL;\n");

        $db = $driver === 'sqlite' ? new Database('sqlite::memory:') : TestDatabases::fresh($driver, 'pos');
        $migrator = new Migrator($db, $dir, 'prueba');

        try {
            $migrator->migrate();
            $this->fail('The broken migration should throw');
        } catch (\PDOException) {
        } finally {
            exec('rm -rf ' . escapeshellarg($dir));
        }

        $versiones = array_column($db->fetchAll('SELECT version FROM schema_migrations'), 'version');
        $this->assertContains('prueba/001_ok', $versiones);
        $this->assertNotContains('prueba/002_rota', $versiones);

        // Transactional DDL: the table created before the error was rolled back too.
        $this->expectException(\PDOException::class);
        $db->fetchAll('SELECT * FROM ok_dos');
    }

    #[DataProvider('drivers')]
    public function testSchemaEnforcesDigitWidthOfIds(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        $this->expectException(\PDOException::class);
        // 9 digits: would produce a URL that routes to "suceso", not "persona".
        $db->insert('personas', ['id' => 612847310, 'nombres' => 'X', 'apellidos' => 'Y']);
    }

    #[DataProvider('drivers')]
    public function testTwoSitesInOneDatabaseFailLoudly(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');

        // Both schemas define usuarios/grupos/colecciones: the POS migration
        // must not be skipped as "already applied" — it has to fail.
        $this->expectException(\PDOException::class);
        Migrator::forSite($db, 'pos')->migrate();
    }

    #[DataProvider('drivers')]
    public function testPendingListsWhatMigrateWouldRunAndChangesNothing(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'pos');
        $migrator = Migrator::forSite($db, 'pos');
        $this->assertSame([], $migrator->pending(), 'a migrated database has nothing pending');

        $db->execute("DELETE FROM schema_migrations WHERE version = 'pos/002_ordenes_codigo'");
        $this->assertSame(['pos/002_ordenes_codigo'], $migrator->pending());
        $this->assertSame(['pos/002_ordenes_codigo'], $migrator->pending(), 'asking twice changes nothing');
        $this->assertSame(1, (int) $db->fetchValue("SELECT COUNT(*) FROM schema_migrations WHERE version = 'pos/001_esquema_inicial'"));
    }

    public function testPendingIsEverythingBeforeTheFirstMigration(): void
    {
        $db = new Database('sqlite::memory:');

        $pending = Migrator::forSite($db, 'pos')->pending();

        $this->assertSame(['pos/001_esquema_inicial', 'pos/002_ordenes_codigo', 'pos/003_orden_eventos_orden'], $pending);
        $this->assertSame([], $db->fetchAll("SELECT name FROM sqlite_master WHERE name = 'schema_migrations'"), 'it did not create the table');
    }

    /**
     * La 003 rehace orden_eventos (la clave pasa de la hora al número de evento): lo que ya había no se pierde y
     * queda numerado por su hora, y desde entonces caben dos eventos de la misma orden en el mismo segundo.
     */
    public function testMigration003KeepsTheEventsOfExistingOrdersAndNumbersThemByTime(): void
    {
        $dir = sys_get_temp_dir() . '/migrator-eventos-' . getmypid();
        @mkdir("{$dir}/migrations", 0777, true);
        $origen = dirname(__DIR__, 3) . '/database/pos/migrations';
        foreach (['001_esquema_inicial', '002_ordenes_codigo'] as $nombre) {
            copy("{$origen}/{$nombre}.sql", "{$dir}/migrations/{$nombre}.sql");
        }

        $db = new Database('sqlite::memory:');
        $migrator = new Migrator($db, $dir, 'pos');

        try {
            $migrator->migrate();
            $db->execute("INSERT INTO usuarios (id, email, nombre) VALUES (1, 'a@b.c', 'A')");
            $db->execute("INSERT INTO ordenes (id, usuario_id, estado, total_centavos) VALUES (10, 1, 'pendiente', 100), (11, 1, 'pendiente', 100)");
            $db->execute(
                "INSERT INTO orden_eventos (orden_id, ocurrido_en, estado, detalle) VALUES
                    (10, '2026-09-02 09:00:00', 'enviada', 'segundo'),
                    (10, '2026-09-01 10:00:00', 'pagada', 'primero'),
                    (11, '2026-09-01 10:00:00', 'pendiente', NULL)"
            );

            copy("{$origen}/003_orden_eventos_orden.sql", "{$dir}/migrations/003_orden_eventos_orden.sql");
            $this->assertSame(['pos/003_orden_eventos_orden'], $migrator->migrate());

            $filas = $db->fetchAll('SELECT orden_id, orden, estado, detalle FROM orden_eventos ORDER BY orden_id, orden');
            $this->assertSame(
                [[10, 1, 'pagada', 'primero'], [10, 2, 'enviada', 'segundo'], [11, 1, 'pendiente', null]],
                array_map(static fn(array $f): array => [(int)$f['orden_id'], (int)$f['orden'], $f['estado'], $f['detalle']], $filas)
            );

            $db->execute("INSERT INTO orden_eventos (orden_id, orden, ocurrido_en, estado) VALUES (10, 3, '2026-09-02 09:00:00', 'entregada')");
            $this->assertSame(3, (int)$db->fetchValue('SELECT COUNT(*) FROM orden_eventos WHERE orden_id = 10'), 'same second, same order: it fits');
        } finally {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}

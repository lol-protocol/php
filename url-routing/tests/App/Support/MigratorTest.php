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
            $this->assertSame(["{$site}/001_esquema_inicial"], array_column($db->fetchAll('SELECT version FROM schema_migrations'), 'version'));
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
}

<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

class DatabaseTest extends TestCase
{
    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private function db(): Database
    {
        return new Database('sqlite::memory:');
    }

    public function testInsertRejectsInjectedColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->insert('personas', ['nombre) VALUES (1); DROP TABLE personas; --' => 'x']);
    }

    public function testInsertRejectsInjectedTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->insert('personas; DROP TABLE personas', ['nombre' => 'x']);
    }

    public function testUpdateRejectsInjectedWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->update('personas', ['nombre' => 'x'], ['1=1 OR id' => 1]);
    }

    public function testDeleteRejectsInjectedWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->delete('personas', ['id = id OR 1' => 1]);
    }

    public function testListArrayKeysAreNotValidColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->insert('personas', ['x', 'y']);
    }

    public function testDeleteWithoutWhereIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db()->delete('personas', []);
    }

    #[DataProvider('drivers')]
    public function testCrudWithPlainIdentifiers(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'pos');

        $db->insert('usuarios', ['id' => 50, 'email' => 'x@example.com', 'nombre' => 'Equis']);
        $this->assertSame(1, $db->update('usuarios', ['nombre' => 'Ye'], ['id' => 50]));
        $this->assertSame('Ye', $db->fetchValue('SELECT nombre FROM usuarios WHERE id = ?', [50]));
        $this->assertSame(1, $db->delete('usuarios', ['id' => 50]));
        $this->assertNull($db->fetchOne('SELECT * FROM usuarios WHERE id = ?', [50]));
    }

    #[DataProvider('drivers')]
    public function testTransactionRollsBackOnError(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'pos');

        try {
            $db->transaction(function (Database $tx): void {
                $tx->insert('usuarios', ['id' => 60, 'email' => 'a@example.com', 'nombre' => 'A']);
                $tx->insert('usuarios', ['id' => 61, 'email' => 'a@example.com', 'nombre' => 'B']); // duplicate email
            });
            $this->fail('Unique violation should propagate');
        } catch (\PDOException) {
        }

        $this->assertNull($db->fetchOne('SELECT id FROM usuarios WHERE id = ?', [60]));
    }

    #[DataProvider('drivers')]
    public function testQueryErrorsPropagateInsteadOfLookingLikeNoRows(string $driver): void
    {
        $this->expectException(\PDOException::class);

        TestDatabases::fresh($driver, 'pos')->fetchAll('SELECT * FROM no_such_table');
    }

    public function testSqliteEnforcesForeignKeys(): void
    {
        $db = TestDatabases::fresh('sqlite', 'pos');

        $this->expectException(\PDOException::class);
        $db->insert('deseos', ['usuario_id' => 999, 'producto_id' => 999]);
    }

    public function testForSiteFallsBackToALocalSqliteFile(): void
    {
        putenv('DB_DSN_TESTSITE');
        putenv('DB_DSN');

        $db = Database::forSite('testsite');
        $ref = new \ReflectionProperty(Database::class, 'dsn');

        $this->assertStringEndsWith('/var/testsite.sqlite', $ref->getValue($db));
    }

    public function testForSiteUsesOnlyTheSiteSpecificVariable(): void
    {
        putenv('DB_DSN=sqlite:/tmp/compartida.sqlite');
        putenv('DB_DSN_TESTSITE=sqlite:/tmp/especifica.sqlite');

        try {
            $ref = new \ReflectionProperty(Database::class, 'dsn');
            $this->assertSame('sqlite:/tmp/especifica.sqlite', $ref->getValue(Database::forSite('testsite')));
            // A shared DB_DSN is ignored: two sites in one database would mix
            // their same-named tables.
            $this->assertStringEndsWith('/var/otro.sqlite', $ref->getValue(Database::forSite('otro')));
        } finally {
            putenv('DB_DSN');
            putenv('DB_DSN_TESTSITE');
        }
    }
}

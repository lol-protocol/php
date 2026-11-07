<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Database;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * The throwaway PostgreSQL databases of the tests are connections, and the server has only so many: see the
 * class comment of TestDatabases. These tests pin that the oldest one is dropped and disconnected when too
 * many are open, and that the newest keep working.
 */
class TestDatabasesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!getenv('TEST_PG_DSN')) {
            $this->markTestSkipped('Needs TEST_PG_DSN: only PostgreSQL databases are connections to count.');
        }
    }

    private function esquemas(string $nombre): int
    {
        $otra = new Database((string)getenv('TEST_PG_DSN'), (string)getenv('TEST_PG_USER'), (string)getenv('TEST_PG_PASSWORD'));

        return (int)$otra->fetchValue('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?', [$nombre]);
    }

    public function testSoloSeguenAbiertasLasMasRecientes(): void
    {
        $primera = TestDatabases::fresh('pgsql', 'genealogy');
        $esquemaDeLaPrimera = (string)$primera->fetchValue('SELECT current_schema()');
        $this->assertSame(1, $this->esquemas($esquemaDeLaPrimera), 'while it is recent, its schema exists');

        for ($i = 0; $i < 30; $i++) {
            $ultima = TestDatabases::fresh('pgsql', 'genealogy');
        }

        $this->assertLessThanOrEqual(20, TestDatabases::abiertas(), 'the oldest were dropped and disconnected');
        $this->assertSame(0, $this->esquemas($esquemaDeLaPrimera), 'the first one is gone');
        $this->assertSame(0, (int)$ultima->fetchValue('SELECT COUNT(*) FROM personas'), 'the newest still has its tables');
    }

    public function testLaBaseCompartidaNoSeSueltaNuncaAunqueSeAbranMuchas(): void
    {
        $compartida = TestDatabases::seeded('pgsql', 'pos');
        $productos = (int)$compartida->fetchValue('SELECT COUNT(*) FROM productos');
        $this->assertGreaterThan(0, $productos);

        for ($i = 0; $i < 30; $i++) {
            TestDatabases::fresh('pgsql', 'pos');
        }

        $this->assertSame($productos, (int)$compartida->fetchValue('SELECT COUNT(*) FROM productos'), 'seeded() databases are shared by the whole run');
    }
}

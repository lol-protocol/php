<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Database;
use App\Support\HealthCheck;
use App\Support\Migrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

class HealthCheckTest extends TestCase
{
    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    public function testMatchesHealthPath(): void
    {
        $this->assertTrue(HealthCheck::isHealthCheckRequest('/health'));
        $this->assertTrue(HealthCheck::isHealthCheckRequest('/health/'));
        $this->assertTrue(HealthCheck::isHealthCheckRequest('/health?verbose=1'));
    }

    public function testDoesNotMatchOtherPaths(): void
    {
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/'));
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/healthcheck'));
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/1234567890'));
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/health/extra'));
    }

    public function testMatchesReadyPathAndNothingElse(): void
    {
        $this->assertTrue(HealthCheck::isReadinessRequest('/ready'));
        $this->assertTrue(HealthCheck::isReadinessRequest('/ready/'));
        $this->assertTrue(HealthCheck::isReadinessRequest('/ready?x=1'));
        $this->assertFalse(HealthCheck::isReadinessRequest('/health'));
        $this->assertFalse(HealthCheck::isReadinessRequest('/readyz'));
        $this->assertFalse(HealthCheck::isReadinessRequest('/ready/extra'));
        $this->assertFalse(HealthCheck::isReadinessRequest('/'));
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/ready'), 'the two probes do not answer for each other');
        $this->assertFalse(HealthCheck::isReadinessRequest('/health'));
    }

    #[DataProvider('drivers')]
    public function testReadyWhenTheDatabaseAnswersAndEveryMigrationIsApplied(string $driver): void
    {
        foreach (['genealogy', 'pos'] as $site) {
            $db = TestDatabases::fresh($driver, $site);

            $result = HealthCheck::readiness($db, Migrator::forSite($db, $site));

            $this->assertSame(200, $result['status'], $site);
            $this->assertSame(['status' => 'ok', 'db' => 'ok', 'migrations' => 'applied'], $result['body'], $site);
            $this->assertNull($result['detail'], $site);
        }
    }

    /** A deploy that forgot `bin/migrate.php`: the database is up, the code is ahead of it. */
    public function testNotReadyWhileAMigrationIsPending(): void
    {
        $db = new Database('sqlite::memory:');
        $migrator = Migrator::forSite($db, 'genealogy');

        $before = HealthCheck::readiness($db, $migrator);
        $this->assertSame(503, $before['status']);
        $this->assertSame(['status' => 'error', 'db' => 'ok', 'migrations' => 'pending'], $before['body']);
        $this->assertStringContainsString('genealogy/001_esquema_inicial', (string) $before['detail'], 'the log names them');
        $this->assertStringNotContainsString('genealogy/001', json_encode($before['body']), 'the body does not');

        $migrator->migrate();
        $this->assertSame(200, HealthCheck::readiness($db, $migrator)['status']);
    }

    public function testNotReadyWhenTheDatabaseIsDown(): void
    {
        if (!in_array('pgsql', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_pgsql is needed to point at a server that is not there');
        }
        // Port 1 has nobody listening: the connection is refused at once.
        $db = new Database('pgsql:host=127.0.0.1;port=1;dbname=nada', 'nadie', 'la-clave-que-no-debe-salir');

        $result = HealthCheck::readiness($db, Migrator::forSite($db, 'genealogy'));

        $this->assertSame(503, $result['status']);
        $this->assertSame(['status' => 'error', 'db' => 'down', 'migrations' => 'unknown'], $result['body']);
        $this->assertStringStartsWith('database: ', (string) $result['detail']);
        $this->assertStringNotContainsString('la-clave-que-no-debe-salir', (string) $result['detail']);
        $this->assertStringNotContainsString('la-clave-que-no-debe-salir', json_encode($result['body']));
    }
}

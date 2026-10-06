<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Database;
use App\Support\Migrator;

/**
 * Migrated + seeded databases for tests, on every engine available:
 * SQLite (in memory) always, PostgreSQL when TEST_PG_DSN is set (CI sets it;
 * locally, point it at any database the TEST_PG_USER can create schemas in).
 * Each PostgreSQL run gets its own throwaway schema.
 */
final class TestDatabases
{
    /** @var array<string, Database> */
    private static array $cache = [];

    /** @return array<string, array{string}> PHPUnit data provider rows */
    public static function drivers(): array
    {
        $drivers = ['sqlite' => ['sqlite']];
        if (getenv('TEST_PG_DSN')) {
            $drivers['pgsql'] = ['pgsql'];
        }
        return $drivers;
    }

    /** Shared, read-only fixture: migrated and seeded once per process. */
    public static function seeded(string $driver, string $site): Database
    {
        return self::$cache["{$driver}:{$site}"] ??= self::fresh($driver, $site, seed: true);
    }

    /** A new, isolated database — use when the test writes. */
    public static function fresh(string $driver, string $site, bool $seed = false): Database
    {
        $db = match ($driver) {
            'sqlite' => new Database('sqlite::memory:'),
            'pgsql' => self::postgres(),
            default => throw new \InvalidArgumentException($driver),
        };

        $migrator = Migrator::forSite($db, $site);
        $migrator->migrate();
        if ($seed) {
            $migrator->seed();
        }

        return $db;
    }

    private static function postgres(): Database
    {
        $db = new Database(
            (string)getenv('TEST_PG_DSN'),
            (string)getenv('TEST_PG_USER'),
            (string)getenv('TEST_PG_PASSWORD'),
        );

        $schema = 'test_' . getmypid() . '_' . bin2hex(random_bytes(4));
        $db->execute("CREATE SCHEMA {$schema}");
        $db->execute("SET search_path TO {$schema}");
        register_shutdown_function(static fn() => $db->execute("DROP SCHEMA {$schema} CASCADE"));

        return $db;
    }
}

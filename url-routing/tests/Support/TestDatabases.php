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
 *
 * Every PostgreSQL database is a connection, and the server refuses more than max_connections (100 by
 * default, 97 of them for ordinary roles). Before, each fresh() database stayed connected until the end of
 * the run, held by the closure that drops its schema, so the suite needed one connection per test that wrote
 * and sat a few below the limit: the next tests added failed in MigratorTest, the last one to ask for a
 * connection, with "remaining connection slots are reserved". Now only the MAX_ABIERTAS most recent fresh
 * databases stay open; the oldest is dropped and disconnected when a new one is made. A test uses its
 * database while it runs and the tests run one after another, so one that old has long been finished.
 */
final class TestDatabases
{
    /** How many throwaway PostgreSQL databases stay connected at once (the shared seeded() ones don't count). */
    private const MAX_ABIERTAS = 20;

    /** @var array<string, Database> */
    private static array $cache = [];

    /** @var list<array{Database, string}> the fresh PostgreSQL databases still connected, oldest first */
    private static array $abiertas = [];

    /** @var list<array{Database, string}> the seeded() ones: kept for the whole run, dropped at the end */
    private static array $permanentes = [];

    private static bool $limpiezaRegistrada = false;

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
        return self::$cache["{$driver}:{$site}"] ??= self::crear($driver, $site, seed: true, permanente: true);
    }

    /** A new, isolated database — use when the test writes. */
    public static function fresh(string $driver, string $site, bool $seed = false): Database
    {
        return self::crear($driver, $site, $seed, permanente: false);
    }

    /** How many fresh() PostgreSQL databases are connected right now (for TestDatabasesTest). */
    public static function abiertas(): int
    {
        return count(self::$abiertas);
    }

    private static function crear(string $driver, string $site, bool $seed, bool $permanente): Database
    {
        $db = match ($driver) {
            'sqlite' => new Database('sqlite::memory:'),
            'pgsql' => self::postgres($permanente),
            default => throw new \InvalidArgumentException($driver),
        };

        $migrator = Migrator::forSite($db, $site);
        $migrator->migrate();
        if ($seed) {
            $migrator->seed();
        }

        return $db;
    }

    private static function postgres(bool $permanente): Database
    {
        $db = new Database(
            (string)getenv('TEST_PG_DSN'),
            (string)getenv('TEST_PG_USER'),
            (string)getenv('TEST_PG_PASSWORD'),
        );

        $schema = 'test_' . getmypid() . '_' . bin2hex(random_bytes(4));
        $db->execute("CREATE SCHEMA {$schema}");
        $db->execute("SET search_path TO {$schema}");

        if (!self::$limpiezaRegistrada) {
            self::$limpiezaRegistrada = true;
            register_shutdown_function([self::class, 'limpiar']);
        }
        if ($permanente) {
            self::$permanentes[] = [$db, $schema];

            return $db;
        }

        self::$abiertas[] = [$db, $schema];
        while (count(self::$abiertas) > self::MAX_ABIERTAS) {
            self::soltar(array_shift(self::$abiertas));
        }

        return $db;
    }

    /**
     * Drops the schema of a database that is no longer needed and closes its connection. Nothing here may
     * throw: a test that left its connection in a failed transaction must not take the run down with it.
     *
     * @param array{Database, string} $base
     */
    private static function soltar(array $base): void
    {
        [$db, $schema] = $base;

        try {
            $pdo = $db->pdo();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $db->execute("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        } catch (\Throwable) {
        }

        // Database keeps the only reference to its PDO, and a PDO closes its connection when it is released.
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue($db, null);
    }

    /** At the end of the run: drops what is still there. */
    public static function limpiar(): void
    {
        foreach ([...self::$abiertas, ...self::$permanentes] as $base) {
            self::soltar($base);
        }
        self::$abiertas = [];
        self::$permanentes = [];
    }
}

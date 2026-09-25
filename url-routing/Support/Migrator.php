<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Applies database/<site>/migrations/NNN_*.sql in order, once each, tracking
 * them in schema_migrations as "<site>/NNN_name". Each file runs inside a
 * transaction (both SQLite and PostgreSQL support transactional DDL), so a
 * failing migration leaves nothing half-applied.
 *
 * Recording the site in the version means that if two sites are ever pointed
 * at the same database, the second one fails loudly on its first CREATE TABLE
 * instead of silently skipping migrations it believes already ran.
 */
final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $directory,
        private readonly string $namespace,
    ) {
    }

    public static function forSite(Database $db, string $site): self
    {
        return new self($db, dirname(__DIR__) . "/database/{$site}", $site);
    }

    /** @return list<string> versions applied by this call */
    public function migrate(): array
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $applied = array_column($this->db->fetchAll('SELECT version FROM schema_migrations'), 'version');
        $done = [];

        foreach ($this->files('migrations') as $nombre => $file) {
            $version = "{$this->namespace}/{$nombre}";
            if (in_array($version, $applied, true)) {
                continue;
            }

            $this->db->transaction(function (Database $db) use ($version, $file): void {
                $this->runFile($file);
                $db->execute('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
            });
            $done[] = $version;
        }

        return $done;
    }

    /** Loads the demo data set (development and tests only). */
    public function seed(): void
    {
        $this->db->transaction(function (): void {
            foreach ($this->files('seeds') as $file) {
                $this->runFile($file);
            }
        });
    }

    /** @return array<string, string> version => path, sorted */
    private function files(string $kind): array
    {
        $files = [];
        foreach (glob("{$this->directory}/{$kind}/*.sql") ?: [] as $path) {
            $files[basename($path, '.sql')] = $path;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function runFile(string $path): void
    {
        foreach (self::statements((string)file_get_contents($path)) as $sql) {
            $this->db->execute($sql);
        }
    }

    /**
     * Splits a script on semicolons that end a line. Our migrations contain
     * no procedural bodies, so this is enough and avoids engine-specific
     * multi-statement handling (PDO's pgsql driver rejects it with bound
     * statements, SQLite silently stops after the first).
     *
     * @return list<string>
     */
    public static function statements(string $script): array
    {
        $lines = array_filter(
            preg_split('/\R/', $script) ?: [],
            static fn(string $line) => !str_starts_with(ltrim($line), '--')
        );

        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn($s) => $s !== ''));
    }
}

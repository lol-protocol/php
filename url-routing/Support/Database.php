<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Thin PDO wrapper shared by SQLite and PostgreSQL. All SQL in this project
 * is written to run unchanged on both engines; which one a site uses is a
 * deployment choice (see docs/DATABASE.md), not something the code knows.
 *
 * Errors are not swallowed: a failing query throws, the router logs it and
 * answers 500 — silently returning "no rows" would render a DB outage as
 * "not found".
 */
final class Database
{
    private ?\PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user = '',
        private readonly string $password = '',
    ) {
    }

    /**
     * Connection for a site: DB_DSN_<SITE> (with DB_USER_<SITE> and
     * DB_PASSWORD_<SITE>), or a local SQLite file under var/ for development.
     *
     * There is deliberately no shared DB_DSN fallback: both sites have tables
     * with the same names (usuarios, grupos, colecciones), so pointing them at
     * one database would mix their data.
     */
    public static function forSite(string $site): self
    {
        $suffix = strtoupper($site);
        $env = static fn(string $name): string => (string)(getenv("{$name}_{$suffix}") ?: '');

        $dsn = $env('DB_DSN') ?: 'sqlite:' . dirname(__DIR__) . "/var/{$site}.sqlite";

        return new self($dsn, $env('DB_USER'), $env('DB_PASSWORD'));
    }

    public function pdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if (str_starts_with($this->dsn, 'sqlite:')) {
            $path = substr($this->dsn, strlen('sqlite:'));
            if ($path !== ':memory:' && $path !== '' && !is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
        }

        $pdo = new \PDO($this->dsn, $this->user, $this->password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        if ($this->driver($pdo) === 'sqlite') {
            // SQLite ignores FOREIGN KEY clauses unless told otherwise; WAL lets
            // readers proceed while a checkout is writing.
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $this->pdo = $pdo;
    }

    public function driver(?\PDO $pdo = null): string
    {
        return (string)($pdo ?? $this->pdo())->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Runs a statement and returns the number of affected rows. */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @template T
     * @param callable(self): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function insert(string $table, array $data): void
    {
        $this->assertIdentifiers($table, $data);
        if ($data === []) {
            throw new \InvalidArgumentException('Nothing to insert');
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->execute("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})", array_values($data));
    }

    public function update(string $table, array $data, array $where): int
    {
        $this->assertIdentifiers($table, $data, $where);
        if ($data === [] || $where === []) {
            throw new \InvalidArgumentException('Update needs both data and a WHERE clause');
        }

        $set = implode(', ', array_map(static fn($c) => "{$c} = ?", array_keys($data)));
        $cond = implode(' AND ', array_map(static fn($c) => "{$c} = ?", array_keys($where)));

        return $this->execute(
            "UPDATE {$table} SET {$set} WHERE {$cond}",
            array_merge(array_values($data), array_values($where))
        );
    }

    public function delete(string $table, array $where): int
    {
        $this->assertIdentifiers($table, $where);
        if ($where === []) {
            throw new \InvalidArgumentException('Refusing to delete without a WHERE clause');
        }

        $cond = implode(' AND ', array_map(static fn($c) => "{$c} = ?", array_keys($where)));
        return $this->execute("DELETE FROM {$table} WHERE {$cond}", array_values($where));
    }

    private function run(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        foreach (array_values($params) as $i => $value) {
            $stmt->bindValue($i + 1, $value, match (true) {
                is_int($value) => \PDO::PARAM_INT,
                is_bool($value) => \PDO::PARAM_BOOL,
                $value === null => \PDO::PARAM_NULL,
                default => \PDO::PARAM_STR,
            });
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * Values are bound as parameters, but table and column names can't be —
     * they're interpolated into the SQL. Reject anything that isn't a plain
     * identifier so a caller passing request data (e.g. $_POST) as $data
     * can't inject SQL through the array keys.
     */
    private function assertIdentifiers(string $table, array ...$columnSets): void
    {
        $names = [$table];
        foreach ($columnSets as $set) {
            foreach (array_keys($set) as $column) {
                $names[] = (string)$column;
            }
        }

        foreach ($names as $name) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                throw new \InvalidArgumentException("Invalid SQL identifier: {$name}");
            }
        }
    }
}

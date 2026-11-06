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

    /** How many transaction() calls are open inside each other right now: names the savepoint of each inner one. */
    private int $profundidad = 0;

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
            // PostgreSQL's TRANSLATE(), which SQLite lacks: the searches use it
            // to ignore accents (Repository::sinAcentos()), so both engines run the same SQL.
            $pdo->sqliteCreateFunction('translate', [self::class, 'traducir'], 3, \PDO::SQLITE_DETERMINISTIC);
            // And octet_length(), which PostgreSQL has and SQLite only since 3.43: sinAcentos() asks it whether a
            // text is all ASCII, to skip the translation. A newer SQLite brings its own, which is faster.
            if (version_compare((string)$pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '3.43.0', '<')) {
                $pdo->sqliteCreateFunction('octet_length', [self::class, 'bytes'], 1, \PDO::SQLITE_DETERMINISTIC);
            }
        }

        if ($this->driver($pdo) === 'pgsql') {
            // PostgreSQL compiles (JIT) any query whose estimated cost is high, and the privacy expressions
            // (Privacidad) make a visitor's searches and lists look expensive to it: with 20,000 personas the
            // compilation took 2.5 s of a search that runs in 70 ms without it, on every request. For short
            // queries like this app's it never pays off, so it is off for this connection whatever the server
            // is configured to do. A server without the setting (before PostgreSQL 11) has no JIT to turn off.
            try {
                $pdo->exec('SET jit = off');
            } catch (\PDOException) {
            }
        }

        return $this->pdo = $pdo;
    }

    /** octet_length($texto): how many bytes the text takes. */
    public static function bytes(?string $texto): ?int
    {
        return $texto === null ? null : strlen($texto);
    }

    /**
     * TRANSLATE($texto, $de, $a): each character of $de becomes the one at the same place in $a, and the
     * characters of $de that have none (when $de is longer) are deleted, as PostgreSQL does. A character
     * repeated in $de keeps its first meaning, also as in PostgreSQL.
     *
     * SQLite calls this once per row a search reads, always with the same two strings, so the map is built
     * once and reused: building it (array_combine of two mb_str_split) used to cost nine times the translation.
     */
    public static function traducir(?string $texto, string $de, string $a): ?string
    {
        static $de0 = null, $a0 = null, $mapa = [];

        if ($texto === null) {
            return null;
        }

        if ($de !== $de0 || $a !== $a0) {
            $nuevos = mb_str_split($a);
            $mapa = [];
            foreach (mb_str_split($de) as $i => $caracter) {
                $mapa[$caracter] ??= $nuevos[$i] ?? '';
            }
            [$de0, $a0] = [$de, $a];
        }

        return $mapa === [] ? $texto : strtr($texto, $mapa);
    }

    public function driver(?\PDO $pdo = null): string
    {
        return (string)($pdo ?? $this->pdo())->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * @return list<array<string, mixed>>
     * @param array<int|string, mixed> $params
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int|string, mixed> $params
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Runs a statement and returns the number of affected rows.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Runs $work in a transaction: it commits if $work returns and rolls everything back if it throws,
     * and the exception goes on.
     *
     * It can be nested. A call inside another one (or inside a transaction somebody opened on the PDO
     * itself) does not begin a second transaction, which both engines refuse; it opens a SAVEPOINT. If the
     * inner $work throws, only what it did is undone, and the outer one can catch that and go on, or let it
     * through and undo everything. So a repository method that opens its own transaction can be called from
     * another that already did.
     *
     * @template T
     * @param callable(self): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $anidada = $pdo->inTransaction();
        $punto = 'sp_' . $this->profundidad;

        if ($anidada) {
            $pdo->exec("SAVEPOINT {$punto}");
        } else {
            $pdo->beginTransaction();
        }
        $this->profundidad++;

        try {
            $result = $work($this);
            $this->profundidad--;
            if ($anidada) {
                $pdo->exec("RELEASE SAVEPOINT {$punto}");
            } else {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            $this->profundidad = max(0, $this->profundidad - 1);
            $this->deshacer($pdo, $anidada, $punto);
            throw $e;
        }
    }

    /**
     * Undoes a failed transaction() without hiding why it failed: if the engine already ended the
     * transaction (a COMMIT that failed on a deferred constraint, $work that rolled back itself) there is
     * nothing to undo, and a rollBack() would throw "There is no active transaction" over the real error.
     */
    private function deshacer(\PDO $pdo, bool $anidada, string $punto): void
    {
        try {
            if (!$pdo->inTransaction()) {
                return;
            }
            if ($anidada) {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$punto}");
                $pdo->exec("RELEASE SAVEPOINT {$punto}");
            } else {
                $pdo->rollBack();
            }
        } catch (\PDOException) {
            // The error that matters is the one $work threw, which the caller is about to get.
        }
    }

    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
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

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where): int
    {
        $this->assertIdentifiers($table, $where);
        if ($where === []) {
            throw new \InvalidArgumentException('Refusing to delete without a WHERE clause');
        }

        $cond = implode(' AND ', array_map(static fn($c) => "{$c} = ?", array_keys($where)));
        return $this->execute("DELETE FROM {$table} WHERE {$cond}", array_values($where));
    }

    /**
     * @param array<int|string, mixed> $params
     */
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
     *
     * @param array<string, mixed> ...$columnSets
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

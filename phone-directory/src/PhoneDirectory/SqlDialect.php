<?php

namespace PhoneDirectory;

/**
 * The SQL that differs between SQLite, MySQL/MariaDB and PostgreSQL.
 *
 * Columns are declared with a logical type (id, string, text, int, country, datetime)
 * followed by optional modifiers, e.g. ['string', 'NOT NULL'].
 */
final class SqlDialect
{
    private const TYPES = [
        'sqlite' => [
            'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'datetime' => 'DATETIME',
        ],
        'mysql' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            // TIMESTAMP only spans 1970-2038; historical directories go back to the 1870s.
            'datetime' => 'DATETIME',
        ],
        'pgsql' => [
            'id' => 'SERIAL PRIMARY KEY',
            'datetime' => 'TIMESTAMP',
        ],
    ];

    private const COMMON_TYPES = [
        // MySQL cannot index TEXT without a prefix length, so indexed text columns are VARCHAR.
        'string' => 'VARCHAR(255)',
        'text' => 'TEXT',
        'int' => 'INTEGER',
        'country' => 'VARCHAR(2)',
    ];

    private \PDO $pdo;
    private string $driver;

    public function __construct(\PDO $pdo)
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!isset(self::TYPES[$driver])) {
            throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
        }

        $this->pdo = $pdo;
        $this->driver = $driver;
    }

    public static function connect(string $dsn, ?string $username = null, ?string $password = null): \PDO
    {
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

        // MySQL otherwise reports only rows whose values changed, so re-saving an unchanged record would look like a failed update.
        if (str_starts_with($dsn, 'mysql:') && defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
            $options[\PDO::MYSQL_ATTR_FOUND_ROWS] = true;
        }

        try {
            return new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Database connection failed: {$e->getMessage()}");
        }
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Creates the table if needed, adds any of $addedColumns an existing table lacks, and builds the indexes.
     */
    public function ensureTable(string $table, array $baseColumns, array $addedColumns, array $indexedColumns): void
    {
        $definitions = [];
        foreach ($baseColumns as $column => $spec) {
            $definitions[] = "{$column} {$this->columnDefinition($spec)}";
        }
        $tableOptions = $this->driver === 'mysql' ? ' DEFAULT CHARSET=utf8mb4' : '';
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (\n    " . implode(",\n    ", $definitions) . "\n){$tableOptions}");

        $existing = $this->existingColumns($table);
        foreach ($addedColumns as $column => $spec) {
            if (!in_array($column, $existing, true)) {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$this->columnDefinition($spec)}");
            }
        }

        // Index names are database-wide, so they carry the table name.
        foreach ($indexedColumns as $column) {
            $this->createIndex($table, "idx_{$table}_{$column}", $column);
        }
    }

    /** SQL condition for a case-insensitive "contains" match; bind the value from containsValue(). */
    public function containsCondition(string $column, string $parameter): string
    {
        return "LOWER({$column}) LIKE {$parameter} ESCAPE '!'";
    }

    public function containsValue(string $text): string
    {
        return '%' . strtr(mb_strtolower($text, 'UTF-8'), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }

    private function columnDefinition(array $spec): string
    {
        [$type, $modifiers] = $spec + [1 => ''];
        $sqlType = self::TYPES[$this->driver][$type] ?? self::COMMON_TYPES[$type]
            ?? throw new \InvalidArgumentException("Unknown column type: {$type}");

        return trim("{$sqlType} {$modifiers}");
    }

    private function existingColumns(string $table): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$table} LIMIT 0");
        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $columns[] = strtolower($stmt->getColumnMeta($i)['name']);
        }

        return $columns;
    }

    private function createIndex(string $table, string $index, string $column): void
    {
        if ($this->driver !== 'mysql') {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS {$index} ON {$table}({$column})");
            return;
        }

        // MySQL has no CREATE INDEX IF NOT EXISTS.
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
        );
        $stmt->execute([$table, $index]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec("CREATE INDEX {$index} ON {$table}({$column})");
        }
    }
}

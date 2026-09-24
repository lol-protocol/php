<?php

declare(strict_types=1);

namespace App\Support;

class Database
{
    private static ?Database $instance = null;
    private ?\PDO $connection = null;
    private string $dsn;
    private string $user;
    private string $password;

    private function __construct()
    {
        $this->dsn = getenv('DB_DSN') ?: 'sqlite:' . sys_get_temp_dir() . '/app.db';
        $this->user = getenv('DB_USER') ?: '';
        $this->password = getenv('DB_PASSWORD') ?: '';
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connect(): bool
    {
        if ($this->connection !== null) {
            return true;
        }

        try {
            $this->connection = new \PDO(
                $this->dsn,
                $this->user,
                $this->password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            return true;
        } catch (\PDOException $e) {
            ServiceLocator::getInstance()->getLogger()->error('Database connection failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function prepare(string $sql): ?\PDOStatement
    {
        if (!$this->connect()) {
            return null;
        }

        try {
            return $this->connection->prepare($sql);
        } catch (\PDOException $e) {
            ServiceLocator::getInstance()->getLogger()->error('Prepare statement failed', [
                'sql' => $sql,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function execute(\PDOStatement $stmt, array $params = []): bool
    {
        try {
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            ServiceLocator::getInstance()->getLogger()->error('Execute statement failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql);
        if ($stmt === null) {
            return [];
        }

        if (!$this->execute($stmt, $params)) {
            return [];
        }

        return $stmt->fetchAll() ?: [];
    }

    public function queryOne(string $sql, array $params = []): array|null
    {
        $stmt = $this->prepare($sql);
        if ($stmt === null) {
            return null;
        }

        if (!$this->execute($stmt, $params)) {
            return null;
        }

        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function insert(string $table, array $data): int|false
    {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->prepare($sql);
        if ($stmt === null) {
            return false;
        }

        if (!$this->execute($stmt, array_values($data))) {
            return false;
        }

        $id = $this->connection->lastInsertId();
        return $id !== false ? (int)$id : false;
    }

    public function update(string $table, array $data, array $where): int
    {
        if (empty($data) || empty($where)) {
            return 0;
        }

        $setClauses = array_map(fn($col) => "$col = ?", array_keys($data));
        $whereClauses = array_map(fn($col) => "$col = ?", array_keys($where));

        $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE " . implode(' AND ', $whereClauses);

        $stmt = $this->prepare($sql);
        if ($stmt === null) {
            return 0;
        }

        $params = array_merge(array_values($data), array_values($where));
        if (!$this->execute($stmt, $params)) {
            return 0;
        }

        return $stmt->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            return 0;
        }

        $whereClauses = array_map(fn($col) => "$col = ?", array_keys($where));
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClauses);

        $stmt = $this->prepare($sql);
        if ($stmt === null) {
            return 0;
        }

        if (!$this->execute($stmt, array_values($where))) {
            return 0;
        }

        return $stmt->rowCount();
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }

    public function beginTransaction(): bool
    {
        if (!$this->connect()) {
            return false;
        }
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        if ($this->connection === null) {
            return false;
        }
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        if ($this->connection === null) {
            return false;
        }
        return $this->connection->rollBack();
    }
}

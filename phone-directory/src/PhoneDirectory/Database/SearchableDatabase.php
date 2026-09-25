<?php

namespace PhoneDirectory\Database;

trait SearchableDatabase
{
    /**
     * Execute a prepared statement and return fetched rows as entity objects
     *
     * @template T
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param callable $rowMapper Function to convert database row to entity
     * @return array Mapped entities
     */
    protected function queryAndMap(string $sql, array $params, callable $rowMapper): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($rowMapper, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Execute a prepared statement and return first row as entity object or null
     *
     * @template T
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param callable $rowMapper Function to convert database row to entity
     * @return object|null Mapped entity or null
     */
    protected function queryAndMapSingle(string $sql, array $params, callable $rowMapper)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $rowMapper($row) : null;
    }
}

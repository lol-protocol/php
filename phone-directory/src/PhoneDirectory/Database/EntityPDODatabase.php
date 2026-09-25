<?php

namespace PhoneDirectory\Database;

use PhoneDirectory\DatabaseConstants;
use PhoneDirectory\Exception\DatabaseException;

abstract class EntityPDODatabase extends AbstractPDODatabase
{
    abstract protected function getTableName(): string;

    abstract protected function ensureConnected(): void;

    abstract protected function rowToEntity(array $row);

    protected function ensureConnection(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
    }

    /**
     * Insert entities in a single transaction, preparing the statement once and reusing it for
     * every row instead of re-preparing per row. $typeGuard skips entities of the wrong type so
     * the returned count reflects rows actually inserted, not loop iterations.
     */
    protected function insertManyWithTransaction(array $entities, string $sql, callable $typeGuard, callable $paramMapperFn): int
    {
        $this->ensureConnection();

        $count = 0;
        $stmt = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();

        try {
            foreach ($entities as $entity) {
                if (!$typeGuard($entity)) {
                    continue;
                }
                $stmt->execute($paramMapperFn($entity));
                $count++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            try {
                $this->pdo->rollBack();
            } catch (\Throwable $rollbackError) {
                // Log but do not suppress original error
            }
            throw new DatabaseException(sprintf(DatabaseConstants::ERROR_BATCH_INSERT_FAILED, $e->getMessage()), 0, $e);
        }

        return $count;
    }

    protected function updateWithTransaction(callable $updateFn): bool
    {
        $this->ensureConnection();
        return $updateFn();
    }

    protected function findOneById(int $id): ?array
    {
        $this->ensureConnection();

        $sql = sprintf(DatabaseConstants::QUERY_LIMIT_ONE_BY_ID, $this->getTableName());
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    protected function findOneByPhone(string $phone): ?array
    {
        $this->ensureConnection();

        $sql = 'SELECT * FROM ' . $this->getTableName() . ' WHERE phone_number = :phone LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':phone' => $phone]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    protected function findMany(string $condition, array $params, string $orderBy = 'id'): array
    {
        $this->ensureConnection();

        $sql = 'SELECT * FROM ' . $this->getTableName() . ' WHERE ' . $condition . ' ORDER BY ' . $orderBy;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    protected function getAllOrdered(string $orderBy = 'id'): array
    {
        $this->ensureConnection();

        $sql = sprintf(DatabaseConstants::QUERY_GET_ALL, $this->getTableName(), $orderBy);
        $stmt = $this->pdo->query($sql);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    protected function deleteById(int $id): bool
    {
        $this->ensureConnection();

        $sql = sprintf(DatabaseConstants::QUERY_DELETE_BY_ID, $this->getTableName());
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    protected function countAll(): int
    {
        $this->ensureConnection();

        $sql = sprintf(DatabaseConstants::QUERY_COUNT, $this->getTableName());
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($row['count'] ?? 0);
    }

    protected function clearTable(): bool
    {
        $this->ensureConnection();

        $this->pdo->exec('DELETE FROM ' . $this->getTableName());
        return true;
    }

    protected function validateInsertId($id): int
    {
        if (!$id || $id === '0' || $id === 0) {
            throw new DatabaseException(DatabaseConstants::ERROR_NO_LAST_INSERT_ID);
        }
        return (int) $id;
    }

    protected function validateEntityId($id): void
    {
        if ($id === 0) {
            throw new \InvalidArgumentException(DatabaseConstants::ERROR_UPDATE_WITHOUT_ID);
        }
    }

    protected function executeWithBackfill(string $selectQuery, string $updateStmt, callable $backfillValuesFn): void
    {
        $this->ensureConnection();

        $result = $this->pdo->query($selectQuery);
        if ($result === false) {
            throw new DatabaseException(sprintf(
                DatabaseConstants::SQL_BACKFILL_NOT_NULL_ERROR,
                $this->getTableName(),
                implode(', ', $this->pdo->errorInfo())
            ));
        }

        $rows = $result->fetchAll(\PDO::FETCH_ASSOC);
        if ($rows === []) {
            return;
        }

        $stmt = $this->pdo->prepare($updateStmt);
        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute($backfillValuesFn($row));
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            try {
                $this->pdo->rollBack();
            } catch (\Throwable $rollbackError) {
                // Log but do not suppress original error
            }
            throw new DatabaseException(sprintf(DatabaseConstants::ERROR_BACKFILL_TRANSACTION_FAILED, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Build a parameterized WHERE clause from search criteria, skipping empty ones.
     *
     * $fieldMap maps a criteria key to ['column' => string, 'contains' => bool, 'fold' => bool].
     * 'contains' uses the dialect's substring match instead of equality; 'fold' applies accent/case
     * folding to the value before comparing it against a *_folded column.
     *
     * @return array{0: string[], 1: array<string, mixed>} [$whereClauses, $params]
     */
    protected function buildSearchWhere(array $criteria, array $fieldMap): array
    {
        $where = [];
        $params = [];

        foreach ($fieldMap as $criteriaKey => $spec) {
            if (empty($criteria[$criteriaKey])) {
                continue;
            }

            $placeholder = ':' . $criteriaKey;
            $value = ($spec['fold'] ?? false) ? $this->fold($criteria[$criteriaKey]) : $criteria[$criteriaKey];

            if ($spec['contains'] ?? false) {
                $where[] = $this->dialect->containsCondition($spec['column'], $placeholder);
                $params[$placeholder] = $this->dialect->containsValue($value);
            } else {
                $where[] = $spec['column'] . ' = ' . $placeholder;
                $params[$placeholder] = $value;
            }
        }

        return [$where, $params];
    }

    protected function getDefaultCountryCode(): string
    {
        return DatabaseConstants::DEFAULT_COUNTRY_CODE;
    }

    protected function formatDatetime(\DateTime $date): string
    {
        return $date->format(DatabaseConstants::DATETIME_FORMAT);
    }
}

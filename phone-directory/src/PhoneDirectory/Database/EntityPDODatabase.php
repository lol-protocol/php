<?php

namespace PhoneDirectory\Database;

use PhoneDirectory\DatabaseConstants;

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

    protected function insertBatchWithTransaction(array $entities, callable $insertFn): int
    {
        $this->ensureConnection();

        $count = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($entities as $entity) {
                $insertFn($entity);
                $count++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            try {
                $this->pdo->rollBack();
            } catch (\Throwable $rollbackError) {
                // Log but do not suppress original error
            }
            throw new \RuntimeException(sprintf(DatabaseConstants::ERROR_BATCH_INSERT_FAILED, $e->getMessage()), 0, $e);
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
            throw new \RuntimeException(DatabaseConstants::ERROR_NO_LAST_INSERT_ID);
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
            throw new \RuntimeException(sprintf(
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
            throw new \RuntimeException(sprintf(DatabaseConstants::ERROR_BACKFILL_TRANSACTION_FAILED, $e->getMessage()), 0, $e);
        }
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

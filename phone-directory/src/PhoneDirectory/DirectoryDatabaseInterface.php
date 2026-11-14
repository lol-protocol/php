<?php

namespace PhoneDirectory;

/**
 * What the database of natural persons (PhoneDirectoryDatabaseInterface) and the one of businesses
 * (JuridicalEntityDatabaseInterface) have in common, written once.
 *
 * The entity type only appears in the docblocks (PHPStan generics): PHP cannot make a parameter type
 * depend on a template, so insert(), update() and search(), which take the concrete entity or the
 * criteria of one table, stay in each of the two interfaces. findById() and findByPhone() return
 * `?object` at run time and `?TEntity` for PHPStan.
 *
 * @template TEntity of object
 */
interface DirectoryDatabaseInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    public function createTable(): void;

    /**
     * Inserts the entities in one transaction and returns how many rows were inserted; anything in
     * $entities that is not a TEntity is skipped, not counted.
     *
     * @param array<TEntity> $entities
     */
    public function insertBatch(array $entities): int;

    /** @return TEntity|null */
    public function findById(int $id): ?object;

    /**
     * @param int|null $limit The most entities to return; null for all
     * @param int $offset How many of the entities the reader may see to skip first
     * @return list<TEntity>
     * @throws \InvalidArgumentException If $limit is below 1 or $offset is below 0
     */
    public function findByStreet(string $street, ?int $limit = null, int $offset = 0): array;

    /** @return TEntity|null */
    public function findByPhone(string $phone): ?object;

    /**
     * @param int|null $limit The most entities to return; null for all
     * @param int $offset How many of the entities the reader may see to skip first
     * @return list<TEntity>
     * @throws \InvalidArgumentException If $limit is below 1 or $offset is below 0
     */
    public function getAll(?int $limit = null, int $offset = 0): array;

    public function delete(int $id): bool;

    public function count(): int;

    public function clear(): bool;
}

<?php

namespace PhoneDirectory;

use PhoneDirectory\Entity\JuridicalEntity;

interface JuridicalEntityDatabaseInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    public function createTable(): void;

    public function insert(JuridicalEntity $entity): int;

    /**
     * Inserts the entities in one transaction and returns how many rows were inserted; anything in
     * $entities that is not a JuridicalEntity is skipped, not counted.
     *
     * @param array<JuridicalEntity> $entities
     */
    public function insertBatch(array $entities): int;

    public function findById(int $id): ?JuridicalEntity;

    /** @return list<JuridicalEntity> */
    public function findByBusinessName(string $name): array;

    /** @return list<JuridicalEntity> */
    public function findByStreet(string $street): array;

    public function findByPhone(string $phone): ?JuridicalEntity;

    /** @return list<JuridicalEntity> */
    public function findByBusinessType(string $type): array;

    /** @return list<JuridicalEntity> */
    public function getAll(): array;

    public function update(JuridicalEntity $entity): bool;

    public function delete(int $id): bool;

    public function count(): int;

    /**
     * @param array{businessName?: string, street?: string, phone?: string, businessType?: string} $criteria
     * @return list<JuridicalEntity>
     */
    public function search(array $criteria): array;

    public function clear(): bool;
}

<?php

namespace PhoneDirectory;

interface JuridicalEntityDatabaseInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    public function createTable(): void;

    public function insert(JuridicalEntity $entity): int;

    public function insertBatch(array $entities): int;

    public function findById(int $id): ?JuridicalEntity;

    public function findByBusinessName(string $name): array;

    public function findByStreet(string $street): array;

    public function findByPhone(string $phone): ?JuridicalEntity;

    public function findByBusinessType(string $type): array;

    public function getAll(): array;

    public function update(JuridicalEntity $entity): bool;

    public function delete(int $id): bool;

    public function count(): int;

    public function search(array $criteria): array;

    public function clear(): bool;
}

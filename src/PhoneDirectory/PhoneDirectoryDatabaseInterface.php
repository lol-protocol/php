<?php

namespace PhoneDirectory;

interface PhoneDirectoryDatabaseInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    public function createTable(): void;

    public function insert(PhoneDirectoryEntry $entry): int;

    public function insertBatch(array $entries): int;

    public function findById(int $id): ?PhoneDirectoryEntry;

    public function findByName(string $name): array;

    public function findByStreet(string $street): array;

    public function findByPhone(string $phone): ?PhoneDirectoryEntry;

    /** Entries whose surname sounds like $surname; $language enables that language's spelling equivalences. */
    public function findBySurnameSound(string $surname, ?string $language = null): array;

    public function getAll(): array;

    public function update(PhoneDirectoryEntry $entry): bool;

    public function delete(int $id): bool;

    public function count(): int;

    public function search(array $criteria): array;

    public function clear(): bool;
}

<?php

namespace PhoneDirectory;

use PhoneDirectory\Entity\PhoneDirectoryEntry;

interface PhoneDirectoryDatabaseInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    public function createTable(): void;

    public function insert(PhoneDirectoryEntry $entry): int;

    /**
     * Inserts the entries in one transaction and returns how many rows were inserted; anything in
     * $entries that is not a PhoneDirectoryEntry is skipped, not counted.
     *
     * @param array<PhoneDirectoryEntry> $entries
     */
    public function insertBatch(array $entries): int;

    public function findById(int $id): ?PhoneDirectoryEntry;

    /** @return list<PhoneDirectoryEntry> */
    public function findByName(string $name): array;

    /** @return list<PhoneDirectoryEntry> */
    public function findByStreet(string $street): array;

    public function findByPhone(string $phone): ?PhoneDirectoryEntry;

    /**
     * Entries whose surname sounds like $surname; $language enables that language's spelling equivalences.
     *
     * @return list<PhoneDirectoryEntry>
     */
    public function findBySurnameSound(string $surname, ?string $language = null): array;

    /** @return list<PhoneDirectoryEntry> */
    public function findBySourceDirectory(string $sourceDirectoryId): array;

    /** @return list<PhoneDirectoryEntry> */
    public function getAll(): array;

    public function update(PhoneDirectoryEntry $entry): bool;

    public function delete(int $id): bool;

    public function count(): int;

    /**
     * @param array{name?: string, street?: string, phone?: string} $criteria
     * @return list<PhoneDirectoryEntry>
     */
    public function search(array $criteria): array;

    public function clear(): bool;
}

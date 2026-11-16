<?php

namespace PhoneDirectory;

use PhoneDirectory\Entity\PhoneDirectoryEntry;

/**
 * The database of natural persons. The calls every directory database has (connect, insertBatch,
 * findById, getAll...) are in DirectoryDatabaseInterface.
 *
 * @extends DirectoryDatabaseInterface<PhoneDirectoryEntry>
 */
interface PhoneDirectoryDatabaseInterface extends DirectoryDatabaseInterface
{
    public function insert(PhoneDirectoryEntry $entry): int;

    /**
     * @param int|null $limit The most entries to return; null for all
     * @param int $offset How many of the entries the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     * @throws \InvalidArgumentException If $limit is below 1 or $offset is below 0
     */
    public function findByName(string $name, ?int $limit = null, int $offset = 0): array;

    /**
     * Entries whose surname sounds like $surname; $language enables that language's spelling equivalences.
     *
     * @param int|null $limit The most entries to return; null for all
     * @param int $offset How many of the entries the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     * @throws \InvalidArgumentException If $limit is below 1 or $offset is below 0
     */
    public function findBySurnameSound(string $surname, ?string $language = null, ?int $limit = null, int $offset = 0): array;

    /**
     * @param int|null $limit The most entries to return; null for all
     * @param int $offset How many of the entries the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     * @throws \InvalidArgumentException If $limit is below 1 or $offset is below 0
     */
    public function findBySourceDirectory(string $sourceDirectoryId, ?int $limit = null, int $offset = 0): array;

    public function update(PhoneDirectoryEntry $entry): bool;

    /**
     * @param array{name?: string, street?: string, phone?: string} $criteria
     * @param int|null $limit The most entries to return; null for all
     * @param int $offset How many of the entries the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     * @throws \InvalidArgumentException If $criteria has a key other than those, $limit is below 1 or $offset is below 0
     */
    public function search(array $criteria, ?int $limit = null, int $offset = 0): array;
}

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

    /** @return list<PhoneDirectoryEntry> */
    public function findByName(string $name): array;

    /**
     * Entries whose surname sounds like $surname; $language enables that language's spelling equivalences.
     *
     * @return list<PhoneDirectoryEntry>
     */
    public function findBySurnameSound(string $surname, ?string $language = null): array;

    /** @return list<PhoneDirectoryEntry> */
    public function findBySourceDirectory(string $sourceDirectoryId): array;

    public function update(PhoneDirectoryEntry $entry): bool;

    /**
     * @param array{name?: string, street?: string, phone?: string} $criteria
     * @return list<PhoneDirectoryEntry>
     * @throws \InvalidArgumentException If $criteria has a key other than those
     */
    public function search(array $criteria): array;
}

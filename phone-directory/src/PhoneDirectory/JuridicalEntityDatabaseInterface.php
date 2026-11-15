<?php

namespace PhoneDirectory;

use PhoneDirectory\Entity\JuridicalEntity;

/**
 * The database of businesses. The calls every directory database has (connect, insertBatch,
 * findById, getAll...) are in DirectoryDatabaseInterface.
 *
 * @extends DirectoryDatabaseInterface<JuridicalEntity>
 */
interface JuridicalEntityDatabaseInterface extends DirectoryDatabaseInterface
{
    public function insert(JuridicalEntity $entity): int;

    /**
     * @param int|null $limit The most businesses to return; null for all
     * @param int $offset How many of the businesses the reader may see to skip first
     * @return list<JuridicalEntity>
     * @throws \InvalidArgumentException If $limit is below 1 or $offset is below 0
     */
    public function findByBusinessName(string $name, ?int $limit = null, int $offset = 0): array;

    /**
     * @param int|null $limit The most businesses to return; null for all
     * @param int $offset How many of the businesses the reader may see to skip first
     * @return list<JuridicalEntity>
     * @throws \InvalidArgumentException If $limit is below 1 or $offset is below 0
     */
    public function findByBusinessType(string $type, ?int $limit = null, int $offset = 0): array;

    public function update(JuridicalEntity $entity): bool;

    /**
     * @param array{businessName?: string, street?: string, phone?: string, businessType?: string} $criteria
     * @param int|null $limit The most businesses to return; null for all
     * @param int $offset How many of the businesses the reader may see to skip first
     * @return list<JuridicalEntity>
     * @throws \InvalidArgumentException If $criteria has a key other than those, $limit is below 1 or $offset is below 0
     */
    public function search(array $criteria, ?int $limit = null, int $offset = 0): array;
}

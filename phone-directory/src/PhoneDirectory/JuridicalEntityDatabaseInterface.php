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

    /** @return list<JuridicalEntity> */
    public function findByBusinessName(string $name): array;

    /** @return list<JuridicalEntity> */
    public function findByBusinessType(string $type): array;

    public function update(JuridicalEntity $entity): bool;

    /**
     * @param array{businessName?: string, street?: string, phone?: string, businessType?: string} $criteria
     * @return list<JuridicalEntity>
     * @throws \InvalidArgumentException If $criteria has a key other than those
     */
    public function search(array $criteria): array;
}

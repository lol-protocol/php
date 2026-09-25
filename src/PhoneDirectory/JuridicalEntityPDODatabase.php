<?php

namespace PhoneDirectory;

use PhoneDirectory\Database\EntityPDODatabase;
use PhoneDirectory\Entity\JuridicalEntity;

class JuridicalEntityPDODatabase extends EntityPDODatabase implements JuridicalEntityDatabaseInterface
{
    private const TABLE_NAME = 'juridical_entities';

    private const BASE_COLUMNS = [
        'id' => ['id'],
        'business_name' => ['string', 'NOT NULL'],
        'legal_name' => ['text'],
        'street' => ['string', 'NOT NULL'],
        'phone_number' => ['string'],
        'business_type' => ['string'],
        'record_date' => ['datetime', 'DEFAULT CURRENT_TIMESTAMP'],
        'created_at' => ['datetime', 'DEFAULT CURRENT_TIMESTAMP'],
        'updated_at' => ['datetime', 'DEFAULT CURRENT_TIMESTAMP'],
    ];

    private const ADDED_COLUMNS = [
        'country_code' => ['country', "NOT NULL DEFAULT 'US'"],
        'source_directory_id' => ['string'],
        'source_line' => ['int'],
        'business_name_folded' => ['string'],
        'street_folded' => ['string'],
    ];

    private const INDEXED_COLUMNS = [
        'business_name', 'street', 'phone_number', 'business_type', 'country_code', 'source_directory_id',
        'business_name_folded', 'street_folded',
    ];

    protected function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    protected function ensureConnected(): void
    {
        $this->ensureConnection();
    }

    public function createTable(): void
    {
        $this->ensureConnection();

        $this->dialect->ensureTable(self::TABLE_NAME, self::BASE_COLUMNS, self::ADDED_COLUMNS, self::INDEXED_COLUMNS);

        $this->pdo->exec(sprintf(DatabaseConstants::MIGRATION_NULL_COUNTRY_CODE, self::TABLE_NAME, $this->getDefaultCountryCode()));
        $this->backfillFoldedColumns();
    }

    private function backfillFoldedColumns(): void
    {
        $selectQuery = 'SELECT id, business_name, street FROM ' . self::TABLE_NAME . ' WHERE business_name_folded IS NULL';
        $updateStmt = 'UPDATE juridical_entities SET business_name_folded = :businessNameFolded, street_folded = :streetFolded WHERE id = :id';

        $this->executeWithBackfill($selectQuery, $updateStmt, function (array $row) {
            $entity = new JuridicalEntity(
                businessName: $row['business_name'],
                street: $row['street'],
                countryCode: $this->getDefaultCountryCode()
            );
            return $this->foldedSearchParams($entity) + [':id' => $row['id']];
        });
    }

    public function insert(JuridicalEntity $entity): int
    {
        $this->ensureConnection();

        $sql = <<<SQL
        INSERT INTO juridical_entities (business_name, legal_name, street, phone_number, business_type, country_code,
            source_directory_id, source_line, business_name_folded, street_folded, record_date)
        VALUES (:businessName, :legalName, :street, :phoneNumber, :businessType, :countryCode,
            :sourceDirectoryId, :sourceLine, :businessNameFolded, :streetFolded, :recordDate)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entityParams($entity) + [
            ':recordDate' => $this->formatDatetime($entity->getRecordDate()),
        ]);

        return $this->validateInsertId($this->pdo->lastInsertId());
    }

    public function insertBatch(array $entities): int
    {
        return $this->insertBatchWithTransaction($entities, function ($entity) {
            if ($entity instanceof JuridicalEntity) {
                $this->insert($entity);
            }
        });
    }

    public function findById(int $id): ?JuridicalEntity
    {
        $row = $this->findOneById($id);
        return $row ? $this->rowToEntity($row) : null;
    }

    public function findByBusinessName(string $name): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE ' . $this->dialect->containsCondition('business_name_folded', ':name') . ' ORDER BY business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $this->dialect->containsValue($this->fold($name))]);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByStreet(string $street): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE ' . $this->dialect->containsCondition('street_folded', ':street') . ' ORDER BY street, business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':street' => $this->dialect->containsValue($this->fold($street))]);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByPhone(string $phone): ?JuridicalEntity
    {
        $row = $this->findOneByPhone($phone);
        return $row ? $this->rowToEntity($row) : null;
    }

    public function findByBusinessType(string $type): array
    {
        $this->ensureConnection();
        $sql = $this->dialect->containsCondition('business_type', ':type');
        return $this->findMany($sql, [':type' => $this->dialect->containsValue($type)], 'business_name');
    }

    public function getAll(): array
    {
        return $this->getAllOrdered('business_name');
    }

    public function update(JuridicalEntity $entity): bool
    {
        $this->ensureConnection();
        $this->validateEntityId($entity->getId());

        $sql = <<<SQL
        UPDATE juridical_entities
        SET business_name = :businessName, legal_name = :legalName, street = :street,
            phone_number = :phoneNumber, business_type = :businessType, country_code = :countryCode,
            source_directory_id = :sourceDirectoryId, source_line = :sourceLine,
            business_name_folded = :businessNameFolded, street_folded = :streetFolded, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entityParams($entity) + [':id' => $entity->getId()]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        return $this->deleteById($id);
    }

    public function count(): int
    {
        return $this->countAll();
    }

    public function search(array $criteria): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $where = [];
        $params = [];

        if (!empty($criteria['businessName'])) {
            $where[] = $this->dialect->containsCondition('business_name_folded', ':businessName');
            $params[':businessName'] = $this->dialect->containsValue($this->fold($criteria['businessName']));
        }

        if (!empty($criteria['street'])) {
            $where[] = $this->dialect->containsCondition('street_folded', ':street');
            $params[':street'] = $this->dialect->containsValue($this->fold($criteria['street']));
        }

        if (!empty($criteria['phone'])) {
            $where[] = 'phone_number = :phone';
            $params[':phone'] = $criteria['phone'];
        }

        if (!empty($criteria['businessType'])) {
            $where[] = $this->dialect->containsCondition('business_type', ':businessType');
            $params[':businessType'] = $this->dialect->containsValue($criteria['businessType']);
        }

        if (empty($where)) {
            return $this->getAll();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE ' . implode(' AND ', $where) . ' ORDER BY business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function clear(): bool
    {
        return $this->clearTable();
    }

    protected function rowToEntity(array $row): JuridicalEntity
    {
        return new JuridicalEntity(
            businessName: $row['business_name'],
            street: $row['street'],
            legalName: $row['legal_name'],
            businessType: $row['business_type'],
            phoneNumber: $row['phone_number'],
            id: (int) $row['id'],
            recordDate: new \DateTime($row['record_date']),
            countryCode: $row['country_code'] ?? $this->getDefaultCountryCode(),
            sourceDirectoryId: $row['source_directory_id'] ?? null,
            sourceLine: isset($row['source_line']) ? (int) $row['source_line'] : null
        );
    }

    private function entityParams(JuridicalEntity $entity): array
    {
        return [
            ':businessName' => $entity->getBusinessName(),
            ':legalName' => $entity->getLegalName(),
            ':street' => $entity->getStreet(),
            ':phoneNumber' => $entity->getPhoneNumber(),
            ':businessType' => $entity->getBusinessType(),
            ':countryCode' => $entity->getCountryCode(),
            ':sourceDirectoryId' => $entity->getSourceDirectoryId(),
            ':sourceLine' => $entity->getSourceLine(),
        ] + $this->foldedSearchParams($entity);
    }

    private function foldedSearchParams(JuridicalEntity $entity): array
    {
        return [
            ':businessNameFolded' => $this->fold($entity->getBusinessName()),
            ':streetFolded' => $this->fold($entity->getStreet()),
        ];
    }

}

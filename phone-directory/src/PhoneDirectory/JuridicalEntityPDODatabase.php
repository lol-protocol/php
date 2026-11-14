<?php

namespace PhoneDirectory;

use PhoneDirectory\Database\EntityPDODatabase;
use PhoneDirectory\Entity\JuridicalEntity;

/**
 * @extends EntityPDODatabase<JuridicalEntity>
 * @phpstan-import-type Row from EntityPDODatabase
 * @phpstan-import-type SqlParams from EntityPDODatabase
 */
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

    private readonly Privacy $privacy;

    /**
     * @param Privacy|null $privacy who is asking: by default a visitor, who gets no business named after
     *        someone who may be alive (see Privacy::mayNameALivingPerson). Pass Privacy::forOwner() to read
     *        everything that is stored.
     */
    public function __construct(string $dsn = 'sqlite::memory:', ?string $username = null, ?string $password = null, ?Privacy $privacy = null)
    {
        parent::__construct($dsn, $username, $password);
        $this->privacy = $privacy ?? Privacy::forVisitors();
    }

    public function getPrivacy(): Privacy
    {
        return $this->privacy;
    }

    protected function getTableName(): string
    {
        return self::TABLE_NAME;
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

    private function insertSql(): string
    {
        return <<<SQL
        INSERT INTO juridical_entities (business_name, legal_name, street, phone_number, business_type, country_code,
            source_directory_id, source_line, business_name_folded, street_folded, record_date)
        VALUES (:businessName, :legalName, :street, :phoneNumber, :businessType, :countryCode,
            :sourceDirectoryId, :sourceLine, :businessNameFolded, :streetFolded, :recordDate)
        SQL;
    }

    /** @return SqlParams */
    private function insertParams(JuridicalEntity $entity): array
    {
        return $this->entityParams($entity) + [
            ':recordDate' => $this->formatDatetime($entity->getRecordDate()),
        ];
    }

    public function insert(JuridicalEntity $entity): int
    {
        $this->ensureConnection();

        $stmt = $this->pdo->prepare($this->insertSql());
        $stmt->execute($this->insertParams($entity));

        return $this->validateInsertId($this->pdo->lastInsertId());
    }

    /** @param array<JuridicalEntity> $entities */
    public function insertBatch(array $entities): int
    {
        return $this->insertManyWithTransaction(
            $entities,
            $this->insertSql(),
            fn ($entity) => $entity instanceof JuridicalEntity,
            fn (JuridicalEntity $entity) => $this->insertParams($entity)
        );
    }

    public function findById(int $id): ?JuridicalEntity
    {
        $row = $this->findOneById($id);

        return $row ? $this->shown([$row])[0] ?? null : null;
    }

    /** @return list<JuridicalEntity> */
    public function findByBusinessName(string $name, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureConnection();

        return $this->queryShown(
            $this->dialect->containsCondition('business_name_folded', ':name'),
            [':name' => $this->dialect->containsValue($this->fold($name))],
            'business_name',
            $limit,
            $offset
        );
    }

    /** @return list<JuridicalEntity> */
    public function findByStreet(string $street, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureConnection();

        return $this->queryShown(
            $this->dialect->containsCondition('street_folded', ':street'),
            [':street' => $this->dialect->containsValue($this->fold($street))],
            'street, business_name',
            $limit,
            $offset
        );
    }

    public function findByPhone(string $phone): ?JuridicalEntity
    {
        if (!$this->privacy->hidesLiving()) {
            $row = $this->findOneByPhone($phone);
            return $row ? $this->rowToEntity($row) : null;
        }

        // A number is reused from one edition to the next: the first row may be hidden and a later one shown.
        $this->ensureConnection();
        $stmt = $this->pdo->prepare('SELECT * FROM juridical_entities WHERE phone_number = :phone ORDER BY id');
        $stmt->execute([':phone' => $phone]);

        return $this->shown($stmt->fetchAll(\PDO::FETCH_ASSOC))[0] ?? null;
    }

    /** @return list<JuridicalEntity> */
    public function findByBusinessType(string $type, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureConnection();

        return $this->queryShown(
            $this->dialect->containsCondition('business_type', ':type'),
            [':type' => $this->dialect->containsValue($type)],
            'business_name',
            $limit,
            $offset
        );
    }

    /** @return list<JuridicalEntity> */
    public function getAll(?int $limit = null, int $offset = 0): array
    {
        return $this->queryShown('1 = 1', [], 'business_name', $limit, $offset);
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

    /**
     * How many businesses this reader can see: everything stored for the owner, only what may be shown for a
     * visitor (as PhoneDirectoryPDODatabase::count()).
     */
    public function count(): int
    {
        return $this->privacy->hidesLiving() ? count($this->getAll()) : $this->countAll();
    }

    /**
     * @param array{businessName?: string, street?: string, phone?: string, businessType?: string} $criteria
     * @return list<JuridicalEntity>
     */
    public function search(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureConnection();

        [$where, $params] = $this->buildSearchWhere($criteria, [
            'businessName' => ['column' => 'business_name_folded', 'contains' => true, 'fold' => true],
            'street' => ['column' => 'street_folded', 'contains' => true, 'fold' => true],
            'phone' => ['column' => 'phone_number'],
            'businessType' => ['column' => 'business_type', 'contains' => true],
        ]);

        if (empty($where)) {
            return $this->getAll($limit, $offset);
        }

        return $this->queryShown(implode(' AND ', $where), $params, 'business_name', $limit, $offset);
    }

    public function clear(): bool
    {
        return $this->clearTable();
    }

    /**
     * The businesses matching $where that this reader may see; with $limit, one page of them, counted over what the
     * reader may see (a visitor does not see the businesses that carry a living person's name).
     *
     * @param SqlParams $params
     * @return list<JuridicalEntity>
     */
    private function queryShown(string $where, array $params, string $orderBy, ?int $limit, int $offset): array
    {
        return $this->fetchPage($where, $params, $orderBy, $limit, $offset, $this->privacy->hidesLiving(), fn(array $rows) => $this->shown($rows));
    }

    /**
     * @param list<Row> $rows
     * @return list<JuridicalEntity>
     */
    private function shown(array $rows): array
    {
        return $this->privacy->filterBusinesses(array_map([$this, 'rowToEntity'], $rows));
    }

    /** @param Row $row */
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

    /** @return SqlParams */
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

    /** @return SqlParams */
    private function foldedSearchParams(JuridicalEntity $entity): array
    {
        return [
            ':businessNameFolded' => $this->fold($entity->getBusinessName()),
            ':streetFolded' => $this->fold($entity->getStreet()),
        ];
    }

}

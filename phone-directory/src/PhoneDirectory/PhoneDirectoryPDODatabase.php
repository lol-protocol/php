<?php

namespace PhoneDirectory;

use PhoneDirectory\Database\EntityPDODatabase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;

/**
 * @extends EntityPDODatabase<PhoneDirectoryEntry>
 * @phpstan-import-type Row from EntityPDODatabase
 * @phpstan-import-type SqlParams from EntityPDODatabase
 */
class PhoneDirectoryPDODatabase extends EntityPDODatabase implements PhoneDirectoryDatabaseInterface
{
    private const TABLE_NAME = 'phone_directory';

    private const BASE_COLUMNS = [
        'id' => ['id'],
        'full_name' => ['string', 'NOT NULL'],
        'street' => ['string', 'NOT NULL'],
        'phone_number' => ['string'],
        'record_date' => ['datetime', 'DEFAULT CURRENT_TIMESTAMP'],
        'created_at' => ['datetime', 'DEFAULT CURRENT_TIMESTAMP'],
        'updated_at' => ['datetime', 'DEFAULT CURRENT_TIMESTAMP'],
    ];

    private const ADDED_COLUMNS = [
        'raw_name' => ['text'],
        'language' => ['string'],
        'country_code' => ['country', "NOT NULL DEFAULT 'US'"],
        'zone' => ['string'],
        'city' => ['string'],
        'source_directory_id' => ['string'],
        'source_line' => ['int'],
        'surname_soundex' => ['string'],
        'surname_phonetic' => ['string'],
        'full_name_folded' => ['string'],
        'street_folded' => ['string'],
    ];

    private const INDEXED_COLUMNS = [
        'full_name', 'country_code', 'street', 'phone_number', 'source_directory_id',
        'surname_soundex', 'surname_phonetic', 'full_name_folded', 'street_folded',
    ];

    private readonly Privacy $privacy;

    /**
     * @param Privacy|null $privacy who is asking: by default a visitor, who gets no entry that may be a
     *        living person (see Privacy). Pass Privacy::forOwner() to read everything that is stored.
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

    /** @deprecated Never called; use ensureConnection(). */
    protected function ensureConnected(): void
    {
        $this->ensureConnection();
    }

    public function createTable(): void
    {
        $this->ensureConnection();

        $this->dialect->ensureTable(self::TABLE_NAME, self::BASE_COLUMNS, self::ADDED_COLUMNS, self::INDEXED_COLUMNS);

        $this->pdo->exec(sprintf(DatabaseConstants::MIGRATION_NULL_COUNTRY_CODE, self::TABLE_NAME, $this->getDefaultCountryCode()));
        $this->backfillDerivedColumns();
    }

    private function backfillDerivedColumns(): void
    {
        $selectQuery = 'SELECT id, full_name, street FROM ' . self::TABLE_NAME . ' WHERE surname_soundex IS NULL OR full_name_folded IS NULL';
        $updateStmt = <<<SQL
            UPDATE phone_directory
            SET surname_soundex = :surnameSoundex, surname_phonetic = :surnamePhonetic,
                full_name_folded = :fullNameFolded, street_folded = :streetFolded
            WHERE id = :id
            SQL;

        $this->executeWithBackfill($selectQuery, $updateStmt, function (array $row) {
            $entry = new PhoneDirectoryEntry(
                fullName: $row['full_name'],
                countryCode: $this->getDefaultCountryCode(),
                street: $row['street']
            );
            return $this->surnameKeyParams($entry) + $this->foldedSearchParams($entry) + [':id' => $row['id']];
        });
    }

    private function insertSql(): string
    {
        return <<<SQL
        INSERT INTO phone_directory (full_name, raw_name, language, country_code, zone, city, street, phone_number,
            source_directory_id, source_line, surname_soundex, surname_phonetic, full_name_folded, street_folded, record_date)
        VALUES (:fullName, :rawName, :language, :countryCode, :zone, :city, :street, :phoneNumber,
            :sourceDirectoryId, :sourceLine, :surnameSoundex, :surnamePhonetic, :fullNameFolded, :streetFolded, :recordDate)
        SQL;
    }

    /** @return SqlParams */
    private function insertParams(PhoneDirectoryEntry $entry): array
    {
        return $this->entryParams($entry) + [
            ':recordDate' => $this->formatDatetime($entry->getRecordDate()),
        ];
    }

    public function insert(PhoneDirectoryEntry $entry): int
    {
        $this->ensureConnection();

        $stmt = $this->pdo->prepare($this->insertSql());
        $stmt->execute($this->insertParams($entry));

        return $this->validateInsertId($this->pdo->lastInsertId());
    }

    /** @param array<PhoneDirectoryEntry> $entries */
    public function insertBatch(array $entries): int
    {
        return $this->insertManyWithTransaction(
            $entries,
            $this->insertSql(),
            fn ($entity) => $entity instanceof PhoneDirectoryEntry,
            fn (PhoneDirectoryEntry $entry) => $this->insertParams($entry)
        );
    }

    public function findById(int $id): ?PhoneDirectoryEntry
    {
        $row = $this->findOneById($id);
        $entry = $row ? $this->rowToEntry($row) : null;

        return $entry !== null && $this->privacy->allows($entry) ? $entry : null;
    }

    /** @return list<PhoneDirectoryEntry> */
    public function findByName(string $name): array
    {
        $this->ensureConnection();

        $sql = 'SELECT * FROM phone_directory WHERE ' . $this->dialect->containsCondition('full_name_folded', ':name') . ' ORDER BY full_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $this->dialect->containsValue($this->fold($name))]);

        return $this->shown($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** @return list<PhoneDirectoryEntry> */
    public function findByStreet(string $street): array
    {
        $this->ensureConnection();

        $sql = 'SELECT * FROM phone_directory WHERE ' . $this->dialect->containsCondition('street_folded', ':street') . ' ORDER BY street, full_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':street' => $this->dialect->containsValue($this->fold($street))]);

        return $this->shown($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByPhone(string $phone): ?PhoneDirectoryEntry
    {
        if (!$this->privacy->hidesLiving()) {
            $row = $this->findOneByPhone($phone);
            return $row ? $this->rowToEntry($row) : null;
        }

        // A number is reused from one edition to the next: the first row may be a hidden 1960 entry
        // and the next an old one that may be shown.
        $this->ensureConnection();
        $stmt = $this->pdo->prepare('SELECT * FROM phone_directory WHERE phone_number = :phone ORDER BY id');
        $stmt->execute([':phone' => $phone]);

        return $this->shown($stmt->fetchAll(\PDO::FETCH_ASSOC))[0] ?? null;
    }

    /** @return list<PhoneDirectoryEntry> */
    public function findBySurnameSound(string $surname, ?string $language = null): array
    {
        $this->ensureConnection();

        $root = SurnameKeys::root($surname);
        $soundex = SurnameKeys::soundex($root);
        if ($soundex === null) {
            return [];
        }

        $where = 'surname_soundex = :soundex';
        $params = [':soundex' => $soundex];

        // Language keys are only comparable between entries folded with the same language's rules.
        $phonetic = SurnameKeys::languageKey($root, $language);
        if ($phonetic !== null) {
            $where .= ' OR (language = :language AND surname_phonetic = :phonetic)';
            $params[':language'] = $language;
            $params[':phonetic'] = $phonetic;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM phone_directory WHERE {$where} ORDER BY full_name");
        $stmt->execute($params);

        return $this->shown($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** @return list<PhoneDirectoryEntry> */
    public function findBySourceDirectory(string $sourceDirectoryId): array
    {
        $this->ensureConnection();

        $stmt = $this->pdo->prepare('SELECT * FROM phone_directory WHERE source_directory_id = :source ORDER BY source_line, id');
        $stmt->execute([':source' => $sourceDirectoryId]);

        return $this->shown($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** @return list<PhoneDirectoryEntry> */
    public function getAll(): array
    {
        return $this->privacy->filter($this->getAllOrdered('full_name'));
    }

    public function update(PhoneDirectoryEntry $entry): bool
    {
        $this->ensureConnection();
        $this->validateEntityId($entry->getId());

        $sql = <<<SQL
        UPDATE phone_directory
        SET full_name = :fullName, raw_name = :rawName, language = :language, country_code = :countryCode, zone = :zone, city = :city,
            street = :street, phone_number = :phoneNumber, source_directory_id = :sourceDirectoryId, source_line = :sourceLine,
            surname_soundex = :surnameSoundex, surname_phonetic = :surnamePhonetic,
            full_name_folded = :fullNameFolded, street_folded = :streetFolded, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entryParams($entry) + [':id' => $entry->getId()]);

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

    /**
     * @param array{name?: string, street?: string, phone?: string} $criteria
     * @return list<PhoneDirectoryEntry>
     */
    public function search(array $criteria): array
    {
        $this->ensureConnection();

        [$where, $params] = $this->buildSearchWhere($criteria, [
            'name' => ['column' => 'full_name_folded', 'contains' => true, 'fold' => true],
            'street' => ['column' => 'street_folded', 'contains' => true, 'fold' => true],
            'phone' => ['column' => 'phone_number'],
        ]);

        if (empty($where)) {
            return $this->getAll();
        }

        $sql = 'SELECT * FROM phone_directory WHERE ' . implode(' AND ', $where) . ' ORDER BY full_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->shown($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function clear(): bool
    {
        return $this->clearTable();
    }

    /**
     * The rows as entries, without the ones this reader may not see.
     *
     * @param array<Row> $rows
     * @return list<PhoneDirectoryEntry>
     */
    private function shown(array $rows): array
    {
        return $this->privacy->filter(array_map([$this, 'rowToEntry'], $rows));
    }

    /** @param Row $row */
    protected function rowToEntity(array $row): PhoneDirectoryEntry
    {
        return $this->rowToEntry($row);
    }

    /** @return SqlParams */
    private function entryParams(PhoneDirectoryEntry $entry): array
    {
        return [
            ':fullName' => $entry->getFullName(),
            ':rawName' => $entry->getRawName(),
            ':language' => $entry->getLanguage(),
            ':countryCode' => $entry->getCountryCode(),
            ':zone' => $entry->getZone(),
            ':city' => $entry->getCity(),
            ':street' => $entry->getStreet(),
            ':phoneNumber' => $entry->getPhoneNumber(),
            ':sourceDirectoryId' => $entry->getSourceDirectoryId(),
            ':sourceLine' => $entry->getSourceLine(),
        ] + $this->surnameKeyParams($entry) + $this->foldedSearchParams($entry);
    }

    /** @return SqlParams */
    private function surnameKeyParams(PhoneDirectoryEntry $entry): array
    {
        $root = $entry->getPersonName()->getSurnameRoot();

        return [
            ':surnameSoundex' => SurnameKeys::soundex($root),
            ':surnamePhonetic' => SurnameKeys::languageKey($root, $entry->getLanguage()),
        ];
    }

    /** @return SqlParams */
    private function foldedSearchParams(PhoneDirectoryEntry $entry): array
    {
        return [
            ':fullNameFolded' => $this->fold($entry->getFullName()),
            ':streetFolded' => $this->fold($entry->getStreet()),
        ];
    }

    /** @param Row $row */
    private function rowToEntry(array $row): PhoneDirectoryEntry
    {
        return new PhoneDirectoryEntry(
            fullName: $row['raw_name'] ?? $row['full_name'],
            countryCode: $row['country_code'] ?? $this->getDefaultCountryCode(),
            street: $row['street'],
            phoneNumber: $row['phone_number'] ?? null,
            zone: $row['zone'] ?? null,
            city: $row['city'] ?? null,
            id: (int) $row['id'],
            recordDate: new \DateTime($row['record_date']),
            sourceDirectoryId: $row['source_directory_id'] ?? null,
            language: $row['language'] ?? null,
            sourceLine: isset($row['source_line']) ? (int) $row['source_line'] : null
        );
    }
}

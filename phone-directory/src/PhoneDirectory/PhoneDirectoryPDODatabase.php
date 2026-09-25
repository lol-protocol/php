<?php

namespace PhoneDirectory;

use PhoneDirectory\Database\EntityPDODatabase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;

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

    public function insert(PhoneDirectoryEntry $entry): int
    {
        $this->ensureConnection();

        $sql = <<<SQL
        INSERT INTO phone_directory (full_name, raw_name, language, country_code, zone, city, street, phone_number,
            source_directory_id, source_line, surname_soundex, surname_phonetic, full_name_folded, street_folded, record_date)
        VALUES (:fullName, :rawName, :language, :countryCode, :zone, :city, :street, :phoneNumber,
            :sourceDirectoryId, :sourceLine, :surnameSoundex, :surnamePhonetic, :fullNameFolded, :streetFolded, :recordDate)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entryParams($entry) + [
            ':recordDate' => $this->formatDatetime($entry->getRecordDate()),
        ]);

        return $this->validateInsertId($this->pdo->lastInsertId());
    }

    public function insertBatch(array $entries): int
    {
        return $this->insertBatchWithTransaction($entries, function ($entry) {
            if ($entry instanceof PhoneDirectoryEntry) {
                $this->insert($entry);
            }
        });
    }

    public function findById(int $id): ?PhoneDirectoryEntry
    {
        $row = $this->findOneById($id);
        return $row ? $this->rowToEntry($row) : null;
    }

    public function findByName(string $name): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM phone_directory WHERE ' . $this->dialect->containsCondition('full_name_folded', ':name') . ' ORDER BY full_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $this->dialect->containsValue($this->fold($name))]);

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByStreet(string $street): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM phone_directory WHERE ' . $this->dialect->containsCondition('street_folded', ':street') . ' ORDER BY street, full_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':street' => $this->dialect->containsValue($this->fold($street))]);

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByPhone(string $phone): ?PhoneDirectoryEntry
    {
        $row = $this->findOneByPhone($phone);
        return $row ? $this->rowToEntry($row) : null;
    }

    public function findBySurnameSound(string $surname, ?string $language = null): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

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

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findBySourceDirectory(string $sourceDirectoryId): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $stmt = $this->pdo->prepare('SELECT * FROM phone_directory WHERE source_directory_id = :source ORDER BY source_line, id');
        $stmt->execute([':source' => $sourceDirectoryId]);

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function getAll(): array
    {
        return $this->getAllOrdered('full_name');
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

    public function search(array $criteria): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $where = [];
        $params = [];

        if (!empty($criteria['name'])) {
            $where[] = $this->dialect->containsCondition('full_name_folded', ':name');
            $params[':name'] = $this->dialect->containsValue($this->fold($criteria['name']));
        }

        if (!empty($criteria['street'])) {
            $where[] = $this->dialect->containsCondition('street_folded', ':street');
            $params[':street'] = $this->dialect->containsValue($this->fold($criteria['street']));
        }

        if (!empty($criteria['phone'])) {
            $where[] = 'phone_number = :phone';
            $params[':phone'] = $criteria['phone'];
        }

        if (empty($where)) {
            return $this->getAll();
        }

        $sql = 'SELECT * FROM phone_directory WHERE ' . implode(' AND ', $where) . ' ORDER BY full_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function clear(): bool
    {
        return $this->clearTable();
    }

    protected function rowToEntity(array $row): PhoneDirectoryEntry
    {
        return $this->rowToEntry($row);
    }

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

    private function surnameKeyParams(PhoneDirectoryEntry $entry): array
    {
        $root = $entry->getPersonName()->getSurnameRoot();

        return [
            ':surnameSoundex' => SurnameKeys::soundex($root),
            ':surnamePhonetic' => SurnameKeys::languageKey($root, $entry->getLanguage()),
        ];
    }

    private function foldedSearchParams(PhoneDirectoryEntry $entry): array
    {
        return [
            ':fullNameFolded' => $this->fold($entry->getFullName()),
            ':streetFolded' => $this->fold($entry->getStreet()),
        ];
    }

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

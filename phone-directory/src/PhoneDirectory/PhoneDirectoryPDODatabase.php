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
        'deceased_year' => ['int'],
        'deceased_evidence' => ['text'],
    ];

    /** A death is recorded in a year no earlier than this: nothing in the catalog is older. */
    private const EARLIEST_DEATH_YEAR = 1800;

    private const MAX_EVIDENCE_LENGTH = 1000;

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

    /**
     * Saves the entry's fields. It does not touch the deceased mark (markDeceased() and clearDeceased() are the
     * only ways to change it), so editing an entry never publishes or hides a person by accident.
     */
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

    /**
     * Records that the person in this entry died in $year, so a visitor may see it whatever year the directory
     * came out (see Privacy). A wrong mark publishes a living person's address and phone number, so it asks for
     * what a second person needs to check it: where the death is documented ($evidence: an obituary, a death
     * certificate, a family book) and a year that fits the directory it comes from.
     *
     * Only the owner of the database can do this: a database opened for visitors refuses.
     *
     * @return bool false when there is no such entry
     * @throws \LogicException If this database was opened for visitors
     * @throws \InvalidArgumentException If $evidence is empty or too long, or $year is not plausible
     */
    public function markDeceased(int $id, int $year, string $evidence): bool
    {
        $this->requireOwner('record a death');

        $evidence = trim($evidence);
        if ($evidence === '') {
            throw new \InvalidArgumentException('Say where the death is documented (an obituary, a death certificate): without it nobody can check the mark');
        }
        if (mb_strlen($evidence) > self::MAX_EVIDENCE_LENGTH) {
            throw new \InvalidArgumentException('The evidence is longer than ' . self::MAX_EVIDENCE_LENGTH . ' characters: say where it is, do not copy it');
        }

        $this->validateEntityId($id);
        $row = $this->findOneById($id);
        if ($row === null) {
            return false;
        }

        $this->assertPlausibleDeathYear($year, isset($row['source_directory_id']) ? (string) $row['source_directory_id'] : null);

        $stmt = $this->pdo->prepare('UPDATE phone_directory SET deceased_year = :year, deceased_evidence = :evidence, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':year' => $year, ':evidence' => $evidence, ':id' => $id]);

        return true;
    }

    /**
     * Takes the mark back: the entry goes back to following the directory's year.
     *
     * @return bool false when there is no such entry
     * @throws \LogicException If this database was opened for visitors
     */
    public function clearDeceased(int $id): bool
    {
        $this->requireOwner('take back a death');

        $this->validateEntityId($id);
        if ($this->findOneById($id) === null) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE phone_directory SET deceased_year = NULL, deceased_evidence = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return true;
    }

    /**
     * Where the death of this entry's person is documented, or null when none is recorded. The text is not part
     * of the entry (it may name relatives), so it is only for the owner.
     *
     * @throws \LogicException If this database was opened for visitors
     */
    public function getDeceasedEvidence(int $id): ?string
    {
        $this->requireOwner('read the evidence of a death');

        $row = $this->findOneById($id);

        return isset($row['deceased_evidence']) ? (string) $row['deceased_evidence'] : null;
    }

    private function requireOwner(string $action): void
    {
        if ($this->privacy->hidesLiving()) {
            throw new \LogicException("A database opened for visitors cannot {$action}: open it with Privacy::forOwner()");
        }
    }

    /**
     * A person is listed in a directory a year before it comes out at the earliest, so a death earlier than that
     * is a different person (a namesake) or a typo. Nor can it be in the future.
     */
    private function assertPlausibleDeathYear(int $year, ?string $directoryId): void
    {
        $thisYear = (int) date('Y');
        if ($year < self::EARLIEST_DEATH_YEAR || $year > $thisYear) {
            throw new \InvalidArgumentException("The year of death must be between " . self::EARLIEST_DEATH_YEAR . " and {$thisYear}, not {$year}");
        }

        $directoryYear = (new PhoneDirectoryCatalog())->yearOfDirectory($directoryId);
        if ($directoryYear !== null && $year < $directoryYear - 1) {
            throw new \InvalidArgumentException("The entry is from the {$directoryYear} directory: a death in {$year} would be a different person");
        }
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
        $entry = new PhoneDirectoryEntry(
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

        return isset($row['deceased_year']) ? $entry->withDeceasedYear((int) $row['deceased_year']) : $entry;
    }
}

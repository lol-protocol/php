<?php

namespace PhoneDirectory;

class PhoneDirectoryPDODatabase implements PhoneDirectoryDatabaseInterface
{
    // Columns added after the original table; createTable() adds any that an existing database lacks.
    private const ADDED_COLUMNS = [
        'raw_name' => 'TEXT',
        'language' => 'TEXT',
        'country_code' => "TEXT NOT NULL DEFAULT 'US'",
        'zone' => 'TEXT',
        'city' => 'TEXT',
        'source_directory_id' => 'TEXT',
        'source_line' => 'INTEGER',
    ];

    private ?\PDO $pdo = null;
    private string $dsn;

    public function __construct(string $dsn = 'sqlite::memory:')
    {
        $this->dsn = $dsn;
    }

    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        try {
            $this->pdo = new \PDO($this->dsn);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Database connection failed: {$e->getMessage()}");
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public function createTable(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $this->pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS phone_directory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            street TEXT NOT NULL,
            phone_number TEXT,
            record_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        $this->addMissingColumns();

        // Index names are database-wide, so they carry the table name to avoid colliding with juridical_entities.
        foreach (['full_name', 'country_code', 'street', 'phone_number', 'source_directory_id'] as $column) {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_phone_directory_{$column} ON phone_directory({$column})");
        }
    }

    private function addMissingColumns(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM phone_directory LIMIT 0');
        $existing = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $existing[] = $stmt->getColumnMeta($i)['name'];
        }

        foreach (self::ADDED_COLUMNS as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                $this->pdo->exec("ALTER TABLE phone_directory ADD COLUMN {$column} {$definition}");
            }
        }
    }

    public function insert(PhoneDirectoryEntry $entry): int
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = <<<SQL
        INSERT INTO phone_directory (full_name, raw_name, language, country_code, zone, city, street, phone_number, source_directory_id, source_line, record_date)
        VALUES (:fullName, :rawName, :language, :countryCode, :zone, :city, :street, :phoneNumber, :sourceDirectoryId, :sourceLine, :recordDate)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entryParams($entry) + [
            ':recordDate' => $entry->getRecordDate()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function insertBatch(array $entries): int
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $count = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($entries as $entry) {
                if ($entry instanceof PhoneDirectoryEntry) {
                    $this->insert($entry);
                    $count++;
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException("Batch insert failed: {$e->getMessage()}");
        }

        return $count;
    }

    public function findById(int $id): ?PhoneDirectoryEntry
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM phone_directory WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->rowToEntry($row) : null;
    }

    public function findByName(string $name): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = "SELECT * FROM phone_directory WHERE full_name LIKE :name ESCAPE '!' ORDER BY full_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $this->containsPattern($name)]);

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByStreet(string $street): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = "SELECT * FROM phone_directory WHERE street LIKE :street ESCAPE '!' ORDER BY street, full_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':street' => $this->containsPattern($street)]);

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByPhone(string $phone): ?PhoneDirectoryEntry
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM phone_directory WHERE phone_number = :phone LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':phone' => $phone]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->rowToEntry($row) : null;
    }

    public function getAll(): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM phone_directory ORDER BY full_name';
        $stmt = $this->pdo->query($sql);

        return array_map([$this, 'rowToEntry'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function update(PhoneDirectoryEntry $entry): bool
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if ($entry->getId() === 0) {
            throw new \InvalidArgumentException('Cannot update entry without ID');
        }

        $sql = <<<SQL
        UPDATE phone_directory
        SET full_name = :fullName, raw_name = :rawName, language = :language, country_code = :countryCode, zone = :zone, city = :city,
            street = :street, phone_number = :phoneNumber, source_directory_id = :sourceDirectoryId, source_line = :sourceLine,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entryParams($entry) + [
            ':id' => $entry->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'DELETE FROM phone_directory WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function count(): int
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM phone_directory');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($row['count'] ?? 0);
    }

    public function search(array $criteria): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $where = [];
        $params = [];

        if (!empty($criteria['name'])) {
            $where[] = "full_name LIKE :name ESCAPE '!'";
            $params[':name'] = $this->containsPattern($criteria['name']);
        }

        if (!empty($criteria['street'])) {
            $where[] = "street LIKE :street ESCAPE '!'";
            $params[':street'] = $this->containsPattern($criteria['street']);
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
        if (!$this->isConnected()) {
            $this->connect();
        }

        $this->pdo->exec('DELETE FROM phone_directory');
        return true;
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
        ];
    }

    private function rowToEntry(array $row): PhoneDirectoryEntry
    {
        // Re-parse the original transcription: the normalized full_name loses the comma that marks surnames.
        return new PhoneDirectoryEntry(
            fullName: $row['raw_name'] ?? $row['full_name'],
            countryCode: $row['country_code'] ?? 'US',
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

    private function containsPattern(string $text): string
    {
        return '%' . strtr($text, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
}

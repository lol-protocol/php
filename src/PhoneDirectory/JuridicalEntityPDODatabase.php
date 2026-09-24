<?php

namespace PhoneDirectory;

class JuridicalEntityPDODatabase implements JuridicalEntityDatabaseInterface
{
    private const TABLE = 'juridical_entities';

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

    // Columns added after the original table; createTable() adds any that an existing database lacks.
    private const ADDED_COLUMNS = [
        'country_code' => ['country', "NOT NULL DEFAULT 'US'"],
        'source_directory_id' => ['string'],
        'source_line' => ['int'],
    ];

    private const INDEXED_COLUMNS = ['business_name', 'street', 'phone_number', 'business_type', 'country_code', 'source_directory_id'];

    private ?\PDO $pdo = null;
    private ?SqlDialect $dialect = null;
    private string $dsn;
    private ?string $username;
    private ?string $password;

    public function __construct(string $dsn = 'sqlite::memory:', ?string $username = null, ?string $password = null)
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
    }

    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        $this->pdo = SqlDialect::connect($this->dsn, $this->username, $this->password);
        $this->dialect = new SqlDialect($this->pdo);
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->dialect = null;
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

        $this->dialect->ensureTable(self::TABLE, self::BASE_COLUMNS, self::ADDED_COLUMNS, self::INDEXED_COLUMNS);
    }

    public function insert(JuridicalEntity $entity): int
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = <<<SQL
        INSERT INTO juridical_entities (business_name, legal_name, street, phone_number, business_type, country_code, source_directory_id, source_line, record_date)
        VALUES (:businessName, :legalName, :street, :phoneNumber, :businessType, :countryCode, :sourceDirectoryId, :sourceLine, :recordDate)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entityParams($entity) + [
            ':recordDate' => $entity->getRecordDate()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function insertBatch(array $entities): int
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $count = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($entities as $entity) {
                if ($entity instanceof JuridicalEntity) {
                    $this->insert($entity);
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

    public function findById(int $id): ?JuridicalEntity
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->rowToEntity($row) : null;
    }

    public function findByBusinessName(string $name): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE ' . $this->dialect->containsCondition('business_name', ':name') . ' ORDER BY business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $this->dialect->containsValue($name)]);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByStreet(string $street): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE ' . $this->dialect->containsCondition('street', ':street') . ' ORDER BY street, business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':street' => $this->dialect->containsValue($street)]);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByPhone(string $phone): ?JuridicalEntity
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE phone_number = :phone LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':phone' => $phone]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->rowToEntity($row) : null;
    }

    public function findByBusinessType(string $type): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE ' . $this->dialect->containsCondition('business_type', ':type') . ' ORDER BY business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':type' => $this->dialect->containsValue($type)]);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function getAll(): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities ORDER BY business_name';
        $stmt = $this->pdo->query($sql);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function update(JuridicalEntity $entity): bool
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if ($entity->getId() === 0) {
            throw new \InvalidArgumentException('Cannot update entity without ID');
        }

        $sql = <<<SQL
        UPDATE juridical_entities
        SET business_name = :businessName, legal_name = :legalName, street = :street,
            phone_number = :phoneNumber, business_type = :businessType, country_code = :countryCode,
            source_directory_id = :sourceDirectoryId, source_line = :sourceLine, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->entityParams($entity) + [
            ':id' => $entity->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'DELETE FROM juridical_entities WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function count(): int
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM juridical_entities');
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

        if (!empty($criteria['businessName'])) {
            $where[] = $this->dialect->containsCondition('business_name', ':businessName');
            $params[':businessName'] = $this->dialect->containsValue($criteria['businessName']);
        }

        if (!empty($criteria['street'])) {
            $where[] = $this->dialect->containsCondition('street', ':street');
            $params[':street'] = $this->dialect->containsValue($criteria['street']);
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
        if (!$this->isConnected()) {
            $this->connect();
        }

        $this->pdo->exec('DELETE FROM juridical_entities');
        return true;
    }

    private function rowToEntity(array $row): JuridicalEntity
    {
        return new JuridicalEntity(
            businessName: $row['business_name'],
            street: $row['street'],
            legalName: $row['legal_name'],
            businessType: $row['business_type'],
            phoneNumber: $row['phone_number'],
            id: (int) $row['id'],
            recordDate: new \DateTime($row['record_date']),
            countryCode: $row['country_code'] ?? 'US',
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
        ];
    }
}

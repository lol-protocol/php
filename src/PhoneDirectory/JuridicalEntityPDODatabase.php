<?php

namespace PhoneDirectory;

class JuridicalEntityPDODatabase implements JuridicalEntityDatabaseInterface
{
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

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS juridical_entities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            business_name TEXT NOT NULL,
            legal_name TEXT,
            street TEXT NOT NULL,
            phone_number TEXT,
            business_type TEXT,
            record_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_business_name ON juridical_entities(business_name);
        CREATE INDEX IF NOT EXISTS idx_street ON juridical_entities(street);
        CREATE INDEX IF NOT EXISTS idx_phone_number ON juridical_entities(phone_number);
        CREATE INDEX IF NOT EXISTS idx_business_type ON juridical_entities(business_type);
        SQL;

        $this->pdo->exec($sql);
    }

    public function insert(JuridicalEntity $entity): int
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = <<<SQL
        INSERT INTO juridical_entities (business_name, legal_name, street, phone_number, business_type, record_date)
        VALUES (:businessName, :legalName, :street, :phoneNumber, :businessType, :recordDate)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':businessName' => $entity->getBusinessName(),
            ':legalName' => $entity->getLegalName(),
            ':street' => $entity->getStreet(),
            ':phoneNumber' => $entity->getPhoneNumber(),
            ':businessType' => $entity->getBusinessType(),
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

        $sql = 'SELECT * FROM juridical_entities WHERE business_name LIKE :name ORDER BY business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => "%{$name}%"]);

        return array_map([$this, 'rowToEntity'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByStreet(string $street): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $sql = 'SELECT * FROM juridical_entities WHERE street LIKE :street ORDER BY street, business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':street' => "%{$street}%"]);

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

        $sql = 'SELECT * FROM juridical_entities WHERE business_type LIKE :type ORDER BY business_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':type' => "%{$type}%"]);

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
            phone_number = :phoneNumber, business_type = :businessType, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':businessName' => $entity->getBusinessName(),
            ':legalName' => $entity->getLegalName(),
            ':street' => $entity->getStreet(),
            ':phoneNumber' => $entity->getPhoneNumber(),
            ':businessType' => $entity->getBusinessType(),
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
            $where[] = 'business_name LIKE :businessName';
            $params[':businessName'] = "%{$criteria['businessName']}%";
        }

        if (!empty($criteria['street'])) {
            $where[] = 'street LIKE :street';
            $params[':street'] = "%{$criteria['street']}%";
        }

        if (!empty($criteria['phone'])) {
            $where[] = 'phone_number = :phone';
            $params[':phone'] = $criteria['phone'];
        }

        if (!empty($criteria['businessType'])) {
            $where[] = 'business_type LIKE :businessType';
            $params[':businessType'] = "%{$criteria['businessType']}%";
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
            recordDate: new \DateTime($row['record_date'])
        );
    }
}

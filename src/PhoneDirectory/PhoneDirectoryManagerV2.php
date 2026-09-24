<?php

namespace PhoneDirectory;

class PhoneDirectoryManagerV2
{
    private MultiLanguagePhoneDirectoryParser $parser;
    private PhoneDirectoryDatabaseInterface $naturalDatabase;
    private JuridicalEntityDatabaseInterface $juridicalDatabase;

    public function __construct(
        ?MultiLanguagePhoneDirectoryParser $parser = null,
        ?PhoneDirectoryDatabaseInterface $naturalDatabase = null,
        ?JuridicalEntityDatabaseInterface $juridicalDatabase = null
    ) {
        $this->parser = $parser ?? new MultiLanguagePhoneDirectoryParser();
        $this->naturalDatabase = $naturalDatabase ?? new PhoneDirectoryPDODatabase();
        $this->juridicalDatabase = $juridicalDatabase ?? new JuridicalEntityPDODatabase();
    }

    public function processFile(string $filePath, ?string $language = null, bool $clearExisting = false): array
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        $this->naturalDatabase->createTable();
        $this->juridicalDatabase->createTable();

        if ($clearExisting) {
            $this->naturalDatabase->clear();
            $this->juridicalDatabase->clear();
        }

        $entries = $this->parser->parseFile($filePath, $language);

        $naturalPeople = [];
        $juridicalEntities = [];

        foreach ($entries as $item) {
            if ($item['type'] === 'natural') {
                $naturalPeople[] = $item['entity'];
            } else {
                $juridicalEntities[] = $item['entity'];
            }
        }

        $naturalInserted = 0;
        $juridicalInserted = 0;

        if (!empty($naturalPeople)) {
            $naturalInserted = $this->naturalDatabase->insertBatch($naturalPeople);
        }

        if (!empty($juridicalEntities)) {
            $juridicalInserted = $this->juridicalDatabase->insertBatch($juridicalEntities);
        }

        return [
            'file' => $filePath,
            'language' => $this->parser->getDetectedLanguage(),
            'totalParsed' => count($entries),
            'naturalPeople' => count($naturalPeople),
            'juridicalEntities' => count($juridicalEntities),
            'naturalInserted' => $naturalInserted,
            'juridicalInserted' => $juridicalInserted,
            'totalInserted' => $naturalInserted + $juridicalInserted,
            'errors' => $this->parser->getErrors(),
            'errorCount' => count($this->parser->getErrors()),
        ];
    }

    public function addNaturalPerson(PhoneDirectoryEntry $entry): int
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->insert($entry);
    }

    public function addJuridicalEntity(JuridicalEntity $entity): int
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->insert($entity);
    }

    public function getNaturalPerson(int $id): ?PhoneDirectoryEntry
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->findById($id);
    }

    public function getJuridicalEntity(int $id): ?JuridicalEntity
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->findById($id);
    }

    public function findNaturalPeopleByName(string $name): array
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->findByName($name);
    }

    public function findNaturalPeopleBySurnameSound(string $surname, ?string $language = null): array
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->findBySurnameSound($surname, $language);
    }

    public function findJuridicalEntitiesByName(string $name): array
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->findByBusinessName($name);
    }

    public function findNaturalPeopleByStreet(string $street): array
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->findByStreet($street);
    }

    public function findJuridicalEntitiesByStreet(string $street): array
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->findByStreet($street);
    }

    public function findByStreet(string $street): array
    {
        $natural = $this->findNaturalPeopleByStreet($street);
        $juridical = $this->findJuridicalEntitiesByStreet($street);

        return [
            'natural' => $natural,
            'juridical' => $juridical,
        ];
    }

    public function findNaturalPersonByPhone(string $phone): ?PhoneDirectoryEntry
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->findByPhone($phone);
    }

    public function findJuridicalEntityByPhone(string $phone): ?JuridicalEntity
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->findByPhone($phone);
    }

    public function findByPhone(string $phone): array
    {
        $natural = $this->findNaturalPersonByPhone($phone);
        $juridical = $this->findJuridicalEntityByPhone($phone);

        return [
            'natural' => $natural,
            'juridical' => $juridical,
        ];
    }

    public function findJuridicalEntitiesByType(string $type): array
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->findByBusinessType($type);
    }

    public function getAllNaturalPeople(): array
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->getAll();
    }

    public function getAllJuridicalEntities(): array
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->getAll();
    }

    public function getAll(): array
    {
        return [
            'natural' => $this->getAllNaturalPeople(),
            'juridical' => $this->getAllJuridicalEntities(),
        ];
    }

    public function searchNaturalPeople(array $criteria): array
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->search($criteria);
    }

    public function searchJuridicalEntities(array $criteria): array
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->search($criteria);
    }

    public function updateNaturalPerson(PhoneDirectoryEntry $entry): bool
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->update($entry);
    }

    public function updateJuridicalEntity(JuridicalEntity $entity): bool
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->update($entity);
    }

    public function deleteNaturalPerson(int $id): bool
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->delete($id);
    }

    public function deleteJuridicalEntity(int $id): bool
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->delete($id);
    }

    public function getTotalNaturalPeopleCount(): int
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
        }

        return $this->naturalDatabase->count();
    }

    public function getTotalJuridicalEntitiesCount(): int
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
        }

        return $this->juridicalDatabase->count();
    }

    public function getTotalCount(): int
    {
        return $this->getTotalNaturalPeopleCount() + $this->getTotalJuridicalEntitiesCount();
    }

    public function getStatistics(): array
    {
        $natural = $this->getTotalNaturalPeopleCount();
        $juridical = $this->getTotalJuridicalEntitiesCount();

        return [
            'total' => $natural + $juridical,
            'natural_people' => $natural,
            'juridical_entities' => $juridical,
            'ratio_natural_to_juridical' => $juridical > 0 ? round($natural / $juridical, 2) : null,
        ];
    }

    public function getParser(): MultiLanguagePhoneDirectoryParser
    {
        return $this->parser;
    }

    public function getNaturalDatabase(): PhoneDirectoryDatabaseInterface
    {
        return $this->naturalDatabase;
    }

    public function getJuridicalDatabase(): JuridicalEntityDatabaseInterface
    {
        return $this->juridicalDatabase;
    }

    public function disconnect(): void
    {
        $this->naturalDatabase->disconnect();
        $this->juridicalDatabase->disconnect();
    }
}

<?php

namespace PhoneDirectory\Manager;

use PhoneDirectory\Parser\MultiLanguagePhoneDirectoryParser;
use PhoneDirectory\PhoneDirectoryDatabaseInterface;
use PhoneDirectory\JuridicalEntityDatabaseInterface;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Entity\JuridicalEntity;

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
        $this->ensureNaturalReady();
        $this->ensureJuridicalReady();

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
        $this->ensureNaturalReady();

        return $this->naturalDatabase->insert($entry);
    }

    public function addJuridicalEntity(JuridicalEntity $entity): int
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->insert($entity);
    }

    public function getNaturalPerson(int $id): ?PhoneDirectoryEntry
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findById($id);
    }

    public function getJuridicalEntity(int $id): ?JuridicalEntity
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->findById($id);
    }

    public function findNaturalPeopleByName(string $name): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findByName($name);
    }

    public function findNaturalPeopleBySurnameSound(string $surname, ?string $language = null): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findBySurnameSound($surname, $language);
    }

    public function findJuridicalEntitiesByName(string $name): array
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->findByBusinessName($name);
    }

    public function findNaturalPeopleByStreet(string $street): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findByStreet($street);
    }

    public function findJuridicalEntitiesByStreet(string $street): array
    {
        $this->ensureJuridicalReady();

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
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findByPhone($phone);
    }

    public function findJuridicalEntityByPhone(string $phone): ?JuridicalEntity
    {
        $this->ensureJuridicalReady();

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
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->findByBusinessType($type);
    }

    public function getAllNaturalPeople(): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->getAll();
    }

    public function getAllJuridicalEntities(): array
    {
        $this->ensureJuridicalReady();

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
        $this->ensureNaturalReady();

        return $this->naturalDatabase->search($criteria);
    }

    public function searchJuridicalEntities(array $criteria): array
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->search($criteria);
    }

    public function updateNaturalPerson(PhoneDirectoryEntry $entry): bool
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->update($entry);
    }

    public function updateJuridicalEntity(JuridicalEntity $entity): bool
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->update($entity);
    }

    public function deleteNaturalPerson(int $id): bool
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->delete($id);
    }

    public function deleteJuridicalEntity(int $id): bool
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->delete($id);
    }

    public function getTotalNaturalPeopleCount(): int
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->count();
    }

    public function getTotalJuridicalEntitiesCount(): int
    {
        $this->ensureJuridicalReady();

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

    private function ensureNaturalReady(): void
    {
        if (!$this->naturalDatabase->isConnected()) {
            $this->naturalDatabase->connect();
            $this->naturalDatabase->createTable();
        }
    }

    private function ensureJuridicalReady(): void
    {
        if (!$this->juridicalDatabase->isConnected()) {
            $this->juridicalDatabase->connect();
            $this->juridicalDatabase->createTable();
        }
    }

    public function disconnect(): void
    {
        $this->naturalDatabase->disconnect();
        $this->juridicalDatabase->disconnect();
    }
}

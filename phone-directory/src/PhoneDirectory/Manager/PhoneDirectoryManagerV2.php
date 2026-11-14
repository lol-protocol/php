<?php

namespace PhoneDirectory\Manager;

use PhoneDirectory\Parser\MultiLanguagePhoneDirectoryParser;
use PhoneDirectory\PhoneDirectoryDatabaseInterface;
use PhoneDirectory\JuridicalEntityDatabaseInterface;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Entity\JuridicalEntity;

/**
 * @phpstan-import-type ParseError from MultiLanguagePhoneDirectoryParser
 */
class PhoneDirectoryManagerV2
{
    /** Entries parsed and stored per batch by processFile(). */
    public const DEFAULT_BATCH_SIZE = 5000;

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

    /**
     * @return array{
     *     file: string,
     *     language: string,
     *     totalParsed: int,
     *     naturalPeople: int,
     *     juridicalEntities: int,
     *     naturalInserted: int,
     *     juridicalInserted: int,
     *     totalInserted: int,
     *     errors: list<ParseError>,
     *     errorCount: int
     * }
     */
    public function processFile(string $filePath, ?string $language = null, bool $clearExisting = false, int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        $this->ensureNaturalReady();
        $this->ensureJuridicalReady();

        if ($clearExisting) {
            $this->naturalDatabase->clear();
            $this->juridicalDatabase->clear();
        }

        // The file is read and stored $batchSize entries at a time, so memory stays flat however large it is.
        // Each batch is its own transaction: if the import stops half way (a database error), the batches before
        // it stay stored, so a failed import of a big file is re-run with $clearExisting.
        $naturalParsed = 0;
        $juridicalParsed = 0;
        $naturalInserted = 0;
        $juridicalInserted = 0;

        foreach ($this->parser->parseFileInBatches($filePath, $batchSize, $language) as $batch) {
            $naturalPeople = [];
            $juridicalEntities = [];

            foreach ($batch as $item) {
                if ($item['type'] === 'natural') {
                    $naturalPeople[] = $item['entity'];
                } else {
                    $juridicalEntities[] = $item['entity'];
                }
            }

            $naturalParsed += count($naturalPeople);
            $juridicalParsed += count($juridicalEntities);

            if ($naturalPeople !== []) {
                $naturalInserted += $this->naturalDatabase->insertBatch($naturalPeople);
            }
            if ($juridicalEntities !== []) {
                $juridicalInserted += $this->juridicalDatabase->insertBatch($juridicalEntities);
            }
        }

        return [
            'file' => $filePath,
            'language' => $this->parser->getDetectedLanguage(),
            'totalParsed' => $naturalParsed + $juridicalParsed,
            'naturalPeople' => $naturalParsed,
            'juridicalEntities' => $juridicalParsed,
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

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     */
    public function findNaturalPeopleByName(string $name, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findByName($name, $limit, $offset);
    }

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     */
    public function findNaturalPeopleBySurnameSound(string $surname, ?string $language = null, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findBySurnameSound($surname, $language, $limit, $offset);
    }

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<JuridicalEntity>
     */
    public function findJuridicalEntitiesByName(string $name, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->findByBusinessName($name, $limit, $offset);
    }

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     */
    public function findNaturalPeopleByStreet(string $street, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->findByStreet($street, $limit, $offset);
    }

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<JuridicalEntity>
     */
    public function findJuridicalEntitiesByStreet(string $street, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->findByStreet($street, $limit, $offset);
    }

    /** @return array{natural: list<PhoneDirectoryEntry>, juridical: list<JuridicalEntity>} */
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

    /** @return array{natural: PhoneDirectoryEntry|null, juridical: JuridicalEntity|null} */
    public function findByPhone(string $phone): array
    {
        $natural = $this->findNaturalPersonByPhone($phone);
        $juridical = $this->findJuridicalEntityByPhone($phone);

        return [
            'natural' => $natural,
            'juridical' => $juridical,
        ];
    }

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<JuridicalEntity>
     */
    public function findJuridicalEntitiesByType(string $type, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->findByBusinessType($type, $limit, $offset);
    }

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     */
    public function getAllNaturalPeople(?int $limit = null, int $offset = 0): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->getAll($limit, $offset);
    }

    /**
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<JuridicalEntity>
     */
    public function getAllJuridicalEntities(?int $limit = null, int $offset = 0): array
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->getAll($limit, $offset);
    }

    /** @return array{natural: list<PhoneDirectoryEntry>, juridical: list<JuridicalEntity>} */
    public function getAll(): array
    {
        return [
            'natural' => $this->getAllNaturalPeople(),
            'juridical' => $this->getAllJuridicalEntities(),
        ];
    }

    /**
     * @param array{name?: string, street?: string, phone?: string} $criteria
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<PhoneDirectoryEntry>
     */
    public function searchNaturalPeople(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureNaturalReady();

        return $this->naturalDatabase->search($criteria, $limit, $offset);
    }

    /**
     * @param array{businessName?: string, street?: string, phone?: string, businessType?: string} $criteria
     * @param int|null $limit The most to return; null for all
     * @param int $offset How many of what the reader may see to skip first
     * @return list<JuridicalEntity>
     */
    public function searchJuridicalEntities(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $this->ensureJuridicalReady();

        return $this->juridicalDatabase->search($criteria, $limit, $offset);
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

    /** @return array{total: int, natural_people: int, juridical_entities: int, ratio_natural_to_juridical: float|null} */
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

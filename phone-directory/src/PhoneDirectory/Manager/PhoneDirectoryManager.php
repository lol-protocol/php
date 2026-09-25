<?php

namespace PhoneDirectory\Manager;

use PhoneDirectory\Parser\PhoneDirectoryParser;
use PhoneDirectory\PhoneDirectoryDatabaseInterface;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;

/**
 * @deprecated Use PhoneDirectoryManagerV2 instead, which also supports juridical entities.
 *     This class is kept only for backward compatibility and is not used by the CLI or examples.
 */
class PhoneDirectoryManager
{
    private PhoneDirectoryParser $parser;
    private PhoneDirectoryDatabaseInterface $database;

    public function __construct(
        ?PhoneDirectoryParser $parser = null,
        ?PhoneDirectoryDatabaseInterface $database = null
    ) {
        $this->parser = $parser ?? new PhoneDirectoryParser();
        $this->database = $database ?? new PhoneDirectoryPDODatabase();
    }

    public function processFile(string $filePath, bool $clearExisting = false): array
    {
        $this->ensureReady();

        if ($clearExisting) {
            $this->database->clear();
        }

        $entries = $this->parser->parseFile($filePath);
        $insertedCount = 0;

        if (!empty($entries)) {
            $insertedCount = $this->database->insertBatch($entries);
        }

        return [
            'file' => $filePath,
            'totalParsed' => count($entries),
            'insertedCount' => $insertedCount,
            'errors' => $this->parser->getErrors(),
            'errorCount' => $this->parser->getErrorsCount(),
        ];
    }

    public function addEntry(PhoneDirectoryEntry $entry): int
    {
        $this->ensureReady();

        return $this->database->insert($entry);
    }

    public function getEntry(int $id): ?PhoneDirectoryEntry
    {
        $this->ensureReady();

        return $this->database->findById($id);
    }

    public function findByName(string $name): array
    {
        $this->ensureReady();

        return $this->database->findByName($name);
    }

    public function findByStreet(string $street): array
    {
        $this->ensureReady();

        return $this->database->findByStreet($street);
    }

    public function findByPhone(string $phone): ?PhoneDirectoryEntry
    {
        $this->ensureReady();

        return $this->database->findByPhone($phone);
    }

    public function findBySurnameSound(string $surname, ?string $language = null): array
    {
        $this->ensureReady();

        return $this->database->findBySurnameSound($surname, $language);
    }

    public function getAllEntries(): array
    {
        $this->ensureReady();

        return $this->database->getAll();
    }

    public function search(array $criteria): array
    {
        $this->ensureReady();

        return $this->database->search($criteria);
    }

    public function updateEntry(PhoneDirectoryEntry $entry): bool
    {
        $this->ensureReady();

        return $this->database->update($entry);
    }

    public function deleteEntry(int $id): bool
    {
        $this->ensureReady();

        return $this->database->delete($id);
    }

    public function getTotalCount(): int
    {
        $this->ensureReady();

        return $this->database->count();
    }

    public function getParser(): PhoneDirectoryParser
    {
        return $this->parser;
    }

    public function getDatabase(): PhoneDirectoryDatabaseInterface
    {
        return $this->database;
    }

    private function ensureReady(): void
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
            $this->database->createTable();
        }
    }

    public function disconnect(): void
    {
        $this->database->disconnect();
    }
}

<?php

namespace PhoneDirectory;

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
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        $this->database->createTable();

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
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->insert($entry);
    }

    public function getEntry(int $id): ?PhoneDirectoryEntry
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->findById($id);
    }

    public function findByName(string $name): array
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->findByName($name);
    }

    public function findByStreet(string $street): array
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->findByStreet($street);
    }

    public function findByPhone(string $phone): ?PhoneDirectoryEntry
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->findByPhone($phone);
    }

    public function getAllEntries(): array
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->getAll();
    }

    public function search(array $criteria): array
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->search($criteria);
    }

    public function updateEntry(PhoneDirectoryEntry $entry): bool
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->update($entry);
    }

    public function deleteEntry(int $id): bool
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

        return $this->database->delete($id);
    }

    public function getTotalCount(): int
    {
        if (!$this->database->isConnected()) {
            $this->database->connect();
        }

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

    public function disconnect(): void
    {
        $this->database->disconnect();
    }
}

<?php

namespace PhoneDirectory;

class PhoneDirectoryParser
{
    private const COMMON_PATTERNS = [
        'line_separator' => '/^(?:[-=_*]\s*){2,}$/',
        'phone_pattern' => '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\b\d{10}\b/',
        'street_marker' => '/\b(?:st|street|ave|avenue|rd|road|dr|drive|ln|lane|blvd|boulevard|cir|circle)\b/iu',
    ];

    private array $entries = [];
    private array $parseErrors = [];

    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("File is not readable: {$filePath}");
        }

        $this->entries = [];
        $this->parseErrors = [];

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read file: {$filePath}");
        }

        return $this->parseContent($content);
    }

    public function parseContent(string $content): array
    {
        $lines = explode("\n", $content);
        $buffer = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $trimmed = trim($line);

            if (empty($trimmed)) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $lineNumber - count($buffer));
                    $buffer = [];
                }
                continue;
            }

            if (preg_match(self::COMMON_PATTERNS['line_separator'], $trimmed)) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $lineNumber - count($buffer));
                    $buffer = [];
                }
                continue;
            }

            $buffer[] = $trimmed;
        }

        if (!empty($buffer)) {
            $this->processBuffer($buffer, $lineNumber - count($buffer));
        }

        return $this->entries;
    }

    private function processBuffer(array $lines, int $startLine): void
    {
        $data = [
            'name' => null,
            'phone' => null,
            'street' => null,
        ];

        $nameSet = false;

        foreach ($lines as $line) {
            if (empty($data['phone']) && preg_match(self::COMMON_PATTERNS['phone_pattern'], $line, $matches)) {
                $data['phone'] = $matches[0];
            }

            if (empty($data['street'])) {
                $streetMatch = $this->extractStreet($line);
                if ($streetMatch) {
                    $data['street'] = $streetMatch;
                }
            }

            if (!$nameSet && !preg_match(self::COMMON_PATTERNS['phone_pattern'], $line)) {
                $streetMatch = $this->extractStreet($line);
                if (!$streetMatch) {
                    $data['name'] = $line;
                    $nameSet = true;
                }
            }
        }

        if (!$this->validateEntry($data)) {
            $this->parseErrors[] = [
                'line' => $startLine,
                'reason' => 'Missing required fields (name and street)',
                'data' => $data,
            ];
            return;
        }

        try {
            $entry = new PhoneDirectoryEntry(
                fullName: $data['name'],
                countryCode: 'US',
                street: $data['street'],
                phoneNumber: $data['phone']
            );
            $this->entries[] = $entry;
        } catch (\Throwable $e) {
            $this->parseErrors[] = [
                'line' => $startLine,
                'reason' => $e->getMessage(),
                'data' => $data,
            ];
        }
    }

    private function extractStreet(string $line): ?string
    {
        if (preg_match(self::COMMON_PATTERNS['street_marker'], $line)) {
            return $line;
        }

        if (preg_match('/\d+\s+[\w\s]+/', $line, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function validateEntry(array $data): bool
    {
        return !empty($data['name']) && !empty($data['street']);
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getErrors(): array
    {
        return $this->parseErrors;
    }

    public function getEntriesCount(): int
    {
        return count($this->entries);
    }

    public function getErrorsCount(): int
    {
        return count($this->parseErrors);
    }

    public function reset(): void
    {
        $this->entries = [];
        $this->parseErrors = [];
    }
}

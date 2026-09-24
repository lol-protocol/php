<?php

namespace PhoneDirectory;

class PhoneDirectoryParser
{
    private array $entries = [];
    private array $parseErrors = [];
    private string $countryCode;
    private ?string $sourceDirectoryId;
    private ?array $patterns = null;

    public function __construct(string $countryCode = 'US', ?string $sourceDirectoryId = null)
    {
        if (!preg_match('/^[A-Za-z]{2}$/', $countryCode)) {
            throw new \InvalidArgumentException("Country code must be 2 letters (ISO 3166-1 alpha-2): {$countryCode}");
        }

        $this->countryCode = strtoupper($countryCode);
        $this->sourceDirectoryId = $sourceDirectoryId;
    }

    public static function forCatalogDirectory(string $directoryId, ?PhoneDirectoryCatalog $catalog = null): self
    {
        $directory = ($catalog ?? new PhoneDirectoryCatalog())->get($directoryId);
        if ($directory === null) {
            throw new \InvalidArgumentException("Unknown catalog directory: {$directoryId}");
        }

        return new self($directory['country'], $directoryId);
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getSourceDirectoryId(): ?string
    {
        return $this->sourceDirectoryId;
    }

    private function getPatterns(): array
    {
        if ($this->patterns !== null) {
            return $this->patterns;
        }

        $this->patterns = [
            'line_separator' => '/^(?:[-=_*]\s*){2,}$/',
            'phone_pattern' => PhonePattern::getValidatedRegex(),
            'street_marker' => '/\b(?:st|street|ave|avenue|rd|road|dr|drive|ln|lane|blvd|boulevard|cir|circle)\b/iu',
        ];

        return $this->patterns;
    }

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
        $bufferStart = 0;
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $trimmed = trim($line);

            $patterns = $this->getPatterns();
            if ($trimmed === '' || preg_match($patterns['line_separator'], $trimmed)) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $bufferStart);
                    $buffer = [];
                }
                continue;
            }

            // Most historical directories print one full record per line rather than one field per
            // line; try that shape before falling back to the original multi-line block assumption.
            $single = SingleLineEntrySplitter::split($trimmed, fn($street) => $this->isStreet($street), 1);
            if ($single !== null) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $bufferStart);
                    $buffer = [];
                }
                $this->finalizeEntry($single, $lineNumber);
                continue;
            }

            if ($buffer === []) {
                $bufferStart = $lineNumber;
            }
            $buffer[] = $trimmed;
        }

        if (!empty($buffer)) {
            $this->processBuffer($buffer, $bufferStart);
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
        $patterns = $this->getPatterns();

        foreach ($lines as $line) {
            if (empty($data['phone']) && preg_match($patterns['phone_pattern'], $line, $matches)) {
                $data['phone'] = $matches[0];
            }

            if (empty($data['street'])) {
                $streetMatch = $this->extractStreet($line);
                if ($streetMatch) {
                    $data['street'] = $streetMatch;
                }
            }

            if (!$nameSet && !preg_match($patterns['phone_pattern'], $line)) {
                $streetMatch = $this->extractStreet($line);
                if (!$streetMatch) {
                    $data['name'] = $line;
                    $nameSet = true;
                }
            }
        }

        $this->finalizeEntry($data, $startLine);
    }

    private function finalizeEntry(array $data, int $startLine): void
    {
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
                countryCode: $this->countryCode,
                street: $data['street'],
                phoneNumber: $data['phone'] ?? null,
                sourceDirectoryId: $this->sourceDirectoryId,
                sourceLine: $startLine
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

    private function isStreet(string $line): bool
    {
        $patterns = $this->getPatterns();
        return preg_match($patterns['street_marker'], $line) === 1;
    }

    private function extractStreet(string $line): ?string
    {
        if ($this->isStreet($line)) {
            return $line;
        }

        // A line that is only a phone number is not also a street, even though it starts with digits.
        if (PhonePattern::isOnlyAPhoneNumber($line)) {
            return null;
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

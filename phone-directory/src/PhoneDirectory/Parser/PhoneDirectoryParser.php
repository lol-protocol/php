<?php

namespace PhoneDirectory\Parser;

use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryCatalog;
use PhoneDirectory\Validation\InputValidator;

/**
 * @phpstan-type RawEntry array{name: string|null, phone: string|null, street: string|null}
 * @phpstan-type ParseError array{line: int, reason: string, data: RawEntry}
 * @phpstan-type Patterns array{line_separator: string, phone_pattern: string}
 */
class PhoneDirectoryParser
{
    use InputValidator;

    /** @var list<PhoneDirectoryEntry> */
    private array $entries = [];
    /** @var list<ParseError> */
    private array $parseErrors = [];
    private string $countryCode;
    private ?string $sourceDirectoryId;
    /** @var Patterns|null */
    private ?array $patterns = null;

    public function __construct(string $countryCode = 'US', ?string $sourceDirectoryId = null)
    {
        $this->validateCountryCode($countryCode);

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

    /** @return Patterns */
    private function getPatterns(): array
    {
        if ($this->patterns !== null) {
            return $this->patterns;
        }

        $this->patterns = [
            'line_separator' => '/^(?:[-=_*]\s*){2,}$/',
            'phone_pattern' => PhonePattern::getValidatedRegex(),
        ];

        return $this->patterns;
    }

    /** @return list<PhoneDirectoryEntry> */
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

        return $this->parseLines(LineReader::read($filePath));
    }

    /** @return list<PhoneDirectoryEntry> */
    public function parseContent(string $content): array
    {
        return $this->parseLines(explode("\n", $content));
    }

    /**
     * @param iterable<string> $lines
     * @return list<PhoneDirectoryEntry>
     */
    private function parseLines(iterable $lines): array
    {
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
            // Invalid UTF-8 would crash the splitter's /u regex; such lines fall through to the block
            // path, where finalizeEntry() reports the record as a parse error.
            $single = mb_check_encoding($trimmed, 'UTF-8')
                ? SingleLineEntrySplitter::split($trimmed, fn($street) => StreetRules::hasStreetWord($street, 'en'), 1)
                : null;
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

    /** @param list<string> $lines */
    private function processBuffer(array $lines, int $startLine): void
    {
        $phonePattern = $this->getPatterns()['phone_pattern'];
        foreach (BlockReader::read($lines, fn($line) => LineFacts::of($line, 'en', $phonePattern)) as $record) {
            $this->finalizeEntry(
                ['name' => $record['name'], 'phone' => $record['phone'], 'street' => $record['street']],
                $startLine + $record['offset']
            );
        }
    }

    /** @param RawEntry $data */
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

        // Rejected here so one badly-encoded line becomes a parse error instead of aborting a whole batch insert.
        foreach ($data as $value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $this->parseErrors[] = [
                    'line' => $startLine,
                    'reason' => 'Invalid UTF-8 encoding',
                    'data' => $data,
                ];
                return;
            }
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

    /**
     * $afterName: the record's name has been read, so a dwelling line ("Apt 4 Building 7") also counts.
     *
     * @deprecated since 2026-11: BlockReader reads the street from LineFacts, which asks StreetRules directly.
     *             Kept, not deleted, by the repository's rule on unused code (CLAUDE.md).
     */
    // @phpstan-ignore method.unused
    private function extractStreet(string $line, bool $afterName = false): ?string
    {
        return StreetRules::strong($line, 'en') ?? ($afterName ? StreetRules::afterName($line, 'en') : null);
    }

    /** @param RawEntry $data */
    private function validateEntry(array $data): bool
    {
        return !empty($data['name']) && !empty($data['street']);
    }

    /** @return list<PhoneDirectoryEntry> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /** @return list<ParseError> */
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

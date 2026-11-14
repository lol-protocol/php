<?php

namespace PhoneDirectory\Parser;

use PhoneDirectory\Validation\InputValidator;
use PhoneDirectory\Exception\InvalidLanguageException;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Entity\PersonName;
use PhoneDirectory\PhoneDirectoryCatalog;

/**
 * @phpstan-type RawEntry array{name: string|null, phone: string|null, street: string|null, type: string|null}
 * @phpstan-type ParseError array{line: int, reason: string, language: string, data: RawEntry}
 * @phpstan-type NaturalEntry array{type: 'natural', entity: PhoneDirectoryEntry}
 * @phpstan-type JuridicalEntry array{type: 'juridical', entity: JuridicalEntity}
 * @phpstan-type ParsedEntry NaturalEntry|JuridicalEntry
 */
class MultiLanguagePhoneDirectoryParser
{
    use InputValidator;
    // Subset of StreetRules' street words used only to detect the language, deliberately excluding words
    // that are also ordinary English words or English street types ("plaza", "avenue", "boulevard",
    // "square", "via", "ring"): one of those appearing in an otherwise-English directory would otherwise
    // outscore English's own (zero) count and misdetect the whole file. English is this project's
    // baseline language, so detection only overrides it on genuinely distinctive vocabulary. Street
    // *extraction* for an already-known language still uses all of StreetRules' words.
    private const LANGUAGE_DETECTION_MARKERS = [
        'es' => ['calle', 'avenida', 'pasaje', 'camino', 'ruta', 'carrera'],
        'fr' => ['rue', 'allée', 'cours'],
        'pt' => ['rua', 'avenida', 'praça', 'alameda', 'estrada', 'largo'],
        'de' => [], // detected via StreetRules::SUFFIXES only; its own marker words are too ambiguous
        'it' => ['viale', 'corso', 'piazza', 'largo', 'strada'],
    ];

    // Words left out of LANGUAGE_DETECTION_MARKERS because they are also English, still counted where the
    // line has a shape English doesn't use: "Via Roma 10" or "Via Roma, 10" opens the line and ends with
    // the house number, while English writes "12 Via Lane" or uses "via" mid-sentence.
    private const LANGUAGE_DETECTION_LINES = [
        'it' => "/^[ \\t]*via\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*(?:\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*)*,?\\s+\\d{1,5}[a-z]?\\b/imu",
    ];

    // Words that name a kind of business, or a family firm ("Smith & Sons", "Hermanos García", "Hnos."), and
    // that almost nobody has for a surname. A word many people carry as a surname (Baker, Miller, Bank...)
    // does not belong in these lists: it would take them out from under Privacy, which only covers persons.
    private const ENTITY_TYPE_MARKERS = [
        'es' => ['spa', 'srl', 'sa', 'ltda', 'inc', 'comercial', 'empresa', 'negocio', 'tienda', 'restaurante', 'hotel', 'banco', 'farmacia', 'hospital', 'hnos', 'hermanos', 'hijos', 'sucesores', 'taller', 'ferretería', 'clínica', 'panadería', 'carnicería', 'librería', 'zapatería'],
        'en' => ['corp', 'inc', 'ltd', 'llc', 'company', 'store', 'shop', 'restaurant', 'hotel', 'bank', 'pharmacy', 'hospital', 'sons', 'bros', 'associates', 'enterprises', 'industries', 'services', 'plumbing', 'garage', 'bakery', 'hardware', 'clinic', 'pub', 'motel'],
        'fr' => ['sarl', 'sas', 'eirl', 'eurl', 'magasin', 'restaurant', 'hôtel', 'banque', 'pharmacie', 'hôpital', 'frères', 'boulangerie', 'boucherie', 'garage', 'clinique', 'quincaillerie', 'cabinet'],
        'pt' => ['ltda', 'lda', 'eireli', 'empresa', 'comercial', 'loja', 'padaria', 'restaurante', 'hotel', 'banco', 'farmácia', 'hospital', 'irmãos', 'filhos', 'oficina', 'clínica', 'mercearia', 'ferragens', 'sapataria'],
        'de' => ['gmbh', 'ag', 'kg', 'ohg', 'apotheke', 'bäckerei', 'gasthaus', 'restaurant', 'hotel', 'bank', 'krankenhaus', 'gebrüder', 'söhne', 'werkstatt', 'metzgerei', 'tischlerei', 'klinik'],
        'it' => ['srl', 'spa', 'snc', 'sas', 'negozio', 'panificio', 'trattoria', 'ristorante', 'albergo', 'hotel', 'banca', 'farmacia', 'ospedale', 'fratelli', 'figli', 'officina', 'macelleria', 'ferramenta', 'pasticceria', 'pizzeria', 'clinica'],
    ];

    private const SUPPORTED_LANGUAGES = StreetRules::SUPPORTED_LANGUAGES;

    private string $detectedLanguage = 'en';
    /** @var list<ParsedEntry> */
    private array $entries = [];
    /** @var list<ParseError> */
    private array $parseErrors = [];
    private string $countryCode;
    private ?string $sourceDirectoryId;

    /** @var array<string, string|null> */
    private array $entityTypePatternCache = [];
    /** @var array<string, string|null> */
    private array $detectionPatternCache = [];

    /**
     * @param string $countryCode Two-letter ISO 3166-1 alpha-2 country code
     * @param string|null $sourceDirectoryId Identifier for the source directory
     * @throws \InvalidArgumentException If country code is invalid
     */
    public function __construct(string $countryCode = 'US', ?string $sourceDirectoryId = null)
    {
        $this->validateCountryCode($countryCode);
        $this->validateOptionalNotEmpty($sourceDirectoryId, 'Source directory ID');

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

    /** @return list<ParsedEntry> */
    public function parseFile(string $filePath, ?string $language = null): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        // Two streaming passes (detect, then parse) instead of holding the whole file in memory.
        $language ??= $this->detectLanguage(LineReader::read($filePath));

        return $this->parseLines(LineReader::read($filePath), $language);
    }

    /**
     * Parse a file and hand its entries over $batchSize at a time, without ever holding the whole file's
     * entries: memory stays flat however large the directory is (parseFile() keeps every entry, about 1.8 KB each).
     *
     * The file, the batch size and the language are checked when this is called, not when the first batch is read.
     * While the batches are read, getErrors() grows and getDetectedLanguage() is set; getEntries() stays empty,
     * because an entry that has been handed over is not kept.
     *
     * @return \Generator<int, list<ParsedEntry>> Batches of up to $batchSize entries, in file order
     * @throws \RuntimeException If the file does not exist
     * @throws \InvalidArgumentException If $batchSize is below 1 or the language is unsupported
     */
    public function parseFileInBatches(string $filePath, int $batchSize = 1000, ?string $language = null): \Generator
    {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException("The batch size must be at least 1, not {$batchSize}");
        }
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $language ??= $this->detectLanguage(LineReader::read($filePath));
        $this->assertSupported($language);

        return $this->batches(LineReader::read($filePath), $language, $batchSize);
    }

    /**
     * @param iterable<string> $lines
     * @return \Generator<int, list<ParsedEntry>>
     */
    private function batches(iterable $lines, string $language, int $batchSize): \Generator
    {
        $batch = [];
        foreach ($this->streamLines($lines, $language) as $entry) {
            $batch[] = $entry;
            if (count($batch) >= $batchSize) {
                yield $batch;
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * Parse phone directory content and extract entries
     *
     * @param string $content The raw directory content to parse
     * @param string|null $language Language code (es, en, fr, pt, de, it). Auto-detected if null.
     * @return list<ParsedEntry> Parsed entries, each with 'type' => 'natural'|'juridical' and its 'entity'
     * @throws \InvalidArgumentException If language is unsupported
     */
    public function parseContent(string $content, ?string $language = null): array
    {
        $language ??= $this->detectLanguage([$content]);

        return $this->parseLines(explode("\n", $content), $language);
    }

    /**
     * @param iterable<string> $lines
     * @return list<ParsedEntry>
     */
    private function parseLines(iterable $lines, string $language): array
    {
        $this->assertSupported($language);

        $all = [];
        foreach ($this->streamLines($lines, $language) as $entry) {
            $all[] = $entry;
        }

        return $this->entries = $all;
    }

    private function assertSupported(string $language): void
    {
        if (!in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            throw new InvalidLanguageException("Unsupported language: {$language}. Supported languages: " . implode(', ', self::SUPPORTED_LANGUAGES));
        }
    }

    /**
     * The entries finalizeEntry() has produced since the last call, handed over: this parser no longer keeps them.
     *
     * @return \Generator<int, ParsedEntry>
     */
    private function entriesSoFar(): \Generator
    {
        $taken = $this->entries;
        $this->entries = [];

        foreach ($taken as $entry) {
            yield $entry;
        }
    }

    /**
     * Reads the lines and yields each entry as soon as its record is complete.
     *
     * @param iterable<string> $lines
     * @return \Generator<int, ParsedEntry>
     */
    private function streamLines(iterable $lines, string $language): \Generator
    {
        $this->entries = [];
        $this->parseErrors = [];

        $this->assertSupported($language);

        $this->detectedLanguage = $language;
        $phoneRegex = PhonePattern::regexFor($language);

        $buffer = [];
        $bufferStart = 0;
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $trimmed = trim($line);

            if ($trimmed === '' || preg_match('/^(?:[-=_*]\s*){2,}$/', $trimmed)) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $bufferStart, $language, $phoneRegex);
                    $buffer = [];
                    yield from $this->entriesSoFar();
                }
                continue;
            }

            // Most historical directories print one full record per line rather than one field per
            // line; try that shape before falling back to the original multi-line block assumption.
            // Invalid UTF-8 would crash the splitter's /u regex; such lines fall through to the block
            // path, where finalizeEntry() reports the record as a parse error.
            $single = mb_check_encoding($trimmed, 'UTF-8') ? SingleLineEntrySplitter::split(
                $trimmed,
                // Only a street word, as PhoneDirectoryParser does: the number-first fallback alone would
                // turn "Restaurante 3 Hermanos" or "Apt 4 Building 7" into a record of their own.
                fn($street) => StreetRules::hasStreetWord($street, $language),
                PersonName::LANGUAGE_SURNAME_COUNT[$language] ?? 1,
                $language,
                in_array($language, StreetRules::NUMBER_LAST_LANGUAGES, true) ? fn($beforeNumber) => StreetRules::streetWordOffset($beforeNumber, $language) : null
            ) : null;
            if ($single !== null) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $bufferStart, $language, $phoneRegex);
                    $buffer = [];
                }
                $this->finalizeEntry(
                    ['name' => $single['name'], 'phone' => $single['phone'], 'street' => $single['street'], 'type' => $this->businessTypeOfName($single['name'], $language)],
                    $lineNumber,
                    $language
                );
                yield from $this->entriesSoFar();
                continue;
            }

            if ($buffer === []) {
                $bufferStart = $lineNumber;
            }
            $buffer[] = $trimmed;
        }

        if (!empty($buffer)) {
            $this->processBuffer($buffer, $bufferStart, $language, $phoneRegex);
        }

        yield from $this->entriesSoFar();
    }

    /**
     * @param iterable<string> $chunks The whole content as one chunk, or a file line by line
     */
    private function detectLanguage(iterable $chunks): string
    {
        $scores = ['en' => 0];
        $patterns = [];
        foreach (self::LANGUAGE_DETECTION_MARKERS as $lang => $words) {
            $scores[$lang] = 0;
            $patterns[$lang] = $this->getDetectionPattern($lang, $words);
        }

        foreach ($chunks as $chunk) {
            foreach ($patterns as $lang => $pattern) {
                if ($pattern !== null) {
                    $scores[$lang] += (int) preg_match_all($pattern, $chunk);
                }
                if (isset(self::LANGUAGE_DETECTION_LINES[$lang])) {
                    $scores[$lang] += (int) preg_match_all(self::LANGUAGE_DETECTION_LINES[$lang], $chunk);
                }
            }
        }

        arsort($scores);
        $best = array_key_first($scores);

        return $scores[$best] > 0 ? $best : 'en';
    }

    /** @param list<string> $words */
    private function getDetectionPattern(string $language, array $words): ?string
    {
        if (!isset($this->detectionPatternCache[$language])) {
            $suffixes = StreetRules::SUFFIXES[$language] ?? [];
            if ($words === [] && $suffixes === []) {
                $this->detectionPatternCache[$language] = null;
                return null;
            }

            $parts = [];
            if ($words !== []) {
                $parts[] = '\\b(?:' . implode('|', array_map(fn($m) => preg_quote($m, '/'), $words)) . ')\\b';
            }
            foreach ($suffixes as $suffix) {
                $parts[] = preg_quote($suffix, '/') . '\\b';
            }

            $this->detectionPatternCache[$language] = '/' . implode('|', $parts) . '/iu';
        }

        return $this->detectionPatternCache[$language];
    }

    /** @param list<string> $lines */
    private function processBuffer(array $lines, int $startLine, string $language, string $phoneRegex): void
    {
        $records = BlockReader::read($lines, fn($line) => LineFacts::of($line, $language, $phoneRegex));
        foreach ($records as $record) {
            // Only the name and the notes can say it is a business: "12 Bank Street" or "Calle del Banco 4"
            // say where someone lives, not what they are.
            $type = $record['name'] !== null ? $this->businessTypeOfName($record['name'], $language) : null;
            foreach ($record['notes'] as $note) {
                $type ??= $this->extractBusinessType($note, $language);
            }

            $this->finalizeEntry(
                ['name' => $record['name'], 'phone' => $record['phone'], 'street' => $record['street'], 'type' => $type],
                $startLine + $record['offset'],
                $language
            );
        }
    }

    /** @param RawEntry $data */
    private function finalizeEntry(array $data, int $startLine, string $language): void
    {
        if (!$this->validateEntry($data)) {
            $this->parseErrors[] = [
                'line' => $startLine,
                'reason' => 'Missing required fields',
                'language' => $language,
                'data' => $data,
            ];
            return;
        }

        // Rejected here so one badly-encoded line becomes a parse error instead of aborting a whole batch insert.
        if (!self::isValidUtf8($data)) {
            $this->parseErrors[] = [
                'line' => $startLine,
                'reason' => 'Invalid UTF-8 encoding',
                'language' => $language,
                'data' => $data,
            ];
            return;
        }

        try {
            $isJuridical = $this->isJuridicalEntity($data, $language);

            if ($isJuridical) {
                $entity = new JuridicalEntity(
                    businessName: $data['name'],
                    street: $data['street'],
                    businessType: $data['type'],
                    phoneNumber: $data['phone'],
                    countryCode: $this->countryCode,
                    sourceDirectoryId: $this->sourceDirectoryId,
                    sourceLine: $startLine
                );
                $this->entries[] = [
                    'type' => 'juridical',
                    'entity' => $entity,
                ];
            } else {
                $entry = new PhoneDirectoryEntry(
                    fullName: $data['name'],
                    countryCode: $this->countryCode,
                    street: $data['street'],
                    phoneNumber: $data['phone'],
                    sourceDirectoryId: $this->sourceDirectoryId,
                    language: $language,
                    sourceLine: $startLine
                );
                $this->entries[] = [
                    'type' => 'natural',
                    'entity' => $entry,
                ];
            }
        } catch (\Throwable $e) {
            $this->parseErrors[] = [
                'line' => $startLine,
                'reason' => $e->getMessage(),
                'language' => $language,
                'data' => $data,
            ];
        }
    }

    private function extractBusinessType(string $line, string $language): ?string
    {
        $markers = self::ENTITY_TYPE_MARKERS[$language] ?? [];
        if (empty($markers)) {
            return null;
        }

        $pattern = $this->getEntityTypePattern($language);
        if (preg_match($pattern, $line, $matches)) {
            return mb_strtolower($matches[1], 'UTF-8');
        }

        return null;
    }

    /**
     * The business word in a name, unless the name is a surname followed by a given name: "BANK, Henry" is a
     * person whose surname happens to be a business word (Bank, Store, Hospital, Albergo...), and treating them
     * as a business would also take them out from under Privacy, which only covers persons. Only the exact
     * shape "<one word>, <given name>" is spared, with no business word in the given-name part: "BANK OF
     * AMERICA, N.A." and "HOTEL REINA, S.A." stay businesses.
     */
    private function businessTypeOfName(string $name, string $language): ?string
    {
        $type = $this->extractBusinessType($name, $language);
        if ($type === null) {
            return null;
        }

        $parts = array_map('trim', explode(',', $name));
        $isSurnameAndGivenName = count($parts) === 2
            && mb_strtolower($parts[0], 'UTF-8') === $type
            && preg_match('/^\p{L}[\p{L}\'. -]*$/u', $parts[1]) === 1
            && $this->extractBusinessType($parts[1], $language) === null;

        return $isSurnameAndGivenName ? null : $type;
    }

    private function getEntityTypePattern(string $language): ?string
    {
        if (!isset($this->entityTypePatternCache[$language])) {
            $markers = self::ENTITY_TYPE_MARKERS[$language] ?? [];

            if (empty($markers)) {
                $this->entityTypePatternCache[$language] = null;
            } else {
                $pattern = '\\b(' . implode('|', array_map(fn($m) => preg_quote($m, '/'), $markers)) . ')\\b';
                $this->entityTypePatternCache[$language] = '/' . $pattern . '/iu';
            }
        }

        return $this->entityTypePatternCache[$language];
    }

    /** @param RawEntry $data */
    private function isJuridicalEntity(array $data, string $language): bool
    {
        if (!empty($data['type'])) {
            return true;
        }

        return $this->businessTypeOfName($data['name'] ?? '', $language) !== null;
    }

    /** @param array<string, string|null> $data */
    private static function isValidUtf8(array $data): bool
    {
        foreach ($data as $value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                return false;
            }
        }

        return true;
    }

    /** @param RawEntry $data */
    private function validateEntry(array $data): bool
    {
        return !empty($data['name']) && !empty($data['street']);
    }

    /** @return list<ParsedEntry> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /** @return list<ParseError> */
    public function getErrors(): array
    {
        return $this->parseErrors;
    }

    public function getDetectedLanguage(): string
    {
        return $this->detectedLanguage;
    }

    /** @return array<int, NaturalEntry> Keys are the entries' original positions, so it is not a list */
    public function getNaturalPeople(): array
    {
        return array_filter($this->entries, fn($item) => $item['type'] === 'natural');
    }

    /** @return array<int, JuridicalEntry> Keys are the entries' original positions, so it is not a list */
    public function getJuridicalEntities(): array
    {
        return array_filter($this->entries, fn($item) => $item['type'] === 'juridical');
    }

    public function reset(): void
    {
        $this->entries = [];
        $this->parseErrors = [];
        $this->detectedLanguage = 'en';
    }
}

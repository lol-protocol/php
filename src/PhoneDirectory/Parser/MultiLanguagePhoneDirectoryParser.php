<?php

namespace PhoneDirectory\Parser;

use PhoneDirectory\Validation\InputValidator;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Entity\PersonName;
use PhoneDirectory\PhoneDirectoryCatalog;

class MultiLanguagePhoneDirectoryParser
{
    use InputValidator;
    private const LANGUAGE_STREET_MARKERS = [
        'es' => ['calle', 'avenida', 'av', 'plaza', 'pasaje', 'camino', 'ruta', 'carrera'],
        'en' => ['street', 'st', 'avenue', 'ave', 'road', 'rd', 'drive', 'dr', 'lane', 'ln', 'boulevard', 'blvd', 'circle', 'cir'],
        'fr' => ['rue', 'avenue', 'allée', 'place', 'boulevard', 'bd', 'cours', 'square'],
        'pt' => ['rua', 'avenida', 'av', 'praça', 'alameda', 'estrada', 'largo'],
        'de' => ['ring', 'hof'],
        'it' => ['via', 'viale', 'corso', 'piazza', 'largo', 'strada'],
    ];

    // German compounds glue the street type onto the name ("Hauptstraße"), so these match as word endings.
    private const LANGUAGE_STREET_SUFFIXES = [
        'de' => ['straße', 'strasse', 'allee', 'weg', 'platz'],
    ];

    // Subset of LANGUAGE_STREET_MARKERS used only to detect the language, deliberately excluding words
    // that are also ordinary English words or English street types ("plaza", "avenue", "boulevard",
    // "square", "via", "ring"): one of those appearing in an otherwise-English directory would otherwise
    // outscore English's own (zero) count and misdetect the whole file. English is this project's
    // baseline language, so detection only overrides it on genuinely distinctive vocabulary. Street
    // *extraction* for an already-known language still uses the full LANGUAGE_STREET_MARKERS list.
    private const LANGUAGE_DETECTION_MARKERS = [
        'es' => ['calle', 'avenida', 'pasaje', 'camino', 'ruta', 'carrera'],
        'fr' => ['rue', 'allée', 'cours'],
        'pt' => ['rua', 'avenida', 'praça', 'alameda', 'estrada', 'largo'],
        'de' => [], // detected via LANGUAGE_STREET_SUFFIXES only; its own marker words are too ambiguous
        'it' => ['viale', 'corso', 'piazza', 'largo', 'strada'],
    ];

    private const ENTITY_TYPE_MARKERS = [
        'es' => ['spa', 'srl', 'sa', 'ltda', 'inc', 'comercial', 'empresa', 'negocio', 'tienda', 'restaurante', 'hotel', 'banco', 'farmacia', 'hospital'],
        'en' => ['corp', 'inc', 'ltd', 'llc', 'company', 'store', 'shop', 'restaurant', 'hotel', 'bank', 'pharmacy', 'hospital'],
        'fr' => ['sarl', 'sas', 'eirl', 'eurl', 'magasin', 'restaurant', 'hôtel', 'banque', 'pharmacie', 'hôpital'],
    ];

    private const SUPPORTED_LANGUAGES = ['es', 'en', 'fr', 'pt', 'de', 'it'];

    private PhoneDirectoryParser $parser;
    private string $detectedLanguage = 'en';
    private array $entries = [];
    private array $parseErrors = [];
    private string $countryCode;
    private ?string $sourceDirectoryId;

    private array $streetPatternCache = [];
    private array $entityTypePatternCache = [];
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
        $this->parser = new PhoneDirectoryParser($this->countryCode, $sourceDirectoryId);
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

    public function parseFile(string $filePath, ?string $language = null): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read file: {$filePath}");
        }

        return $this->parseContent($content, $language);
    }

    /**
     * Parse phone directory content and extract entries
     *
     * @param string $content The raw directory content to parse
     * @param string|null $language Language code (es, en, fr, pt, de, it). Auto-detected if null.
     * @return array Parsed entries with 'type' => 'natural'|'juridical' and 'entity'
     * @throws \InvalidArgumentException If language is unsupported
     */
    public function parseContent(string $content, ?string $language = null): array
    {
        $this->entries = [];
        $this->parseErrors = [];

        if ($language === null) {
            $language = $this->detectLanguage($content);
        }

        if (!in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            throw new \InvalidArgumentException("Unsupported language: {$language}. Supported languages: " . implode(', ', self::SUPPORTED_LANGUAGES));
        }

        $this->detectedLanguage = $language;
        $phoneRegex = PhonePattern::getValidatedRegex();

        $lines = explode("\n", $content);
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
                }
                continue;
            }

            // Most historical directories print one full record per line rather than one field per
            // line; try that shape before falling back to the original multi-line block assumption.
            $single = SingleLineEntrySplitter::split(
                $trimmed,
                fn($street) => $this->extractStreet($street, $language) !== null,
                PersonName::LANGUAGE_SURNAME_COUNT[$language] ?? 1
            );
            if ($single !== null) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $bufferStart, $language, $phoneRegex);
                    $buffer = [];
                }
                $this->finalizeEntry(
                    ['name' => $single['name'], 'phone' => $single['phone'], 'street' => $single['street'], 'type' => $this->extractBusinessType($single['name'], $language)],
                    $lineNumber,
                    $language
                );
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

        return $this->entries;
    }

    private function detectLanguage(string $content): string
    {
        $scores = ['en' => 0];
        foreach (self::LANGUAGE_DETECTION_MARKERS as $lang => $words) {
            $pattern = $this->getDetectionPattern($lang, $words);
            $scores[$lang] = $pattern === null ? 0 : preg_match_all($pattern, $content);
        }

        arsort($scores);
        $best = array_key_first($scores);

        return $scores[$best] > 0 ? $best : 'en';
    }

    private function getDetectionPattern(string $language, array $words): ?string
    {
        if (!isset($this->detectionPatternCache[$language])) {
            $suffixes = self::LANGUAGE_STREET_SUFFIXES[$language] ?? [];
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

    private function processBuffer(array $lines, int $startLine, string $language, string $phoneRegex): void
    {
        $data = [
            'name' => null,
            'phone' => null,
            'street' => null,
            'type' => null,
        ];

        $nameSet = false;

        foreach ($lines as $line) {
            if (empty($data['phone']) && preg_match($phoneRegex, $line, $matches)) {
                $data['phone'] = $matches[0];
            }

            if (empty($data['type'])) {
                $typeMatch = $this->extractBusinessType($line, $language);
                if ($typeMatch) {
                    $data['type'] = $typeMatch;
                }
            }

            if (empty($data['street'])) {
                $streetMatch = $this->extractStreet($line, $language);
                if ($streetMatch) {
                    $data['street'] = $streetMatch;
                }
            }

            if (!$nameSet && !preg_match($phoneRegex, $line) && !$this->extractStreet($line, $language)) {
                $data['name'] = $line;
                $nameSet = true;
            }
        }

        $this->finalizeEntry($data, $startLine, $language);
    }

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

    private function extractStreet(string $line, string $language): ?string
    {
        if (!mb_check_encoding($line, 'UTF-8')) {
            return null;
        }

        if (preg_match($this->getStreetPattern($language), $line)) {
            return $line;
        }

        if (PhonePattern::isOnlyAPhoneNumber($line)) {
            return null;
        }

        if (preg_match('/\d+\s+[\w\s]+/', $line, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function extractBusinessType(string $line, string $language): ?string
    {
        $markers = self::ENTITY_TYPE_MARKERS[$language] ?? [];
        if (empty($markers)) {
            return null;
        }

        $pattern = $this->getEntityTypePattern($language);
        if (preg_match($pattern, $line)) {
            foreach ($markers as $marker) {
                if (str_contains(strtolower($line), strtolower($marker))) {
                    return $marker;
                }
            }
        }

        return null;
    }

    private function getStreetPattern(string $language): string
    {
        if (!isset($this->streetPatternCache[$language])) {
            if (!isset(self::LANGUAGE_STREET_MARKERS[$language])) {
                throw new \InvalidArgumentException("Unsupported language for street pattern: {$language}. Supported languages: " . implode(', ', self::SUPPORTED_LANGUAGES));
            }

            $words = implode('|', array_map(fn($m) => preg_quote($m, '/'), self::LANGUAGE_STREET_MARKERS[$language]));
            $suffixes = array_map(fn($m) => preg_quote($m, '/'), self::LANGUAGE_STREET_SUFFIXES[$language] ?? []);

            $pattern = '\\b(?:' . $words . ')\\b';
            if ($suffixes) {
                $pattern .= '|(?:' . implode('|', $suffixes) . ')\\b';
            }

            $this->streetPatternCache[$language] = '/' . $pattern . '/iu';
        }

        return $this->streetPatternCache[$language];
    }

    private function getEntityTypePattern(string $language): ?string
    {
        if (!isset($this->entityTypePatternCache[$language])) {
            $markers = self::ENTITY_TYPE_MARKERS[$language] ?? [];

            if (empty($markers)) {
                $this->entityTypePatternCache[$language] = null;
            } else {
                $pattern = '\\b(?:' . implode('|', array_map(fn($m) => preg_quote($m, '/'), $markers)) . ')\\b';
                $this->entityTypePatternCache[$language] = '/' . $pattern . '/iu';
            }
        }

        return $this->entityTypePatternCache[$language];
    }

    private function isJuridicalEntity(array $data, string $language): bool
    {
        if (!empty($data['type'])) {
            return true;
        }

        $pattern = $this->getEntityTypePattern($language);
        return $pattern !== null && preg_match($pattern, $data['name'] ?? '') === 1;
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

    public function getDetectedLanguage(): string
    {
        return $this->detectedLanguage;
    }

    public function getNaturalPeople(): array
    {
        return array_filter($this->entries, fn($item) => $item['type'] === 'natural');
    }

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

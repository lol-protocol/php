<?php

namespace PhoneDirectory;

class MultiLanguagePhoneDirectoryParser
{
    private const LANGUAGE_STREET_MARKERS = [
        'es' => ['calle', 'avenida', 'av', 'plaza', 'pasaje', 'camino', 'ruta', 'carrera'],
        'en' => ['street', 'st', 'avenue', 'ave', 'road', 'rd', 'drive', 'dr', 'lane', 'ln', 'boulevard', 'blvd', 'circle', 'cir'],
        'fr' => ['rue', 'avenue', 'allée', 'place', 'boulevard', 'bd', 'cours', 'square'],
        'pt' => ['rua', 'avenida', 'av', 'praça', 'alameda', 'estrada', 'largo'],
        'de' => ['straße', 'strasse', 'allee', 'weg', 'platz', 'ring', 'hof'],
        'it' => ['via', 'viale', 'corso', 'piazza', 'largo', 'strada'],
    ];

    private const ENTITY_TYPE_MARKERS = [
        'es' => ['spa', 'srl', 'sa', 'ltda', 'inc', 'comercial', 'empresa', 'negocio', 'tienda', 'restaurante', 'hotel', 'banco', 'farmacia', 'hospital'],
        'en' => ['corp', 'inc', 'ltd', 'llc', 'company', 'store', 'shop', 'restaurant', 'hotel', 'bank', 'pharmacy', 'hospital'],
        'fr' => ['sarl', 'sas', 'eirl', 'eurl', 'magasin', 'restaurant', 'hôtel', 'banque', 'pharmacie', 'hôpital'],
    ];

    private PhoneDirectoryParser $parser;
    private string $detectedLanguage = 'en';
    private array $entries = [];
    private array $parseErrors = [];

    public function __construct()
    {
        $this->parser = new PhoneDirectoryParser();
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

    public function parseContent(string $content, ?string $language = null): array
    {
        $this->entries = [];
        $this->parseErrors = [];

        if ($language === null) {
            $language = $this->detectLanguage($content);
        }

        $this->detectedLanguage = $language;

        $lines = explode("\n", $content);
        $buffer = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $trimmed = trim($line);

            if (empty($trimmed)) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $lineNumber - count($buffer), $language);
                    $buffer = [];
                }
                continue;
            }

            if (preg_match('/^\s*-{2,}|={2,}\s*$/', $trimmed)) {
                if (!empty($buffer)) {
                    $this->processBuffer($buffer, $lineNumber - count($buffer), $language);
                    $buffer = [];
                }
                continue;
            }

            $buffer[] = $trimmed;
        }

        if (!empty($buffer)) {
            $this->processBuffer($buffer, $lineNumber - count($buffer), $language);
        }

        return $this->entries;
    }

    private function detectLanguage(string $content): string
    {
        $scores = [];
        foreach (array_keys(self::LANGUAGE_STREET_MARKERS) as $lang) {
            $scores[$lang] = 0;
        }

        $lowerContent = strtolower($content);

        foreach (self::LANGUAGE_STREET_MARKERS as $lang => $markers) {
            foreach ($markers as $marker) {
                $scores[$lang] += substr_count($lowerContent, $marker);
            }
        }

        arsort($scores);
        return array_key_first($scores) ?? 'en';
    }

    private function processBuffer(array $lines, int $startLine, string $language): void
    {
        $data = [
            'name' => null,
            'phone' => null,
            'street' => null,
            'type' => null,
        ];

        foreach ($lines as $line) {
            if (empty($data['phone'])) {
                preg_match('/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\b\d{10}\b/', $line, $matches);
                if (!empty($matches)) {
                    $data['phone'] = $matches[0];
                }
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

            if (empty($data['name'])) {
                $data['name'] = $line;
            }
        }

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
                    phoneNumber: $data['phone']
                );
                $this->entries[] = [
                    'type' => 'juridical',
                    'entity' => $entity,
                ];
            } else {
                $entry = new PhoneDirectoryEntry(
                    fullName: $data['name'],
                    street: $data['street'],
                    phoneNumber: $data['phone']
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
        $markers = self::LANGUAGE_STREET_MARKERS[$language] ?? self::LANGUAGE_STREET_MARKERS['en'];

        foreach ($markers as $marker) {
            if (stripos($line, $marker) !== false) {
                return $line;
            }
        }

        if (preg_match('/\d+\s+[\w\s]+/', $line, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function extractBusinessType(string $line, string $language): ?string
    {
        $markers = self::ENTITY_TYPE_MARKERS[$language] ?? [];

        foreach ($markers as $marker) {
            if (stripos($line, $marker) !== false) {
                return $marker;
            }
        }

        return null;
    }

    private function isJuridicalEntity(array $data, string $language): bool
    {
        if (!empty($data['type'])) {
            return true;
        }

        $markers = self::ENTITY_TYPE_MARKERS[$language] ?? [];
        $name = strtolower($data['name'] ?? '');

        foreach ($markers as $marker) {
            if (stripos($name, $marker) !== false) {
                return true;
            }
        }

        return false;
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

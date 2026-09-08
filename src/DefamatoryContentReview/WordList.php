<?php

namespace DefamatoryContentReview;

class WordList
{
    private array $words = [];
    private array $normalizedWords = [];
    private string $language = 'es';
    private array $riskCategories = [];
    private bool $useUnicodeNormalization = true;

    public function __construct(array $wordsConfig, string $language = 'es', array $riskCategories = [])
    {
        $this->language = $language;
        $this->riskCategories = $riskCategories;
        $this->loadWords($wordsConfig);
    }

    private function loadWords(array $wordsConfig): void
    {
        foreach ($wordsConfig as $category => $terms) {
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    if (is_array($term)) {
                        $this->addWord($term, $category);
                    } else {
                        $this->addWord(['word' => $term], $category);
                    }
                }
            }
        }
    }

    private function addWord(array $wordData, string $category): void
    {
        $term = $wordData['word'] ?? '';
        if (empty($term)) {
            return;
        }

        $normalized = $this->normalize($term);
        $this->words[$normalized] = [
            'original' => $term,
            'category' => $category,
            'riskType' => $wordData['riskType'] ?? 'ordinario',
            'description' => $wordData['description'] ?? '',
            'severity' => $wordData['severity'] ?? 'medium',
        ];
        $this->normalizedWords[] = $normalized;
    }

    public function normalize(string $word): string
    {
        $word = mb_strtolower($word, 'UTF-8');
        $word = preg_replace('/\s+/', '', $word);

        if ($this->useUnicodeNormalization) {
            $word = $this->removeAccents($word);
        }

        return $word;
    }

    private function removeAccents(string $text): string
    {
        $replacements = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ç' => 'c',
            'ß' => 'ss',
            'š' => 's', 'ž' => 'z', 'č' => 'c',
            'ł' => 'l',
        ];

        return strtr($text, $replacements);
    }

    public function search(string $word): ?array
    {
        $normalized = $this->normalize($word);
        return $this->words[$normalized] ?? null;
    }

    public function findInText(string $text): array
    {
        $matches = [];
        $words = preg_split('/[\s\-\.]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $result = $this->search($word);
            if ($result !== null) {
                $matches[] = array_merge($result, ['found' => $word]);
            }
        }

        return $matches;
    }

    public function getByRiskType(string $riskType): array
    {
        return array_filter($this->words, fn($word) => $word['riskType'] === $riskType);
    }

    public function getByCategory(string $category): array
    {
        return array_filter($this->words, fn($word) => $word['category'] === $category);
    }

    public function getByRiskTypeAndCategory(string $riskType, string $category): array
    {
        return array_filter($this->words, fn($word) =>
            $word['riskType'] === $riskType && $word['category'] === $category
        );
    }

    public function getAllWords(): array
    {
        return $this->words;
    }

    public function getWordCount(): int
    {
        return count($this->words);
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getRiskCategories(): array
    {
        return $this->riskCategories;
    }

    public function setRiskCategories(array $categories): self
    {
        $this->riskCategories = $categories;
        return $this;
    }

    public function getStatistics(): array
    {
        $stats = [
            'totalWords' => $this->getWordCount(),
            'language' => $this->language,
            'byRiskType' => [],
            'byCategory' => [],
            'bySeverity' => [],
        ];

        foreach ($this->words as $word) {
            $riskType = $word['riskType'];
            $category = $word['category'];
            $severity = $word['severity'];

            $stats['byRiskType'][$riskType] = ($stats['byRiskType'][$riskType] ?? 0) + 1;
            $stats['byCategory'][$category] = ($stats['byCategory'][$category] ?? 0) + 1;
            $stats['bySeverity'][$severity] = ($stats['bySeverity'][$severity] ?? 0) + 1;
        }

        return $stats;
    }
}

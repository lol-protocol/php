<?php

namespace DefamatoryContentReview;

class WordList
{
    private array $words = [];
    private array $normalizedWords = [];

    public function __construct(array $wordsConfig)
    {
        $this->loadWords($wordsConfig);
    }

    private function loadWords(array $wordsConfig): void
    {
        foreach ($wordsConfig as $category => $terms) {
            foreach ($terms as $term) {
                $normalized = $this->normalize($term);
                $this->words[$normalized] = [
                    'original' => $term,
                    'category' => $category,
                ];
                $this->normalizedWords[] = $normalized;
            }
        }
    }

    public function normalize(string $word): string
    {
        $word = mb_strtolower($word, 'UTF-8');
        $word = preg_replace('/\s+/', '', $word);

        $replacements = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];

        $word = strtr($word, $replacements);

        return $word;
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
                $matches[] = [
                    'found' => $word,
                    'category' => $result['category'],
                    'original' => $result['original'],
                ];
            }
        }

        return $matches;
    }

    public function getAllWords(): array
    {
        return $this->words;
    }

    public function getWordCount(): int
    {
        return count($this->words);
    }
}

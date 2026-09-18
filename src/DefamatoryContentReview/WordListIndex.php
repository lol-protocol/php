<?php

namespace DefamatoryContentReview;

/**
 * El almacén normalizado => datos del término y las consultas de sólo
 * lectura sobre él (filtros y estadísticas). Colaborador interno de
 * WordList — separado porque cargar el diccionario y consultar el índice
 * ya cargado son dos momentos distintos.
 */
final class WordListIndex
{
    /** @var array<string,array> */
    private array $words = [];

    public function set(string $key, array $data): void { $this->words[$key] = $data; }
    public function get(string $key): ?array { return $this->words[$key] ?? null; }
    public function all(): array { return $this->words; }
    public function count(): int { return count($this->words); }

    public function byRiskType(string $riskType): array { return $this->filterBy('riskType', $riskType); }
    public function byCategory(string $category): array { return $this->filterBy('category', $category); }
    public function bySeverity(string $severity): array { return $this->filterBy('severity', $severity); }
    /** Términos que colisionan con nombres o apellidos legítimos. */
    public function nameCollisions(): array { return array_filter($this->words, fn(array $w) => $w['nameCollision'] === true); }

    private function filterBy(string $field, string $value): array
    {
        return array_filter($this->words, fn(array $w) => $w[$field] === $value);
    }

    public function statistics(string $language, string $coverage): array
    {
        $stats = [
            'language' => $language, 'coverage' => $coverage,
            'totalWords' => $this->count(), 'nameCollisions' => count($this->nameCollisions()),
            'byRiskType' => [], 'byCategory' => [], 'bySeverity' => [],
        ];

        foreach ($this->words as $word) {
            foreach (['riskType', 'category', 'severity'] as $field) {
                $key = $field === 'riskType' ? 'byRiskType' : ($field === 'category' ? 'byCategory' : 'bySeverity');
                $stats[$key][$word[$field]] = ($stats[$key][$word[$field]] ?? 0) + 1;
            }
        }

        arsort($stats['byRiskType']);
        arsort($stats['byCategory']);
        arsort($stats['bySeverity']);

        return $stats;
    }
}
